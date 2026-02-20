<?php
/*
Plugin Name:  Gravity Notifications
Plugin URI:   https://jonmather.au
Description:  Mangage notifications for Gravity Forms in one place so can assign to multiple forms.
Version:      1.6.1
Author:       Jon Mather
Author URI:   https://jonmather.au
License:      GPL v2 or later
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  gnt
Domain Path:  /languages
Requires Plugins: gravityforms
*/

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

define('GNT_VERSION', '1.6.1');
define('GNT_URL', plugin_dir_url(__FILE__));
define('GNT_PATH', plugin_dir_path(__FILE__));
define('GNT_FILE', __FILE__);

// Include the updater class
require_once plugin_dir_path(__FILE__) . 'github-updater.php';

// For private repos, uncomment and add your token:
// define('SW_GITHUB_ACCESS_TOKEN', 'your_token_here');

if (class_exists('SimpliWeb_GitHub_Updater')) {
    $updater = new SimpliWeb_GitHub_Updater(__FILE__);
    $updater->set_username('westcoastdigital'); // Update Username
    $updater->set_repository('Gravity-Notifications'); // Update plugin slug
    
    if (defined('GITHUB_ACCESS_TOKEN')) {
      $updater->authorize(SW_GITHUB_ACCESS_TOKEN);
    }
    
    $updater->initialize();
}
// ============================================


include_once GNT_PATH . 'admin/init.php';