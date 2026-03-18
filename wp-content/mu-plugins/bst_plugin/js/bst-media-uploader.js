document.addEventListener('DOMContentLoaded', function() {
    console.log("Media Uploader script loaded");

    var mediaUploader;

    var uploadButton = document.getElementById('upload_image_button');
    if (uploadButton) {
        uploadButton.addEventListener('click', function(e) {
            e.preventDefault();
            console.log("Upload Image button clicked");
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }
            mediaUploader = wp.media.frames.file_frame = wp.media({
                title: 'Choose Image',
                button: {
                    text: 'Choose Image'
                },
                multiple: false
            });
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                document.getElementById('bst_banner_image').value = attachment.url;
                var imgPreview = document.getElementById('bst_banner_image_preview');
                imgPreview.src = attachment.url;
                imgPreview.style.display = 'block';
            });
            mediaUploader.open();
        });
    } else {
        console.log("Upload Image button not found");
    }
});