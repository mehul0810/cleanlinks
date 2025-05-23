<?php
/**
 * CleanLinks | Helpers Tests.
 *
 * @package WordPress
 * @subpackage CleanLinks
 * @since 1.0.0
 */

namespace MG\CleanLinks\Tests;

use MG\CleanLinks\Includes\Helpers;
use WP_UnitTestCase;

class Test_Helpers extends WP_UnitTestCase {

	/**
	 * Test for get_total_access_count method with caching.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function test_get_total_access_count_caching() {
		// Create a test post
		$post_id = $this->factory->post->create(['post_type' => 'cleanlinks']);

		// Set initial count
		update_post_meta($post_id, 'cleanlink_redirect_count', 5);

		// First call should read from database
		$count = Helpers::get_total_access_count($post_id);
		$this->assertEquals(5, $count);

		// Update the count in database, but cache should still be used
		update_post_meta($post_id, 'cleanlink_redirect_count', 10);

		// Second call should read from cache
		$cached_count = Helpers::get_total_access_count($post_id);
		$this->assertEquals(5, $cached_count);

		// Clear the cache
		wp_cache_flush();

		// Third call should read from database again
		$refreshed_count = Helpers::get_total_access_count($post_id);
		$this->assertEquals(10, $refreshed_count);
	}

	/**
	 * Test for clean method.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function test_clean() {
		// Test with string input
		$dirty_string = '<script>alert("XSS")</script>Sample text';
		$clean_string = Helpers::clean($dirty_string);
		$this->assertEquals('alert("XSS")Sample text', $clean_string);

		// Test with array input
		$dirty_array = [
			'key1' => '<script>alert("XSS")</script>Sample text',
			'key2' => 'Clean text'
		];
		$clean_array = Helpers::clean($dirty_array);
		$this->assertEquals([
			'key1' => 'alert("XSS")Sample text',
			'key2' => 'Clean text'
		], $clean_array);
	}
}
