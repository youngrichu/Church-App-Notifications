<?php
// Check user capabilities
if (!current_user_can('manage_options')) {
    return;
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
            <a href="<?php echo esc_url(admin_url('admin.php?page=church-app-notifications')); ?>"
                class="button">Cancel</a>
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