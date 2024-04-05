<?php
/**
 * Simplified Links | Admin Actions.
 *
 * @package WordPress
 * @subpackage Simplified Links
 * @since 1.0.0
 */

namespace SimplifiedWP\Links\Tests;

use SimplifiedWP\Links\Admin;
use WP_UnitTestCase;

class Test_Admin_Actions extends WP_UnitTestCase {
	/**
	 * Initialize the class.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */

	/**
	 * Instance of the class being tested.
	 *
	 * @var SimplifiedWP\Admin
	 */
	private static $class_instance;

	/**
	 * Set up the class instance to be tested.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		self::$class_instance = new Admin\Actions();
	}

	/**
	 * Tests hooks registration.
	 *
	 * @covers FewerTags\Admin::register_hooks
	 */
	public function test_register_hooks() {
		self::$class_instance->register_hooks();

		$this->assertSame( 10, has_action( 'admin_enqueue_scripts', [ self::$class_instance, 'register_assets' ] ) );
		$this->assertSame( 10, has_action( 'admin_menu', [ self::$class_instance, 'add_admin_pages' ] ) );
		$this->assertSame( 10, has_action( 'manage_simplifiedwp_links_posts_custom_column', [ self::$class_instance, 'simplifiedwp_links_custom_column_values' ] ), 10, 3 );
		$this->assertSame( 10, has_action( 'post_submitbox_minor_actions', [ self::$class_instance, 'before_preview_changes' ] ), 10, 2 );
	}

	/**
	 * Test for register_assets method.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function test_register_assets() {
		self::$class_instance->register_assets();

		$this->assertTrue( wp_script_is( 'simplified-admin', 'enqueued' ) );
	}
}
