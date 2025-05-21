<?php
/**
 * CleanLinks | Admin Actions.
 *
 * @package WordPress
 * @subpackage CleanLinks
 * @since 1.0.0
 */

namespace MG\CleanLinks\Tests;

use MG\CleanLinks\Admin;
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
	 * @var CleanLinks\Admin
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
		$this->assertSame( 10, has_action( 'admin_menu', [ self::$class_instance, 'add_admin_pages' ] ) );
		$this->assertSame( 10, has_action( 'manage_clean_links_posts_custom_column', [ self::$class_instance, 'clean_links_custom_column_values' ] ), 10, 3 );
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
		// Test with a non-CleanLinks page
		self::$class_instance->register_assets( 'edit.php' );
		$this->assertFalse( wp_script_is( 'cleanlink-admin', 'enqueued' ) );
		
		// Test with a CleanLinks page
		self::$class_instance->register_assets( 'edit.php?post_type=clean_links' );
		$this->assertTrue( wp_script_is( 'cleanlink-admin', 'enqueued' ) );
	}
	
	 /**
     * Test adding admin pages.
     */
    public function test_add_admin_pages() {
        // Create a new instance of the Actions class
        self::$class_instance->add_admin_pages();

        // Check if the admin pages are added
        $this->assertTrue( $this->admin_page_exists( 'cleanlinks_reports' ) );
        $this->assertTrue( $this->admin_page_exists( 'cleanlinks_support' ) );
        $this->assertTrue( $this->admin_page_exists( 'cleanlinks_more_plugins' ) );
    }

	/**
     * Check if a specific admin page exists.
     *
     * @param string $page_slug The slug of the admin page to check.
     * @return bool True if the page exists, false otherwise.
     */
    private function admin_page_exists( $page_slug ) {
        global $submenu;

        if ( isset( $submenu['edit.php?post_type=clean_links'] ) ) {
            foreach ( $submenu['edit.php?post_type=clean_links'] as $submenu_item ) {
                if ( isset( $submenu_item[2] ) && $submenu_item[2] === $page_slug ) {
                    return true;
                }
            }
        }

        return false;
    }
}
