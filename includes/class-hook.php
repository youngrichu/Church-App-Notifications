<?php
/**
 * Hooks and filters for notifications
 */
class Church_App_Notifications_Hooks {
    public function __construct() {
        error_log('Initializing Church_App_Notifications_Hooks');
        add_action('transition_post_status', array($this, 'handle_post_status_transition'), 10, 3);
    }

    public function notify_new_post($post_id, $post) {
        error_log('notify_new_post called for post ID: ' . $post_id);

        global $wpdb;
        $table_name = $wpdb->prefix . 'app_notifications';

        // Check if notification already exists for this post
        $existing_notification = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE reference_id = %d AND reference_type = 'post' LIMIT 1",
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
                'type' => 'post',
                'created_at' => current_time('mysql'),
                'reference_id' => $post_id,
                'reference_type' => 'post',
                'reference_url' => $post_url,
                'image_url' => $featured_image_url
            )
        );

        if ($result === false) {
            error_log('Failed to insert notification: ' . $wpdb->last_error);
            return;
        }

        error_log('Notification created successfully');

        // Send push notifications
        $users = get_users();
        foreach ($users as $user) {
            $token = get_user_meta($user->ID, 'expo_push_token', true);
            if ($token) {
                $this->send_push_notification(
                    $token, 
                    'New Blog Post: ' . $post->post_title, 
                    $excerpt,
                    array(
                        'postId' => $post_id,
                        'postUrl' => $post_url,
                        'imageUrl' => $featured_image_url,
                        'type' => 'post'
                    )
                );
            }
        }
    }

    public function handle_post_status_transition($new_status, $old_status, $post) {
        error_log("Post status transition: {$old_status} -> {$new_status}");
        if ($new_status === 'publish' && $old_status !== 'publish' && $post->post_type === 'post') {
            error_log('Calling notify_new_post for newly published post');
            $this->notify_new_post($post->ID, $post);
        }
    }

    private function send_push_notification($token, $title, $body, $data = array()) {
        $message = array(
            'to' => $token,
            'sound' => 'default',
            'title' => $title,
            'body' => $body,
            'data' => $data
        );

        error_log('Sending push notification: ' . print_r($message, true));

        $ch = curl_init('https://exp.host/--/api/v2/push/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        error_log('Push notification response: ' . $response);
        return $response;
    }
}