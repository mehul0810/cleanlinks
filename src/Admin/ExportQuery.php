<?php
/**
 * CleanLinks | Export Query.
 *
 * @package WordPress
 * @subpackage CleanLinks
 * @since 1.1.1
 */

namespace MG\CleanLinks\Admin;

/**
 * Bailout, if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the published CleanLinks rows used by the export.
 */
class ExportQuery {
	/**
	 * Get published CleanLinks export rows.
	 *
	 * @since 1.1.1
	 * @access public
	 *
	 * @return array
	 */
	public function get_rows() {
		$query = new \WP_Query(
			array(
				'post_type'              => 'cleanlinks',
				'post_status'            => 'publish',
				'posts_per_page'         => 200,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$rows = array();

		foreach ( $query->posts as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			$rows[] = array(
				$post_id,
				$post->post_title,
				get_permalink( $post_id ),
				get_post_meta( $post_id, 'cleanlink_redirect_url', true ),
			);
		}

		return $rows;
	}
}
