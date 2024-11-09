<?php
/**
 * The admin-specific functionality of the plugin.
 */
class Church_App_Notifications_Admin {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function add_plugin_admin_menu() {
        add_menu_page(
            'Church App Notifications',
            'App Notifications',
            'manage_options',
            'church-app-notifications',
            array($this, 'display_notifications_page'),
            'dashicons-bell',
            30
        );

        add_submenu_page(
            'church-app-notifications',
            'Send Notification',
            'Send Notification',
            'manage_options',
            'church-app-send-notification',
            array($this, 'display_send_notification_page')
        );

        add_submenu_page(
            'church-app-notifications',
            'Settings',
            'Settings',
            'manage_options',
            'church-app-notification-settings',
            array($this, 'display_settings_page')
        );
    }

    public function display_notifications_page() {
        // Include the notifications list partial
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/notifications-list.php';
    }

    public function display_send_notification_page() {
        // Include the send notification partial
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/send-notification.php';
    }

    public function display_settings_page() {
        // Include the settings partial
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/settings.php';
    }

    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/admin.css', array(), $this->version, 'all');
    }

    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/admin.js', array('jquery'), $this->version, false);
    }
}