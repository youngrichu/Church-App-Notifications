<?php
// Check user capabilities
if (!current_user_can('manage_options')) {
    return;
}

// Handle form submission
if (isset($_POST['send_notification'])) {
    $title = sanitize_text_field($_POST['notification_title']);
    $message = sanitize_textarea_field($_POST['notification_message']);
    $type = sanitize_text_field($_POST['notification_type']);
    $user_id = intval($_POST['user_id']);
    $image_url = esc_url_raw($_POST['image_url']);
    $deep_link = sanitize_text_field($_POST['deep_link']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'app_notifications';
    
    error_log('Creating notification from admin interface');
    error_log('Title: ' . $title);
    error_log('Message: ' . $message);
    error_log('Type: ' . $type);
    error_log('User ID: ' . $user_id);
    error_log('Image URL: ' . $image_url);
    error_log('Deep Link: ' . $deep_link);
    
    // Insert notification
    $result = $wpdb->insert(
        $table_name,
        array(
            'user_id' => $user_id,
            'title' => $title,
            'body' => $message,
            'type' => $type,
            'created_at' => current_time('mysql'),
            'image_url' => $image_url,
            'reference_url' => $deep_link
        )
    );

    if ($result === false) {
        error_log('Failed to insert notification: ' . $wpdb->last_error);
        echo '<div class="notice notice-error"><p>Failed to create notification: ' . esc_html($wpdb->last_error) . '</p></div>';
        return;
    }

    $notification_id = $wpdb->insert_id;
    error_log('Notification created with ID: ' . $notification_id);

    // Initialize Expo Push and send notification
    $expo_push = new Church_App_Notifications_Expo_Push();
    $sent = $expo_push->send_notification($notification_id);

    if ($sent) {
        echo '<div class="notice notice-success"><p>Notification sent successfully!</p></div>';
    } else {
        echo '<div class="notice notice-warning"><p>Notification created but push notification may have failed. Check the logs for details.</p></div>';
    }
}

// Get all users for the dropdown
$users = get_users();

// Get WordPress media uploader scripts
wp_enqueue_media();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="">
        <table class="form-table">
            <tr>
                <th scope="row"><label for="user_id">Send To</label></th>
                <td>
                    <select name="user_id" id="user_id">
                        <option value="0">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo esc_attr($user->ID); ?>">
                                <?php echo esc_html($user->user_login); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="notification_title">Title</label></th>
                <td>
                    <input type="text" name="notification_title" id="notification_title" class="regular-text" required>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="notification_message">Message</label></th>
                <td>
                    <textarea name="notification_message" id="notification_message" class="large-text" rows="5" required></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="notification_type">Type</label></th>
                <td>
                    <select name="notification_type" id="notification_type">
                        <option value="general">General</option>
                        <option value="event">Event</option>
                        <option value="blog">Blog Post</option>
                        <option value="announcement">Announcement</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="image_url">Image URL</label></th>
                <td>
                    <input type="url" name="image_url" id="image_url" class="regular-text">
                    <button type="button" class="button" id="upload_image_button">Upload Image</button>
                    <p class="description">Add an image to make your notification more engaging (optional)</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="deep_link">Deep Link</label></th>
                <td>
                    <input type="text" name="deep_link" id="deep_link" class="regular-text">
                    <p class="description">Add a deep link to direct users to specific content (e.g., dubaidebremewi://events/123)</p>
                </td>
            </tr>
        </table>
        
        <?php submit_button('Send Notification', 'primary', 'send_notification'); ?>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $('#upload_image_button').click(function(e) {
        e.preventDefault();
        var image = wp.media({ 
            title: 'Upload Image',
            multiple: false
        }).open()
        .on('select', function(e){
            var uploaded_image = image.state().get('selection').first();
            var image_url = uploaded_image.toJSON().url;
            $('#image_url').val(image_url);
        });
    });
});
</script>