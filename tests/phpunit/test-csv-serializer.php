<?php
/**
 * CleanLinks | CSV Serializer Tests.
 *
 * @package WordPress
 * @subpackage CleanLinks
 * @since 1.1.1
 */

namespace MG\CleanLinks\Tests;

use MG\CleanLinks\Admin\ExportCsvSerializer;
use WP_UnitTestCase;

/**
 * Tests for CSV formula neutralization.
 */
class Test_Csv_Serializer extends WP_UnitTestCase {
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
}
