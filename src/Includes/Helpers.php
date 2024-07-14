<?php
/**
 * Simplified Links | Helpers
 *
 * @package WordPress
 * @subpackage Simplified Links
 * @since 1.0.0
 */

namespace SimplifiedWP\Links\Includes;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Helpers {
	/**
	 * Helps cleaning the input data. Prevents XSS.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param string|array $input Any type of input data.
	 *
	 * @return void
	 */
	public static function clean( $input ) {
		if ( is_array( $input ) ) {
			return array_map( [ 'self', 'clean' ], $input );
		} else {
			return is_scalar( $input ) ? sanitize_text_field( $input ) : $input;
		}
	}

	/**
	 * Get total access count based on the post id.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public static function get_total_access_count( $post_id ) {
		$access_count = get_post_meta( $post_id, 'simplifiedwp_links_redirect_count', true );

		return $access_count ?: 0;
	}

	/**
	 * Check whether slug exists or not
	 *
	 * @param string $post_name Post name.
	 * @param int    $post_id   Post id. Default to 0.
	 */
	public static function the_slug_exists( $post_name, $post_id = 0 ) {
		global $wpdb;
		if ( empty( $post_name ) ) {
			return false;
		}

		$posts_tbl = $wpdb->posts;
		$sql       = '
			SELECT
				ID,
				post_name,
				post_type
			FROM '
				. $posts_tbl . '
			WHERE
				post_name = %s
				AND ID != %d
				AND post_status <> "trash"
				AND post_type = %s
			LIMIT 1
		';

		$sql_prepare = $wpdb->prepare( $sql, $post_name, $post_id, 'simplifiedwp_links' );

		$row     = $wpdb->get_row( $sql_prepare, 'ARRAY_A' ); // phpcs:ignore

		return $row ? $row : false;
	}

	/**
	 * Get unique post name of Simplifiedwp_links post
	 *
	 * @param int    $post_id   Post id.
	 * @param string $post_name Post name.
	 */
	public static function simplified_unique_post_name( $post_id, $post_name ) {
		if ( intval( $post_id ) > 0 && ! empty( $post_name ) && self::the_slug_exists( $post_name, $post_id ) ) {
			$post_name = rtrim( $post_name, '-link' ); // ? Fix the issue adding multiple "-link" string to the end.
			$post_name = wp_unique_post_slug( $post_name, $post_id, 'publish', 'simplifiedwp_links', 0 );
		}

		return $post_name;
	}

	/**
	 * Get list of plugins that supports migration to Simplified Links.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return array
	 */
	public static function get_migration_supported_plugins() {
		return [
			[
				'name'      => 'Lasso Lite',
				'slug'      => 'simple-urls',
				'path'      => 'simple-urls/plugin.php',
				'post_type' => 'surl',
				'meta_key'  => '_surl_redirect',
			],
			[
				'name'      => 'Pretty Links',
				'slug'      => 'pretty-link',
				'path'      => 'pretty-link/pretty-link.php',
				'post_type' => 'pretty-link',
				'meta_key'  => '',
			],
			[
				'name'      => 'Affiliate Links',
				'slug'      => 'affiliate-links',
				'path'      => 'affiliate-links/affiliate-links.php',
				'post_type' => 'affiliate-links',
				'meta_key'  => '',
			],
		];
	}
}
