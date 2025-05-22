<?php
// Add this to your main plugin file or create a new file in your inc/ directory

class GNT_License_Manager {
    
    private $license_key_option = 'gnt_license_key';
    private $license_status_option = 'gnt_license_status';
    
    public function __construct() {
        add_filter( 'plugin_row_meta', array( $this, 'add_license_row' ), 10, 2 );
        add_action( 'wp_ajax_gnt_validate_license', array( $this, 'ajax_validate_license' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }
    
    /**
     * Add license field under plugin name in plugins list
     */
    public function add_license_row( $plugin_meta, $plugin_file ) {
        static $added = false;
        
        if ( plugin_basename( GNT_FILE ) !== $plugin_file || $added ) {
            return $plugin_meta;
        }
        
        $added = true;
        
        $license_key = get_option( $this->license_key_option, '' );
        $license_status = get_option( $this->license_status_option, 'inactive' );
        
        $status_class = $license_status === 'valid' ? 'gnt-license-valid' : 'gnt-license-invalid';
        $status_text = $license_status === 'valid' ? __( 'Valid', 'gnt' ) : __( 'Invalid/Inactive', 'gnt' );
        
        $license_row = sprintf(
            '<div class="gnt-license-row" style="margin-top: 8px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <label for="gnt-license-key" style="font-weight: 600;">%s:</label>
                    <input type="password" id="gnt-license-key" value="%s" placeholder="%s" style="width: 300px;" />
                    <button type="button" id="gnt-validate-license" class="button button-secondary">%s</button>
                    <span class="gnt-license-status %s" style="font-weight: 600;">%s</span>
                    <span class="gnt-license-spinner" style="display: none;">
                        <span class="spinner" style="visibility: visible; float: none; margin: 0;"></span>
                    </span>
                </div>
                <div id="gnt-license-message" style="margin-top: 5px;"></div>
            </div>',
            __( 'License Key', 'gnt' ),
            esc_attr( $license_key ),
            __( 'Enter your license key', 'gnt' ),
            __( 'Validate', 'gnt' ),
            $status_class,
            $status_text
        );
        
        $plugin_meta[] = $license_row;
        
        return $plugin_meta;
    }
    
    /**
     * Enqueue JavaScript for license validation
     */
    public function enqueue_scripts( $hook ) {
        if ( $hook !== 'plugins.php' ) {
            return;
        }
        
        wp_enqueue_script( 
            'gnt-license-admin', 
            GNT_URL . 'assets/license-admin.js', 
            array( 'jquery' ), 
            GNT_VERSION, 
            true 
        );
        
        wp_localize_script( 'gnt-license-admin', 'gntLicense', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'gnt_license_nonce' ),
            'validating' => __( 'Validating...', 'gnt' ),
            'valid' => __( 'Valid', 'gnt' ),
            'invalid' => __( 'Invalid', 'gnt' ),
            'error' => __( 'Error validating license', 'gnt' )
        ) );
        
        // Add inline CSS
        wp_add_inline_style( 'wp-admin', '
            .gnt-license-valid { color: #46b450; }
            .gnt-license-invalid { color: #dc3232; }
            .gnt-license-message { font-size: 12px; }
            .gnt-license-message.success { color: #46b450; }
            .gnt-license-message.error { color: #dc3232; }
        ' );
    }
    
    /**
     * AJAX handler for license validation
     */
    public function ajax_validate_license() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'gnt_license_nonce' ) ) {
            wp_die( __( 'Security check failed', 'gnt' ) );
        }
        
        // Check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions', 'gnt' ) );
        }
        
        $license_key = sanitize_text_field( $_POST['license_key'] );
        
        if ( empty( $license_key ) ) {
            wp_send_json_error( array(
                'message' => __( 'Please enter a license key', 'gnt' )
            ) );
        }
        
        // Validate the license key
        $validation_result = $this->validate_license_key( $license_key );
        
        if ( $validation_result['valid'] ) {
            update_option( $this->license_key_option, $license_key );
            update_option( $this->license_status_option, 'valid' );
            
            wp_send_json_success( array(
                'message' => __( 'License key is valid and has been saved.', 'gnt' ),
                'status' => 'valid'
            ) );
        } else {
            update_option( $this->license_status_option, 'invalid' );
            
            wp_send_json_error( array(
                'message' => $validation_result['message']
            ) );
        }
    }
    
    /**
     * Validate license key against jonmather.au license server
     */
    private function validate_license_key( $license_key ) {
        $api_url = 'https://jonmather.au/?license_api=1';
        
        $response = wp_remote_post( $api_url, array(
            'body' => array(
                'action' => 'validate',
                'license_key' => $license_key,
                'domain' => parse_url( home_url(), PHP_URL_HOST ),
                'product' => 'gravity-notifications'
            ),
            'timeout' => 15,
            'sslverify' => true
        ) );
        
        if ( is_wp_error( $response ) ) {
            return array(
                'valid' => false,
                'message' => __( 'Could not connect to license server. Please try again later.', 'gnt' )
            );
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            return array(
                'valid' => false,
                'message' => __( 'License server returned an error. Please try again later.', 'gnt' )
            );
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return array(
                'valid' => false,
                'message' => __( 'Invalid response from license server.', 'gnt' )
            );
        }
        
        if ( isset( $data['valid'] ) && $data['valid'] ) {
            return array(
                'valid' => true,
                'message' => isset( $data['message'] ) ? $data['message'] : __( 'License is valid', 'gnt' )
            );
        } else {
            $message = isset( $data['message'] ) ? $data['message'] : __( 'Invalid license key', 'gnt' );
            return array(
                'valid' => false,
                'message' => $message
            );
        }
    }
    
    /**
     * Check if license is valid
     */
    public function is_license_valid() {
        return get_option( $this->license_status_option, 'inactive' ) === 'valid';
    }
    
    /**
     * Get license key
     */
    public function get_license_key() {
        return get_option( $this->license_key_option, '' );
    }
}

// Initialize the license manager
new GNT_License_Manager();