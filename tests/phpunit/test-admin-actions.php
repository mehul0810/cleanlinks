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
		// These assertions should be checked here as these actions will be called on construct of Admin\Actions class.
		$this->assertSame( 10, has_action( 'admin_enqueue_scripts', [ self::$class_instance, 'register_assets' ] ) );
		$this->assertSame( 10, has_action( 'wp_ajax_simplified_import', [ self::$class_instance, 'simplified_import_all_links' ] ) );
		$this->assertSame( 10, has_action( 'admin_menu', [ self::$class_instance, 'register_admin_pages' ] ) );
		$this->assertSame( 10, has_action( 'manage_simplifiedwp_links_posts_custom_column', [ self::$class_instance, 'register_custom_columns' ] ) );
		$this->assertSame( 10, has_action( 'post_submitbox_misc_actions', [ self::$class_instance, 'before_preview_changes' ] ) );
		$this->assertSame( 10, has_action( 'admin_post_export', [ self::$class_instance, 'export_csv' ] ) );
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

	/**
	 * Test adding admin pages.
	 */
	public function test_add_admin_pages() {
		wp_set_current_user(
			self::factory()->user->create(
				array(
					'role' => 'administrator',
				)
			)
		);

		self::$class_instance->register_admin_pages();

		$this->assertTrue( $this->admin_page_exists( 'simplified_links_migrate' ) );
		$this->assertTrue( $this->admin_page_exists( 'simplified_links_import_export' ) );
		$this->assertTrue( $this->admin_page_exists( 'simplified_links_more_plugins' ) );
	}

	/**
     * Check if a specific admin page exists.
     *
     * @param string $page_slug The slug of the admin page to check.
     * @return bool True if the page exists, false otherwise.
     */
	private function admin_page_exists( $page_slug ) {
		global $submenu;

		if ( isset( $submenu['edit.php?post_type=simplifiedwp_links'] ) ) {
			foreach ( $submenu['edit.php?post_type=simplifiedwp_links'] as $submenu_item ) {
				if ( isset( $submenu_item[2] ) && $submenu_item[2] === $page_slug ) {
					return true;
				}
			}
		}

		return false;
	}
}
