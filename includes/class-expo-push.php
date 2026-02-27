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
            $ticket_ids = array();

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

                // Process each ticket response
                if (isset($body['data']) && !empty($body['data'])) {
                    foreach ($body['data'] as $idx => $ticket) {
                        $token = $chunk[$idx]['to'];
                        if ($ticket['status'] === 'ok') {
                            // Update last_used timestamp for successful tokens
                            $wpdb->update(
                                $this->tokens_table,
                                array('last_used' => current_time('mysql')),
                                array('token' => $token),
                                array('%s'),
                                array('%s')
                            );
                        } else {
                            // Log the per-ticket error with full details
                            $error_detail = isset($ticket['details']['error']) ? $ticket['details']['error'] : 'unknown';
                            $error_message = isset($ticket['message']) ? $ticket['message'] : 'no message';
                            error_log(sprintf(
                                'Expo push ticket error for token %s: status=%s, error=%s, message=%s',
                                $token,
                                $ticket['status'],
                                $error_detail,
                                $error_message
                            ));

                            // Clean up invalid tokens
                            if (in_array($error_detail, ['DeviceNotRegistered', 'InvalidCredentials'])) {
                                $wpdb->delete(
                                    $this->tokens_table,
                                    array('token' => $token),
                                    array('%s')
                                );
                                error_log('Removed invalid token: ' . $token);
                            }

                            $success = false;
                        }
                    }
                }

                // Collect ticket IDs for receipt checking
                if (isset($body['data'])) {
                    foreach ($body['data'] as $idx => $ticket) {
                        if ($ticket['status'] === 'ok' && isset($ticket['id'])) {
                            $ticket_ids[$ticket['id']] = $chunk[$idx]['to'];
                        }
                    }
                }
            }

            // Check receipts to catch DeviceNotRegistered errors at the FCM/APNs level
            if (!empty($ticket_ids)) {
                // Small delay to let Expo process the tickets
                sleep(2);
                $this->check_receipts($ticket_ids);
            }

            return $success;

        } catch (Exception $e) {
            error_log('Error sending Expo push notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check Expo push receipts and clean up invalid tokens
     */
    private function check_receipts($ticket_ids)
    {
        global $wpdb;

        try {
            $ids = array_keys($ticket_ids);
            $response = wp_remote_post('https://exp.host/--/api/v2/push/getReceipts', array(
                'headers' => array('Content-Type' => 'application/json'),
                'body' => json_encode(array('ids' => $ids)),
                'timeout' => 30,
            ));

            if (is_wp_error($response)) {
                error_log('Failed to check push receipts: ' . $response->get_error_message());
                return;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!$body || !isset($body['data'])) {
                return;
            }

            foreach ($body['data'] as $receipt_id => $receipt) {
                if (isset($receipt['status']) && $receipt['status'] === 'error') {
                    $error = isset($receipt['details']['error']) ? $receipt['details']['error'] : 'unknown';
                    $token = isset($ticket_ids[$receipt_id]) ? $ticket_ids[$receipt_id] : 'unknown';

                    error_log(sprintf(
                        'Expo receipt error for token %s: %s — %s',
                        $token,
                        $error,
                        isset($receipt['message']) ? $receipt['message'] : ''
                    ));

                    if ($error === 'DeviceNotRegistered' && $token !== 'unknown') {
                        $wpdb->delete(
                            $this->tokens_table,
                            array('token' => $token),
                            array('%s')
                        );
                        error_log('Removed DeviceNotRegistered token via receipt check: ' . $token);
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Error checking push receipts: ' . $e->getMessage());
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
