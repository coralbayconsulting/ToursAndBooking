# Quick Start - Local by Flywheel

## Step 1: Find Your Table Prefix
Check your wp-config.php for the line:
```php
$table_prefix = 'wp_';
```

If it's NOT `wp_`, edit the SQL file and replace all `wp_` with your prefix.

## Step 2: Open Site Shell
In Local by Flywheel:
- Right-click your site name
- Click "Open Site Shell"

## Step 3: Run Migration
Copy and paste this command (replace `bluestradatours-production` with your site folder name if different):

```bash
mysql -u root -proot local < /app/public/wp-content/mu-plugins/bst_plugin/sql/add-batch-tracking.sql
```

Or if using MySQL 5.7 compatible version:
```bash
mysql -u root -proot local < /app/public/wp-content/mu-plugins/bst_plugin/sql/add-batch-tracking-mysql57.sql
```

## Step 4: Verify
```bash
mysql -u root -proot local -e "DESCRIBE wp_bst_email_log;"
mysql -u root -proot local -e "DESCRIBE wp_bst_email_batch;"
```

You should see `batch_id` and `error_message` in the first output, and the full batch table structure in the second.

## Done! 
The database is now ready for batch tracking. Test by:
1. Going to WordPress dashboard
2. Finding a finalization group
3. Clicking "✉️ Send Email"
4. Sending a test email or bulk email

The "Last finalization email" line should now show accurate batch information.
