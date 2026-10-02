<?php
namespace MG\CleanLinks\Tests;

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
	public function test_direct_metadata_save_rolls_back_partial_failure() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$id = self::factory()->post->create( array( 'post_type' => 'cleanlinks' ) );
		update_post_meta( $id, 'cleanlink_redirect_url', 'https://example.org/working' );
		update_post_meta( $id, 'cleanlink_redirect_nofollow', '1' );
		$fail = static function ( $value, $id, $key ) { return 'cleanlink_redirect_nofollow' === $key ? true : $value; };
		add_filter( 'update_post_metadata', $fail, 10, 3 );
		$result = ( new LinkMetadataCommand() )->execute( $id, array( 'destination' => 'https://example.org/rejected', 'nofollow' => false ) );
		remove_filter( 'update_post_metadata', $fail, 10 );
		$this->assertWPError( $result );
		$this->assertSame( 'https://example.org/working', get_post_meta( $id, 'cleanlink_redirect_url', true ) );
		$this->assertSame( '1', get_post_meta( $id, 'cleanlink_redirect_nofollow', true ) );
	}

}
