<?php
/**
 * Plugin Name: Church App Notifications
 * Description: Handles push notifications for the Church App
 * Version: 2.4.0
 * Author: Habtamu
 * Author URI: https://github.com/youngrichu
 * Text Domain: church-app-notifications
 */


// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('CHURCH_APP_NOTIFICATIONS_VERSION', '2.4.0');
define('CHURCH_APP_NOTIFICATIONS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CHURCH_APP_NOTIFICATIONS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once CHURCH_APP_NOTIFICATIONS_PLUGIN_DIR . 'includes/class-notifications.php';
require_once CHURCH_APP_NOTIFICATIONS_PLUGIN_DIR . 'includes/class-api.php';
require_once CHURCH_APP_NOTIFICATIONS_PLUGIN_DIR . 'includes/class-database.php';
require_once CHURCH_APP_NOTIFICATIONS_PLUGIN_DIR . 'includes/class-hook.php';
require_once CHURCH_APP_NOTIFICATIONS_PLUGIN_DIR . 'includes/class-token-handler.php';
require_once CHURCH_APP_NOTIFICATIONS_PLUGIN_DIR . 'includes/class-expo-push.php';
require_once CHURCH_APP_NOTIFICATIONS_PLUGIN_DIR . 'includes/class-blog-notifications.php';
require_once CHURCH_APP_NOTIFICATIONS_PLUGIN_DIR . 'admin/class-admin.php';

// Activation hook
register_activation_hook(__FILE__, function () {
    require_once CHURCH_APP_NOTIFICATIONS_PLUGIN_DIR . 'includes/class-database.php';
    $database = new Church_App_Notifications_DB();
    $database->create_tables();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function () {
    require_once CHURCH_APP_NOTIFICATIONS_PLUGIN_DIR . 'includes/class-database.php';
    $database = new Church_App_Notifications_DB();
    $database->drop_tables();
});

// Initialize the plugin
function run_church_app_notifications()
{
    $plugin = new Church_App_Notifications();
    $plugin->run();

    // Initialize hooks
    $hooks = new Church_App_Notifications_Hooks();

    // Initialize token handler
    $token_handler = new Church_App_Notifications_Token_Handler();
    add_action('rest_api_init', array($token_handler, 'register_routes'));
}

// Hook into WordPress init
add_action('init', 'run_church_app_notifications');

/**
 * Upgrade routine for database schema changes
 * Creates the notification reads table for per-user read tracking
 */
function church_app_notifications_upgrade()
{
    $current_db_version = get_option('church_app_notifications_db_version', '1.0.0');

    // Upgrade to 2.4.0: Add notification reads table for per-user tracking
    if (version_compare($current_db_version, '2.4.0', '<')) {
        error_log('Church App Notifications: Upgrading database schema to 2.4.0');

        $database = new Church_App_Notifications_DB();
        $database->create_reads_table();

        update_option('church_app_notifications_db_version', '2.4.0');
        error_log('Church App Notifications: Database upgrade to 2.4.0 complete');
    }
}
add_action('plugins_loaded', 'church_app_notifications_upgrade');