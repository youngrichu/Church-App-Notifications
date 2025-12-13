<?php
/**
 * Hooks and filters for notifications
 */
class Church_App_Notifications_Hooks
{
    private $expo_push;
    private $api;

    public function __construct()
    {
        $this->expo_push = new Church_App_Notifications_Expo_Push();
        $this->api = new Church_App_Notifications_API();
        $this->api->init();
        new Church_App_Blog_Notifications();
        add_action('transition_post_status', array($this, 'handle_post_status_transition'), 10, 3);
        add_action('save_post_event', array($this, 'notify_new_event'), 10, 3);
    }

    public function notify_new_post($post_id, $post)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'app_notifications';

        if ($post->post_status !== 'publish') {
            return;
        }

        $existing_notification = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE reference_id = %d AND reference_type = 'blog' LIMIT 1",
            $post_id
        ));

        if ($existing_notification) {
            return;
        }

        if (!get_option('church_app_auto_notify_new_post', true)) {
            return;
        }

        $excerpt = has_excerpt($post_id)
            ? wp_strip_all_tags(get_the_excerpt($post_id))
            : wp_trim_words(wp_strip_all_tags($post->post_content), 20);

        $featured_image_url = '';
        if (has_post_thumbnail($post_id)) {
            $featured_image_url = get_the_post_thumbnail_url($post_id, 'medium');
        }

        $post_url = get_permalink($post_id);

        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => 0,
                'title' => 'New Blog Post: ' . $post->post_title,
                'body' => $excerpt,
                'type' => 'blog',
                'created_at' => current_time('mysql'),
                'reference_id' => $post_id,
                'reference_type' => 'blog',
                'reference_url' => $post_url,
                'image_url' => $featured_image_url
            )
        );

        if ($result === false) {
            error_log('Failed to insert notification: ' . $wpdb->last_error);
            return;
        }

        $notification_id = $wpdb->insert_id;
        $this->expo_push->send_notification($notification_id);
    }

    public function notify_new_event($post_id, $post, $update)
    {
        if ($update || $post->post_status !== 'publish') {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'app_notifications';

        $existing_notification = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE reference_id = %d AND reference_type = 'event' LIMIT 1",
            $post_id
        ));

        if ($existing_notification) {
            return;
        }

        if (!get_option('church_app_auto_notify_new_event', true)) {
            return;
        }

        $excerpt = has_excerpt($post_id)
            ? wp_strip_all_tags(get_the_excerpt($post_id))
            : wp_trim_words(wp_strip_all_tags($post->post_content), 20);

        $featured_image_url = '';
        if (has_post_thumbnail($post_id)) {
            $featured_image_url = get_the_post_thumbnail_url($post_id, 'medium');
        }

        $event_url = get_permalink($post_id);

        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => 0,
                'title' => 'New Event: ' . $post->post_title,
                'body' => $excerpt,
                'type' => 'event',
                'created_at' => current_time('mysql'),
                'reference_id' => $post_id,
                'reference_type' => 'event',
                'reference_url' => $event_url,
                'image_url' => $featured_image_url
            )
        );

        if ($result === false) {
            error_log('Failed to insert notification: ' . $wpdb->last_error);
            return;
        }

        $notification_id = $wpdb->insert_id;
        $this->expo_push->send_notification($notification_id);
    }

    public function handle_post_status_transition($new_status, $old_status, $post)
    {
        if ($new_status === 'publish' && $old_status !== 'publish') {
            if ($post->post_type === 'post') {
                $this->notify_new_post($post->ID, $post);
            } elseif ($post->post_type === 'event') {
                $this->notify_new_event($post->ID, $post, false);
            }
        }
    }
}