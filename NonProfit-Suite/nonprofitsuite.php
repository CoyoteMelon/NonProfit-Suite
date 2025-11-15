<?php
/**
 * NonprofitSuite
 *
 * @package           NonprofitSuite
 * @author            Brad Forschner
 * @copyright         2024 Brad Forschner
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       NonprofitSuite
 * Plugin URI:        https://silverhost.net/nonprofitsuite
 * Description:       Complete meeting and document management for nonprofits. FREE forever. Mobile-first nonprofit management system for 501(c)(3) organizations.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Brad Forschner
 * Author URI:        https://silverhost.net
 * Text Domain:       nonprofitsuite
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

/*
NonprofitSuite is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

NonprofitSuite is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with NonprofitSuite. If not, see http://www.gnu.org/licenses/gpl-2.0.txt.
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Current plugin version.
 */
if ( ! defined( 'NONPROFITSUITE_VERSION' ) ) {
	define( 'NONPROFITSUITE_VERSION', '1.0.0' );
}

/**
 * Plugin directory path.
 */
if ( ! defined( 'NONPROFITSUITE_PATH' ) ) {
	define( 'NONPROFITSUITE_PATH', plugin_dir_path( __FILE__ ) );
}

/**
 * Plugin directory URL.
 */
if ( ! defined( 'NONPROFITSUITE_URL' ) ) {
	define( 'NONPROFITSUITE_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * The code that runs during plugin activation.
 */
function activate_nonprofitsuite() {
	require_once NONPROFITSUITE_PATH . 'includes/class-activator.php';
	if ( class_exists( 'NonprofitSuite_Activator' ) ) {
		NonprofitSuite_Activator::activate();
	}
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_nonprofitsuite() {
	require_once NONPROFITSUITE_PATH . 'includes/class-deactivator.php';
	if ( class_exists( 'NonprofitSuite_Deactivator' ) ) {
		NonprofitSuite_Deactivator::deactivate();
	}
}

register_activation_hook( __FILE__, 'activate_nonprofitsuite' );
register_deactivation_hook( __FILE__, 'deactivate_nonprofitsuite' );

/**
 * Register the autoloader for lazy loading classes.
 */
require_once NONPROFITSUITE_PATH . 'includes/class-autoloader.php';
if ( class_exists( 'NonprofitSuite_Autoloader' ) ) {
	NonprofitSuite_Autoloader::register();
	NonprofitSuite_Autoloader::preload_critical_classes();
}

/**
 * The core plugin class (loaded via autoloader).
 */
$core_file = NONPROFITSUITE_PATH . 'includes/class-core.php';
if ( ! file_exists( $core_file ) ) {
	add_action( 'admin_notices', function() {
		printf(
			'<div class="error"><p>%s</p></div>',
			esc_html__( 'NonprofitSuite: core files are missing. Please reinstall the plugin.', 'nonprofitsuite' )
		);
	} );
	return;
}
require_once $core_file;

/**
 * Begins execution of the plugin.
 */
function run_nonprofitsuite() {
	if ( ! class_exists( 'NonprofitSuite_Core' ) ) {
		return;
	}
	$plugin = new NonprofitSuite_Core();
	$plugin->run();
}

run_nonprofitsuite();
