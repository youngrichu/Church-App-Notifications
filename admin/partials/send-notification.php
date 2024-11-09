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
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'app_notifications';
    
    // Insert notification
    $wpdb->insert(
        $table_name,
        array(
            'user_id' => $user_id,
            'title' => $title,
            'body' => $message,
            'type' => $type,
            'created_at' => current_time('mysql')
        )
    );
    
    // Send push notification if user has a device token
    if ($user_id > 0) {
        $token = get_user_meta($user_id, 'expo_push_token', true);
        if ($token) {
            Church_App_Notifications_API::send_push_notification($token, $title, $message);
        }
    } else {
        // Send to all users
        $users = get_users();
        foreach ($users as $user) {
            $token = get_user_meta($user->ID, 'expo_push_token', true);
            if ($token) {
                Church_App_Notifications_API::send_push_notification($token, $title, $message);
            }
        }
    }
    
    echo '<div class="notice notice-success"><p>Notification sent successfully!</p></div>';
}

// Get all users for the dropdown
$users = get_users();
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
        </table>
        
        <?php submit_button('Send Notification', 'primary', 'send_notification'); ?>
    </form>
</div>