<?php
/**
 * CleanLinks | Actions Tests.
 *
 * @package WordPress
 * @subpackage CleanLinks
 * @since 1.1.0
 */

namespace MG\CleanLinks\Tests;

use MG\CleanLinks\Includes\Actions;
use ReflectionMethod;
use WP_UnitTestCase;

class Test_Actions extends WP_UnitTestCase {
	/**
	 * Test that published cleanlinks increment the redirect count.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function test_published_cleanlinks_increment_access_count() {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => 'cleanlinks',
				'post_status' => 'publish',
			)
		);

		update_post_meta( $post_id, 'cleanlink_redirect_count', 2 );

		$count = $this->invoke_update_access_count( $post_id );

		$this->assertSame( 3, $count );
		$this->assertSame( '3', get_post_meta( $post_id, 'cleanlink_redirect_count', true ) );
	}

	/**
	 * Test that draft cleanlinks do not increment the redirect count.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function test_draft_cleanlinks_do_not_increment_access_count() {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => 'cleanlinks',
				'post_status' => 'draft',
			)
		);

		update_post_meta( $post_id, 'cleanlink_redirect_count', 2 );

		$count = $this->invoke_update_access_count( $post_id );

		$this->assertSame( 2, $count );
		$this->assertSame( '2', get_post_meta( $post_id, 'cleanlink_redirect_count', true ) );
	}

	/**
	 * Invoke the private update count method.
	 *
	 * @since 1.1.0
	 *
	 * @param int $post_id The post ID.
	 * @return int The redirect count.
	 */
	private function invoke_update_access_count( $post_id ) {
		$method = new ReflectionMethod( Actions::class, 'update_access_count' );
		$method->setAccessible( true );

		return $method->invoke( new Actions(), $post_id );
	}
}
