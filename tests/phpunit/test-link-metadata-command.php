<?php
namespace MG\CleanLinks\Tests;

use MG\CleanLinks\Includes\LinkMetaSaver;
use MG\CleanLinks\Includes\LinkMetadataCommand;
use WP_UnitTestCase;

class Test_Link_Metadata_Command extends WP_UnitTestCase {
	public function test_explicit_save_preserves_query_and_retry_is_unchanged() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$id = self::factory()->post->create( array( 'post_type' => 'cleanlinks' ) );
		$command = new LinkMetadataCommand();
		$input = array( 'destination' => 'https://example.org/path?a=1&b=%2F&b=two+words', 'nofollow' => true );
		$result = $command->execute( $id, $input );
		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( $input['destination'], get_post_meta( $id, 'cleanlink_redirect_url', true ) );
		$this->assertSame( $result, $command->execute( $id, $input ) );
		$this->assertSame( '1', get_post_meta( $id, 'cleanlink_redirect_nofollow', true ) );
	}

	public function test_legacy_editor_uses_ordinary_metadata_updates_without_transaction_probes() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$id = self::factory()->post->create( array( 'post_type' => 'cleanlinks' ) );
		$queries = array();
		$query = static function ( $sql ) use ( &$queries ) {
			$queries[] = $sql;
			return $sql;
		};
		$original_post = $_POST;
		$_POST = array(
			'cleanlink_redirect_nonce'    => wp_create_nonce( 'cleanlink-save-redirect-meta' ),
			'cleanlink_redirect_url'      => 'https://example.org/legacy',
			'cleanlink_redirect_nofollow' => '1',
		);
		add_filter( 'query', $query );
		try {
			( new LinkMetaSaver() )->save( $id, get_post( $id ) );
		} finally {
			remove_filter( 'query', $query );
			$_POST = $original_post;
		}
		$this->assertSame( 'https://example.org/legacy', get_post_meta( $id, 'cleanlink_redirect_url', true ) );
		$this->assertSame( '1', get_post_meta( $id, 'cleanlink_redirect_nofollow', true ) );
		foreach ( $queries as $sql ) {
			$this->assertDoesNotMatchRegularExpression( '/\b(?:START\s+TRANSACTION|BEGIN|COMMIT|ROLLBACK|SAVEPOINT|RELEASE\s+SAVEPOINT|SHOW\s+TABLE\s+STATUS)\b|cleanlinks_command_mutex/i', $sql );
		}
	}

	public function test_rejections_do_not_mutate_working_metadata() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$id = self::factory()->post->create( array( 'post_type' => 'cleanlinks' ) );
		update_post_meta( $id, 'cleanlink_redirect_url', 'https://example.org/working' );
		$command = new LinkMetadataCommand();
		foreach ( array(
			array( 'destination' => array(), 'nofollow' => false ),
			array( 'destination' => new \stdClass(), 'nofollow' => false ),
			array( 'destination' => 'javascript:alert(1)', 'nofollow' => false ),
			array( 'destination' => 'http://127.0.0.1/private', 'nofollow' => false ),
			array( 'destination' => 'https://example.org/new', 'nofollow' => '0' ),
			array( 'destination' => 'https://example.org/new', 'nofollow' => false, 'post_status' => 'publish' ),
		) as $input ) {
			$result = $command->execute( $id, $input );
			$this->assertWPError( $result );
			$this->assertArrayHasKey( 'field', $result->get_error_data() );
			$this->assertSame( 'https://example.org/working', get_post_meta( $id, 'cleanlink_redirect_url', true ) );
		}
	}

	public function test_object_permissions_and_forged_ids() {
		$owner = self::factory()->user->create( array( 'role' => 'author' ) );
		$id = self::factory()->post->create( array( 'post_type' => 'cleanlinks', 'post_author' => $owner ) );
		$other = self::factory()->post->create( array( 'post_type' => 'post', 'post_author' => $owner ) );
		$command = new LinkMetadataCommand();
		$input = array( 'destination' => 'https://example.org/new', 'nofollow' => false );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertSame( 'forbidden', $command->execute( $id, $input )->get_error_code() );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->assertSame( 'forbidden', $command->execute( $id, $input )->get_error_code() );
		wp_set_current_user( $owner );
		$this->assertFalse( is_wp_error( $command->execute( $id, $input ) ) );
		$this->assertSame( 'invalid_id', $command->execute( $other, $input )->get_error_code() );
		$this->assertSame( 'invalid_id', $command->execute( (string) $id, $input )->get_error_code() );
	}
	public function test_direct_metadata_save_reports_partial_failure_without_rollback() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$id = self::factory()->post->create( array( 'post_type' => 'cleanlinks' ) );
		update_post_meta( $id, 'cleanlink_redirect_url', 'https://example.org/working' );
		update_post_meta( $id, 'cleanlink_redirect_nofollow', '1' );
		$fail = static function ( $value, $id, $key ) { return 'cleanlink_redirect_nofollow' === $key ? true : $value; };
		add_filter( 'update_post_metadata', $fail, 10, 3 );
		$result = ( new LinkMetadataCommand() )->execute( $id, array( 'destination' => 'https://example.org/rejected', 'nofollow' => false ) );
		remove_filter( 'update_post_metadata', $fail, 10 );
		$this->assertWPError( $result );
		$this->assertSame( 'persistence_failed', $result->get_error_code() );
		$this->assertSame( 'cleanlink_redirect_nofollow', $result->get_error_data()['field'] );
		$this->assertSame( 'https://example.org/rejected', get_post_meta( $id, 'cleanlink_redirect_url', true ) );
		$this->assertSame( '1', get_post_meta( $id, 'cleanlink_redirect_nofollow', true ) );
	}

	public function test_recursive_metadata_hook_is_rejected_without_losing_outer_write() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$id = self::factory()->post->create( array( 'post_type' => 'cleanlinks' ) );
		update_post_meta( $id, 'cleanlink_redirect_url', 'https://example.org/working' );
		update_post_meta( $id, 'cleanlink_redirect_nofollow', '1' );
		$nested = null;
		$attempted = false;
		$hook = static function ( $check, $post_id, $key ) use ( $id, &$nested, &$attempted ) {
			if ( 'cleanlink_redirect_url' === $key && ! $attempted ) {
				$attempted = true;
				$nested = ( new LinkMetadataCommand() )->execute( $id, array( 'destination' => 'https://example.org/nested', 'nofollow' => false ) );
			}
			return 'cleanlink_redirect_nofollow' === $key ? true : $check;
		};
		add_filter( 'update_post_metadata', $hook, 10, 3 );
		$result = ( new LinkMetadataCommand() )->execute( $id, array( 'destination' => 'https://example.org/rejected', 'nofollow' => false ) );
		remove_filter( 'update_post_metadata', $hook, 10 );
		$this->assertWPError( $result );
		$this->assertSame( 'command_busy', $nested->get_error_code() );
		$this->assertSame( 'persistence_failed', $result->get_error_code() );
		$this->assertSame( 'https://example.org/rejected', get_post_meta( $id, 'cleanlink_redirect_url', true ) );
		$this->assertSame( '1', get_post_meta( $id, 'cleanlink_redirect_nofollow', true ) );
	}

}
