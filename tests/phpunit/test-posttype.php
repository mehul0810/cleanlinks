<?php
/**
 * Simplified Links | Admin Actions.
 *
 * @package WordPress
 * @subpackage Simplified Links
 * @since 1.0.0
 */

namespace SimplifiedWP\Links\Tests;

use SimplifiedWP\Links\Includes;
use WP_UnitTestCase;

class Test_PostType extends WP_UnitTestCase {
    /**
	 * Post type.
	 *
	 * @since 4.5.0
	 * @var string
	 */
	public $post_type;

    /**
	 * Instance of the class being tested.
	 *
	 * @var SimplifiedWP\Links\Includes
	 */
	private static $class_instance;

	/**
	 * Set up.
	 *
	 * @since 4.5.0
	 */
	public function set_up() {
		parent::set_up();
        self::$class_instance = new Includes\PostType();
	}

    /**
	 * Tests hooks registration.
	 *
	 * @covers FewerTags\Admin::register_hooks
	 */
	public function test_register_hooks() {
		// These assertions should be checked here as these actions will be called on construct of Admin\Actions class.
		$this->assertSame( 10, has_action( 'init', [ self::$class_instance, 'register_post_type' ] ) );
	}

	/**
	 * Test Simplifiedwp CPT Exists
	 */
	    public function test_register_post_type() {
			self::$class_instance->register_post_type();
			$post_types = get_post_types( array(), 'names' );
			$this->assertContains( 'simplifiedwp_links', $post_types );
	    }

    /**
	 * Test Simplifiedwp CPT Labels
	 */
		public function test_payment_post_type_labels() {
			self::$class_instance->register_post_type();
			$wp_post_types = get_post_types( array(), 'objects' );
			$this->assertEquals( 'Simplified Links', $wp_post_types['simplifiedwp_links']->labels->name );
		$this->assertEquals( 'Simplified Link', $wp_post_types['simplifiedwp_links']->labels->singular_name );
		$this->assertEquals( 'Add New Link', $wp_post_types['simplifiedwp_links']->labels->add_new );
		$this->assertEquals( 'Add New Link', $wp_post_types['simplifiedwp_links']->labels->add_new_item );
		$this->assertEquals( 'Edit Simplified Link', $wp_post_types['simplifiedwp_links']->labels->edit_item );
		$this->assertEquals( 'New Simplified Link', $wp_post_types['simplifiedwp_links']->labels->new_item );
		$this->assertEquals( 'All Links', $wp_post_types['simplifiedwp_links']->labels->all_items );
		$this->assertEquals( 'View Simplified Link', $wp_post_types['simplifiedwp_links']->labels->view_item );
		$this->assertEquals( 'Search Simplified Links', $wp_post_types['simplifiedwp_links']->labels->search_items );
		$this->assertEquals( 'No Simplified Links found.', $wp_post_types['simplifiedwp_links']->labels->not_found );
		$this->assertEquals( 'No Simplified Links found in Trash.', $wp_post_types['simplifiedwp_links']->labels->not_found_in_trash );
		$this->assertEquals( 'Simplified Links', $wp_post_types['simplifiedwp_links']->labels->menu_name );
		$this->assertEquals( 'Simplified Link', $wp_post_types['simplifiedwp_links']->labels->name_admin_bar );
		$this->assertEquals( 1, $wp_post_types['simplifiedwp_links']->publicly_queryable );
		$this->assertEquals( 'post', $wp_post_types['simplifiedwp_links']->capability_type );
	}
}
