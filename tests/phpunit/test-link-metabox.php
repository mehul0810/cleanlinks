<?php
/**
 * CleanLinks link editor summary tests.
 *
 * @package CleanLinks
 */

namespace MG\CleanLinks\Tests;

use MG\CleanLinks\Includes\LinkMetaBox;
use WP_UnitTestCase;

class Test_LinkMetaBox extends WP_UnitTestCase {
	/**
	 * Published links show the saved destination and a copyable short URL.
	 */
	public function test_published_link_overview_uses_saved_values() {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'cleanlinks',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $post_id, 'cleanlink_redirect_url', 'https://example.com/destination' );
		update_post_meta( $post_id, 'cleanlink_redirect_count', 7 );

		ob_start();
		( new LinkMetaBox() )->render( get_post( $post_id ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Saved link overview', $html );
		$this->assertStringContainsString( 'data-url="' . esc_url( get_permalink( $post_id ) ) . '"', $html );
		$this->assertStringContainsString( 'https://example.com/destination', $html );
		$this->assertStringContainsString( '301 permanent', $html );
		$this->assertStringContainsString( '<dd>7</dd>', $html );
	}

	/**
	 * Unpublishing a link must not erase its historical count in the editor.
	 */
	public function test_draft_overview_retains_historical_clicks_without_copy_action() {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'cleanlinks',
				'post_status' => 'draft',
			)
		);
		update_post_meta( $post_id, 'cleanlink_redirect_count', 9 );

		ob_start();
		( new LinkMetaBox() )->render( get_post( $post_id ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'The short URL is available after publishing.', $html );
		$this->assertStringNotContainsString( 'data-url=', $html );
		$this->assertStringContainsString( '<dd>9</dd>', $html );
	}

	/**
	 * The unsaved editor state should use a readable status label.
	 */
	public function test_auto_draft_overview_has_draft_label() {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'cleanlinks',
				'post_status' => 'auto-draft',
			)
		);

		ob_start();
		( new LinkMetaBox() )->render( get_post( $post_id ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( '<dd>Draft</dd>', $html );
		$this->assertStringNotContainsString( '<dd>auto-draft</dd>', $html );
	}
}
