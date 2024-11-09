<?php
/**
 * Database functionality for notifications
 */
class Church_App_Notifications_DB {
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'app_notifications';
    }

    public function create_tables() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'app_notifications';
        
        $charset_collate = $wpdb->get_charset_collate();
    
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
            UNIQUE KEY unique_reference (reference_type, reference_id)
        ) $charset_collate;";
    
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);
    
        error_log('Table creation result: ' . print_r($result, true));
    
        // Verify table exists and has the unique index
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        error_log('Table exists check: ' . ($table_exists ? 'Yes' : 'No'));
    
        if ($table_exists) {
            // Check if unique index exists
            $index_exists = $wpdb->get_results("SHOW INDEX FROM $table_name WHERE Key_name = 'unique_reference'");
            if (empty($index_exists)) {
                // Add unique index if it doesn't exist
                $wpdb->query("ALTER TABLE $table_name ADD UNIQUE KEY unique_reference (reference_type, reference_id)");
            }
        }
    }

    public function drop_tables() {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS $this->table_name");
    }
}