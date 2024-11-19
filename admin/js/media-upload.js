jQuery(document).ready(function($) {
    // Media uploader
    var mediaUploader;
    
    $('#upload_image_button').click(function(e) {
        e.preventDefault();
        
        // If the uploader object has already been created, reopen the dialog
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        
        // Create the media uploader
        mediaUploader = wp.media({
            title: 'Choose Image',
            button: {
                text: 'Choose Image'
            },
            multiple: false
        });
        
        // When an image is selected, run a callback
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#image_url').val(attachment.url);
            $('#image_preview').attr('src', attachment.url).show();
        });
        
        // Open the uploader dialog
        mediaUploader.open();
    });
    
    // Clear image button
    $('#clear_image_button').click(function(e) {
        e.preventDefault();
        $('#image_url').val('');
        $('#image_preview').attr('src', '').hide();
    });
});
