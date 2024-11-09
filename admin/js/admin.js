jQuery(document).ready(function($) {
    // Handle notification preview
    $('#preview-notification').on('click', function(e) {
        e.preventDefault();
        
        var title = $('#notification_title').val();
        var message = $('#notification_message').val();
        
        if (!title || !message) {
            alert('Please fill in both title and message fields');
            return;
        }
        
        $('#notification-preview').html(
            '<div class="preview-box">' +
            '<h4>' + title + '</h4>' +
            '<p>' + message + '</p>' +
            '</div>'
        ).show();
    });
    
    // Handle notification test send
    $('#test-notification').on('click', function(e) {
        e.preventDefault();
        
        var data = {
            action: 'test_notification',
            title: $('#notification_title').val(),
            message: $('#notification_message').val(),
            type: $('#notification_type').val(),
            nonce: $('#notification_nonce').val()
        };
        
        $.post(ajaxurl, data, function(response) {
            if (response.success) {
                alert('Test notification sent successfully!');
            } else {
                alert('Failed to send test notification: ' + response.data.message);
            }
        });
    });
    
    // Handle bulk actions
    $('#delete-selected-notifications').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to delete the selected notifications?')) {
            return;
        }
        
        var selectedIds = [];
        $('.notification-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });
        
        if (selectedIds.length === 0) {
            alert('Please select notifications to delete');
            return;
        }
        
        var data = {
            action: 'delete_notifications',
            notification_ids: selectedIds,
            nonce: $('#notification_nonce').val()
        };
        
        $.post(ajaxurl, data, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Failed to delete notifications: ' + response.data.message);
            }
        });
    });
});