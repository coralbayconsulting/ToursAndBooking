<?php
/**
 * Email Configuration Checker & Fixer
 * Access via: /wp-content/mu-plugins/bst_plugin/fix-email-config.php
 */

// Load WordPress
require_once '../../../../wp-config.php';

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Access denied. Admin privileges required.');
}

echo "<h2>🔧 Email Configuration Checker & Fixer</h2>";

// Check for common email plugin configurations
$email_plugins_options = array(
    'wp_mail_smtp' => 'WP Mail SMTP',
    'easy_wp_smtp' => 'Easy WP SMTP',
    'post_smtp_options' => 'Post SMTP',
    'swpsmtp_options' => 'SW WP Mail SMTP',
    'smtp_options' => 'Generic SMTP Options',
    'mailgun_options' => 'Mailgun',
    'sendgrid_options' => 'SendGrid',
    'ses_options' => 'Amazon SES'
);

echo "<h3>1. Email Plugin Configuration Check</h3>";

$found_configs = array();
foreach ($email_plugins_options as $option_name => $plugin_name) {
    $option_value = get_option($option_name);
    if ($option_value && !empty($option_value)) {
        $found_configs[$option_name] = $plugin_name;
        echo "🔍 Found $plugin_name configuration: $option_name<br>";
        
        if (is_array($option_value)) {
            echo "<pre style='background: #f5f5f5; padding: 10px; margin: 10px 0; font-size: 12px;'>";
            print_r($option_value);
            echo "</pre>";
        } else {
            echo "Value: $option_value<br>";
        }
    }
}

if (empty($found_configs)) {
    echo "✅ No email plugin configurations found<br>";
}

// Check WordPress core mail settings
echo "<h3>2. WordPress Core Mail Settings</h3>";

$core_options = array(
    'admin_email' => 'Admin Email',
    'mailserver_url' => 'Mail Server URL',
    'mailserver_login' => 'Mail Server Login',
    'mailserver_pass' => 'Mail Server Password',
    'mailserver_port' => 'Mail Server Port'
);

foreach ($core_options as $option => $label) {
    $value = get_option($option);
    if ($value) {
        echo "$label: $value<br>";
    }
}

// Check for Azure-specific configurations
echo "<h3>3. Azure Email Configuration Check</h3>";

$azure_options = array(
    'azure_email_settings',
    'azure_smtp_settings', 
    'office365_smtp_settings',
    'microsoft_graph_settings',
    'azure_communication_services'
);

$found_azure = false;
foreach ($azure_options as $option) {
    $value = get_option($option);
    if ($value) {
        $found_azure = true;
        echo "🔍 Found Azure config: $option<br>";
        if (is_array($value)) {
            echo "<pre style='background: #fff3cd; padding: 10px; margin: 10px 0; font-size: 12px;'>";
            print_r($value);
            echo "</pre>";
        }
    }
}

if (!$found_azure) {
    echo "✅ No Azure email configurations found in standard options<br>";
}

// Check active plugins for email-related ones
echo "<h3>4. Active Email-Related Plugins</h3>";

$active_plugins = get_option('active_plugins', array());
$email_related_plugins = array();

foreach ($active_plugins as $plugin) {
    if (stripos($plugin, 'mail') !== false || 
        stripos($plugin, 'smtp') !== false ||
        stripos($plugin, 'azure') !== false ||
        stripos($plugin, 'sendgrid') !== false ||
        stripos($plugin, 'mailgun') !== false) {
        $email_related_plugins[] = $plugin;
    }
}

if (!empty($email_related_plugins)) {
    echo "📧 Email-related plugins found:<br>";
    foreach ($email_related_plugins as $plugin) {
        echo "- $plugin<br>";
    }
} else {
    echo "✅ No email-related plugins detected<br>";
}

// Provide fix options
echo "<hr><h3>🛠️ Fix Options</h3>";

if (!empty($found_configs) || !empty($email_related_plugins)) {
    echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px;'>";
    echo "<h4>⚠️ Email configurations detected</h4>";
    echo "<p>To use Mailpit for local development, you have these options:</p>";
    
    echo "<h5>Option 1: Temporarily disable email plugins</h5>";
    echo "<p>Deactivate email plugins for local development:</p>";
    if (!empty($email_related_plugins)) {
        foreach ($email_related_plugins as $plugin) {
            $plugin_file = WP_PLUGIN_DIR . '/' . $plugin;
            if (file_exists($plugin_file)) {
                echo "<button onclick='deactivatePlugin(\"$plugin\")' style='margin: 2px; padding: 5px 10px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer;'>Deactivate " . dirname($plugin) . "</button><br>";
            }
        }
    }
    
    echo "<h5>Option 2: Override with wp-config.php</h5>";
    echo "<p>Add this to your wp-config.php to force Mailpit usage:</p>";
    echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 3px;'>";
    echo "// Force Mailpit for local development\n";
    echo "if (defined('WP_DEBUG') && WP_DEBUG) {\n";
    echo "    define('SMTP_HOST', 'localhost');\n";
    echo "    define('SMTP_PORT', 1025);\n";
    echo "    define('SMTP_AUTH', false);\n";
    echo "    \n";
    echo "    // Override any plugin SMTP settings\n";
    echo "    add_filter('wp_mail', function(\$args) {\n";
    echo "        \$args['headers'] = array();\n";
    echo "        return \$args;\n";
    echo "    });\n";
    echo "}\n";
    echo "</pre>";
    
    echo "<h5>Option 3: Quick disable (click below)</h5>";
    echo "<form method='post' style='margin: 10px 0;'>";
    echo "<input type='hidden' name='action' value='disable_email_configs'>";
    echo "<button type='submit' style='padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer;'>🚫 Temporarily Disable All Email Configs</button>";
    echo "</form>";
    
    echo "</div>";
} else {
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px;'>";
    echo "<h4>✅ No conflicting email configurations found</h4>";
    echo "<p>The wp_mail() failure might be due to PHP mail configuration. Try installing WP Mail SMTP plugin and configure it for Mailpit.</p>";
    echo "</div>";
}

// Handle form submission
if (isset($_POST['action']) && $_POST['action'] === 'disable_email_configs') {
    echo "<h3>🚫 Disabling Email Configurations...</h3>";
    
    foreach ($found_configs as $option_name => $plugin_name) {
        $backup_option_name = $option_name . '_backup_' . date('Ymd_His');
        $current_value = get_option($option_name);
        
        // Backup current value
        update_option($backup_option_name, $current_value);
        
        // Disable the option
        delete_option($option_name);
        
        echo "✅ Disabled $plugin_name (backed up as $backup_option_name)<br>";
    }
    
    echo "<div style='background: #d4edda; padding: 15px; margin: 15px 0; border-radius: 5px;'>";
    echo "<h4>✅ Email configurations disabled!</h4>";
    echo "<p>Now test your waiting list email again. The configurations have been backed up and can be restored later.</p>";
    echo "<a href='../../../wp-admin/admin.php?page=bst-plugin' style='background: #0073aa; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px;'>← Back to Dashboard</a>";
    echo "</div>";
}

echo "<script>";
echo "function deactivatePlugin(plugin) {";
echo "  if (confirm('Deactivate ' + plugin + '?')) {";
echo "    window.location.href = '../../../wp-admin/plugins.php?action=deactivate&plugin=' + encodeURIComponent(plugin) + '&_wpnonce=' + 'nonce';";
echo "  }";
echo "}";
echo "</script>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h3 { color: #333; border-bottom: 2px solid #0073aa; padding-bottom: 5px; }
h4 { color: #666; }
pre { overflow-x: auto; }
</style>