<?php
/**
 * Plugin Name: Heirloom & Hearth -- Farm-to-Table Privenance API
 * Plugin URI: https://github.com/Natnael-Alemayehu/heirloom-and-hearth
 * Description: Live Provenance API powering digital menu boards and the mobile app.
 * Version: 1.0.0
 * Author: Nate 
 */

namespace HeirloomHearth;

defined('ABSPATH') || exit;

// Constants
define('HH_VERSION', '1.0.0');
define('HH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HH_PLUGIN_FILE', __FILE__);

$hh_includes = array(
    'includes/class-cpt-registration.php',
    'includes/class-meta-fields.php',
    'includes/class-rest-api.php',
    'includes/class-supplier-role.php',
);

foreach ( $hh_includes as $hh_file ) {
    require_once HH_PLUGIN_DIR . $hh_file;
}

// BOOTSTRAP
add_action('init', array( new CPT_Registration(), 'register' ));
add_action('init', array(new Meta_Fields(), 'register'));
add_action('rest_api_init', array(new REST_API(), 'register_routes'));

register_activation_hook(__FILE__, array(__NAMESPACE__ . '\Supplier_Role', 'add_role'));
register_deactivation_hook(__FILE__, array(__NAMESPACE__ . '\Supplier_Role', 'remove_role'));