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
		return iterator_to_array( $this->iterate_rows() );
	}

	/**
	 * Lazily yield published CleanLinks export rows.
	 *
	 * @since 1.1.1
	 * @access public
	 *
	 * @return \Generator
	 */
	public function iterate_rows() {
		$max_id  = $this->get_max_id();
		$last_id = 0;

		while ( $last_id < $max_id ) {
			$ids = $this->get_ids_after( $last_id, $max_id );
			if ( empty( $ids ) ) {
				break;
			}

			$posts = new \WP_Query(
				array(
					'post_type'              => 'cleanlinks',
					'post_status'            => 'publish',
					'post__in'               => $ids,
					'posts_per_page'         => count( $ids ),
					'orderby'                => 'post__in',
					'fields'                 => 'all',
					'no_found_rows'          => true,
					'cache_results'         => false,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);

			$posts_by_id = array();
			try {
				foreach ( $posts->posts as $post ) {
					$posts_by_id[ (int) $post->ID ] = $post;
				}

				update_meta_cache( 'post', $ids );
				foreach ( $ids as $post_id ) {
					$post_id = (int) $post_id;
					if ( ! isset( $posts_by_id[ $post_id ] ) ) {
						continue;
					}

					$post = $posts_by_id[ $post_id ];
					yield array(
						$post_id,
						$post->post_title,
						get_permalink( $post ),
						get_post_meta( $post_id, 'cleanlink_redirect_url', true ),
					);
				}
			} finally {
				$this->clear_batch_cache( $ids );
				unset( $posts_by_id, $posts );
			}

			$last_id = (int) end( $ids );
		}
	}

	/**
	 * Get the upper ID bound for a consistent export snapshot.
	 *
	 * @return int
	 */
	private function get_max_id() {
		$query = new \WP_Query(
			array(
				'post_type'              => 'cleanlinks',
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'orderby'                => 'ID',
				'order'                  => 'DESC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return empty( $query->posts ) ? 0 : (int) $query->posts[0];
	}

	/**
	 * Get the next bounded ID batch using a keyset cursor.
	 *
	 * @param int $last_id Last yielded post ID.
	 * @param int $max_id  Upper post ID snapshot bound.
	 * @return array
	 */
	private function get_ids_after( $last_id, $max_id ) {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND ID > %d AND ID <= %d ORDER BY ID ASC LIMIT %d",
			'cleanlinks',
			'publish',
			$last_id,
			$max_id,
			self::PAGE_SIZE
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- Keyset pagination requires a bounded prepared ID query not available in WP_Query.
		return array_map( 'intval', $wpdb->get_col( $sql ) );
	}

	/**
	 * Clear cache entries populated while processing one export page.
	 *
	 * @param array $ids Post IDs in the completed page.
	 * @return void
	 */
	private function clear_batch_cache( array $ids ) {
		foreach ( $ids as $post_id ) {
			wp_cache_delete( (int) $post_id, 'posts' );
			wp_cache_delete( (int) $post_id, 'post_meta' );
		}
	}
}
