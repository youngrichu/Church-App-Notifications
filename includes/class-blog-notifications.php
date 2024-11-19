<?php

/**
 * Handle notifications for blog posts
 */
class Church_App_Blog_Notifications {
    
    public function __construct() {
        // Hook into post publication
        add_action('publish_post', array($this, 'handle_post_notification'), 10, 2);
    }

    /**
     * Handle notification when a post is published
     * 
     * @param int $post_id The post ID
     * @param WP_Post $post The post object
     */
    public function handle_post_notification($post_id, $post) {
        // Don't send notification if this is a revision
        if (wp_is_post_revision($post_id)) {
            return;
        }

        // Don't send notification if this post was already published
        if (get_post_meta($post_id, '_notification_sent', true)) {
            return;
        }

        try {
            // Get post details
            $post_title = get_the_title($post_id);
            $post_excerpt = has_excerpt($post_id) 
                ? wp_strip_all_tags(get_the_excerpt($post_id))
                : wp_trim_words(wp_strip_all_tags($post->post_content), 20);
            
            // Get featured image if available
            $image_url = '';
            if (has_post_thumbnail($post_id)) {
                $image_url = get_the_post_thumbnail_url($post_id, 'full');
            }

            // Create notification data
            global $wpdb;
            $table_name = $wpdb->prefix . 'app_notifications';
            
            $notification_data = array(
                'user_id' => 0, // Send to all users
                'title' => sprintf(__('New Blog Post: %s', 'church-app-notifications'), $post_title),
                'body' => $post_excerpt,
                'type' => 'blog',
                'reference_id' => $post_id,
                'reference_type' => 'post',
                'reference_url' => "dubaidebremewi://blog/{$post_id}",
                'image_url' => $image_url,
                'created_at' => current_time('mysql')
            );

            // Insert notification
            $result = $wpdb->insert(
                $table_name,
                $notification_data,
                array(
                    '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s'
                )
            );

            if ($result !== false) {
                $notification_id = $wpdb->insert_id;
                
                // Send push notification using Expo
                if (class_exists('Church_App_Notifications_Expo_Push')) {
                    $expo_push = new Church_App_Notifications_Expo_Push();
                    $sent = $expo_push->send_notification($notification_id);
                    
                    if ($sent) {
                        // Mark that we've sent a notification for this post
                        update_post_meta($post_id, '_notification_sent', true);
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Error sending blog post notification: ' . $e->getMessage());
        }
    }
}
