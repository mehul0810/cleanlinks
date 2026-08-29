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
	 * CSV serialization neutralizes formula-leading values, including control whitespace prefixes.
	 *
	 * @since 1.1.1
	 * @access public
	 *
	 * @return void
	 */
	public function test_csv_serializer_neutralizes_formula_leading_values() {
		$serializer       = new ExportCsvSerializer();
		$dangerous_values = array(
			'=1+1',
			'+1+1',
			'-1+1',
			'@SUM(1+1)',
			"\t=1+1",
			"\n=1+1",
			"\v=1+1",
			"\f=1+1",
			"\r=1+1",
			" \t=1+1",
		);

		foreach ( $dangerous_values as $value ) {
			$this->assertSame(
				implode(
					"\r\n",
					array(
						'"ID","Title","Redirect From","Redirect To"',
						'"42","' . "'" . $value . '","https://example.test/from","https://example.test/to"',
					)
				),
				$serializer->serialize(
					array(
						array(
							42,
							$value,
							'https://example.test/from',
							'https://example.test/to',
						),
					)
				)
			);
		}
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
}
