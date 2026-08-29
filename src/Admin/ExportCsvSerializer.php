<?php
/**
 * CleanLinks | Export CSV Serializer.
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
 * Serializes CleanLinks export rows into CSV.
 */
class ExportCsvSerializer {
	/**
	 * Serialize export rows.
	 *
	 * @since 1.1.1
	 * @access public
	 *
	 * @param array $rows Rows containing ID, title, permalink, and redirect URL.
	 * @return string
	 */
	public function serialize( array $rows ) {
		$lines   = array( '"ID","Title","Redirect From","Redirect To"' );

		foreach ( $rows as $row ) {
			$escaped = array_map( array( $this, 'escape_value' ), $row );
			$lines[] = implode( ',', $escaped );
		}

		return implode( "\r\n", $lines );
	}

	/**
	 * Escape a CSV value.
	 *
	 * @since 1.1.1
	 * @access private
	 *
	 * @param mixed $value Value to escape.
	 * @return string
	 */
	private function escape_value( $value ) {
		$value = (string) $value;

		// Prevent spreadsheet applications from evaluating formula-like values,
		// including values preceded by control whitespace.
		if ( preg_match( '/^[\x09-\x0D\x20]*[=+\-@]/', $value ) ) {
			$value = "'" . $value;
		}

		return '"' . str_replace( '"', '""', $value ) . '"';
	}
}
