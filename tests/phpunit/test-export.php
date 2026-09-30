<?php
/**
 * CleanLinks | Export Tests.
 *
 * @package WordPress
 * @subpackage CleanLinks
 * @since 1.1.1
 */

namespace MG\CleanLinks\Tests;

use MG\CleanLinks\Admin\ExportCsvSerializer;
use MG\CleanLinks\Admin\ExportQuery;
use WP_UnitTestCase;

/**
 * Tests for export composition seams.
 */
class Test_Export extends WP_UnitTestCase {
	/**
	 * Ordinary term queries and the dedicated CSV query retain CleanLinks data.
	 *
	 * @return void
	 */
	public function test_general_wxr_filter_preserves_ordinary_terms_and_csv_rows() {
		register_taxonomy( 'proof_export_groups', 'post' );
		$group = wp_insert_term( 'Private client group', 'cleanlinks_groups' );
		$other = wp_insert_term( 'Public proof group', 'proof_export_groups' );
		$this->assertNotWPError( $group );
		$this->assertNotWPError( $other );

		$link_id = $this->factory->post->create(
			array(
				'post_type'   => 'cleanlinks',
				'post_status' => 'publish',
			)
		);
		$taxonomies = array( 'cleanlinks_groups', 'proof_export_groups' );
		$terms      = get_terms( array( 'taxonomy' => $taxonomies, 'hide_empty' => false ) );
		$this->assertCount( 2, $terms );
		$this->assertContains( $link_id, wp_list_pluck( ( new ExportQuery() )->get_rows(), 0 ) );
	}

	/**
	 * The generated WXR omits CleanLinks data but retains unrelated taxonomy terms.
	 *
	 * @return void
	 */
	public function test_wordpress_all_content_wxr_omits_cleanlinks_groups() {
		require_once ABSPATH . 'wp-admin/includes/export.php';

		register_taxonomy( 'proof_export_groups', 'post' );
		wp_insert_term( 'Private client group', 'cleanlinks_groups' );
		wp_insert_term( 'Public proof group', 'proof_export_groups' );
		$this->factory->post->create(
			array(
				'post_type'   => 'cleanlinks',
				'post_status' => 'publish',
				'post_title'  => 'Private link title',
			)
		);

		$this->assertFalse( is_admin() );
		$intervening_query = static function ( $filename ) {
			get_terms( array( 'taxonomy' => 'cleanlinks_groups', 'hide_empty' => false ) );
			return $filename;
		};
		add_filter( 'export_wp_filename', $intervening_query );
		ob_start();
		try {
			// The WordPress test bootstrap prints before export_wp() sends headers.
			@export_wp( array( 'content' => 'all' ) );
			$wxr = ob_get_contents();
		} finally {
			ob_end_clean();
			remove_filter( 'export_wp_filename', $intervening_query );
		}

		$this->assertStringNotContainsString( 'Private client group', $wxr );
		$this->assertStringNotContainsString( 'Private link title', $wxr );
		$this->assertStringContainsString( 'Public proof group', $wxr );
		$this->assertCount( 1, get_terms( array( 'taxonomy' => 'cleanlinks_groups', 'hide_empty' => false ) ) );
	}

	/**
	 * CSV serialization preserves the public export schema and escaping.
	 *
	 * @since 1.1.1
	 * @access public
	 *
	 * @return void
	 */
	public function test_csv_serializer_preserves_schema_and_escapes_values() {
		$serializer = new ExportCsvSerializer();

		$this->assertSame(
			implode(
				"\r\n",
				array(
					'"ID","Title","Redirect From","Redirect To"',
					'"42","Title ""with quotes""","https://example.test/from","https://example.test/to"',
				)
			),
			$serializer->serialize(
				array(
					array(
						42,
						'Title "with quotes"',
						'https://example.test/from',
						'https://example.test/to',
					),
				)
			)
		);
	}

	/**
	 * Export query returns only published CleanLinks rows.
	 *
	 * @since 1.1.1
	 * @access public
	 *
	 * @return void
	 */
	public function test_export_query_returns_published_rows() {
		$published_id = $this->factory->post->create(
			array(
				'post_type'   => 'cleanlinks',
				'post_status' => 'publish',
				'post_title'  => 'Published export link',
			)
		);
		$draft_id     = $this->factory->post->create(
			array(
				'post_type'   => 'cleanlinks',
				'post_status' => 'draft',
			)
		);

		update_post_meta( $published_id, 'cleanlink_redirect_url', 'https://example.test/destination' );

		$rows = ( new ExportQuery() )->get_rows();

		$this->assertContains(
			array(
				$published_id,
				'Published export link',
				get_permalink( $published_id ),
				'https://example.test/destination',
			),
			$rows
		);
		$this->assertNotContains( $draft_id, wp_list_pluck( $rows, 0 ) );
	}

	/**
	 * Export query returns all published rows and avoids per-row database lookups.
	 *
	 * @since 1.1.1
	 * @access public
	 *
	 * @return void
	 */
	public function test_export_query_paginates_large_exports_without_n_plus_one_queries() {
		global $wpdb;

		$post_ids = array();
		for ( $index = 0; $index < 205; $index++ ) {
			$post_ids[] = (int) $this->factory->post->create(
				array(
					'post_type'   => 'cleanlinks',
					'post_status' => 'publish',
					'post_title'  => 'Large export row ' . $index,
				)
			);
		}

		$queries_before = $wpdb->num_queries;
		$rows           = ( new ExportQuery() )->get_rows();
		$query_count    = $wpdb->num_queries - $queries_before;

		$this->assertCount( 205, $rows );
		$exported_ids = wp_list_pluck( $rows, 0 );
		sort( $post_ids );
		sort( $exported_ids );
		$this->assertSame( $post_ids, $exported_ids );
		$this->assertLessThan( 20, $query_count );
	}

	/**
	 * Export iterator preserves exact IDs for a ten-thousand-row dataset.
	 *
	 * @since 1.1.1
	 * @access public
	 *
	 * @return void
	 */
	public function test_export_query_iterates_ten_thousand_rows_with_bounded_queries() {
		global $wpdb;

		$post_ids = array();
		for ( $index = 0; $index < 10000; $index++ ) {
			$post_ids[] = (int) $this->factory->post->create(
				array(
					'post_type'   => 'cleanlinks',
					'post_status' => 'publish',
					'post_title'  => 'Ten thousand row ' . $index,
				)
			);
		}

		foreach ( array_slice( $post_ids, 0, ExportQuery::PAGE_SIZE ) as $post_id ) {
			update_post_meta( $post_id, 'cleanlink_redirect_url', 'https://example.test/' . $post_id );
		}

		$queries_before = $wpdb->num_queries;
		sort( $post_ids );
		$iterator = ( new ExportQuery() )->iterate_rows();
		$index    = 0;
		foreach ( $iterator as $row ) {
			$this->assertSame( $post_ids[ $index ], $row[0] );
			$index++;

			if ( ExportQuery::PAGE_SIZE + 1 === $index ) {
				$this->assertFalse( wp_cache_get( $post_ids[0], 'posts' ) );
				$this->assertFalse( wp_cache_get( $post_ids[0], 'post_meta' ) );
			}
		}

		$this->assertSame( 10000, $index );
		$this->assertLessThan( 250, $wpdb->num_queries - $queries_before );
	}

	/**
	 * Early iterator destruction clears the active page cache.
	 *
	 * @since 1.1.1
	 * @access public
	 *
	 * @return void
	 */
	public function test_export_query_clears_cache_when_iterator_is_destroyed_early() {
		$post_id = (int) $this->factory->post->create(
			array(
				'post_type'   => 'cleanlinks',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $post_id, 'cleanlink_redirect_url', 'https://example.test/' . $post_id );

		$iterator = ( new ExportQuery() )->iterate_rows();
		$iterator->rewind();
		$this->assertSame( $post_id, $iterator->current()[0] );
		$this->assertNotFalse( wp_cache_get( $post_id, 'post_meta' ) );

		unset( $iterator );

		$this->assertFalse( wp_cache_get( $post_id, 'post_meta' ) );
	}
}
