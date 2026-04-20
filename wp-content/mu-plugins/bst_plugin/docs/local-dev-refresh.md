# Refreshing local development after production data import

Use this checklist when you replace the local database (or clone the site) and pull fresh code. Skipping ACF sync is a common cause of **programmatic `update_field` failures** while **reading** repeaters in PHP still works.

## 1. Git: match the branch you intend to run

- From the project root (or this plugin path if you work subtree-only), fetch and check out the branch you need, e.g. `main` or a feature branch:

  ```bash
  git fetch origin
  git checkout main
  git pull origin main
  ```

- Confirm the BST mu-plugin and theme files match what you expect (`git status` should be clean unless you have intentional local edits).

## 2. Database import

- Import production (or staging) SQL into Local / your dev DB as you usually do.
- If URLs differ, run your normal search-replace for `siteurl` / `home` (WP-CLI, Better Search Replace, or Local’s tool).

## 3. Advanced Custom Fields (required)

Imported field group **definitions in the database** may not match the **Local JSON** files shipped in this repo (`acf-json/` under the plugin).

1. In wp-admin go to **Custom Fields → Field Groups**.
2. For any row that shows **Sync available** (often **Tour** and **Vehicle** after an import), open the group and click **Sync** so the DB matches JSON.
3. Wait until **Local JSON** shows **Saved** (not “Sync available”) for those groups.

Until this is done, release cleanup steps that call **`update_field( 'vehicle_pricing', … )`** may fail, while browsing tours and reading repeater data can still look fine.

## 4. Optional: vehicle migration / re-link

After ACF is synced:

- **Tools → BST** — use **Re-link tour pricing from labels** if tour rows have correct vehicle text but CPT links need rewriting.
- If something still won’t save, open a tour in the editor and **Update** once; that uses the normal ACF save path and often persists `vehicle_pricing` correctly.

## 5. Stripe webhooks after import (required if Stripe is enabled)

When you import production/staging DB into another environment, Stripe webhook signing secrets are often copied from the source site and become invalid for the target endpoint.

1. In Stripe Dashboard, choose the correct mode (**Test** or **Live**) for the environment.
2. Go to **Developers → Webhooks** and open the endpoint for that environment URL.
3. Copy the endpoint **Signing secret** (`whsec_...`).
4. In WordPress go to **Forms → Settings → Stripe** and update:
   - **Test Signing Secret** for test mode
   - **Live Signing Secret** for live mode
5. Save and resend a recent webhook event from Stripe to confirm 2xx.

If this is skipped, Stripe deliveries can fail with 400 errors such as: `Invalid request. Webhook could not be processed.`

## 6. Routine WordPress checks

- **Settings → Permalinks** — Save once (flushes rewrite rules).
- Clear object cache if you use Redis/Memcached in dev.

## 7. Reference: canonical vehicle matching

Vehicle labels are matched to Vehicle CPT `post_title` using `bst_vehicle_exact_text_key()` (strip tags, remove `(...)`, trim, collapse spaces). See `includes/vehicle-helpers.php` and `includes/vehicle-migration.php`.
