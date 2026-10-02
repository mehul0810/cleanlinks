<?php
namespace MG\CleanLinks\Tests;

use MG\CleanLinks\Application\LinkCommands;
use MG\CleanLinks\Admin\LinkCommandController;
use WP_UnitTestCase;

class Test_Link_Commands extends WP_UnitTestCase {
	private $commands;
	public function set_up() {
		parent::set_up();
		$this->commands = new LinkCommands();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}
	private function input( $key = 'request_key_for_test_01', $slug = 'command-test' ) {
		return array( 'request_key' => $key, 'destination' => 'https://example.org/a?one=1&two=%2F&two=hello+world', 'title' => 'Command test', 'slug' => $slug, 'status' => 'publish', 'nofollow' => true );
	}
	public function test_create_retry_and_request_key_reuse() {
		$input = $this->input();
		$result = $this->commands->execute( $input );
		$this->assertFalse( is_wp_error( $result ), is_wp_error( $result ) ? $result->get_error_code() : '' );
		$this->assertSame( $input['destination'], $result['destination'] );
		$this->assertSame( 'publish', $result['status'] );
		$this->assertSame( $result, ( new LinkCommands() )->execute( $input ) );
		$input['title'] = 'Changed request';
		$this->assertSame( 'request_key_conflict', $this->commands->execute( $input )->get_error_code() );
		$this->assertSame( 'Command test', get_the_title( $result['id'] ) );
	}
	public function test_update_conflicts_and_identity_counts_groups_preserved() {
		$group = self::factory()->term->create( array( 'taxonomy' => 'cleanlinks_groups' ) );
		$input = $this->input();
		$input['groups'] = array( $group );
		$saved = $this->commands->execute( $input );
		$this->assertFalse( is_wp_error( $saved ) );
		update_post_meta( $saved['id'], 'cleanlink_redirect_count', '42' );
		$update = array( 'id' => $saved['id'], 'expected_version' => $saved['version'], 'request_key' => 'update_request_for_test_01', 'destination' => 'https://example.org/new?a=1&b=2' );
		$next = $this->commands->execute( $update );
		$this->assertFalse( is_wp_error( $next ) );
		$this->assertSame( $saved['id'], $next['id'] );
		$this->assertSame( $saved['url'], $next['url'] );
		$this->assertSame( array( $group ), $next['groups'] );
		$this->assertSame( '42', get_post_meta( $next['id'], 'cleanlink_redirect_count', true ) );
		$this->assertSame( $next, $this->commands->execute( $update ) );
		$update['request_key'] = 'stale_request_for_test_01';
		$this->assertSame( 'stale_version', $this->commands->execute( $update )->get_error_code() );
		$this->assertSame( $next['destination'], get_post_meta( $next['id'], 'cleanlink_redirect_url', true ) );
	}
	public function test_slug_collision_and_change_confirmation() {
		$saved = $this->commands->execute( $this->input() );
		$this->assertSame( 'slug_conflict', $this->commands->execute( $this->input( 'another_create_request_01' ) )->get_error_code() );
		$update = array( 'id' => $saved['id'], 'expected_version' => $saved['version'], 'request_key' => 'rename_request_for_test_01', 'slug' => 'renamed' );
		$this->assertSame( 'slug_confirmation_required', $this->commands->execute( $update )->get_error_code() );
		$update['confirm_slug_change'] = true;
		$this->assertSame( 'renamed', $this->commands->execute( $update )['slug'] );
	}
	public function test_role_matrix_and_group_authority() {
		$admin = get_current_user_id();
		foreach ( array( 'subscriber', 'contributor', 'author', 'editor', 'administrator' ) as $role ) {
			$user = self::factory()->user->create( array( 'role' => $role ) );
			wp_set_current_user( $user );
			$input = $this->input( 'role_request_test_' . $role, 'role-' . $role );
			$result = $this->commands->execute( $input );
			if ( 'subscriber' === $role ) {
				$this->assertSame( 'forbidden', $result->get_error_code() );
			} elseif ( 'contributor' === $role ) {
				$this->assertSame( 'forbidden_status', $result->get_error_code() );
				$input['status'] = 'draft';
				$this->assertSame( 'draft', $this->commands->execute( $input )['status'] );
			} else {
				$this->assertSame( 'publish', $result['status'] );
			}
		}
		wp_set_current_user( $admin );
		$saved = $this->commands->execute( $this->input( 'admin_request_test_02', 'admin-owned' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->assertSame( 'forbidden', $this->commands->execute( array( 'id' => $saved['id'], 'request_key' => 'other_author_update_key', 'expected_version' => $saved['version'], 'title' => 'Forged' ) )->get_error_code() );
	}
	public function test_failed_write_rolls_back_post_and_receipt() {
		$fail = static function ( $value, $id, $key ) { return 'cleanlink_redirect_nofollow' === $key ? true : $value; };
		add_filter( 'add_post_metadata', $fail, 10, 3 );
		$result = $this->commands->execute( $this->input() );
		remove_filter( 'add_post_metadata', $fail, 10 );
		$this->assertWPError( $result );
		$this->assertSame( 'persistence_failed', $result->get_error_code() );
		$this->assertEmpty( get_posts( array( 'post_type' => 'cleanlinks', 'name' => 'command-test', 'post_status' => 'any' ) ) );
		$this->assertFalse( is_wp_error( $this->commands->execute( $this->input() ) ) );
	}
	public function test_rows_partial_failure_and_retry() {
		$valid = $this->input();
		$invalid = $this->input( 'invalid_row_request_key', 'invalid-row' );
		$invalid['destination'] = 'http://127.0.0.1/private';
		$result = $this->commands->execute_rows( array( $valid, $invalid ) );
		$this->assertArrayHasKey( 'saved', $result[0] );
		$this->assertSame( 'unsafe_destination', $result[1]['error']['code'] );
		$this->assertSame( $result, $this->commands->execute_rows( array( $valid, $invalid ) ) );
		$this->assertWPError( $this->commands->execute_rows( array_fill( 0, 51, $valid ) ) );
	}
	public function test_rest_session_and_scoped_fields() {
		$controller = new LinkCommandController();
		$controller->register_hooks();
		do_action( 'rest_api_init' );
		$request = new \WP_REST_Request( 'POST', '/cleanlinks/v1/links' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $this->input() ) );
		$this->assertSame( 'invalid_session', $controller->permission( $request )->get_error_code() );
		$request->set_header( 'X-WP-Nonce', 'expired-nonce' );
		$this->assertSame( 'invalid_session', $controller->permission( $request )->get_error_code() );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$this->assertTrue( $controller->permission( $request ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'id', $response->get_data() );
		$input = $this->input( 'forged_payload_test_key', 'forged-link' );
		$input['meta_input'] = array( 'arbitrary' => 'value' );
		$request->set_body( wp_json_encode( $input ) );
		$this->assertSame( 400, rest_get_server()->dispatch( $request )->get_status() );
	}
	public function test_command_ignores_legacy_request_globals_and_preserves_hooks() {
		$_POST = array( 'cleanlink_redirect_nonce' => wp_create_nonce( 'cleanlink-save-redirect-meta' ), 'cleanlink_redirect_url' => 'https://example.org/forged-global' );
		$observed = null;
		$hook = static function ( $id ) use ( &$observed ) { $observed = get_post_meta( $id, 'cleanlink_redirect_url', true ); };
		add_action( 'save_post_cleanlinks', $hook, 20 );
		try {
			$input = $this->input();
			$saved = $this->commands->execute( $input );
			$this->assertFalse( is_wp_error( $saved ) );
			$this->assertSame( $input['destination'], $observed );
			$this->assertSame( $input['destination'], $saved['destination'] );
		} finally {
			remove_action( 'save_post_cleanlinks', $hook, 20 );
			$_POST = array();
		}
	}
	public function test_update_partial_failure_rolls_back_fields_and_count() {
		$saved = $this->commands->execute( $this->input() );
		update_post_meta( $saved['id'], 'cleanlink_redirect_count', '17' );
		$fail = static function ( $value, $id, $key ) { return 'cleanlink_redirect_nofollow' === $key ? true : $value; };
		add_filter( 'update_post_metadata', $fail, 10, 3 );
		$input = array( 'id' => $saved['id'], 'expected_version' => $saved['version'], 'request_key' => 'partial_update_test_key', 'title' => 'Rejected title', 'destination' => 'https://example.org/rejected', 'nofollow' => false );
		$result = $this->commands->execute( $input );
		remove_filter( 'update_post_metadata', $fail, 10 );
		$this->assertWPError( $result );
		$this->assertSame( $saved, $this->commands->read( $saved['id'] ) );
		$this->assertSame( '17', get_post_meta( $saved['id'], 'cleanlink_redirect_count', true ) );
		$this->assertFalse( is_wp_error( $this->commands->execute( $input ) ) );
	}
	public function test_malformed_input_groups_and_publication_changes() {
		foreach ( array( null, 'text', new \stdClass(), array( 'id' => null ), array( 'id' => array() ), array( 'destination' => array() ) ) as $input ) {
			$this->assertWPError( $this->commands->execute( $input ) );
		}
		$input = $this->input();
		$input['groups'] = array( '1' );
		$this->assertSame( 'invalid_groups', $this->commands->execute( $input )->get_error_code() );
		$input['groups'] = array( 99999999 );
		$this->assertSame( 'invalid_groups', $this->commands->execute( $input )->get_error_code() );
		unset( $input['groups'] );
		$saved = $this->commands->execute( $input );
		update_post_meta( $saved['id'], 'cleanlink_redirect_count', '5' );
		$draft = $this->commands->execute( array( 'id' => $saved['id'], 'request_key' => 'unpublish_request_test_key', 'expected_version' => $saved['version'], 'status' => 'draft' ) );
		$this->assertSame( 'draft', $draft['status'] );
		$this->assertSame( $saved['slug'], $draft['slug'] );
		$this->assertSame( '5', get_post_meta( $saved['id'], 'cleanlink_redirect_count', true ) );
	}
	public function test_editor_other_object_group_assignment_and_administration_roles() {
		$saved = $this->commands->execute( $this->input() );
		$group = self::factory()->term->create( array( 'taxonomy' => 'cleanlinks_groups' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$updated = $this->commands->execute( array( 'id' => $saved['id'], 'request_key' => 'editor_other_object_key', 'expected_version' => $saved['version'], 'groups' => array( $group ) ) );
		$this->assertSame( array( $group ), $updated['groups'] );
		$this->assertTrue( current_user_can( get_taxonomy( 'cleanlinks_groups' )->cap->manage_terms ) );
		$this->assertFalse( current_user_can( 'manage_options' ) );
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $author );
		$this->assertTrue( current_user_can( get_taxonomy( 'cleanlinks_groups' )->cap->assign_terms ) );
		$this->assertFalse( current_user_can( get_taxonomy( 'cleanlinks_groups' )->cap->manage_terms ) );
		$this->assertFalse( current_user_can( 'manage_options' ) );
	}
	public function test_rest_update_read_and_forged_body_identity() {
		do_action( 'rest_api_init' );
		$saved = $this->commands->execute( $this->input() );
		$request = new \WP_REST_Request( 'PATCH', '/cleanlinks/v1/links/' . $saved['id'] );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_body( wp_json_encode( array( 'request_key' => 'rest_update_request_key', 'expected_version' => $saved['version'], 'title' => 'Updated by REST' ) ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Updated by REST', $response->get_data()['title'] );
		$read = new \WP_REST_Request( 'GET', '/cleanlinks/v1/links/' . $saved['id'] );
		$read->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$this->assertSame( $response->get_data(), rest_get_server()->dispatch( $read )->get_data() );
		$request->set_body( wp_json_encode( array( 'id' => $saved['id'] ) ) );
		$this->assertSame( 400, rest_get_server()->dispatch( $request )->get_status() );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$this->assertSame( 403, rest_get_server()->dispatch( $request )->get_status() );
	}

}
