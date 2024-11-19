<?php
// Check user capabilities
if (!current_user_can('manage_options')) {
    return;
}

// Handle form submission
if (isset($_POST['send_notification'])) {
    if (!isset($_POST['notification_nonce']) || !wp_verify_nonce($_POST['notification_nonce'], 'send_notification')) {
        wp_die('Invalid nonce');
    }

    // Get API instance
    require_once plugin_dir_path(dirname(dirname(__FILE__))) . 'includes/class-api.php';
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

// Get all users for the dropdown
$users = get_users(array('fields' => array('ID', 'user_email')));

// Display any error messages
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
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Send New Notification</h1>
    <hr class="wp-header-end">

    <form method="post" action="" class="send-notification-form">
        <?php wp_nonce_field('send_notification', 'notification_nonce'); ?>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="user_id">Send To</label></th>
                <td>
                    <select name="user_id" id="user_id" required>
                        <option value="0">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo esc_attr($user->ID); ?>">
                                <?php echo esc_html($user->user_email); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="title">Title</label></th>
                <td>
                    <input type="text" name="title" id="title" class="regular-text" required>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="body">Message</label></th>
                <td>
                    <?php
                    $editor_settings = array(
                        'textarea_name' => 'body',
                        'textarea_rows' => 10,
                        'media_buttons' => false,
                        'tinymce' => array(
                            'toolbar1' => 'bold,italic,underline,bullist,numlist,link,unlink',
                            'toolbar2' => '',
                        ),
                        'quicktags' => false,
                    );
                    wp_editor('', 'body', $editor_settings);
                    ?>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="type">Type</label></th>
                <td>
                    <select name="type" id="type">
                        <option value="general">General</option>
                        <option value="event">Event</option>
                        <option value="news">News</option>
                        <option value="announcement">Announcement</option>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="image_url">Image</label></th>
                <td>
                    <input type="text" name="image_url" id="image_url" class="regular-text" readonly>
                    <input type="button" id="upload_image_button" class="button" value="Choose Image">
                    <input type="button" id="clear_image_button" class="button" value="Clear">
                    <br>
                    <img id="image_preview" src="" style="max-width: 300px; margin-top: 10px; display: none;">
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="send_notification" class="button button-primary" value="Send Notification">
            <a href="<?php echo esc_url(admin_url('admin.php?page=church-app-notifications')); ?>" class="button">Cancel</a>
        </p>
    </form>
</div>

<style>
.send-notification-form {
    max-width: 800px;
    margin-top: 20px;
}

.form-table td {
    padding: 15px 10px;
}

.form-table th {
    padding: 20px 10px 20px 0;
}

#image_preview {
    border: 1px solid #ddd;
    padding: 5px;
    background: #f9f9f9;
}

/* TinyMCE Editor Styles */
.wp-editor-wrap {
    max-width: 100%;
}

.wp-editor-area {
    border: 1px solid #ddd !important;
}
</style>
