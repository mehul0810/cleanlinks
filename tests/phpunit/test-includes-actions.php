<?php
/**
 * CleanLinks | Includes Actions Tests.
 *
 * @package WordPress
 * @subpackage CleanLinks
 * @since 1.0.0
 */

namespace MG\CleanLinks\Tests;

use MG\CleanLinks\Includes;
use WP_UnitTestCase;

class Test_Includes_Actions extends WP_UnitTestCase {
	/**
	 * Instance of the class being tested.
	 *
	 * @var MG\CleanLinks\Includes\Actions
	 */
	private static $class_instance;

	/**
	 * Set up the class instance to be tested.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		self::$class_instance = new Includes\Actions();
	}

	/**
	 * Tests hooks registration.
	 *
	 * @covers MG\CleanLinks\Includes\Actions::__construct
	 */
	public function test_register_hooks() {
		// Test that the template_redirect action is registered
		$this->assertSame( 10, has_action( 'template_redirect', [ self::$class_instance, 'cleanlink_redirect_and_count' ] ) );

		// Test that the wp_head action is registered for robots meta tag
		$this->assertSame( 1, has_action( 'wp_head', [ self::$class_instance, 'add_robots_meta_tag' ] ) );
	}

	/**
	 * Test robots meta tag output with noindex only.
	 *
	 * @covers MG\CleanLinks\Includes\Actions::add_robots_meta_tag
	 */
	public function test_add_robots_meta_tag_noindex_only() {
		// Create a cleanlinks post without nofollow
		$post_id = $this->factory->post->create( [
			'post_type'   => 'cleanlinks',
			'post_status' => 'publish',
		] );

		// Set redirect URL but don't enable nofollow
		update_post_meta( $post_id, 'cleanlink_redirect_url', 'https://example.com' );
		update_post_meta( $post_id, 'cleanlink_redirect_nofollow', '0' );

		// Go to the cleanlinks post
		$this->go_to( get_permalink( $post_id ) );

		// Capture output
		ob_start();
		self::$class_instance->add_robots_meta_tag();
		$output = ob_get_clean();

		// Assert that noindex meta tag is present
		$this->assertStringContainsString( '<meta name="robots" content="noindex"', $output );
		$this->assertStringNotContainsString( 'nofollow', $output );

		// Clean up
		wp_delete_post( $post_id, true );
	}

	/**
	 * Test robots meta tag output with noindex and nofollow.
	 *
	 * @covers MG\CleanLinks\Includes\Actions::add_robots_meta_tag
	 */
	public function test_add_robots_meta_tag_noindex_nofollow() {
		// Create a cleanlinks post with nofollow enabled
		$post_id = $this->factory->post->create( [
			'post_type'   => 'cleanlinks',
			'post_status' => 'publish',
		] );

		// Set redirect URL and enable nofollow
		update_post_meta( $post_id, 'cleanlink_redirect_url', 'https://example.com' );
		update_post_meta( $post_id, 'cleanlink_redirect_nofollow', '1' );

		// Go to the cleanlinks post
		$this->go_to( get_permalink( $post_id ) );

		// Capture output
		ob_start();
		self::$class_instance->add_robots_meta_tag();
		$output = ob_get_clean();

		// Assert that both noindex and nofollow meta tags are present
		$this->assertStringContainsString( '<meta name="robots" content="noindex, nofollow"', $output );

		// Clean up
		wp_delete_post( $post_id, true );
	}

	/**
	 * Test that robots meta tag is not output on non-cleanlinks pages.
	 *
	 * @covers MG\CleanLinks\Includes\Actions::add_robots_meta_tag
	 */
	public function test_add_robots_meta_tag_non_cleanlinks() {
		// Create a regular post
		$post_id = $this->factory->post->create( [
			'post_type'   => 'post',
			'post_status' => 'publish',
		] );

		// Go to the regular post
		$this->go_to( get_permalink( $post_id ) );

		// Capture output
		ob_start();
		self::$class_instance->add_robots_meta_tag();
		$output = ob_get_clean();

		// Assert that no robots meta tag is output
		$this->assertEmpty( $output );

		// Clean up
		wp_delete_post( $post_id, true );
	}
}
