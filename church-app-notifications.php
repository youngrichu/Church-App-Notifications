<?php
/**
 * Plugin Name: Church App Notifications
 * Description: Handles push notifications for the Church App
 * Version: 2.1.0
 * Author: Habtamu
 * Author URI: https://github.com/youngrichu
 * Text Domain: church-app-notifications
 */


// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('CHURCH_APP_NOTIFICATIONS_VERSION', '2.1.0');
define('CHURCH_APP_NOTIFICATIONS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CHURCH_APP_NOTIFICATIONS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once CHURCH_APP_NOTIFICATIONS_PLUGIN_DIR . 'includes/class-notifications.php';
require_once CHURCH_APP_NOTIFICATIONS_PLUGIN_DIR . 'includes/class-api.php';
require_once CHURCH_APP_NOTIFICATIONS_PLUGIN_DIR . 'includes/class-database.php';
require_once CHURCH_APP_NOTIFICATIONS_PLUGIN_DIR . 'includes/class-hook.php';
require_once CHURCH_APP_NOTIFICATIONS_PLUGIN_DIR . 'admin/class-admin.php';

// Activation hook
register_activation_hook(__FILE__, function() {
    require_once CHURCH_APP_NOTIFICATIONS_PLUGIN_DIR . 'includes/class-database.php';
    $database = new Church_App_Notifications_DB();
    $database->create_tables();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    require_once CHURCH_APP_NOTIFICATIONS_PLUGIN_DIR . 'includes/class-database.php';
    $database = new Church_App_Notifications_DB();
    $database->drop_tables();
});

// Initialize the plugin
function run_church_app_notifications() {
    $plugin = new Church_App_Notifications();
    $plugin->run();
    
    // Initialize hooks
    $hooks = new Church_App_Notifications_Hooks();
}

// Hook into WordPress init
add_action('init', 'run_church_app_notifications');