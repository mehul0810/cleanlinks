<?php
/**
 * CleanLinks | Collaborator Tests.
 *
 * @package WordPress
 * @subpackage CleanLinks
 * @since 1.1.1
 */

namespace MG\CleanLinks\Tests;

use MG\CleanLinks\Includes\AccessCounter;
use MG\CleanLinks\Includes\Actions;
use MG\CleanLinks\Includes\InputSanitizer;
use MG\CleanLinks\Includes\Redirector;
use MG\CleanLinks\Includes\UrlValidator;
use WP_UnitTestCase;

class Test_Collaborators extends WP_UnitTestCase {
	/**
	 * The count collaborator invalidates the read cache after an increment.
	 *
	 * @since 1.1.1
	 *
	 * @return void
	 */
	public function test_access_counter_invalidates_cache_after_increment() {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => 'cleanlinks',
				'post_status' => 'publish',
			)
		);

		update_post_meta( $post_id, 'cleanlink_redirect_count', 2 );

		$counter = new AccessCounter();
		$this->assertSame( 2, (int) $counter->get_total_access_count( $post_id ) );
		$this->assertSame( 3, $counter->increment( $post_id ) );
		$this->assertSame( 3, (int) $counter->get_total_access_count( $post_id ) );
	}

	/**
	 * The count collaborator initializes links that have no count metadata.
	 *
	 * @since 1.1.1
	 *
	 * @return void
	 */
	public function test_access_counter_initializes_missing_count_metadata() {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => 'cleanlinks',
				'post_status' => 'publish',
			)
		);

		$counter = new AccessCounter();

		$this->assertSame( 1, $counter->increment( $post_id ) );
		$this->assertSame( '1', get_post_meta( $post_id, 'cleanlink_redirect_count', true ) );
	}

	/**
	 * An interleaved writer is not overwritten by a stale count read.
	 *
	 * The query filter places a second persisted value immediately before the
	 * counter's UPDATE executes. This models the race that a read-modify-write
	 * implementation loses while asserting the database-side increment uses the
	 * latest value.
	 *
	 * @since 1.1.1
	 *
	 * @return void
	 */
	public function test_access_counter_preserves_interleaved_count_update() {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => 'cleanlinks',
				'post_status' => 'publish',
			)
		);

		update_post_meta( $post_id, 'cleanlink_redirect_count', 2 );

		$interleaved = false;
		$simulate_concurrent_update = static function ( $query ) use ( &$interleaved, $post_id ) {
			if ( ! $interleaved && false !== stripos( $query, 'CAST(meta_value AS UNSIGNED)' ) ) {
				$interleaved = true;
				update_post_meta( $post_id, 'cleanlink_redirect_count', 5 );
			}

			return $query;
		};

		add_filter( 'query', $simulate_concurrent_update );

		try {
			$count = ( new AccessCounter() )->increment( $post_id );
		} finally {
			remove_filter( 'query', $simulate_concurrent_update );
		}

		$this->assertTrue( $interleaved );
		$this->assertSame( 6, $count );
		$this->assertSame( '6', get_post_meta( $post_id, 'cleanlink_redirect_count', true ) );
	}

	/**
	 * The count collaborator does not change unpublished cleanlinks.
	 *
	 * @since 1.1.1
	 *
	 * @return void
	 */
	public function test_access_counter_does_not_increment_unpublished_cleanlinks() {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => 'cleanlinks',
				'post_status' => 'draft',
			)
		);

		update_post_meta( $post_id, 'cleanlink_redirect_count', 2 );

		$counter = new AccessCounter();
		$this->assertSame( 2, $counter->increment( $post_id ) );
		$this->assertSame( '2', get_post_meta( $post_id, 'cleanlink_redirect_count', true ) );
	}

	/**
	 * Public redirect hooks receive the persisted count when a write is rejected.
	 *
	 * @since 1.1.1
	 *
	 * @return void
	 */
	public function test_redirect_hooks_receive_persisted_count_when_increment_fails() {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => 'cleanlinks',
				'post_status' => 'publish',
			)
		);

		update_post_meta( $post_id, 'cleanlink_redirect_count', 2 );
		update_post_meta( $post_id, 'cleanlink_redirect_url', 'https://example.com/destination' );

		$filtered_count = null;
		$action_count   = null;
		$filter         = static function ( $redirect, $count ) use ( &$filtered_count ) {
			$filtered_count = $count;

			return $redirect;
		};
		$action         = static function ( $redirect, $count ) use ( &$action_count ) {
			$action_count = $count;
		};
		$reject_update  = static function ( $check, $object_id, $meta_key ) use ( $post_id ) {
			if ( $post_id === (int) $object_id && 'cleanlink_redirect_count' === $meta_key ) {
				return false;
			}

			return $check;
		};
		$stop_redirect = static function () {
			throw new \RuntimeException( 'Stop redirect for test.' );
		};

		add_filter( 'cleanlinks_urls_redirect_url', $filter, 10, 2 );
		add_action( 'cleanlinks_urls_redirect', $action, 10, 2 );
		add_filter( 'update_post_metadata', $reject_update, 10, 3 );
		add_filter( 'wp_redirect', $stop_redirect );

		$this->go_to( get_permalink( $post_id ) );

		try {
			( new Actions() )->cleanlink_redirect_and_count();
		} catch ( \RuntimeException $exception ) {
			$this->assertSame( 'Stop redirect for test.', $exception->getMessage() );
		} finally {
			remove_filter( 'cleanlinks_urls_redirect_url', $filter, 10 );
			remove_action( 'cleanlinks_urls_redirect', $action, 10 );
			remove_filter( 'update_post_metadata', $reject_update, 10 );
			remove_filter( 'wp_redirect', $stop_redirect, 10 );
		}

		$this->assertSame( 2, $filtered_count );
		$this->assertSame( 2, $action_count );
		$this->assertSame( '2', get_post_meta( $post_id, 'cleanlink_redirect_count', true ) );
	}

	/**
	 * The redirect collaborator preserves the public filter and action count.
	 *
	 * @since 1.1.1
	 *
	 * @return void
	 */
	public function test_redirector_dispatches_public_extension_points_with_count() {
		$post_id = $this->factory->post->create( array( 'post_type' => 'cleanlinks' ) );
		update_post_meta( $post_id, 'cleanlink_redirect_url', 'https://example.com/destination' );

		$filtered_count = null;
		$action_count   = null;
		$filter         = static function ( $redirect, $count ) use ( &$filtered_count ) {
			$filtered_count = $count;

			return $redirect;
		};
		$action         = static function ( $redirect, $count ) use ( &$action_count ) {
			$action_count = $count;
		};

		add_filter( 'cleanlinks_urls_redirect_url', $filter, 10, 2 );
		add_action( 'cleanlinks_urls_redirect', $action, 10, 2 );

		try {
			$redirect = ( new Redirector() )->get_redirect_url( $post_id, 7 );
		} finally {
			remove_filter( 'cleanlinks_urls_redirect_url', $filter, 10 );
			remove_action( 'cleanlinks_urls_redirect', $action, 10 );
		}

		$this->assertSame( 'https://example.com/destination', $redirect );
		$this->assertSame( 7, $filtered_count );
		$this->assertSame( 7, $action_count );
	}

	/**
	 * The input collaborator sanitizes nested scalar input.
	 *
	 * @since 1.1.1
	 *
	 * @return void
	 */
	public function test_input_sanitizer_handles_nested_input() {
		$input = array(
			'name'  => '<script>alert("XSS")</script>Sample text',
			'flags' => array( 'nofollow' => '1' ),
		);

		$this->assertSame(
			array(
				'name'  => 'Sample text',
				'flags' => array( 'nofollow' => '1' ),
			),
			InputSanitizer::sanitize( $input )
		);
	}

	/**
	 * The URL collaborator keeps valid URLs and rejects invalid values.
	 *
	 * @since 1.1.1
	 *
	 * @return void
	 */
	public function test_url_validator_accepts_valid_urls_and_rejects_invalid_values() {
		$this->assertSame( 'https://example.com/destination', UrlValidator::validate( ' https://example.com/destination ' ) );
		$this->assertFalse( UrlValidator::validate( 'not a URL' ) );
	}
}
