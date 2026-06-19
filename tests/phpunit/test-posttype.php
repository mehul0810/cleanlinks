<?php
/**
 * CleanLinks | Post Type.
 *
 * @package WordPress
 * @subpackage CleanLinks
 * @since 1.0.0
 */

namespace MG\CleanLinks\Tests;

use MG\CleanLinks\Includes;
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
	 * @var MG\CleanLinks\Includes
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
	 * Test CleanLinks CPT Exists
	 */
    public function test_register_post_type() {
		$post_types = get_post_types( array(), 'names' );
		$this->assertContains( 'cleanlinks', $post_types );
    }

    /**
	 * Test CleanLinks CPT Labels
	 */
	public function test_payment_post_type_labels() {
		$wp_post_types = get_post_types( array(), 'objects' );
		$this->assertEquals( 'CleanLinks', $wp_post_types['cleanlinks']->labels->name );
		$this->assertEquals( 'CleanLink', $wp_post_types['cleanlinks']->labels->singular_name );
		$this->assertEquals( 'Add New Link', $wp_post_types['cleanlinks']->labels->add_new );
		$this->assertEquals( 'Add New Link', $wp_post_types['cleanlinks']->labels->add_new_item );
		$this->assertEquals( 'Edit CleanLink', $wp_post_types['cleanlinks']->labels->edit_item );
		$this->assertEquals( 'New CleanLink', $wp_post_types['cleanlinks']->labels->new_item );
		$this->assertEquals( 'All Links', $wp_post_types['cleanlinks']->labels->all_items );
		$this->assertEquals( 'View CleanLink', $wp_post_types['cleanlinks']->labels->view_item );
		$this->assertEquals( 'Search links', $wp_post_types['cleanlinks']->labels->search_items );
		$this->assertEquals( 'No link found.', $wp_post_types['cleanlinks']->labels->not_found );
		$this->assertEquals( 'No links found in Trash.', $wp_post_types['cleanlinks']->labels->not_found_in_trash );
		$this->assertEquals( 'CleanLinks', $wp_post_types['cleanlinks']->labels->menu_name );
		$this->assertEquals( 'Link', $wp_post_types['cleanlinks']->labels->name_admin_bar );
		$this->assertEquals( 1, $wp_post_types['cleanlinks']->publicly_queryable );
		$this->assertEquals( 'post', $wp_post_types['cleanlinks']->capability_type );
	}
}
