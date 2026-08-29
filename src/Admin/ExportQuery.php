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
	 * Number of rows loaded per export page.
	 *
	 * @var int
	 */
	const PAGE_SIZE = 200;

	/**
	 * Get published CleanLinks export rows.
	 *
	 * @since 1.1.1
	 * @access public
	 *
	 * @return array
	 */
	public function get_rows() {
		$rows = array();
		$page = 1;

		// Read bounded pages so large exports do not silently stop at the first page.
		do {
			$query = new \WP_Query(
				array(
					'post_type'              => 'cleanlinks',
					'post_status'            => 'publish',
					'posts_per_page'         => self::PAGE_SIZE,
					'paged'                  => $page,
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'fields'                 => 'all',
					'no_found_rows'          => true,
					'update_post_meta_cache' => true,
					'update_post_term_cache' => false,
				)
			);

			foreach ( $query->posts as $post ) {
				$post_id = (int) $post->ID;

				$rows[] = array(
					$post_id,
					$post->post_title,
					get_permalink( $post_id ),
					get_post_meta( $post_id, 'cleanlink_redirect_url', true ),
				);
			}

			$page++;
		} while ( count( $query->posts ) === self::PAGE_SIZE );

		return $rows;
	}
}
