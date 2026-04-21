# BST Plugin Deployment Guide

## Overview
This plugin is configured for Azure WordPress hosting with automatic cache busting to prevent deployment issues where CSS/JS changes don't appear without server restarts.

## Cache Busting Strategy
All plugin CSS and JavaScript files use **timestamp-based versioning** with `filemtime()` to automatically bust caches when files are updated.

### Azure Path Resolution
- Uses `content_url()` instead of `plugin_dir_url()` to avoid malformed Azure paths
- Prevents issues with `/wp-content/plugins/home/site/wwwroot/` path problems on Azure

## Files with Automatic Cache Busting

### CSS Files
- `css/bst-admin-styles.css` - Main admin styling
- `css/admin-date-format.css` - Date formatting styles

### JavaScript Files
- `js/admin-date-format.js` - Date formatting functionality
- `js/bst-exchange-rates.js` - Exchange rates management
- `js/tour-bookings-admin.js` - Tour bookings admin interface
- `js/bst-media-uploader.js` - Media upload functionality

## Implementation Details

### Enqueue Pattern
All files follow this pattern:
```php
$asset_url = content_url('mu-plugins/bst_plugin/path/to/file.ext');
$asset_path = WP_CONTENT_DIR . '/mu-plugins/bst_plugin/path/to/file.ext';
$asset_version = file_exists($asset_path) ? filemtime($asset_path) : time();
wp_enqueue_style/script('handle', $asset_url, $deps, $asset_version);
```

### Functions Using This Pattern
- `bst_enqueue_custom_admin_css()` in `includes/class-bst-plugin.php`
- Date formatting enqueues in `includes/class-bst-plugin.php`
- `enqueue_bst_exchange_rates_script()` in `includes/exchange-rates.php`
- Tour bookings admin script in `includes/class-bst-plugin.php`
- `bst_enqueue_admin_javascript()` in `includes/admin-settings.php`

## Deployment Instructions

### For Plugin Changes
1. **NO ACTION REQUIRED** - Plugin files automatically use timestamp versioning
2. Upload modified files to Azure
3. Cache will be automatically busted on next page load

### Verification
After deployment, check browser developer tools to confirm new timestamps in asset URLs:
- Old: `bst-admin-styles.css?ver=1737123456`
- New: `bst-admin-styles.css?ver=1737123789`

### Advanced Custom Fields (first deploy or new environment)

After code deploys, the **field group definitions in the database** may still differ from the **Local JSON** shipped in this repo (`acf-json/` under the plugin and theme). Until they match, wp-admin can show **Sync available** on one or more groups, and programmatic `update_field()` calls may fail even when the site otherwise looks fine.

1. Log in to **wp-admin** on the deployed environment.
2. Go to **Custom Fields → Field Groups**.
3. For any group that shows **Sync available**—commonly **Tour** and **Vehicle**—open the group and click **Sync** so the database matches the JSON in the repo.
4. Confirm **Local JSON** shows **Saved** (not “Sync available”) for those groups.

Repeat this after a first deploy to a new slot or server, or whenever you pull fresh code that changes ACF JSON. For a fuller checklist (DB import, permalinks, optional BST Tools), see `docs/local-dev-refresh.md`.

## Troubleshooting

### If Styles Don't Update
1. Check file permissions on Azure
2. Verify `WP_CONTENT_DIR` path is correct
3. Check Azure cache settings if timestamps aren't changing

### Azure-Specific Notes
- Uses `content_url()` for reliable path resolution
- Avoids `plugin_dir_url()` which creates malformed paths on Azure
- All asset paths use `WP_CONTENT_DIR` for file existence checks

## Development Notes
- Timestamp versioning is more reliable than manual version increments
- `filemtime()` reflects actual file modification time
- Fallback to `time()` if file doesn't exist prevents errors
- Azure WordPress hosting requires specific path handling approaches
