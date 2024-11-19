<?php
/**
 * Handles push notification token registration and management
 */
class Church_App_Notifications_Token_Handler {
    private $namespace = 'church-app/v1';
    private $route = '/notifications/register-token';
    private $tokens_table;

    public function __construct() {
        global $wpdb;
        $this->tokens_table = $wpdb->prefix . 'app_push_tokens';
        
        // Add debug logging
        error_log('Token Handler initialized with table: ' . $this->tokens_table);
        
        // Add authentication filter
        add_filter('determine_current_user', array($this, 'determine_current_user_from_token'), 20);
    }

    /**
     * Decode JWT token
     */
    private function jwt_decode($token, $verify = true) {
        $parts = explode('.', $token);
        if (count($parts) != 3) {
            return false;
        }

        $header = json_decode(base64_decode(str_replace(array('-', '_'), array('+', '/'), $parts[0])));
        if (!$header) {
            return false;
        }

        $payload = json_decode(base64_decode(str_replace(array('-', '_'), array('+', '/'), $parts[1])));
        if (!$payload) {
            return false;
        }

        if ($verify) {
            // Add signature verification if needed
            return false;
        }

        return $payload;
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        error_log('Registering token routes at: ' . $this->namespace . $this->route);
        
        register_rest_route($this->namespace, $this->route, array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'register_token'),
                'permission_callback' => array($this, 'check_permission'),
                'args' => array(
                    'token' => array(
                        'required' => true,
                        'type' => 'string',
                        'validate_callback' => array($this, 'validate_token'),
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'device_type' => array(
                        'required' => false,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            ),
        ));
    }

    /**
     * Determine current user from JWT token
     */
    public function determine_current_user_from_token($user) {
        // If already authenticated, don't override
        if (!empty($user)) {
            return $user;
        }

        // Get the authorization header from the current request
        $auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : false;
        
        if (!$auth_header) {
            return null;
        }

        // Extract the token
        if (preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
            $token = $matches[1];
            
            // Decode the token
            $decoded = $this->jwt_decode($token, false);
            if ($decoded && isset($decoded->id)) {
                return $decoded->id;
            }
        }

        return null;
    }

    /**
     * Check if user has permission to register token
     */
    public function check_permission($request) {
        $user_id = get_current_user_id();
        error_log('Checking permission for user: ' . $user_id);
        return !empty($user_id);
    }

    /**
     * Validate the push notification token
     */
    public function validate_token($token) {
        error_log('Validating token: ' . $token);
        // Allow both Expo and Firebase tokens
        $valid = preg_match('/^(ExponentPushToken|ExpoPushToken)\[[\w\-_]+\]$/', $token) || 
                preg_match('/^[\w\-]{152,}$/', $token); // Firebase tokens are usually longer
        
        error_log('Token validation result: ' . ($valid ? 'valid' : 'invalid'));
        return $valid;
    }

    /**
     * Handle token registration
     */
    public function register_token($request) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        error_log('Registering token for user ' . $user_id);
        
        if (!$user_id) {
            error_log('No user ID found');
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => 'Authentication required'
                ),
                401
            );
        }

        $token = $request->get_param('token');
        $device_type = $request->get_param('device_type');

        error_log('Token: ' . $token);
        error_log('Device Type: ' . $device_type);
        error_log('Table: ' . $this->tokens_table);

        try {
            // Verify table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->tokens_table}'");
            if (!$table_exists) {
                error_log('Table does not exist: ' . $this->tokens_table);
                // Create the table if it doesn't exist
                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                $charset_collate = $wpdb->get_charset_collate();
                
                $sql = "CREATE TABLE IF NOT EXISTS {$this->tokens_table} (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    user_id bigint(20) NOT NULL,
                    token varchar(255) NOT NULL,
                    device_type varchar(50) DEFAULT NULL,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    last_used datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY token (token),
                    KEY user_id (user_id)
                ) $charset_collate;";
                
                dbDelta($sql);
                
                // Check if table was created
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->tokens_table}'");
                if (!$table_exists) {
                    throw new Exception('Failed to create tokens table');
                }
                error_log('Created tokens table');
            }

            // Check if token already exists
            $existing_token = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id FROM {$this->tokens_table} WHERE token = %s",
                    $token
                )
            );

            if ($existing_token) {
                error_log('Updating existing token');
                // Update existing token
                $updated = $wpdb->update(
                    $this->tokens_table,
                    array(
                        'user_id' => $user_id,
                        'device_type' => $device_type,
                        'last_used' => current_time('mysql'),
                    ),
                    array('token' => $token),
                    array('%d', '%s', '%s'),
                    array('%s')
                );

                if ($updated === false) {
                    error_log('Failed to update token: ' . $wpdb->last_error);
                    throw new Exception('Failed to update token: ' . $wpdb->last_error);
                }
                error_log('Token updated successfully');
            } else {
                error_log('Inserting new token');
                // Insert new token
                $inserted = $wpdb->insert(
                    $this->tokens_table,
                    array(
                        'user_id' => $user_id,
                        'token' => $token,
                        'device_type' => $device_type,
                        'last_used' => current_time('mysql'),
                    ),
                    array('%d', '%s', '%s', '%s')
                );

                if ($inserted === false) {
                    error_log('Failed to insert token: ' . $wpdb->last_error);
                    throw new Exception('Failed to insert token: ' . $wpdb->last_error);
                }
                error_log('Token inserted successfully. ID: ' . $wpdb->insert_id);
            }

            // Verify the token was saved
            $saved_token = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$this->tokens_table} WHERE token = %s",
                    $token
                )
            );
            error_log('Saved token: ' . print_r($saved_token, true));

            // Clean up old tokens
            $this->cleanup_old_tokens($user_id);

            return new WP_REST_Response(
                array(
                    'success' => true,
                    'message' => 'Token registered successfully'
                ),
                200
            );

        } catch (Exception $e) {
            error_log('Token registration error: ' . $e->getMessage());
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => $e->getMessage()
                ),
                500
            );
        }
    }

    /**
     * Clean up old tokens for a user
     */
    private function cleanup_old_tokens($user_id) {
        global $wpdb;

        try {
            // First, get current token count
            $current_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tokens_table} WHERE user_id = %d",
                $user_id
            ));

            error_log("Current token count for user {$user_id}: {$current_count}");

            if ($current_count <= 5) {
                error_log("User {$user_id} has 5 or fewer tokens, skipping cleanup");
                return;
            }

            // Get tokens that will be removed
            $tokens_to_remove = $wpdb->get_results($wpdb->prepare(
                "SELECT t1.* FROM {$this->tokens_table} t1
                LEFT JOIN (
                    SELECT id
                    FROM {$this->tokens_table}
                    WHERE user_id = %d
                    ORDER BY last_used DESC
                    LIMIT 5
                ) t2 ON t1.id = t2.id
                WHERE t1.user_id = %d
                AND t2.id IS NULL",
                $user_id,
                $user_id
            ));

            if (!empty($tokens_to_remove)) {
                error_log("Tokens to be removed for user {$user_id}:");
                foreach ($tokens_to_remove as $token) {
                    error_log("Token ID: {$token->id}, Last used: {$token->last_used}");
                }
            }

            // Keep only the 5 most recently used tokens per user
            $query = $wpdb->prepare(
                "DELETE t1 FROM {$this->tokens_table} t1
                LEFT JOIN (
                    SELECT id
                    FROM {$this->tokens_table}
                    WHERE user_id = %d
                    ORDER BY last_used DESC
                    LIMIT 5
                ) t2 ON t1.id = t2.id
                WHERE t1.user_id = %d
                AND t2.id IS NULL",
                $user_id,
                $user_id
            );

            $wpdb->query($query);
            $removed_count = $wpdb->rows_affected;
            error_log("Removed {$removed_count} old tokens for user {$user_id}");

        } catch (Exception $e) {
            error_log('Token cleanup error: ' . $e->getMessage());
        }
    }
}
