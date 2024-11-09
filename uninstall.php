<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

// Delete options
delete_option('church_app_auto_notify_new_post');
delete_option('church_app_auto_notify_new_event');

// Drop the notifications table
global $wpdb;
$table_name = $wpdb->prefix . 'app_notifications';
$wpdb->query("DROP TABLE IF EXISTS $table_name");