// Create this file at: assets/js/license-admin.js

jQuery(document).ready(function($) {
    
    // Handle license validation button click
    $(document).on('click', '#gnt-validate-license', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $input = $('#gnt-license-key');
        var $status = $('.gnt-license-status');
        var $message = $('#gnt-license-message');
        var $spinner = $('.gnt-license-spinner');
        
        var licenseKey = $input.val().trim();
        
        if (!licenseKey) {
            showMessage('Please enter a license key', 'error');
            return;
        }
        
        // Show loading state
        $button.prop('disabled', true).text(gntLicense.validating);
        $spinner.show();
        $message.empty();
        
        // Send AJAX request
        $.ajax({
            url: gntLicense.ajax_url,
            type: 'POST',
            data: {
                action: 'gnt_validate_license',
                license_key: licenseKey,
                nonce: gntLicense.nonce
            },
            success: function(response) {
                if (response.success) {
                    $status.removeClass('gnt-license-invalid')
                           .addClass('gnt-license-valid')
                           .text(gntLicense.valid);
                    
                    showMessage(response.data.message, 'success');
                    
                    // Reload page after successful validation to clear any error notices
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    $status.removeClass('gnt-license-valid')
                           .addClass('gnt-license-invalid')
                           .text(gntLicense.invalid);
                    
                    showMessage(response.data.message, 'error');
                }
            },
            error: function() {
                $status.removeClass('gnt-license-valid')
                       .addClass('gnt-license-invalid')
                       .text(gntLicense.invalid);
                
                showMessage(gntLicense.error, 'error');
            },
            complete: function() {
                // Reset button state
                $button.prop('disabled', false).text('Validate');
                $spinner.hide();
            }
        });
        
        function showMessage(message, type) {
            $message.removeClass('success error')
                   .addClass('gnt-license-message ' + type)
                   .text(message);
        }
    });
    
    // Allow Enter key to trigger validation
    $(document).on('keypress', '#gnt-license-key', function(e) {
        if (e.which === 13) { // Enter key
            $('#gnt-validate-license').click();
        }
    });
});