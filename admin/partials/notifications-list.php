<?php
// Check user capabilities
if (!current_user_can('manage_options')) {
    return;
}

global $wpdb;
$table_name = $wpdb->prefix . 'app_notifications';
$notifications = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="tablenav top">
        <div class="alignleft actions">
            <button type="button" class="button delete-selected-notifications">Delete Selected</button>
        </div>
    </div>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <td class="manage-column column-cb check-column">
                    <input type="checkbox" id="cb-select-all-1">
                </td>
                <th>Title</th>
                <th>Message</th>
                <th>Type</th>
                <th>User</th>
                <th>Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($notifications as $notification): 
                $user_info = get_userdata($notification->user_id);
                $username = $user_info ? $user_info->user_login : 'All Users';
            ?>
            <tr>
                <th scope="row" class="check-column">
                    <input type="checkbox" name="notification[]" value="<?php echo esc_attr($notification->id); ?>">
                </th>
                <td><?php echo esc_html($notification->title); ?></td>
                <td><?php echo esc_html($notification->body); ?></td>
                <td><?php echo esc_html($notification->type); ?></td>
                <td><?php echo esc_html($username); ?></td>
                <td><?php echo esc_html($notification->created_at); ?></td>
                <td><?php echo $notification->is_read ? 'Read' : 'Unread'; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>