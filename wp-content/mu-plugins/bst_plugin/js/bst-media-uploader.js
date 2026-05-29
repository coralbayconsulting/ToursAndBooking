document.addEventListener('DOMContentLoaded', function() {
    function bindBannerUpload(buttonId, inputId, previewId) {
        var uploadButton = document.getElementById(buttonId);
        if (!uploadButton) {
            return;
        }

        var mediaUploader;

        uploadButton.addEventListener('click', function(e) {
            e.preventDefault();

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
                var input = document.getElementById(inputId);
                var imgPreview = document.getElementById(previewId);

                if (input) {
                    input.value = attachment.url;
                }
                if (imgPreview) {
                    imgPreview.src = attachment.url;
                    imgPreview.style.display = 'block';
                }
            });

            mediaUploader.open();
        });
    }

    bindBannerUpload('upload_image_button', 'bst_banner_image', 'bst_banner_image_preview');
    bindBannerUpload('upload_blog_banner_button', 'bst_blog_banner_image', 'bst_blog_banner_image_preview');
});
