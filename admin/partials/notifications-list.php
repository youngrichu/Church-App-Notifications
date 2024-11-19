<?php
// Check user capabilities
if (!current_user_can('manage_options')) {
    return;
}

// Include the list table class
require_once plugin_dir_path(dirname(dirname(__FILE__))) . 'includes/class-notifications-list-table.php';

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

// Create an instance of our list table
$notifications_table = new Church_App_Notifications_List_Table();
$notifications_table->prepare_items();

// Display any messages
if (isset($_GET['message'])) {
    $stored_message = get_transient('church_app_notification_message');
    if ($stored_message) {
        ?>
        <div class="notice notice-<?php echo esc_attr($stored_message['type']); ?> is-dismissible">
            <p><?php echo esc_html($stored_message['message']); ?></p>
        </div>
        <?php
        delete_transient('church_app_notification_message');
    }
}

// Handle the modal form submission
if (isset($_POST['send_notification'])) {
    require_once plugin_dir_path(dirname(dirname(__FILE__))) . 'admin/partials/send-notification.php';
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <a href="<?php echo esc_url(admin_url('admin.php?page=church-app-notifications-send')); ?>" class="page-title-action">Add New</a>
    
    <hr class="wp-header-end">
    
    <form method="post">
        <?php wp_nonce_field('bulk-notifications'); ?>
        <input type="hidden" name="page" value="church-app-notifications" />
        <?php
        // Display filters
        $notifications_table->views();
        
        // Display the list table
        $notifications_table->display();
        ?>
    </form>
</div>

<style>
#send-notification-modal {
    display: none;
    position: fixed;
    z-index: 999999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.4);
}

.send-notification-modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 80%;
    max-width: 800px;
    position: relative;
    border-radius: 4px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.close-modal {
    position: absolute;
    right: 10px;
    top: 5px;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    color: #666;
}

.close-modal:hover {
    color: #000;
}

.notice {
    margin: 20px 0 10px;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Close modal when clicking outside
    $('#send-notification-modal').click(function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });

    // Add close button to modal
    $('.send-notification-modal-content').prepend('<span class="close-modal">&times;</span>');
    
    // Close modal when clicking the close button
    $('.close-modal').click(function() {
        $('#send-notification-modal').hide();
    });
});
</script>