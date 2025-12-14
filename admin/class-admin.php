<?php
/**
 * The admin-specific functionality of the plugin.
 */
class Church_App_Notifications_Admin
{
    private $plugin_name;
    private $version;
    private $notifications_list_table;

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // Add this line to load the list table class
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-notifications-list-table.php';
    }

    public function add_plugin_admin_menu()
    {
        $hook_list = add_menu_page(
            'Church App Notifications',
            'App Notifications',
            'manage_options',
            'church-app-notifications',
            array($this, 'display_notifications_page'),
            'dashicons-bell',
            30
        );

        $hook_send = add_submenu_page(
            'church-app-notifications',
            'Send Notification',
            'Send Notification',
            'manage_options',
            'church-app-notifications-send',
            array($this, 'display_send_notification_page')
        );

        // Add actions to run before headers are sent
        add_action('load-' . $hook_list, array($this, 'process_notifications_list'));
        add_action('load-' . $hook_send, array($this, 'process_send_notification'));

        add_submenu_page(
            'church-app-notifications',
            'Settings',
            'Settings',
            'manage_options',
            'church-app-notification-settings',
            array($this, 'display_settings_page')
        );
    }

    public function start_notifications_list()
    {
        // Instantiate and prepare the list table early
        $this->notifications_list_table = new Church_App_Notifications_List_Table();
        $this->notifications_list_table->prepare_items();
    }

    public function process_notifications_list()
    {
        $this->start_notifications_list();
    }

    public function process_send_notification()
    {
        // Handle form submission
        if (isset($_POST['send_notification'])) {
            if (!isset($_POST['notification_nonce']) || !wp_verify_nonce($_POST['notification_nonce'], 'send_notification')) {
                wp_die('Invalid nonce');
            }

            // Get API instance
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-api.php';
            $api = new Church_App_Notifications_API();

            // Create WP_REST_Request object
            $request = new WP_REST_Request('POST', '/church-app/v1/notifications/send');

            // Set parameters
            $request->set_param('user_id', intval($_POST['user_id']));
            $request->set_param('title', sanitize_text_field($_POST['title']));
            $request->set_param('body', wp_kses_post($_POST['body']));
            $request->set_param('type', sanitize_text_field($_POST['type']));

            // Handle image URL
            $image_url = esc_url_raw($_POST['image_url']);
            if (!empty($image_url)) {
                $request->set_param('image_url', $image_url);
                $request->set_param('reference_url', $image_url);
            }

            // Send notification
            $result = $api->send_notification($request);

            // Handle result
            if (is_wp_error($result)) {
                $message = 'Error sending notification: ' . $result->get_error_message();
                $type = 'error';
            } else {
                $message = 'Notification sent successfully!';
                $type = 'success';
            }

            // Store message in transient
            set_transient('church_app_notification_message', array(
                'message' => $message,
                'type' => $type
            ), 30);

            // Redirect to notifications list
            wp_safe_redirect(add_query_arg(
                array('page' => 'church-app-notifications', 'message' => $type),
                admin_url('admin.php')
            ));
            exit;
        }
    }

    public function display_notifications_page()
    {
        // Ensure table is ready (failsafe)
        if (!isset($this->notifications_list_table)) {
            $this->start_notifications_list();
        }

        // Include the notifications list partial
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/notifications-list.php';
    }

    public function display_send_notification_page()
    {
        // Include the send notification partial
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/send-notification-page.php';
    }

    public function display_settings_page()
    {
        // Include the settings partial
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/settings.php';
    }

    public function enqueue_styles()
    {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/admin.css', array(), $this->version, 'all');

        // Add media uploader styles
        wp_enqueue_media();
    }

    public function enqueue_scripts()
    {
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/admin.js', array('jquery'), $this->version, false);

        // Add media uploader script
        wp_enqueue_script(
            'notifications-media-upload',
            plugin_dir_url(__FILE__) . 'js/media-upload.js',
            array('jquery'),
            $this->version,
            true
        );
    }
}