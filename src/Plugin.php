<?php
/**
 * CleanLinks | Main Plugin File.
 *
 * @package WordPress
 * @subpackage CleanLinks
 * @since 1.0.0
 */

namespace MG\CleanLinks;

use MG\CleanLinks\Includes\PostType;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads and registers plugin functionality through WordPress hooks.
 *
 * @since 1.0.0
 */
final class Plugin {

	/**
	 * Registers functionality with WordPress hooks.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function register() {
		// Handle plugin activation and deactivation.
		register_activation_hook( CLEANLINKS_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( CLEANLINKS_PLUGIN_FILE, array( $this, 'deactivate' ) );

		// Register services used throughout the plugin.
		add_action( 'plugins_loaded', array( $this, 'register_services' ) );

		// Load text domain.
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
	}

	/**
	 * Registers the individual services of the plugin.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function register_services() {
		new Includes\PostType();
		new Includes\TaxoNomy();
		new Includes\Filters();
		new Includes\Actions();

		if ( is_admin() ) {
			new Admin\Filters();
			new Admin\Actions();
			new Admin\Export();
		}
	}

	/**
	 * Loads the plugin's translated strings.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'cleanlinks',
			false,
			dirname( plugin_basename( CLEANLINKS_PLUGIN_FILE ) ) . '/languages/'
		);
	}

	/**
	 * Handles activation procedures during installation and updates.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param bool $network_wide Optional. Whether the plugin is being enabled on
	 *                           all network sites or a single site. Default false.
	 *
	 * @return void
	 */
	public function activate( $network_wide = false ) {
		$post_type = new Includes\PostType();
		$post_type->register_post_type();

		// Flush rewrite rules to ensure the new post type is recognized.
		// This is necessary to avoid 404 errors on the first load.
		// This should be done only on activation, not on every page load.
		flush_rewrite_rules();
	}

	/**
	 * Handles deactivation procedures.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function deactivate() {}
}
