<?php
namespace MG\CleanLinks\Tests;

use MG\CleanLinks\Admin\Workspace;
use WP_UnitTestCase;

class Test_Workspace extends WP_UnitTestCase {
	private function invoke( $method, $value ) {
		$reflection = new \ReflectionMethod( Workspace::class, $method );
		return $reflection->invoke( new Workspace(), $value );
	}
	public function test_zero_and_single_page_lists_render_without_nullable_pagination_warnings() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$original = $_GET;
		$buffer_level = ob_get_level();
		$_GET = array( 'page' => 'cleanlinks', 's' => 'unique-pagination-fixture' );
		set_error_handler( static function ( $severity, $message ) { throw new \ErrorException( $message, 0, $severity ); } );
		try {
			ob_start();
			( new Workspace() )->render();
			$empty = ob_get_clean();
			$this->assertStringContainsString( 'No links to show', $empty );
			self::factory()->post->create( array( 'post_type' => 'cleanlinks', 'post_status' => 'publish', 'post_title' => 'unique-pagination-fixture' ) );
			ob_start();
			( new Workspace() )->render();
			$single = ob_get_clean();
			$this->assertStringContainsString( 'unique-pagination-fixture', $single );
			$this->assertStringContainsString( 'cl-pagination', $single );
			$this->assertStringNotContainsString( 'page-numbers', $single );
		} finally {
			while ( ob_get_level() > $buffer_level ) { ob_end_clean(); }
			restore_error_handler();
			$_GET = $original;
		}
	}
	public function test_form_shape_rejects_nested_ids_status_flags_and_groups() {
		foreach ( array( array( 'id' => array() ), array( 'link_status' => array( 'publish' ) ), array( 'nofollow' => array( '1' ) ), array( 'confirm_slug_change' => 'yes' ), array( 'groups' => array( array( '1' ) ) ) ) as $bad ) {
			$this->assertWPError( $this->invoke( 'save_input', $bad ) );
		}
		$this->assertWPError( $this->invoke( 'selection', array( 'confirmed' => '1', 'ids' => array( array( '1' ) ), 'selection_action' => 'trash' ) ) );
	}
	public function test_selected_actions_preserve_ids_counts_and_groups_and_recheck_each_capability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$group = self::factory()->term->create( array( 'taxonomy' => 'cleanlinks_groups' ) );
		$id = self::factory()->post->create( array( 'post_type' => 'cleanlinks', 'post_status' => 'publish', 'post_author' => get_current_user_id() ) );
		$other = self::factory()->post->create( array( 'post_type' => 'post', 'post_status' => 'publish' ) );
		update_post_meta( $id, 'cleanlink_redirect_count', '17' );
		wp_set_object_terms( $id, array( $group ), 'cleanlinks_groups' );
		$this->assertWPError( $this->invoke( 'selection', array( 'ids' => array( (string) $id ), 'selection_action' => 'trash' ) ) );
		$result = $this->invoke( 'selection', array( 'confirmed' => '1', 'ids' => array( (string) $id, (string) $other ), 'selection_action' => 'trash' ) );
		$this->assertStringContainsString( '1 links updated; 1 could not', $result );
		$this->assertSame( 'trash', get_post_status( $id ) );
		$this->assertSame( 'publish', get_post_status( $other ) );
		$this->invoke( 'selection', array( 'confirmed' => '1', 'ids' => array( (string) $id ), 'selection_action' => 'restore' ) );
		$this->assertNotSame( 'trash', get_post_status( $id ) );
		$this->assertSame( '17', get_post_meta( $id, 'cleanlink_redirect_count', true ) );
		$this->assertSame( array( $group ), wp_get_object_terms( $id, 'cleanlinks_groups', array( 'fields' => 'ids' ) ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->invoke( 'selection', array( 'confirmed' => '1', 'ids' => array( (string) $id ), 'selection_action' => 'trash' ) );
		$this->assertNotSame( 'trash', get_post_status( $id ) );
	}
	public function test_classic_mode_propagates_without_changing_other_admin_urls() {
		$_GET['cleanlinks_classic'] = '1';
		try {
			$workspace = new Workspace();
			$this->assertStringContainsString( 'cleanlinks_classic=1', $workspace->classic_url( 'https://example.org/wp-admin/post-new.php?post_type=cleanlinks', 'post-new.php?post_type=cleanlinks' ) );
			$this->assertSame( 'https://example.org/wp-admin/plugins.php', $workspace->classic_url( 'https://example.org/wp-admin/plugins.php', 'plugins.php' ) );
		} finally { unset( $_GET['cleanlinks_classic'] ); }
	}
}
