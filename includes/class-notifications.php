<?php
/**
 * Main plugin class
 */
class Church_App_Notifications {
    protected $loader;
    protected $plugin_name;
    protected $version;
    protected $api;
    protected $admin;
    protected $hooks;

    public function __construct() {
        $this->plugin_name = 'church-app-notifications';
        $this->version = '1.0.0';
        
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_api_hooks();
        $this->define_post_hooks();
    }

    private function load_dependencies() {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-api.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-admin.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-hook.php';
        
        $this->api = new Church_App_Notifications_API();
        $this->admin = new Church_App_Notifications_Admin($this->get_plugin_name(), $this->get_version());
        $this->hooks = new Church_App_Notifications_Hooks();
    }

    private function define_admin_hooks() {
        add_action('admin_menu', array($this->admin, 'add_plugin_admin_menu'));
        add_action('admin_enqueue_scripts', array($this->admin, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this->admin, 'enqueue_scripts'));
    }

    private function define_api_hooks() {
        add_action('rest_api_init', array($this->api, 'register_routes'));
    }

    private function define_post_hooks() {
        // Add hooks for post publishing
        add_action('publish_post', array($this->hooks, 'notify_new_post'), 10, 2);
        add_action('transition_post_status', array($this->hooks, 'handle_post_status_transition'), 10, 3);
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_version() {
        return $this->version;
    }

    public function run() {
        // Initialize all hooks
        $this->define_admin_hooks();
        $this->define_api_hooks();
        $this->define_post_hooks();

        // Add any additional initialization here
        do_action('church_app_notifications_init');
    }
}