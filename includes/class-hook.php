<?php
/**
 * Hooks and filters for notifications
 */
class Church_App_Notifications_Hooks {
    public function __construct() {
        add_action('publish_post', array($this, 'notify_new_post'), 10, 2);
        add_action('transition_post_status', array($this, 'handle_post_status_transition'), 10, 3);
    }

    public function notify_new_post($post_id, $post) {
        if (!get_option('church_app_auto_notify_new_post', true)) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'app_notifications';
        
        // Create notification
        $wpdb->insert(
            $table_name,
            array(
                'title' => 'New Blog Post',
                'body' => $post->post_title,
                'type' => 'post',
                'created_at' => current_time('mysql')
            )
        );

        // Send push notifications
        $users = get_users();
        foreach ($users as $user) {
            $token = get_user_meta($user->ID, 'expo_push_token', true);
            if ($token) {
                $this->send_push_notification($token, 'New Blog Post', $post->post_title);
            }
        }
    }

    public function handle_post_status_transition($new_status, $old_status, $post) {
        if ($new_status === 'publish' && $old_status !== 'publish') {
            $this->notify_new_post($post->ID, $post);
        }
    }

    private function send_push_notification($token, $title, $body) {
        $message = array(
            'to' => $token,
            'sound' => 'default',
            'title' => $title,
            'body' => $body,
        );

        $ch = curl_init('https://exp.host/--/api/v2/push/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }
}