<?php
// Check user capabilities
if (!current_user_can('manage_options')) {
    return;
}

// Include the list table class
require_once plugin_dir_path(dirname(dirname(__FILE__))) . 'includes/class-notifications-list-table.php';

// Create an instance of our list table
$notifications_table = new Church_App_Notifications_List_Table();
$notifications_table->prepare_items();

// Display success message if any
if (isset($_REQUEST['message']) && $_REQUEST['message'] === 'deleted') {
    echo '<div class="updated notice is-dismissible"><p>Items deleted successfully.</p></div>';
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="get">
        <input type="hidden" name="page" value="<?php echo $_REQUEST['page']; ?>" />
        <?php
        // Display filters
        $notifications_table->views();
        
        // Display the list table
        $notifications_table->display();
        ?>
    </form>
</div>