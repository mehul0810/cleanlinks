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
		$this->assertArrayHasKey( 'cleanlinks', $post_types );
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

	/**
	 * Test that a valid metadata form saves the redirect URL and nofollow value.
	 *
	 * @since 1.1.1
	 */
	public function test_save_link_meta_persists_valid_metadata() {
		$user_id  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$post_id  = self::factory()->post->create( array( 'post_type' => 'cleanlinks' ) );
		$post     = get_post( $post_id );
		$old_post = $_POST;

		wp_set_current_user( $user_id );
		$_POST = array(
			'cleanlink_redirect_nonce'    => wp_create_nonce( 'cleanlink-save-redirect-meta' ),
			'cleanlink_redirect_url'      => 'https://example.com/destination',
			'cleanlink_redirect_nofollow' => '1',
		);

		try {
			self::$class_instance->save_link_meta( $post_id, $post );
		} finally {
			$_POST = $old_post;
			wp_set_current_user( 0 );
		}

		$this->assertSame( 'https://example.com/destination', get_post_meta( $post_id, 'cleanlink_redirect_url', true ) );
		$this->assertSame( '1', get_post_meta( $post_id, 'cleanlink_redirect_nofollow', true ) );
	}

	/**
	 * Test that an invalid URL removes previously stored redirect metadata.
	 *
	 * @since 1.1.1
	 */
	public function test_save_link_meta_removes_invalid_metadata() {
		$user_id  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$post_id  = self::factory()->post->create( array( 'post_type' => 'cleanlinks' ) );
		$post     = get_post( $post_id );
		$old_post = $_POST;

		update_post_meta( $post_id, 'cleanlink_redirect_url', 'https://example.com/old-destination' );
		update_post_meta( $post_id, 'cleanlink_redirect_nofollow', '1' );

		wp_set_current_user( $user_id );
		$_POST = array(
			'cleanlink_redirect_nonce' => wp_create_nonce( 'cleanlink-save-redirect-meta' ),
			'cleanlink_redirect_url'   => 'not-a-url',
		);

		try {
			self::$class_instance->save_link_meta( $post_id, $post );
		} finally {
			$_POST = $old_post;
			wp_set_current_user( 0 );
		}

		$this->assertSame( '', get_post_meta( $post_id, 'cleanlink_redirect_url', true ) );
		$this->assertSame( '', get_post_meta( $post_id, 'cleanlink_redirect_nofollow', true ) );
	}

	/**
	 * Malformed URL shapes fail closed without throwing or changing stored metadata.
	 *
	 * @since 1.1.1
	 */
	public function test_save_link_meta_rejects_malformed_url_shapes() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$post_id = self::factory()->post->create( array( 'post_type' => 'cleanlinks' ) );
		$post     = get_post( $post_id );
		$old_post = $_POST;

		update_post_meta( $post_id, 'cleanlink_redirect_url', 'https://example.com/old-destination' );
		update_post_meta( $post_id, 'cleanlink_redirect_nofollow', '1' );
		wp_set_current_user( $user_id );

		try {
			foreach ( array( array( 'x' ), array( 'nested' => array( 'x' ) ), (object) array( 'url' => 'x' ), 123 ) as $malformed_url ) {
				$_POST = array(
					'cleanlink_redirect_nonce' => wp_create_nonce( 'cleanlink-save-redirect-meta' ),
					'cleanlink_redirect_url'   => $malformed_url,
				);

				self::$class_instance->save_link_meta( $post_id, $post );
			}

			$_POST = array(
				'cleanlink_redirect_nonce' => array( 'malformed' ),
				'cleanlink_redirect_url'   => 'https://example.com/new-destination',
			);

			self::$class_instance->save_link_meta( $post_id, $post );
		} finally {
			$_POST = $old_post;
			wp_set_current_user( 0 );
		}

		$this->assertSame( 'https://example.com/old-destination', get_post_meta( $post_id, 'cleanlink_redirect_url', true ) );
		$this->assertSame( '1', get_post_meta( $post_id, 'cleanlink_redirect_nofollow', true ) );
	}
}
