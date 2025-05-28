<?php
class GNT_License_Manager
{

    private $license_key_option = 'gnt_license_key';
    private $license_status_option = 'gnt_license_status';
    private $domain;

    public function __construct()
    {
        $this->domain = parse_url(home_url(), PHP_URL_HOST);
        add_filter('plugin_row_meta', array($this, 'add_license_row'), 10, 2);
        add_action('wp_ajax_gnt_validate_license', array($this, 'ajax_validate_license'));
        add_action('wp_ajax_gnt_deactivate_license', array($this, 'ajax_deactivate_license'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Add license field under plugin name in plugins list
     */
    public function add_license_row($plugin_meta, $plugin_file)
    {
        static $added = false;

        if (plugin_basename(GNT_FILE) !== $plugin_file || $added) {
            return $plugin_meta;
        }

        $added = true;

        $license_key = get_option($this->license_key_option, '');
        $license_status = get_option($this->license_status_option, 'inactive');

        $status_class = $license_status === 'valid' ? 'gnt-license-valid' : 'gnt-license-invalid';
        $status_text = $license_status === 'valid' ? __('Valid', 'gnt') : __('Invalid/Inactive', 'gnt');
        // Check if domain is localhost or local IP and no license is required
        if ($this->isLocalEnvironment($this->domain)) {
            $status_class = 'gnt-license-valid';
            $status_text = __('You appear to be working locally so no license is required for testing', 'gnt');
        }


        // Show different buttons based on license status
        $buttons_html = '';
        if ($license_status === 'valid') {
            $buttons_html = sprintf(
                '<button type="button" id="gnt-validate-license" class="button button-secondary">%s</button>
                 <button type="button" id="gnt-deactivate-license" class="button button-secondary">%s</button>',
                __('Revalidate', 'gnt'),
                __('Deactivate', 'gnt')
            );
        } else {
            $buttons_html = sprintf(
                '<button type="button" id="gnt-validate-license" class="button button-secondary">%s</button>',
                __('Validate', 'gnt')
            );
        }

        $license_row = sprintf(
            '<div class="gnt-license-row" style="margin-top: 8px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <label for="gnt-license-key" style="font-weight: 600;">%s:</label>
                    <input type="password" id="gnt-license-key" value="%s" placeholder="%s" style="width: 300px;" />
                    %s
                    <span class="gnt-license-status %s" style="font-weight: 600;">%s</span>
                    <span class="gnt-license-spinner" style="display: none;">
                        <span class="spinner" style="visibility: visible; float: none; margin: 0;"></span>
                    </span>
                </div>
                <div id="gnt-license-message" style="margin-top: 5px;"></div>
            </div>',
            __('License Key', 'gnt'),
            esc_attr($license_key),
            __('Enter your license key', 'gnt'),
            $buttons_html,
            $status_class,
            $status_text
        );

        $plugin_meta[] = $license_row;

        return $plugin_meta;
    }

    /**
     * Enqueue JavaScript for license validation
     */
    public function enqueue_scripts($hook)
    {
        if ($hook !== 'plugins.php') {
            return;
        }

        wp_enqueue_script(
            'gnt-license-admin',
            GNT_URL . 'assets/license-admin.js',
            array('jquery'),
            GNT_VERSION,
            true
        );

        wp_localize_script('gnt-license-admin', 'gntLicense', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gnt_license_nonce'),
            'validating' => __('Validating...', 'gnt'),
            'deactivating' => __('Deactivating...', 'gnt'),
            'valid' => __('Valid', 'gnt'),
            'invalid' => __('Invalid', 'gnt'),
            'inactive' => __('Inactive', 'gnt'),
            'error' => __('Error processing license', 'gnt'),
            'confirm_deactivate' => __('Are you sure you want to deactivate this license? This will remove the license from this site.', 'gnt')
        ));

        // Add inline CSS
        wp_add_inline_style('wp-admin', '
            .gnt-license-valid { color: #46b450; }
            .gnt-license-invalid { color: #dc3232; }
            .gnt-license-message { font-size: 12px; }
            .gnt-license-message.success { color: #46b450; }
            .gnt-license-message.error { color: #dc3232; }
            #gnt-deactivate-license { margin-left: 5px; }
        ');
    }

    /**
     * AJAX handler for license validation
     */
    public function ajax_validate_license()
    {
        // Verify nonce
        if (! wp_verify_nonce($_POST['nonce'], 'gnt_license_nonce')) {
            wp_die(__('Security check failed', 'gnt'));
        }

        // Check user capabilities
        if (! current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'gnt'));
        }

        $license_key = sanitize_text_field($_POST['license_key']);

        if (empty($license_key)) {
            wp_send_json_error(array(
                'message' => __('Please enter a license key', 'gnt')
            ));
        }

        // Validate the license key
        $validation_result = $this->validate_license_key($license_key);

        if ($validation_result['valid']) {
            update_option($this->license_key_option, $license_key);
            update_option($this->license_status_option, 'valid');

            wp_send_json_success(array(
                'message' => __('License key is valid and has been saved.', 'gnt'),
                'status' => 'valid'
            ));
        } else {
            update_option($this->license_status_option, 'invalid');

            wp_send_json_error(array(
                'message' => $validation_result['message']
            ));
        }
    }

    /**
     * AJAX handler for license deactivation
     */
    public function ajax_deactivate_license()
    {
        // Verify nonce
        if (! wp_verify_nonce($_POST['nonce'], 'gnt_license_nonce')) {
            wp_die(__('Security check failed', 'gnt'));
        }

        // Check user capabilities
        if (! current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'gnt'));
        }

        $license_key = get_option($this->license_key_option, '');

        if (empty($license_key)) {
            wp_send_json_error(array(
                'message' => __('No license key found to deactivate', 'gnt')
            ));
        }

        // Deactivate the license key on the server
        $deactivation_result = $this->deactivate_license_key($license_key);

        if ($deactivation_result['success']) {
            // Update local status regardless of server response for better UX
            update_option($this->license_status_option, 'inactive');
            update_option($this->license_key_option, '');

            wp_send_json_success(array(
                'message' => $deactivation_result['message'],
                'status' => 'inactive'
            ));
        } else {
            // Still update local status if server deactivation failed
            // This handles cases where the server is unreachable
            update_option($this->license_status_option, 'inactive');

            wp_send_json_success(array(
                'message' => __('License deactivated locally. Server deactivation may have failed: ', 'gnt') . $deactivation_result['message'],
                'status' => 'inactive'
            ));
        }
    }

    /**
     * Validate license key against jonmather.au license server
     */
    private function validate_license_key($license_key)
    {
        $api_url = 'https://jonmather.au/?license_api=1';

        $response = wp_remote_post($api_url, array(
            'body' => array(
                'action' => 'validate',
                'license_key' => $license_key,
                'domain' => parse_url(home_url(), PHP_URL_HOST),
                'product' => 'gravity-notifications'
            ),
            'timeout' => 15,
            'sslverify' => true
        ));

        if (is_wp_error($response)) {
            return array(
                'valid' => false,
                'message' => __('Could not connect to license server. Please try again later.', 'gnt')
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return array(
                'valid' => false,
                'message' => __('License server returned an error. Please try again later.', 'gnt')
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'valid' => false,
                'message' => __('Invalid response from license server.', 'gnt')
            );
        }

        if (isset($data['valid']) && $data['valid']) {
            return array(
                'valid' => true,
                'message' => isset($data['message']) ? $data['message'] : __('License is valid', 'gnt')
            );
        } else {
            $message = isset($data['message']) ? $data['message'] : __('Invalid license key', 'gnt');
            return array(
                'valid' => false,
                'message' => $message
            );
        }
    }

    /**
     * Deactivate license key on the server
     */
    private function deactivate_license_key($license_key)
    {
        $api_url = 'https://jonmather.au/?license_api=1';

        $response = wp_remote_post($api_url, array(
            'body' => array(
                'action' => 'deactivate',
                'license_key' => $license_key,
                'domain' => parse_url(home_url(), PHP_URL_HOST),
                'product' => 'gravity-notifications'
            ),
            'timeout' => 15,
            'sslverify' => true
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => __('Could not connect to license server.', 'gnt')
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return array(
                'success' => false,
                'message' => __('License server returned an error.', 'gnt')
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'message' => __('Invalid response from license server.', 'gnt')
            );
        }

        if (isset($data['success']) && $data['success']) {

            return array(
                'success' => true,
                'message' => isset($data['message']) ? $data['message'] : __('License deactivated successfully', 'gnt')
            );
        } else {
            $message = isset($data['message']) ? $data['message'] : __('Failed to deactivate license', 'gnt');
            return array(
                'success' => false,
                'message' => $message
            );
        }
    }

    /**
     * Check if license is valid
     */
    public function is_license_valid()
    {
        // Check if domain is localhost or local IP and return true so no license is required
        if ($this->isLocalEnvironment($this->domain)) {
            return true;
        }


        // get option for license key
        $option = get_option($this->license_key_option, '');
        if (empty($option)) {
            return false; // No license key set
        }
        // Check if the license status is valid
        if (get_option($this->license_status_option, 'inactive') === 'invalid') {
            return false; // License is explicitly marked as invalid
        }
        return get_option($this->license_status_option, 'inactive') === 'valid';
    }

    /**
     * Get license key
     */
    public function get_license_key()
    {
        return get_option($this->license_key_option, '');
    }

    public function isLocalEnvironment($domain)
    {
        // Remove port if present
        $domain = explode(':', $domain)[0];

        // Localhost variations
        $localHosts = [
            'localhost',
            '127.0.0.1',
            '::1',
            '0.0.0.0',
        ];

        if (in_array($domain, $localHosts)) {
            return true;
        }

        // Check if it's an IP address
        if (filter_var($domain, FILTER_VALIDATE_IP)) {
            // Check for private/reserved IP ranges
            if (filter_var($domain, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return true;
            }
        }

        // Check for common local development patterns
       $localPatterns = [
            '/^.*\.local$/',           // .local domains
            '/^.*\.localhost$/',       // .localhost domains
            '/^.*\.test$/',            // .test domains
            '/^.*\.dev$/',             // .dev domains (though less reliable now)
            '/^192\.168\./',           // Private IP range
            '/^10\./',                 // Private IP range
            '/^172\.(1[6-9]|2[0-9]|3[0-1])\./', // Private IP range
            '/^10\.176\.28\.106$/'     // Specific local IP
        ];


        foreach ($localPatterns as $pattern) {
            if (preg_match($pattern, $domain)) {
                return true;
            }
        }

        return false;
    }
}

function gnt_add_custom_plugin_links($links) {
    // Custom links to add
    $custom_links = [
        '<a href="' . admin_url('admin.php?page=gnt_global_notifications') . '">' . __('Global Settings', 'gnt') . '</a>',
        '<a href="' . admin_url('edit.php?post_type=gf-notifications') . '">' . __('Notifications', 'gnt') . '</a>',
    ];

    // Merge custom links with existing ones
    return array_merge($custom_links, $links);
}
add_filter('plugin_action_links_gravity-notifications/gravity-notifications.php', 'gnt_add_custom_plugin_links');

$license_manager = new GNT_License_Manager();
if ($license_manager->is_license_valid()) {
    // License is valid, enable premium features
    add_action('plugins_loaded', function () {
        load_plugin_textdomain('gnt', false, dirname(plugin_basename(__FILE__)) . '/languages');
    });

    // Loop through all php files in the inc directory and include them
    $includes = glob(GNT_PATH . 'inc/*.php');
    foreach ($includes as $file) {
        if (file_exists($file)) {
            include_once $file;
        }
    }
} else {
    // show a notice to add a license key
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>' . __('Gravity Notifications license key is invalid. Please enter a valid license key.', 'gnt') . '</p></div>';
    });
}

function gnt_shortcodes_notice()
{
    $content = '<div>';
    $content .= '<h2 style="padding: 0;">' . __('Available Shortcodes', 'gnt') . '</h2>';
    $content .= '<ul>';
    $content .= '<li><code>[gnt_site_name]</code> - ' . __('Displays the site name.', 'gnt') . '</li>';
    $content .= '<li><code>[gnt_site_name link="false"]</code> - ' . __('Displays the site name without a link.', 'gnt') . '</li>';
    $content .= '<li><code>[gnt_year]</code> - ' . __('Displays the current year.', 'gnt') . '</li>';
    $content .= '<li><code>[gnt_current_date format="Y-m-d"]</code> - ' . __('Displays the current date in the specified format.', 'gnt') . '</li>';
    $content .= '<li><code>[gnt_current_date]</code> - ' . __('Displays the current date in the default format.', 'gnt') . '</li>';
    $content .= '</ul>';
    $content .= '<p>' . __('You can use these shortcodes, or any others, in your notifications to dynamically insert content.', 'gnt') . '</p>';
    $content .= '</div>';

    return $content;
}