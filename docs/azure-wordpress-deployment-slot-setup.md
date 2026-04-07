# Azure App Service + WordPress: deployment slot setup

Blue Strada Tours setup: one App Service, **deployment slots**, Azure Database for MySQL, **user-assigned managed identity**, and app settings such as `DATABASE_HOST`, `DATABASE_NAME`, `DATABASE_USERNAME`, `ENABLE_MYSQL_MANAGED_IDENTITY`, and `ENTRA_CLIENT_ID`.

---

## TL;DR — new slot with a working site

Use an **empty MySQL database** and **All-in-One WP Migration** to import a `.wpress` package. Do **not** rely on copying the production database first — the import brings **files + database** together.

1. Create an **empty** database on the MySQL server (e.g. `…_database_staging`).
2. **Deployment slots** → **Add** → clone settings from **production**.
3. On the **slot** (not the parent app): set **`DATABASE_NAME`** to that empty DB; mark it **Deployment slot setting** (sticky) if you use swap.
4. **Identity** (on the **slot**) → **User assigned** → add the **same** identity as production → **Save** → **Restart** the slot.
5. Open the site → complete **`/wp-admin/install.php`**. Note the **first admin email** (Azure/WP images may default to your Microsoft account email, not production).
6. Install **All-in-One WP Migration** (+ Unlimited if you use it) → **Import** a production or saved slot `.wpress` backup.

Until step 4 is done, you may see *Error establishing a database connection* even if `DATABASE_NAME` is correct — the slot needs **managed identity** attached like production.

---

## Checklist detail

### MySQL

- Deployment slots **do not** create databases. You create each database on the server.
- The managed-identity DB user needs **`CREATE`** (and normal DML) on a new empty database so install can create tables.

### Slot configuration

- **`DATABASE_HOST`** — usually unchanged vs production (same server).
- **`DATABASE_NAME`** — **per slot** (`…_staging`, `…_testing`, etc.).
- Sticky **`DATABASE_NAME`** so swap does not cross-wire production and other slots’ databases.

### After AIO import

- The backup usually carries **`siteurl` / `home`**. If the slot still redirects to the wrong host, fix in `wp_options` or run a safe URL replace. Manual SQL example:

```sql
UPDATE wp_options
SET option_value = 'https://<your-slot-hostname>'
WHERE option_name IN ('siteurl', 'home');
```

(Adjust table prefix if not `wp_`.)

### First login on a bare install

- Users live in **`wp_users`** (`user_login`, `user_email`). There is no fixed default password — you define it at install, or the image may pre-fill email.
- Production credentials do **not** apply until that user exists in **this** database (after import or copy).

---

## All-in-One WP Migration

### Import issues on Azure

Large imports can still hit **PHP / request / disk** limits (Unlimited removes the plugin’s export cap, not server timeouts). Mitigations: raise `upload_max_filesize`, `post_max_size`, `memory_limit`, execution time if your image allows; use **server-side** files under `wp-content/ai1wm-backups` when the plugin supports it; smaller exports if needed.

### If import fails partway

Prefer a **clean empty database** again (drop/recreate or new DB name) before re-importing so you are not half-updated.

### When wp-admin is broken

- **Scheduled** or **automated** `.wpress` exports to blob/storage reduce dependence on logging in to export.
- **SSH/SFTP:** remove bad drop-ins (`object-cache.php`, `advanced-cache.php`), rename `plugins` / `mu-plugins` folders to regain admin once.
- **Backup files on disk** (see below) can be pulled with **FileZilla** even when admin will not load — copy a `.wpress` to a machine where you can upload it after the site is healthy, or place it in `ai1wm-backups` and import from server if supported.

---

## Backups: what to use when

### AIO `.wpress` on the server (FileZilla / SFTP)

All-in-One often stores backups under something like **`wp-content/ai1wm-backups/`**. You can download `.wpress` files with **FileZilla** even if **wp-admin** does not load — useful when the portal backup is stale or awkward, as long as a recent export exists on disk.

**FTP username and password (FileZilla):** In Azure Portal, open the **App Service** or **deployment slot** you need → toolbar **Download publish profile** (`.PublishSettings` file). Open it in a text editor: it contains the **FTP** endpoint, **username**, and **password** for that app or slot. Use those in FileZilla (or another FTP client). Each **slot** has its own profile if you download while that **slot** is the resource you are viewing — use the profile that matches the environment you are connecting to. (**Reset publish profile** in the same toolbar rotates those credentials if needed.)

### Azure App Service backup / slot restore

- Covers **app files and configuration** as Microsoft defines them — **not** Azure MySQL data in the same package.
- WordPress **files and database belong together**. Restoring only app files while MySQL stays on a different timeline usually gives a **broken** site.
- **MySQL** uses **server-level** automated backups (all databases on that server). **Point-in-time restore** typically creates a **new** MySQL server at a chosen time; you then export/import the database you need, or use it for DR drills. Confirm each step in the portal before confirming restore.

Use Azure app backup / PITR when you need that specific tool; for day-to-day recovery of a **non-production slot**, the **bare DB + slot + MI + AIO import** path above is the main playbook.

---

## Environment variables (this project)

| Name | Role |
|------|------|
| `DATABASE_HOST` | MySQL server FQDN |
| `DATABASE_NAME` | **Per slot** — e.g. `…_staging` |
| `DATABASE_USERNAME` | MI-backed user (e.g. `bluestrada-…-wpidentity`) |
| `ENABLE_MYSQL_MANAGED_IDENTITY` | `true` for Entra token auth to MySQL |
| `ENTRA_CLIENT_ID` | Client ID for MySQL / Entra |

Optional check in slot SSH (use `grep`, not `rg`, unless installed):

```bash
printenv | grep -E 'DATABASE_HOST|DATABASE_NAME|DATABASE_USERNAME|ENABLE_MYSQL_MANAGED_IDENTITY|ENTRA_CLIENT_ID'
```

---

## Plugin / cache (e.g. W3 Total Cache)

If the site white-screens after removing a caching plugin:

- Remove or rename `wp-content/object-cache.php` and `wp-content/advanced-cache.php`.
- Set `WP_CACHE` to `false` in `wp-config.php` if needed.
- Strip W3TC blocks from `.htaccess`.

Fix via **SSH/SFTP** — admin does not need to load.

---

## Best practices (short)

- **Separate database per environment** on one MySQL server; sticky **`DATABASE_NAME`** per slot before swap.
- **Same user-assigned MI on every slot** that must talk to MySQL.
- **Always On** on production (and on slots that run long imports or cron).
- **Schedule** AIO or other backups that do not require daily wp-admin login.
- Enable **Azure MySQL** automated backups; know retention and how **PITR** works for your SKU.
- Prefer changes on a **non-production slot** first, then promote to production.

---

## Summary

| Step | Action |
|------|--------|
| 1 | Empty MySQL DB for the slot |
| 2 | New slot; clone production settings |
| 3 | Sticky **`DATABASE_NAME`** → that DB |
| 4 | **User-assigned MI** on the **slot**; restart |
| 5 | Run **install**; note first admin **email** |
| 6 | **AIOWPM** → import `.wpress` |
| 7 | Fix URLs only if import left wrong `siteurl`/`home` |

**Remember:** managed identity must be on the **deployment slot** resource, not only implied by cloning settings from production.
