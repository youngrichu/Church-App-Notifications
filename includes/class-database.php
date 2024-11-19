<?php
/**
 * Database functionality for notifications
 */
class Church_App_Notifications_DB {
    private $table_name;
    private $tokens_table;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'app_notifications';
        $this->tokens_table = $wpdb->prefix . 'app_push_tokens';
    }

    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Create notifications table
        $table_name = $wpdb->prefix . 'app_notifications';
        error_log('Creating notifications table...');
    
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL DEFAULT '0',
            title varchar(255) NOT NULL,
            body text NOT NULL,
            type varchar(50) NOT NULL,
            is_read char(1) NOT NULL DEFAULT '0',
            created_at datetime NOT NULL,
            reference_id bigint(20) DEFAULT NULL,
            reference_type varchar(50) DEFAULT NULL,
            reference_url text DEFAULT NULL,
            image_url text DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id)
        ) $charset_collate;";
    
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);
        error_log('Notifications table creation result: ' . print_r($result, true));
    
        // Create push tokens table with correct name
        $tokens_table = $wpdb->prefix . 'app_push_tokens';
        error_log('Creating push tokens table...');

        $sql_tokens = "CREATE TABLE IF NOT EXISTS $tokens_table (
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

        $result = dbDelta($sql_tokens);
        error_log('Push tokens table creation result: ' . print_r($result, true));

        // Verify tables exist
        $notifications_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        $tokens_exists = $wpdb->get_var("SHOW TABLES LIKE '$tokens_table'");
        
        error_log('Notifications table exists: ' . ($notifications_exists ? 'Yes' : 'No'));
        error_log('Push tokens table exists: ' . ($tokens_exists ? 'Yes' : 'No'));

        if (!$notifications_exists || !$tokens_exists) {
            error_log('Tables were not created properly. Trying direct SQL...');
            
            if (!$notifications_exists) {
                $wpdb->query($sql);
            }
            
            if (!$tokens_exists) {
                $wpdb->query($sql_tokens);
            }
            
            // Check again
            $notifications_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            $tokens_exists = $wpdb->get_var("SHOW TABLES LIKE '$tokens_table'");
            
            error_log('After direct SQL - Notifications table exists: ' . ($notifications_exists ? 'Yes' : 'No'));
            error_log('After direct SQL - Push tokens table exists: ' . ($tokens_exists ? 'Yes' : 'No'));
        }
    }

    public function drop_tables() {
        global $wpdb;
        error_log('Dropping tables...');
        
        $wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");
        $wpdb->query("DROP TABLE IF EXISTS {$this->tokens_table}");
        
        error_log('Tables dropped');
    }
}