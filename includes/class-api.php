<?php
/**
 * API functionality for notifications
 */
class Church_App_Notifications_API {
    private $namespace = 'church-app/v1';
    private $expo_push;

    public function __construct() {
        $this->expo_push = new Church_App_Notifications_Expo_Push();
    }

    public function register_routes() {
        // Log registration attempt
        error_log('Registering notification routes at: ' . $this->namespace);

        // Make sure the namespace is correct
        $this->namespace = 'church-app/v1';

        // Add a debug endpoint with proper namespace
        register_rest_route($this->namespace, '/test', array(
            'methods' => WP_REST_Server::READABLE, // Use proper constant for GET
            'callback' => function() {
                return new WP_REST_Response(array(
                    'status' => 'ok',
                    'message' => 'Notifications API is working',
                    'timestamp' => current_time('mysql'),
                    'namespace' => $this->namespace
                ), 200);
            },
            'permission_callback' => '__return_true'
        ));

        // Log the full URL of the test endpoint
        error_log('Test endpoint URL: ' . rest_url($this->namespace . '/test'));

        register_rest_route($this->namespace, '/notifications', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_notifications'),
            'permission_callback' => '__return_true'
        ));

        // Add mark as read endpoint
        register_rest_route($this->namespace, '/notifications/(?P<id>\d+)/read', array(
            'methods' => 'PUT',
            'callback' => array($this, 'mark_notification_read'),
            'permission_callback' => '__return_true', // Temporarily allow all for testing
            'args' => array(
                'id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                )
            )
        ));

        // Add endpoint for sending notifications
        register_rest_route($this->namespace, '/notifications/send', array(
            'methods' => 'POST',
            'callback' => array($this, 'send_notification'),
            'permission_callback' => '__return_true',
            'args' => array(
                'user_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'description' => 'User ID (0 for all users)',
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ),
                'title' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'body' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field'
                ),
                'type' => array(
                    'required' => false,
                    'type' => 'string',
                    'default' => 'general',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'image_url' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'esc_url_raw'
                ),
                'reference_id' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'reference_type' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'reference_url' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'esc_url_raw'
                )
            )
        ));

        // Test endpoint
        register_rest_route($this->namespace, '/test-push', array(
            'methods' => 'POST',
            'callback' => array($this, 'test_push_notification'),
            'permission_callback' => '__return_true' // Allow anyone to test
        ));

        // Log after registration
        error_log('Routes registered successfully');
    }

    // Add this function to your class to ensure the plugin is loaded properly
    public function init() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    private function get_user_from_jwt($request) {
        try {
            error_log('Starting get_user_from_jwt');

            $auth_header = $request->get_header('Authorization');
            error_log('Auth header: ' . print_r($auth_header, true));

            if (!$auth_header) {
                error_log('No Authorization header found');
                return null;
            }

            // Extract token
            if (!preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
                error_log('Invalid Authorization header format');
                return null;
            }

            $jwt = $matches[1];
            error_log('Extracted JWT: ' . $jwt);

            // Decode JWT token
            $token_parts = explode('.', $jwt);
            if (count($token_parts) !== 3) {
                error_log('Invalid JWT format');
                return null;
            }

            // Decode payload
            $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $token_parts[1]));
            $payload_data = json_decode($payload);

            if (!$payload_data || !isset($payload_data->id)) {
                error_log('Invalid JWT payload');
                return null;
            }

            // Get user by ID
            $user = get_user_by('id', $payload_data->id);
            if (!$user) {
                error_log('User not found for ID: ' . $payload_data->id);
                return null;
            }

            error_log('Successfully authenticated user: ' . $user->ID);
            return $user;

        } catch (Exception $e) {
            error_log('Error in get_user_from_jwt: ' . $e->getMessage());
            return null;
        }
    }

    private function get_user_id_from_token($token) {
        try {
            error_log('Validating token: ' . $token);

            if (empty($token)) {
                error_log('Empty token provided');
                return null;
            }

            // Parse token parts
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                error_log('Invalid token format - wrong number of segments');
                return null;
            }

            // Decode payload
            $payload = $parts[1];
            $payload = str_replace(['-', '_'], ['+', '/'], $payload);
            $payload = base64_decode($payload);

            if ($payload === false) {
                error_log('Failed to decode base64 payload');
                return null;
            }

            $data = json_decode($payload);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('Failed to decode JSON payload: ' . json_last_error_msg());
                return null;
            }

            // Check for id in the correct location based on your token structure
            if (isset($data->id)) {
                error_log('Found user ID in token: ' . $data->id);
                return $data->id;
            }

            error_log('Token payload: ' . print_r($data, true));
            error_log('No user ID found in token payload');
            return null;

        } catch (Exception $e) {
            error_log('Error in get_user_id_from_token: ' . $e->getMessage());
            return null;
        }
    }

    public function get_notifications($request) {
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'app_notifications';

            error_log('Getting notifications from table: ' . $table_name);

            // Get user from JWT token
            $user = $this->get_user_from_jwt($request);
            if (!$user) {
                error_log('No valid user found in JWT token');
                return new WP_Error('unauthorized', 'Unauthorized access', array('status' => 401));
            }

            // Verify table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            error_log('Table exists check: ' . ($table_exists ? 'Yes' : 'No'));

            if (!$table_exists) {
                error_log('Notifications table does not exist!');
                return new WP_Error('table_missing', 'Notifications table does not exist', array('status' => 500));
            }

            // Get pagination parameters
            $page = isset($request['page']) ? max(1, intval($request['page'])) : 1;
            $per_page = isset($request['per_page']) ? min(50, max(1, intval($request['per_page']))) : 20;
            $offset = ($page - 1) * $per_page;

            // Prepare the query with user filtering and pagination
            $query = $wpdb->prepare(
                "SELECT SQL_CALC_FOUND_ROWS * FROM $table_name 
                WHERE user_id = %d OR user_id = 0 
                ORDER BY created_at DESC 
                LIMIT %d OFFSET %d",
                $user->ID,
                $per_page,
                $offset
            );

            error_log('Running query: ' . $query);

            // Get notifications
            $notifications = $wpdb->get_results($query);

            if ($wpdb->last_error) {
                error_log('Database error in get_notifications: ' . $wpdb->last_error);
                return new WP_Error('db_error', 'Database error: ' . $wpdb->last_error, array('status' => 500));
            }

            // Get total count for pagination
            $total_items = $wpdb->get_var("SELECT FOUND_ROWS()");
            $total_pages = ceil($total_items / $per_page);

            error_log('Retrieved notifications: ' . print_r($notifications, true));

            // Add pagination headers
            $response = new WP_REST_Response($notifications, 200);
            $response->header('X-WP-Total', $total_items);
            $response->header('X-WP-TotalPages', $total_pages);

            return $response;

        } catch (Exception $e) {
            error_log('Error in get_notifications: ' . $e->getMessage());
            return new WP_Error('server_error', $e->getMessage(), array('status' => 500));
        }
    }

    public function send_notification($request) {
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'app_notifications';

            // Get parameters from request
            $params = $request->get_params();
            
            // Validate required parameters
            if (empty($params['title']) || empty($params['body'])) {
                return new WP_Error(
                    'missing_params',
                    'Title and body are required',
                    array('status' => 400)
                );
            }

            $user_id = isset($params['user_id']) ? intval($params['user_id']) : 0;
            $title = sanitize_text_field($params['title']);
            $body = wp_kses_post($params['body']);
            $type = isset($params['type']) ? sanitize_text_field($params['type']) : 'general';
            $image_url = isset($params['image_url']) ? esc_url_raw($params['image_url']) : '';
            $reference_id = isset($params['reference_id']) ? sanitize_text_field($params['reference_id']) : '';
            $reference_type = isset($params['reference_type']) ? sanitize_text_field($params['reference_type']) : '';
            $reference_url = isset($params['reference_url']) ? esc_url_raw($params['reference_url']) : '';

            // If image_url is provided but reference_url is not, use image_url as reference_url
            if (!empty($image_url) && empty($reference_url)) {
                $reference_url = $image_url;
            }

            // Insert notification into database
            $result = $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'title' => $title,
                    'body' => $body,
                    'type' => $type,
                    'image_url' => $image_url,
                    'reference_id' => $reference_id,
                    'reference_type' => $reference_type,
                    'reference_url' => $reference_url,
                    'is_read' => '0',
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
            );

            if ($result === false) {
                error_log('Database error in send_notification: ' . $wpdb->last_error);
                return new WP_Error(
                    'db_error',
                    'Failed to create notification: ' . $wpdb->last_error,
                    array('status' => 500)
                );
            }

            // Get the inserted notification ID
            $notification_id = $wpdb->insert_id;

            // Send push notification via Expo
            if (!empty($image_url)) {
                try {
                    $this->expo_push->send_notification($notification_id);
                } catch (Exception $e) {
                    error_log('Error sending push notification: ' . $e->getMessage());
                }
            } else {
                $this->expo_push->send_notification($notification_id);
            }

            return new WP_REST_Response(
                array(
                    'id' => $notification_id,
                    'message' => 'Notification created successfully'
                ),
                201
            );

        } catch (Exception $e) {
            error_log('Error in send_notification: ' . $e->getMessage());
            return new WP_Error(
                'server_error',
                'Server error: ' . $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    public function mark_notification_read($request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'app_notifications';
        $notification_id = $request['id'];

        // Add debug logging
        error_log('Attempting to mark notification as read: ' . $notification_id);

        // Update the notification
        $result = $wpdb->update(
            $table_name,
            array('is_read' => '1'),  // Make sure it's a string '1' to match the schema
            array('id' => $notification_id),
            array('%s'),  // Format for is_read
            array('%d')   // Format for id
        );

        if ($result === false) {
            error_log('Failed to update notification: ' . $wpdb->last_error);
            return new WP_Error(
                'update_failed',
                'Failed to mark notification as read',
                array('status' => 500)
            );
        }

        // Verify the update
        $updated_notification = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $notification_id
            )
        );

        error_log('Updated notification: ' . print_r($updated_notification, true));

        return new WP_REST_Response(
            array(
                'success' => true,
                'message' => 'Notification marked as read',
                'notification' => $updated_notification
            ),
            200
        );
    }

    private static function isValidToken($token) {
        try {
            // Just check if the token exists and has the correct format
            $parts = explode('.', $token);
            if (count($parts) !== 3) return false;

            // Basic structure validation is enough since our tokens don't have expiration
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check if user has admin permission
     */
    public function check_admin_permission() {
        return current_user_can('manage_options');
    }

    /**
     * Test push notification endpoint
     */
    public function test_push_notification($request) {
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'app_notifications';

            // Simulate event data
            $event_title = isset($request['title']) ? $request['title'] : 'Test Event';
            $event_date = date('Y-m-d H:i:s');
            $event_location = isset($request['location']) ? $request['location'] : 'Dubai Debremewi Church';
            $event_id = time(); // Simulate an event ID

            // Create notification data exactly like an event
            $notification_data = array(
                'user_id' => 0, // Send to all users
                'title' => sprintf(__('New Event: %s', 'church-events-manager'), $event_title),
                'body' => sprintf(
                    __('New event scheduled for %s at %s', 'church-events-manager'),
                    date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($event_date)),
                    $event_location
                ),
                'type' => 'event',
                'reference_id' => $event_id,
                'reference_type' => 'church_event',
                'reference_url' => "dubaidebremewi://events/{$event_id}",
                'image_url' => isset($request['image_url']) ? $request['image_url'] : '',
                'created_at' => current_time('mysql')
            );

            error_log('Creating test event notification: ' . print_r($notification_data, true));

            // Insert notification
            $result = $wpdb->insert(
                $table_name,
                $notification_data,
                array(
                    '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s'
                )
            );

            if ($result === false) {
                error_log('Failed to insert test notification: ' . $wpdb->last_error);
                return new WP_Error('notification_error', 'Failed to create test notification');
            }

            $notification_id = $wpdb->insert_id;
            error_log('Created test notification with ID: ' . $notification_id);

            // Send push notification using Expo
            $expo_push = new Church_App_Notifications_Expo_Push();
            $sent = $expo_push->send_notification($notification_id);

            return array(
                'success' => $sent,
                'message' => $sent ? 'Test event notification sent successfully' : 'Failed to send test notification',
                'notification_id' => $notification_id,
                'notification_data' => $notification_data
            );

        } catch (Exception $e) {
            error_log('Error in test_push_notification: ' . $e->getMessage());
            return new WP_Error('notification_error', $e->getMessage());
        }
    }
}
