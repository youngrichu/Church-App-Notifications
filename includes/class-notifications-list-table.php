<?php
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Church_App_Notifications_List_Table extends WP_List_Table {
    
    public function __construct() {
        parent::__construct([
            'singular' => 'notification',
            'plural'   => 'notifications',
            'ajax'     => false
        ]);
    }

    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'app_notifications';

        // Handle bulk and single actions first
        $this->process_bulk_action();
        $this->process_single_action();

        // Setup pagination
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        // Setup columns
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

        // Setup orderby
        $sortable_columns = $this->get_sortable_columns();
        $orderby = (isset($_REQUEST['orderby']) && is_string($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'created_at';

        if (array_key_exists($orderby, $sortable_columns)) {
            $orderby = $sortable_columns[$orderby][0];
        } else {
            $orderby = 'created_at';
        }

        $order = (isset($_REQUEST['order']) && is_string($_REQUEST['order'])) ? strtoupper($_REQUEST['order']) : 'DESC';
        if ($order !== 'ASC' && $order !== 'DESC') {
            $order = 'DESC';
        }

        // Setup filters
        $where = '1=1';
        $query_args = [];

        if (!empty($_REQUEST['type'])) {
            $type = sanitize_text_field($_REQUEST['type']);
            $where .= " AND type = %s";
            $query_args[] = $type;
        }
        if (isset($_REQUEST['status']) && $_REQUEST['status'] !== '') {
            $status = sanitize_text_field($_REQUEST['status']);
            $where .= " AND is_read = %s";
            $query_args[] = $status;
        }

        // Add limit/offset args
        $query_args[] = $per_page;
        $query_args[] = ($current_page - 1) * $per_page;

        // Get items
        $sql = $wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE $where 
            ORDER BY $orderby $order 
            LIMIT %d OFFSET %d",
            $query_args
        );
        
        $this->items = $wpdb->get_results($sql);

        // Setup pagination args
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }

    public function get_columns() {
        return [
            'cb'         => '<input type="checkbox" />',
            'title'      => __('Title', 'church-app-notifications'),
            'message'    => __('Message', 'church-app-notifications'),
            'type'       => __('Type', 'church-app-notifications'),
            'user'       => __('User', 'church-app-notifications'),
            'created_at' => __('Date', 'church-app-notifications'),
            'status'     => __('Status', 'church-app-notifications')
        ];
    }

    public function get_sortable_columns() {
        return [
            'title'      => ['title', true],
            'type'       => ['type', false],
            'created_at' => ['created_at', true],
            'status'     => ['is_read', false]
        ];
    }

    protected function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="notifications[]" value="%s" />',
            $item->id
        );
    }

    protected function column_title($item) {
        // Removed the delete action since it's not working properly
        return sprintf(
            '%1$s',
            esc_html($item->title)
        );
    }

    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'message':
                return wp_trim_words(esc_html($item->body), 10);
            case 'type':
                return esc_html($item->type);
            case 'user':
                return $item->user_id ? esc_html(get_userdata($item->user_id)->user_login) : 'All Users';
            case 'created_at':
                return esc_html($item->created_at);
            case 'status':
                return $item->is_read ? 'Read' : 'Unread';
            default:
                return print_r($item, true);
        }
    }

    public function get_bulk_actions() {
        return [
            'delete' => 'Delete'
        ];
    }

    protected function process_single_action() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'app_notifications';

        // Check if this is a single item deletion
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['notification'])) {
            $notification_id = intval($_GET['notification']);
            
            // Verify the nonce using the same nonce as bulk actions
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'bulk-notifications')) {
                wp_die('Security check failed');
            }

            // Delete the notification
            $wpdb->delete(
                $table_name,
                ['id' => $notification_id],
                ['%d']
            );

            // Redirect back to the list with a success message
            wp_safe_redirect(
                add_query_arg(
                    ['page' => 'church-app-notifications', 'message' => 'deleted'],
                    admin_url('admin.php')
                )
            );
            exit;
        }
    }

    protected function process_bulk_action() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'app_notifications';
        $action = $this->current_action();

        if ($action === 'delete') {
            // Verify the nonce
            $nonce = isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : '';
            if (!wp_verify_nonce($nonce, 'bulk-notifications')) {
                wp_die('Security check failed');
            }

            if (isset($_POST['notifications']) && is_array($_POST['notifications'])) {
                $notifications = array_map('intval', $_POST['notifications']);
                
                foreach ($notifications as $id) {
                    $wpdb->delete(
                        $table_name,
                        ['id' => $id],
                        ['%d']
                    );
                }

                // Fixed redirect URL
                $redirect_url = add_query_arg(
                    [
                        'page' => 'church-app-notifications',
                        'message' => 'deleted'
                    ],
                    admin_url('admin.php')
                );
                wp_redirect($redirect_url);
                exit;
            }
        }
    }

    protected function get_views() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'app_notifications';
        
        $status_links = array();
        $current = isset($_REQUEST['status']) ? $_REQUEST['status'] : 'all';

        // All link
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $status_links['all'] = sprintf(
            '<a href="%s" class="%s">All <span class="count">(%s)</span></a>',
            admin_url('admin.php?page=church-app-notifications'),
            $current === 'all' ? 'current' : '',
            $count
        );

        // Unread link
        $unread_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE is_read = '0'");
        $status_links['unread'] = sprintf(
            '<a href="%s" class="%s">Unread <span class="count">(%s)</span></a>',
            add_query_arg('status', '0', admin_url('admin.php?page=church-app-notifications')),
            $current === '0' ? 'current' : '',
            $unread_count
        );

        // Read link
        $read_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE is_read = '1'");
        $status_links['read'] = sprintf(
            '<a href="%s" class="%s">Read <span class="count">(%s)</span></a>',
            add_query_arg('status', '1', admin_url('admin.php?page=church-app-notifications')),
            $current === '1' ? 'current' : '',
            $read_count
        );

        return $status_links;
    }
}