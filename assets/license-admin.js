jQuery(document).ready(function($) {
    
    // Handle license validation
    $(document).on('click', '#gnt-validate-license', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $spinner = $('.gnt-license-spinner');
        var $message = $('#gnt-license-message');
        var $status = $('.gnt-license-status');
        var licenseKey = $('#gnt-license-key').val().trim();
        
        if (!licenseKey) {
            showMessage(gntLicense.error, 'Please enter a license key', 'error');
            return;
        }
        
        // Show loading state
        $button.prop('disabled', true).text(gntLicense.validating);
        $spinner.show();
        $message.empty();
        
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
                    showMessage(response.data.message, null, 'success');
                    updateLicenseStatus('valid');
                    // Reload the page to show the deactivate button
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showMessage(response.data.message, null, 'error');
                    updateLicenseStatus('invalid');
                }
            },
            error: function() {
                showMessage(gntLicense.error, null, 'error');
                updateLicenseStatus('invalid');
            },
            complete: function() {
                $button.prop('disabled', false).text(gntLicense.valid);
                $spinner.hide();
            }
        });
    });
    
    // Handle license deactivation
    $(document).on('click', '#gnt-deactivate-license', function(e) {
        e.preventDefault();
        
        if (!confirm(gntLicense.confirm_deactivate)) {
            return;
        }
        
        var $button = $(this);
        var $spinner = $('.gnt-license-spinner');
        var $message = $('#gnt-license-message');
        var $status = $('.gnt-license-status');
        var $licenseInput = $('#gnt-license-key');
        
        // Show loading state
        $button.prop('disabled', true).text(gntLicense.deactivating);
        $spinner.show();
        $message.empty();
        
        $.ajax({
            url: gntLicense.ajax_url,
            type: 'POST',
            data: {
                action: 'gnt_deactivate_license',
                nonce: gntLicense.nonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message, null, 'success');
                    updateLicenseStatus('inactive');
                    
                    // Clear the license input field
                    $licenseInput.val('');
                    
                    // Reload the page to update the interface
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showMessage(response.data.message, null, 'error');
                }
            },
            error: function() {
                showMessage(gntLicense.error, null, 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text('Deactivate');
                $spinner.hide();
            }
        });
    });
    
    // Show/hide license key on toggle
    $(document).on('click', '.gnt-toggle-license-visibility', function(e) {
        e.preventDefault();
        var $input = $('#gnt-license-key');
        var $icon = $(this).find('i');
        
        if ($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            $input.attr('type', 'password');
            $icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });
    
    // Helper function to show messages
    function showMessage(message, title, type) {
        var $message = $('#gnt-license-message');
        var className = type === 'success' ? 'gnt-license-message success' : 'gnt-license-message error';
        
        var fullMessage = title ? title + ': ' + message : message;
        
        $message
            .removeClass('success error')
            .addClass(className.split(' ')[1])
            .html(fullMessage);
    }
    
    // Helper function to update license status display
    function updateLicenseStatus(status) {
        var $status = $('.gnt-license-status');
        var statusText, statusClass;
        
        switch(status) {
            case 'valid':
                statusText = gntLicense.valid;
                statusClass = 'gnt-license-valid';
                break;
            case 'invalid':
                statusText = gntLicense.invalid;
                statusClass = 'gnt-license-invalid';
                break;
            case 'inactive':
                statusText = gntLicense.inactive;
                statusClass = 'gnt-license-invalid';
                break;
        }
        
        $status
            .removeClass('gnt-license-valid gnt-license-invalid')
            .addClass(statusClass)
            .text(statusText);
    }
    
    // Allow Enter key to trigger validation
    $(document).on('keypress', '#gnt-license-key', function(e) {
        if (e.which === 13) { // Enter key
            $('#gnt-validate-license').click();
        }
    });
    
});