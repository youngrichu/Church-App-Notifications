<?php
/**
 * Handles sending push notifications via Expo's Push API
 */
class Church_App_Notifications_Expo_Push
{
    private $api_url = 'https://exp.host/--/api/v2/push/send';
    private $tokens_table;
    private $notifications_table;

    public function __construct()
    {
        global $wpdb;
        $this->tokens_table = $wpdb->prefix . 'app_push_tokens';
        $this->notifications_table = $wpdb->prefix . 'app_notifications';
    }

    /**
     * Send push notification via Expo
     */
    public function send_notification($notification_id)
    {
        global $wpdb;

        try {
            // Get notification details
            $notification = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$this->notifications_table} WHERE id = %d",
                    $notification_id
                )
            );

            if (!$notification) {
                error_log("Notification not found: {$notification_id}");
                return false;
            }

            // Get tokens based on user_id
            $query = $notification->user_id === '0' || $notification->user_id === 0
                ? "SELECT DISTINCT token FROM {$this->tokens_table} WHERE token != ''"  // Get all valid tokens
                : $wpdb->prepare(
                    "SELECT DISTINCT token FROM {$this->tokens_table} WHERE user_id = %d AND token != ''",
                    $notification->user_id
                );

            $tokens = $wpdb->get_results($query);

            if (empty($tokens)) {
                return false;
            }

            // Get unread notification count for each user
            $messages = array();
            foreach ($tokens as $token_row) {
                if (empty($token_row->token)) {
                    continue;
                }

                // Get user_id for this token to count their unread notifications
                $token_user = $wpdb->get_var($wpdb->prepare(
                    "SELECT user_id FROM {$this->tokens_table} WHERE token = %s",
                    $token_row->token
                ));

                $unread_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->notifications_table} 
                    WHERE (user_id = %d OR user_id = '0') AND is_read = '0'",
                    $token_user
                ));

                // Build the deep link URL based on type
                $deep_link_url = '';
                switch ($notification->type) {
                    case 'event':
                        $deep_link_url = "dubaidebremewi://events/{$notification->reference_id}";
                        break;
                    case 'blog':
                    case 'blog_post':
                        $deep_link_url = "dubaidebremewi://blog/{$notification->reference_id}";
                        break;
                    default:
                        $deep_link_url = $notification->reference_url;
                }

                // Prepare notification data
                $notification_data = array(
                    'id' => (string) $notification->id,
                    'type' => $notification->type === 'blog' ? 'blog_post' : $notification->type,
                    'reference_id' => (string) $notification->reference_id,
                    'image_url' => $notification->image_url,
                    'reference_url' => $deep_link_url
                );

                // Create the message payload
                $message = array(
                    'to' => $token_row->token,
                    'title' => $notification->title,
                    'body' => $notification->body,
                    'data' => $notification_data,
                    'sound' => 'default',
                    'badge' => (int) $unread_count,
                    '_displayInForeground' => true,
                    'priority' => 'high'
                );

                // Add Android specific configuration
                $message['android'] = $this->get_android_config($notification);

                // Add iOS specific configuration
                if (!empty($notification->image_url)) {
                    $message['ios'] = [
                        'sound' => true,
                        'priority' => 10,
                        'attachments' => [
                            'url' => $notification->image_url
                        ]
                    ];
                }

                $messages[] = $message;
            }

            if (empty($messages)) {
                return false;
            }

            // Split messages into chunks of 100 (Expo's limit)
            $chunks = array_chunk($messages, 100);
            $success = true;

            foreach ($chunks as $chunk_index => $chunk) {
                $args = array(
                    'headers' => array(
                        'host' => 'exp.host',
                        'accept' => 'application/json',
                        'accept-encoding' => 'gzip, deflate',
                        'content-type' => 'application/json',
                    ),
                    'body' => json_encode($chunk),
                    'timeout' => 30,
                    'sslverify' => false,
                );

                $response = wp_remote_post($this->api_url, $args);

                if (is_wp_error($response)) {
                    error_log('Failed to send push notification: ' . $response->get_error_message());
                    $success = false;
                    continue;
                }

                $response_code = wp_remote_retrieve_response_code($response);
                $response_body = wp_remote_retrieve_body($response);

                if ($response_code !== 200) {
                    error_log('Expo returned non-200 status code: ' . $response_code);
                    $success = false;
                    continue;
                }

                $body = json_decode($response_body, true);

                if (!$body || !isset($body['data'])) {
                    error_log('Invalid response from Expo');
                    $success = false;
                    continue;
                }

                // Handle errors
                if (isset($body['errors']) && !empty($body['errors'])) {
                    foreach ($body['errors'] as $error) {
                        error_log('Expo Push Error: ' . print_r($error, true));
                        $this->handle_invalid_tokens(array($error));
                    }
                    $success = false;
                }

                // Update last_used timestamp for successful tokens
                if (isset($body['data']) && !empty($body['data'])) {
                    foreach ($body['data'] as $idx => $ticket) {
                        if ($ticket['status'] === 'ok') {
                            $token = $chunk[$idx]['to'];
                            $wpdb->update(
                                $this->tokens_table,
                                array('last_used' => current_time('mysql')),
                                array('token' => $token),
                                array('%s'),
                                array('%s')
                            );
                        }
                    }
                }
            }

            return $success;

        } catch (Exception $e) {
            error_log('Error sending Expo push notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get Android configuration for notification
     */
    private function get_android_config($notification)
    {
        $config = [
            'channelId' => $this->get_channel_id($notification->type),
            'priority' => 'high'
        ];

        // Add notification specific settings
        $config['notification'] = [
            'priority' => 'high'
        ];

        // Add image if available
        if (!empty($notification->image_url)) {
            $config['notification']['image'] = $notification->image_url;
        }

        // Set color based on type
        switch ($notification->type) {
            case 'event':
                $config['notification']['color'] = '#4CAF50';
                break;
            case 'blog':
            case 'blog_post':
                $config['notification']['color'] = '#2196F3';
                break;
            case 'announcement':
                $config['notification']['color'] = '#FF9800';
                break;
            default:
                $config['notification']['color'] = '#2196F3';
        }

        return $config;
    }

    /**
     * Get channel ID based on notification type
     */
    private function get_channel_id($type)
    {
        switch ($type) {
            case 'event':
                return 'events';
            case 'blog':
            case 'blog_post':
                return 'blog';
            case 'announcement':
                return 'announcements';
            default:
                return 'default';
        }
    }

    /**
     * Clean up invalid tokens based on Expo's response
     */
    private function handle_invalid_tokens($errors)
    {
        global $wpdb;

        foreach ($errors as $error) {
            if (
                isset($error['details']['error']) &&
                in_array($error['details']['error'], ['DeviceNotRegistered', 'InvalidCredentials']) &&
                isset($error['details']['token'])
            ) {

                $wpdb->delete(
                    $this->tokens_table,
                    array('token' => $error['details']['token']),
                    array('%s')
                );

                error_log('Removed invalid token: ' . $error['details']['token'] .
                    ' due to error: ' . $error['details']['error']);
            }
        }
    }
}
