<?php
/**
 * Hooks and filters for notifications
 */
class Church_App_Notifications_Hooks {
    private $expo_push;
    private $api;

    public function __construct() {
        error_log('Initializing Church_App_Notifications_Hooks');
        $this->expo_push = new Church_App_Notifications_Expo_Push();
        $this->api = new Church_App_Notifications_API();
        $this->api->init();
        new Church_App_Blog_Notifications();
        add_action('transition_post_status', array($this, 'handle_post_status_transition'), 10, 3);
        add_action('save_post_event', array($this, 'notify_new_event'), 10, 3);
    }

    public function notify_new_post($post_id, $post) {
        error_log('notify_new_post called for post ID: ' . $post_id);

        global $wpdb;
        $table_name = $wpdb->prefix . 'app_notifications';

        // Skip if this is not a new post or if it's not published
        if ($post->post_status !== 'publish') {
            error_log('Skipping notification: Status=' . $post->post_status);
            return;
        }

        // Check if notification already exists for this post
        $existing_notification = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE reference_id = %d AND reference_type = 'blog' LIMIT 1",
            $post_id
        ));

        if ($existing_notification) {
            error_log('Notification already exists for post ID: ' . $post_id);
            return;
        }

        if (!get_option('church_app_auto_notify_new_post', true)) {
            error_log('Auto notifications disabled for posts');
            return;
        }

        // Get post excerpt
        $excerpt = has_excerpt($post_id) 
            ? wp_strip_all_tags(get_the_excerpt($post_id)) 
            : wp_trim_words(wp_strip_all_tags($post->post_content), 20);

        // Get featured image if available
        $featured_image_url = '';
        if (has_post_thumbnail($post_id)) {
            $featured_image_url = get_the_post_thumbnail_url($post_id, 'medium');
        }

        // Get post URL
        $post_url = get_permalink($post_id);

        error_log('Creating notification for post: ' . $post->post_title);
        error_log('Featured image URL: ' . $featured_image_url);
        error_log('Post URL: ' . $post_url);
        
        // Create notification with additional data
        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => 0, // 0 means for all users
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
        error_log('Notification created successfully with ID: ' . $notification_id);

        // Send push notification using Expo
        $this->expo_push->send_notification($notification_id);
    }

    /**
     * Handle new event notifications
     */
    public function notify_new_event($post_id, $post, $update) {
        error_log('notify_new_event called for event ID: ' . $post_id);

        // Skip if this is not a new event or if it's not published
        if ($update || $post->post_status !== 'publish') {
            error_log('Skipping notification: Update=' . ($update ? 'true' : 'false') . ', Status=' . $post->post_status);
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'app_notifications';

        // Check if notification already exists for this event
        $existing_notification = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE reference_id = %d AND reference_type = 'event' LIMIT 1",
            $post_id
        ));

        if ($existing_notification) {
            error_log('Notification already exists for event ID: ' . $post_id);
            return;
        }

        if (!get_option('church_app_auto_notify_new_event', true)) {
            error_log('Auto notifications disabled for events');
            return;
        }

        // Get event excerpt
        $excerpt = has_excerpt($post_id) 
            ? wp_strip_all_tags(get_the_excerpt($post_id)) 
            : wp_trim_words(wp_strip_all_tags($post->post_content), 20);

        // Get featured image if available
        $featured_image_url = '';
        if (has_post_thumbnail($post_id)) {
            $featured_image_url = get_the_post_thumbnail_url($post_id, 'medium');
        }

        // Get event URL
        $event_url = get_permalink($post_id);

        error_log('Creating notification for event: ' . $post->post_title);
        error_log('Featured image URL: ' . $featured_image_url);
        error_log('Event URL: ' . $event_url);
        
        // Create notification with additional data
        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => 0, // 0 means for all users
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
        error_log('Notification created successfully with ID: ' . $notification_id);

        // Send push notification using Expo
        $sent = $this->expo_push->send_notification($notification_id);
        error_log('Push notification sent: ' . ($sent ? 'true' : 'false'));
    }

    public function handle_post_status_transition($new_status, $old_status, $post) {
        error_log("Post status transition: {$old_status} -> {$new_status}");
        
        // Handle both post and event transitions
        if ($new_status === 'publish' && $old_status !== 'publish') {
            if ($post->post_type === 'post') {
                error_log('Calling notify_new_post for newly published post');
                $this->notify_new_post($post->ID, $post);
            } elseif ($post->post_type === 'event') {
                error_log('Calling notify_new_event for newly published event');
                $this->notify_new_event($post->ID, $post, false);
            }
        }
    }
}