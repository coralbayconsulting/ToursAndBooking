# Email Batch Tracking - Database Migration Scripts

## Overview
These SQL scripts add batch tracking functionality to the BST email system. This allows proper tracking of bulk email sends with batch-level metadata and individual email success/failure status.

## Files

- **add-batch-tracking.sql** - Main migration script (MySQL 8.0+)
- **add-batch-tracking-mysql57.sql** - Compatible version (MySQL 5.7)
- **rollback-batch-tracking.sql** - Rollback script to revert changes

## Before Running

⚠️ **Important**: Back up your database before running these scripts!

### Find Your Table Prefix

WordPress table prefix is typically `wp_` but may be different. Check your `wp-config.php`:

```php
$table_prefix = 'wp_'; // <- Your prefix here
```

### Replace Table Prefix

If your prefix is not `wp_`, do a find/replace in the SQL file:
- Find: `wp_`
- Replace: `your_prefix_`

## Running the Scripts

### Option 1: phpMyAdmin
1. Log into phpMyAdmin
2. Select your WordPress database
3. Click "SQL" tab
4. Copy and paste the script content
5. Click "Go"

### Option 2: MySQL Command Line
```bash
mysql -u username -p database_name < add-batch-tracking.sql
```

### Option 3: WP-CLI (if available)
```bash
wp db query < add-batch-tracking.sql
```

### Option 4: Local by Flywheel
1. Right-click site → Open Site Shell
2. Run:
```bash
mysql -u root -proot -h localhost local < /app/public/wp-content/mu-plugins/bst_plugin/sql/add-batch-tracking.sql
```

## Which Script to Use?

### For MySQL 8.0 or later:
Use **add-batch-tracking.sql** - includes `IF NOT EXISTS` clauses for safer execution

### For MySQL 5.7 or earlier:
Use **add-batch-tracking-mysql57.sql** - compatible syntax
- If columns/table already exist, you may see errors that can be ignored

### Check Your MySQL Version:
```sql
SELECT VERSION();
```

## What Gets Changed

### 1. Email Log Table (`wp_bst_email_log`)
Adds two new columns:
- `batch_id` - Links email to its batch (NULL for single emails)
- `error_message` - Stores error details for failed emails

### 2. Email Batch Table (`wp_bst_email_batch`)
Creates new table to track bulk email batches with:
- Batch metadata (timestamp, user, template, subject)
- Tour date association
- Success/failure counts
- Test batch indicator

## Verification

After running the script, verify the changes:

```sql
-- Check email_log columns
DESCRIBE wp_bst_email_log;

-- Check batch table
DESCRIBE wp_bst_email_batch;

-- Should see batch_id and error_message in email_log
-- Should see the new bst_email_batch table
```

## Rollback

If you need to revert the changes:

```bash
mysql -u username -p database_name < rollback-batch-tracking.sql
```

⚠️ **Warning**: Rollback will delete:
- All batch tracking data
- Error messages stored in email logs
- The entire email_batch table and its contents

## Deployment Checklist

- [ ] Back up database
- [ ] Identify table prefix
- [ ] Update table prefix in SQL script if needed
- [ ] Check MySQL version
- [ ] Choose appropriate SQL file
- [ ] Run migration script
- [ ] Verify changes with DESCRIBE commands
- [ ] Test bulk email functionality in dashboard
- [ ] Test single email send from booking page
- [ ] Verify batch info displays on dashboard

## Troubleshooting

### Error: Column already exists
- If using MySQL 5.7 script, this is normal if script was already run
- Ignore the error and continue

### Error: Table already exists
- Table creation already succeeded
- No action needed

### Error: Access denied
- Ensure your MySQL user has ALTER and CREATE privileges
- May need to use root user or database admin

### Changes not appearing in WordPress
- Clear WordPress object cache if using caching
- Hard refresh dashboard page (Ctrl+Shift+R)
- Check browser console for JavaScript errors

## Support

For issues related to:
- **Database errors**: Check MySQL error logs
- **Application errors**: Check WordPress debug.log
- **Feature not working**: Verify JavaScript console in browser

Migration Date: February 11, 2026
