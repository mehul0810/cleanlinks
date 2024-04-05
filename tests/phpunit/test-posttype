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

    public function test_register_post_type() {
        global $wp_post_types;

        if ( isset( $wp_post_types[ 'simplifiedwp_links' ] ) ) {
            unset( $wp_post_types[ 'simplifiedwp_links' ] );
        }
        $this->assertarrayNotHasKey( 'simplifiedwp_links', $wp_post_types );

        // register post type
        self::$class_instance->register_post_type();
    }

     /**
     * Test querying custom posts.
     */
    public function test_query_custom_posts() {
        $posts = get_posts(array('post_type' => 'simplifiedwp_links'));
        $this->assertNotEmpty($posts);
    }
}