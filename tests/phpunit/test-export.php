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
}
