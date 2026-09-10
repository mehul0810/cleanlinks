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
	 * CSV header for CleanLinks exports.
	 *
	 * @since 1.1.1
	 * @access public
	 *
	 * @return string
	 */
	public function serialize_header() {
		return '"ID","Title","Redirect From","Redirect To"';
	}

	/**
	 * Serialize one export row without a trailing newline.
	 *
	 * @since 1.1.1
	 * @access public
	 *
	 * @param array $row Row containing ID, title, permalink, and redirect URL.
	 * @return string
	 */
	public function serialize_row( array $row ) {
		$escaped = array_map( array( $this, 'escape_value' ), $row );

		return implode( ',', $escaped );
	}

	/**
	 * Serialize a bounded row chunk.
	 *
	 * @since 1.1.1
	 * @access public
	 *
	 * @param array $rows          Rows containing export fields.
	 * @param bool  $include_header Whether to include the CSV header.
	 * @return string
	 */
	public function serialize_chunk( array $rows, $include_header = false ) {
		$lines = array();
		if ( $include_header ) {
			$lines[] = $this->serialize_header();
		}

		foreach ( $rows as $row ) {
			$lines[] = $this->serialize_row( $row );
		}

		return implode( "\r\n", $lines );
	}

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
		return $this->serialize_chunk( $rows, true );
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
