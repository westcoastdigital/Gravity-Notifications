<?php
/*
Plugin Name:  Gravity Notifications
Plugin URI:   https://github.com/westcoastdigital/Gravity-Notifications
Description:  Mangage notifications for Gravity Forms in one place so can assign to multiple forms.
Version:      1.0.0
Author:       Jon Mather
Author URI:   https://jonmather.au
License:      GPL v2 or later
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  gnt
Domain Path:  /languages
Requires Plugins: gravityforms
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'GNT_VERSION', '1.0.0' );
define( 'GNT_URL', plugin_dir_url( __FILE__ ) );
define( 'GNT_PATH', plugin_dir_path( __FILE__ ) );
define( 'GNT_FILE', __FILE__ );

// Load the plugin text domain for translations
add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'gnt', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

// Loop through all php files in the inc directory and include them
$includes = glob( GNT_PATH . 'inc/*.php' );
foreach ( $includes as $file ) {
    if ( file_exists( $file ) ) {
        include_once $file;
    }
}