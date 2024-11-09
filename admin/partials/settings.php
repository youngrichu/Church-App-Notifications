<?php
// Check user capabilities
if (!current_user_can('manage_options')) {
    return;
}

// Save settings
if (isset($_POST['save_settings'])) {
    update_option('church_app_auto_notify_new_post', isset($_POST['auto_notify_new_post']));
    update_option('church_app_auto_notify_new_event', isset($_POST['auto_notify_new_event']));
    echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
}

$auto_notify_post = get_option('church_app_auto_notify_new_post', true);
$auto_notify_event = get_option('church_app_auto_notify_new_event', true);
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="">
        <table class="form-table">
            <tr>
                <th scope="row">Automatic Notifications</th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="auto_notify_new_post" <?php checked($auto_notify_post); ?>>
                            Send notification for new blog posts
                        </label>
                        <br>
                        <label>
                            <input type="checkbox" name="auto_notify_new_event" <?php checked($auto_notify_event); ?>>
                            Send notification for new events
                        </label>
                    </fieldset>
                </td>
            </tr>
        </table>
        
        <?php submit_button('Save Settings', 'primary', 'save_settings'); ?>
    </form>
</div>