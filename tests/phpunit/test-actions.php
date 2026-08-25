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
use MG\CleanLinks\Includes\Helpers;
use ReflectionMethod;
use WP_UnitTestCase;

class Test_Actions extends WP_UnitTestCase {
	/**
	 * Test that the default redirect keeps its status and destination without output.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function test_default_redirect_sends_301_without_output() {
		$post_id     = $this->factory->post->create( array( 'post_type' => 'cleanlinks' ) );
		$destination = 'https://example.com/default-destination';

		update_post_meta( $post_id, 'cleanlink_redirect_nofollow', '0' );

		$response = $this->capture_redirect_response( $destination, $post_id );

		$this->assertSame( $destination, $response['location'] );
		$this->assertSame( 301, $response['status'] );
		$this->assertSame( '', $response['output'] );
	}

	/**
	 * Test that nofollow redirects send the same response without premature output.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function test_nofollow_redirect_sends_301_without_output() {
		$post_id     = $this->factory->post->create( array( 'post_type' => 'cleanlinks' ) );
		$destination = 'https://example.com/nofollow-destination';

		update_post_meta( $post_id, 'cleanlink_redirect_nofollow', '1' );

		$response = $this->capture_redirect_response( $destination, $post_id );

		$this->assertSame( $destination, $response['location'] );
		$this->assertSame( 301, $response['status'] );
		$this->assertSame( '', $response['output'] );
	}

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
	 * Test that published redirects invalidate the cached access count.
	 *
	 * @since 1.1.1
	 *
	 * @return void
	 */
	public function test_published_cleanlinks_invalidate_cached_access_count_after_redirect() {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => 'cleanlinks',
				'post_status' => 'publish',
			)
		);

		update_post_meta( $post_id, 'cleanlink_redirect_count', 2 );
		update_post_meta( $post_id, 'cleanlink_redirect_url', 'https://example.com/destination' );
		update_post_meta( $post_id, 'cleanlink_redirect_nofollow', '0' );
		$this->assertSame( 2, (int) Helpers::get_total_access_count( $post_id ) );

		$this->go_to( get_permalink( $post_id ) );
		$location = null;
		$status   = null;
		$filter   = static function ( $redirect_location, $redirect_status ) use ( &$location, &$status ) {
			$location = $redirect_location;
			$status   = $redirect_status;

			throw new \RuntimeException( 'Stop redirect for test.' );
		};

		add_filter( 'wp_redirect', $filter, 10, 2 );

		try {
			( new Actions() )->cleanlink_redirect_and_count();
			$this->fail( 'The redirect path should invoke wp_redirect.' );
		} catch ( \RuntimeException $exception ) {
			$this->assertSame( 'https://example.com/destination', $location );
			$this->assertSame( 301, $status );
		} finally {
			remove_filter( 'wp_redirect', $filter, 10 );
		}

		$this->assertSame( 3, (int) Helpers::get_total_access_count( $post_id ) );
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

	/**
	 * Capture a redirect response without sending headers from the test process.
	 *
	 * @since 1.1.0
	 *
	 * @param string $destination The redirect destination.
	 * @param int    $post_id     The cleanlink post ID.
	 * @return array The captured redirect response.
	 */
	private function capture_redirect_response( $destination, $post_id ) {
		$location = null;
		$status   = null;
		$filter   = static function ( $redirect_location, $redirect_status ) use ( &$location, &$status ) {
			$location = $redirect_location;
			$status   = $redirect_status;

			return false;
		};

		add_filter( 'wp_redirect', $filter, 10, 2 );
		ob_start();

		try {
			$method = new ReflectionMethod( Actions::class, 'perform_redirect' );
			$method->setAccessible( true );
			$method->invoke( new Actions(), $destination, $post_id );
			$output = ob_get_clean();
		} finally {
			remove_filter( 'wp_redirect', $filter, 10 );

			if ( false === isset( $output ) && ob_get_level() ) {
				ob_end_clean();
			}
		}

		return array(
			'location' => $location,
			'status'   => $status,
			'output'   => $output,
		);
	}
}
