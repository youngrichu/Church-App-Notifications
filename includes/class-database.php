<?php
/**
 * Database functionality for notifications
 */
class Church_App_Notifications_DB
{
    private $table_name;
    private $tokens_table;
    private $reads_table;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'app_notifications';
        $this->tokens_table = $wpdb->prefix . 'app_push_tokens';
        $this->reads_table = $wpdb->prefix . 'app_notification_reads';
    }

    public function create_tables()
    {
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

        // Create notification reads table for per-user read tracking
        $this->create_reads_table();

        // Verify tables exist
        $notifications_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        $tokens_exists = $wpdb->get_var("SHOW TABLES LIKE '$tokens_table'");
        $reads_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->reads_table}'");

        error_log('Notifications table exists: ' . ($notifications_exists ? 'Yes' : 'No'));
        error_log('Push tokens table exists: ' . ($tokens_exists ? 'Yes' : 'No'));
        error_log('Notification reads table exists: ' . ($reads_exists ? 'Yes' : 'No'));

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

    /**
     * Create the notification reads table for per-user read tracking
     */
    public function create_reads_table()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        error_log('Creating notification reads table...');

        $sql_reads = "CREATE TABLE IF NOT EXISTS {$this->reads_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            notification_id bigint(20) NOT NULL,
            read_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_notification (user_id, notification_id),
            KEY user_id (user_id),
            KEY notification_id (notification_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql_reads);
        error_log('Notification reads table creation result: ' . print_r($result, true));

        // Verify table exists
        $reads_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->reads_table}'");
        if (!$reads_exists) {
            error_log('Reads table not created, trying direct SQL...');
            $wpdb->query($sql_reads);
        }
    }

    public function drop_tables()
    {
        global $wpdb;
        error_log('Dropping tables...');

        $wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");
        $wpdb->query("DROP TABLE IF EXISTS {$this->tokens_table}");
        $wpdb->query("DROP TABLE IF EXISTS {$this->reads_table}");

        error_log('Tables dropped');
    }

    /**
     * Mark a notification as read for a specific user
     * 
     * @param int $user_id The user ID
     * @param int $notification_id The notification ID
     * @return bool True on success, false on failure
     */
    public function mark_as_read($user_id, $notification_id)
    {
        global $wpdb;

        // Use INSERT IGNORE to handle duplicates gracefully
        $result = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$this->reads_table} (user_id, notification_id, read_at) VALUES (%d, %d, %s)",
            $user_id,
            $notification_id,
            current_time('mysql')
        ));

        if ($result === false) {
            error_log('Failed to mark notification as read: ' . $wpdb->last_error);
            return false;
        }

        return true;
    }

    /**
     * Mark all notifications as read for a specific user
     * 
     * @param int $user_id The user ID
     * @return int|false Number of notifications marked as read, or false on failure
     */
    public function mark_all_as_read($user_id)
    {
        global $wpdb;

        // Get all unread notification IDs for this user
        $unread_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT n.id FROM {$this->table_name} n
             LEFT JOIN {$this->reads_table} r ON n.id = r.notification_id AND r.user_id = %d
             WHERE (n.user_id = %d OR n.user_id = 0) AND r.id IS NULL",
            $user_id,
            $user_id
        ));

        if (empty($unread_ids)) {
            return 0;
        }

        $count = 0;
        foreach ($unread_ids as $notification_id) {
            if ($this->mark_as_read($user_id, $notification_id)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Check if a notification has been read by a specific user
     * 
     * @param int $user_id The user ID
     * @param int $notification_id The notification ID
     * @return bool True if read, false otherwise
     */
    public function is_read($user_id, $notification_id)
    {
        global $wpdb;

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->reads_table} WHERE user_id = %d AND notification_id = %d",
            $user_id,
            $notification_id
        ));

        return !is_null($result);
    }

    /**
     * Get the count of unread notifications for a specific user
     * 
     * @param int $user_id The user ID
     * @return int The count of unread notifications
     */
    public function get_unread_count($user_id)
    {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} n
             LEFT JOIN {$this->reads_table} r ON n.id = r.notification_id AND r.user_id = %d
             WHERE (n.user_id = %d OR n.user_id = 0) AND r.id IS NULL",
            $user_id,
            $user_id
        ));

        return (int) $count;
    }

    /**
     * Get the reads table name
     * 
     * @return string The reads table name
     */
    public function get_reads_table()
    {
        return $this->reads_table;
    }

    /**
     * Get the notifications table name
     * 
     * @return string The notifications table name
     */
    public function get_table_name()
    {
        return $this->table_name;
    }
}