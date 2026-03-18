# Child Theme Deployment Guide

## Overview
This child theme now features **automated version management** for Azure WordPress hosting cache busting. Manual updates are no longer required for most deployments.

## ✅ **Quick Deployment Process**
1. **Go to WordPress Admin → BST Plugin → Global Settings**
2. **Click "Bump Child Theme Versions" button**
3. **Wait for success confirmation**
4. **Copy child theme files to Azure**
5. **Done!** 🚀

## Files Managed Automatically

The automated version bumper handles all these files:

### CSS Files
- `style.css` - Main stylesheet
- `gravity-forms.css` - Gravity Forms styling

### JavaScript Files  
- `js/custom-tooltip.js` - Custom tooltip functionality
- `js/tour-filters.js` - Tour filtering functionality
- `js/rating-help.js` - Rating help system
- `gravity-forms.js` - Gravity Forms functionality

## Automated Version Bumping
**Location**: WordPress Admin → BST Plugin → Global Settings → Deployment Tools

**What it does:**
- ✅ Scans all child theme asset files
- ✅ Automatically increments version numbers
- ✅ Updates functions.php with new versions
- ✅ Shows confirmation of changes made

## Legacy Information (For Reference Only)

## Legacy Information (For Reference Only)

<details>
<summary>Manual Version Update Process (Only if automation fails)</summary>

### Manual Version Update (Emergency Fallback)
1. **Update all version numbers** in `functions.php`
2. **Increment versions** from current version to next version (e.g., `1.0.1` to `1.0.2`)

### Version Update Locations in functions.php

```php
// Update these version numbers before deployment:
wp_enqueue_style('child-style', get_stylesheet_uri(), array(), '1.0.1'); // <- Change this
wp_enqueue_script('custom-tooltip', get_stylesheet_directory_uri() . '/js/custom-tooltip.js', array('jquery'), '1.0.1', true); // <- Change this  
wp_enqueue_script('tour-filters', get_stylesheet_directory_uri() . '/js/tour-filters.js', array('jquery'), '1.0.1', true); // <- Change this
wp_enqueue_script('rating-help', get_stylesheet_directory_uri() . '/js/rating-help.js', array('jquery'), '1.0.1', true); // <- Change this
wp_enqueue_style('gravity-forms-custom-styles', get_stylesheet_directory_uri() . '/gravity-forms.css', array(), '1.0.1'); // <- Change this
wp_enqueue_script('gravity-forms-custom-scripts', get_stylesheet_directory_uri() . '/gravity-forms.js', array('jquery'), '1.0.1', true); // <- Change this
```

</details>

## Deployment Process

### ✅ **Current Process (Automated)**
1. **Click version bumper** in WordPress admin
2. **Copy files** to Azure
3. **Verify** new versions loading

### Legacy Process (For Reference)
<details>
<summary>Old manual process (no longer needed)</summary>

**Option B - Manual:**
```php
// Example: Update from 1.0.1 to 1.0.2
wp_enqueue_style('child-style', get_stylesheet_uri(), array(), '1.0.2');
wp_enqueue_script('custom-tooltip', get_stylesheet_directory_uri() . '/js/custom-tooltip.js', array('jquery'), '1.0.2', true);
wp_enqueue_script('tour-filters', get_stylesheet_directory_uri() . '/js/tour-filters.js', array('jquery'), '1.0.2', true);
wp_enqueue_script('rating-help', get_stylesheet_directory_uri() . '/js/rating-help.js', array('jquery'), '1.0.2', true);
```

</details>

### Step 2: Deploy Files
Upload modified files to Azure hosting

### Step 3: Verification
Check browser developer tools to confirm new versions:
- Old: `style.css?ver=1.0.1`
- New: `style.css?ver=1.0.2`

## Why This System Works
- **Automated tool** eliminates human error and saves time
- **Plugin files** use automatic timestamp versioning (no action needed)
- **Child theme files** get managed by the version bumper button
- **Azure caching** respects version number changes
- **No server restarts** required

## Notes
- ✅ **Primary method**: Use the automated version bumper button
- 🆘 **Backup method**: Manual editing (documented above for emergencies)
- ⚡ **Plugin files**: Require no action (automatic timestamp versioning)
- 🚀 **Result**: One-click deployment preparation

## Related Documentation
See `/wp-content/mu-plugins/bst_plugin/DEPLOYMENT_README.md` for plugin deployment information.
