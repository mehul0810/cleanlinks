<?php
namespace MG\CleanLinks\Tests;

use MG\CleanLinks\Application\CommandReceipts;
use MG\CleanLinks\Application\LinkCommands;
use WP_UnitTestCase;

class Test_Command_Receipts extends WP_UnitTestCase {
	public function test_issuance_expiry_and_future_boundaries() {
		$store = new CommandReceipts();
		$now = 1800000000;
		$suffix = '_receipt_boundary_suffix';
		$this->assertSame( $now, $store->issued_at( $now . $suffix, $now ) );
		$this->assertSame( $now - CommandReceipts::RETENTION_SECONDS + 1, $store->issued_at( ( $now - CommandReceipts::RETENTION_SECONDS + 1 ) . $suffix, $now ) );
		$this->assertSame( 'expired_request_key', $store->issued_at( ( $now - CommandReceipts::RETENTION_SECONDS ) . $suffix, $now )->get_error_code() );
		$this->assertSame( $now + 300, $store->issued_at( ( $now + 300 ) . $suffix, $now ) );
		$this->assertSame( 'invalid_request_key', $store->issued_at( ( $now + 301 ) . $suffix, $now )->get_error_code() );
		$this->assertWPError( $store->issued_at( 'opaque_unstamped_old_key', $now ) );
	}
	public function test_expired_key_cannot_recreate_before_or_after_cleanup() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$store = new CommandReceipts();
		$key = ( time() - CommandReceipts::RETENTION_SECONDS - 1 ) . '_expired_uncertain_request';
		$name = $store->option_name( $key, get_current_user_id() );
		add_option( $name, '{"saved":{"id":99999}}', '', false );
		$input = array( 'request_key' => $key, 'destination' => 'https://example.org/expired', 'slug' => 'expired-proof' );
		$this->assertSame( 'expired_request_key', ( new LinkCommands() )->execute( $input )->get_error_code() );
		$this->assertSame( 1, $store->prune() );
		$this->assertFalse( get_option( $name ) );
		$this->assertSame( 'expired_request_key', ( new LinkCommands() )->execute( $input )->get_error_code() );
		$this->assertEmpty( get_posts( array( 'post_type' => 'cleanlinks', 'name' => 'expired-proof', 'post_status' => 'any' ) ) );
	}
	public function test_prune_is_bounded_and_preserves_active_mutex_and_other_options() {
		$store = new CommandReceipts();
		$now = time();
		foreach ( array( 'first_expired_suffix', 'second_expired_suffix', 'third_expired_suffix' ) as $suffix ) {
			add_option( $store->option_name( ( $now - CommandReceipts::RETENTION_SECONDS ) . '_' . $suffix, 1 ), '{}', '', false );
		}
		$active = $store->option_name( $now . '_active_receipt_suffix', 1 );
		add_option( $active, '{}', '', false );
		add_option( 'cleanlinks_command_mutex', '1', '', false );
		add_option( 'cleanlinks_command_receipt_unrelated', 'keep', '', false );
		$this->assertSame( 2, $store->prune( 2, $now ) );
		$this->assertSame( 1, $store->prune( 2, $now ) );
		$this->assertSame( '{}', get_option( $active ) );
		$this->assertSame( '1', get_option( 'cleanlinks_command_mutex' ) );
		$this->assertSame( 'keep', get_option( 'cleanlinks_command_receipt_unrelated' ) );
	}
	public function test_capacity_counts_expired_backlog_and_scopes_actors() {
		$store = new CommandReceipts();
		$old = time() - CommandReceipts::RETENTION_SECONDS - 10;
		add_option( $store->option_name( $old . '_capacity_first_suffix', 1 ), '{}', '', false );
		add_option( $store->option_name( time() . '_capacity_second_suffix', 1 ), '{}', '', false );
		add_option( $store->option_name( time() . '_capacity_third_suffix', 2 ), '{}', '', false );
		$this->assertFalse( $store->has_capacity( 1, 2, 10 ) );
		$this->assertTrue( $store->has_capacity( 2, 2, 10 ) );
		$this->assertFalse( $store->has_capacity( 3, 2, 3 ) );
	}
	public function test_uninstall_clears_receipts_and_schedule_only() {
		$store = new CommandReceipts();
		$name = $store->option_name( time() . '_uninstall_receipt_suffix', 1 );
		add_option( $name, '{}', '', false );
		add_option( 'cleanlinks_unrelated_option', 'keep' );
		$store->register_hooks();
		$this->assertNotFalse( wp_next_scheduled( 'cleanlinks_expire_command_receipts' ) );
		$store->uninstall();
		$this->assertFalse( get_option( $name ) );
		$this->assertFalse( wp_next_scheduled( 'cleanlinks_expire_command_receipts' ) );
		$this->assertSame( 'keep', get_option( 'cleanlinks_unrelated_option' ) );
	}
}
