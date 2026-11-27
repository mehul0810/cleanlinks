<?php
/**
 * CleanLinks admin copy feedback tests.
 *
 * @package CleanLinks
 */

namespace MG\CleanLinks\Tests;

use MG\CleanLinks\Admin\Actions;
use MG\CleanLinks\Admin\Export;
use WP_UnitTestCase;

class Test_Admin_Copy_Feedback extends WP_UnitTestCase {
	/**
	 * List copy buttons expose the translated failure message to the click handler.
	 */
	public function test_copy_column_has_translated_failure_text() {
		$post_id = $this->factory->post->create( array( 'post_type' => 'cleanlinks' ) );
		$translate_failure = static function ( $translation, $text, $domain ) {
			if ( 'cleanlinks' === $domain && 'Copy failed' === $text ) {
				return 'Localized & failed';
			}
			return $translation;
		};
		add_filter( 'gettext', $translate_failure, 10, 3 );
		$buffer_level = ob_get_level();
		ob_start();
		try {
			( new Actions( new Export() ) )->register_custom_columns( 'cleanlink_permalink', $post_id );
			$output = ob_get_contents();
		} finally {
			while ( ob_get_level() > $buffer_level ) {
				ob_end_clean();
			}
			remove_filter( 'gettext', $translate_failure, 10 );
		}

		$this->assertStringContainsString( 'data-copy-failed-text="Localized &amp; failed"', $output );
	}
}
