<?php
namespace MG\CleanLinks\Tests;

use MG\CleanLinks\Admin\Workspace;
use WP_UnitTestCase;

class Test_Workspace extends WP_UnitTestCase {
	public function test_standard_notices_render_once_after_workspace_navigation() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$workspace = new Workspace();
		$screen_hooks = new \ReflectionProperty( Workspace::class, 'screen_hooks' );
		$screen_hooks->setAccessible( true );
		$screen_hooks->setValue( $workspace, array( 'toplevel_page_cleanlinks' ) );
		$original_screen = get_current_screen();
		set_current_screen( 'toplevel_page_cleanlinks' );
		$calls = array( 'admin' => 0, 'all' => 0 );
		$admin_notice = static function () use ( &$calls ) { ++$calls['admin']; echo '<div class="notice">Admin hook notice</div>'; };
		$all_notice = static function () use ( &$calls ) { ++$calls['all']; echo '<div class="notice">All hook notice</div>'; };
		add_action( 'admin_notices', array( $workspace, 'begin_notice_capture' ), PHP_INT_MIN );
		add_action( 'admin_notices', $admin_notice );
		add_action( 'all_admin_notices', $all_notice );
		add_action( 'all_admin_notices', array( $workspace, 'end_notice_capture' ), PHP_INT_MAX );
		$buffer_level = ob_get_level();
		try {
			ob_start();
			do_action( 'admin_notices' );
			do_action( 'all_admin_notices' );
			$before_render = ob_get_clean();
			$this->assertStringNotContainsString( 'Admin hook notice', $before_render );
			$this->assertStringNotContainsString( 'All hook notice', $before_render );
			ob_start();
			$workspace->render();
			$html = ob_get_clean();
			$this->assertLessThan( strpos( $html, 'Admin hook notice' ), strpos( $html, '</nav>' ) );
			$this->assertLessThan( strpos( $html, 'All hook notice' ), strpos( $html, 'Admin hook notice' ) );
			$this->assertStringContainsString( '<section class="cl-listing">', $html );
			$this->assertLessThan( strpos( $html, '<section class="cl-listing">' ), strpos( $html, 'All hook notice' ) );
			$this->assertSame( 1, substr_count( $html, 'Admin hook notice' ) );
			$this->assertSame( 1, substr_count( $html, 'All hook notice' ) );
			$this->assertSame( array( 'admin' => 1, 'all' => 1 ), $calls );
		} finally {
			while ( ob_get_level() > $buffer_level ) { ob_end_clean(); }
			remove_action( 'admin_notices', array( $workspace, 'begin_notice_capture' ), PHP_INT_MIN );
			remove_action( 'admin_notices', $admin_notice );
			remove_action( 'all_admin_notices', $all_notice );
			remove_action( 'all_admin_notices', array( $workspace, 'end_notice_capture' ), PHP_INT_MAX );
			set_current_screen( $original_screen ? $original_screen->id : 'front' );
		}
	}
	public function test_unrelated_admin_screen_does_not_capture_notices() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$workspace = new Workspace();
		$screen_hooks = new \ReflectionProperty( Workspace::class, 'screen_hooks' );
		$screen_hooks->setAccessible( true );
		$screen_hooks->setValue( $workspace, array( 'toplevel_page_cleanlinks' ) );
		$original_screen = get_current_screen();
		foreach ( array( 'dashboard', 'cleanlinks_page_cleanlinks-export', 'edit-cleanlinks' ) as $screen ) {
			set_current_screen( $screen );
			ob_start();
			$workspace->begin_notice_capture();
			echo '<div class="notice">Unrelated screen notice</div>';
			$workspace->end_notice_capture();
			$this->assertSame( '<div class="notice">Unrelated screen notice</div>', ob_get_clean() );
		}
		set_current_screen( $original_screen ? $original_screen->id : 'front' );
	}
	public function test_notice_capture_does_not_close_a_nested_external_buffer() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$workspace = new Workspace();
		$screen_hooks = new \ReflectionProperty( Workspace::class, 'screen_hooks' );
		$screen_hooks->setAccessible( true );
		$screen_hooks->setValue( $workspace, array( 'toplevel_page_cleanlinks' ) );
		$original_screen = get_current_screen();
		set_current_screen( 'toplevel_page_cleanlinks' );
		$buffer_level = ob_get_level();
		try {
			ob_start();
			$workspace->begin_notice_capture();
			$this->assertSame( $buffer_level + 2, ob_get_level() );
			ob_start();
			echo '<div class="notice">External buffered notice</div>';
			$nested_level = ob_get_level();
			$workspace->end_notice_capture();
			$this->assertSame( $nested_level, ob_get_level() );
			echo '<p>External buffer continued</p>';
			ob_end_flush();
			ob_end_flush();
			$output = ob_get_clean();
			$this->assertStringContainsString( 'External buffered notice', $output );
			$this->assertStringContainsString( 'External buffer continued', $output );
		} finally {
			while ( ob_get_level() > $buffer_level ) { ob_end_clean(); }
			set_current_screen( $original_screen ? $original_screen->id : 'front' );
		}
	}
	public function test_header_precedes_admin_notices_and_uses_public_icon() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$token = 'header-notice-test';
		$original_get = $_GET;
		$_GET['notice'] = $token;
		set_transient( 'cl_notice_' . get_current_user_id() . '_' . $token, array( 'message' => 'Notice placement test' ), MINUTE_IN_SECONDS );
		try {
			ob_start();
			( new Workspace() )->render();
			$html = ob_get_clean();
			$this->assertStringContainsString( CLEANLINKS_PLUGIN_URL . 'images/icon-128x128.png', $html );
			$this->assertStringContainsString( 'v' . CLEANLINKS_VERSION, $html );
			$this->assertStringContainsString( 'https://github.com/mehul0810/cleanlinks#readme', $html );
			$this->assertStringNotContainsString( 'Manage your branded links', $html );
			$this->assertStringNotContainsString( 'cl-header-actions', $html );
			$this->assertFileExists( dirname( CLEANLINKS_PLUGIN_FILE ) . '/images/icon-128x128.png' );
			$this->assertStringContainsString( '<hr class="wp-header-end">', $html );
			$this->assertStringContainsString( 'Notice placement test', $html );
			$this->assertLessThan( strpos( $html, '<hr class="wp-header-end">' ), strpos( $html, '</nav>' ) );
			$this->assertLessThan( strpos( $html, 'Notice placement test' ), strpos( $html, '<hr class="wp-header-end">' ) );
		} finally {
			$_GET = $original_get;
			delete_transient( 'cl_notice_' . get_current_user_id() . '_' . $token );
		}
	}
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
			$id = self::factory()->post->create( array( 'post_type' => 'cleanlinks', 'post_status' => 'publish', 'post_title' => 'unique-pagination-fixture' ) );
			update_post_meta( $id, 'cleanlink_redirect_url', 'https://example.org/?q=<script>' );
			ob_start();
			( new Workspace() )->render();
			$single = ob_get_clean();
			$this->assertStringContainsString( 'unique-pagination-fixture', $single );
			$this->assertStringContainsString( 'cl-pagination', $single );
			$this->assertStringContainsString( 'cl-list-heading', $single );
			$this->assertSame( 1, substr_count( $single, 'data-cl-select-all' ) );
			$this->assertStringContainsString( 'Select all on this page', $single );
			$this->assertLessThan( strpos( $single, '<table class="cl-table">' ), strpos( $single, 'data-cl-select-all' ) );
			$this->assertStringContainsString( '<span class="screen-reader-text">Select links</span>', $single );
			$this->assertStringContainsString( 'All groups', $single );
			$this->assertStringContainsString( '<th scope="col">Status</th><th scope="col">Groups</th>', $single );
			$this->assertStringContainsString( 'dashicons-admin-page', $single );
			$this->assertStringContainsString( 'data-label="Groups"><span class="cl-groups">None', $single );
			$this->assertStringContainsString( '&lt;script&gt;', $single );
			$this->assertStringNotContainsString( '<script>', $single );
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
