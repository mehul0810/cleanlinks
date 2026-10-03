<?php
namespace MG\CleanLinks\Tests;

use MG\CleanLinks\Application\CommandReceipts;
use MG\CleanLinks\Application\LinkCommands;
use WP_UnitTestCase;

class Test_Command_Receipts extends WP_UnitTestCase {
	private static $site;
	private static $other_site;

	/** Site initialization uses DDL; create fixtures before test transactions. */
	public static function wpSetUpBeforeClass( $factory ) {
		if ( ! is_multisite() ) {
			return;
		}
		self::$site = $factory->blog->create( array( 'user_id' => 1 ) );
		$network = $factory->network->create( array( 'domain' => 'other.example.org' ) );
		update_network_option( $network, 'ms_files_rewriting', 0 );
		self::$other_site = $factory->blog->create( array( 'user_id' => 1, 'network_id' => $network, 'domain' => 'other.example.org' ) );
	}

	/** Shared plugin-file removal clears receipts on all blogs without expanding legacy data deletion. */
	public function test_multisite_uninstall_entry_clears_receipts_only_on_all_sites() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires the existing multisite test runtime.' );
		}
		$current = get_current_blog_id();
		$store = new CommandReceipts();
		$key = $store->option_name( time() . '_multisite_uninstall_receipt', 1 );
		foreach ( array( $current, self::$site, self::$other_site ) as $site ) {
			switch_to_blog( $site );
			try {
				add_option( $key, '{"saved":{"id":123}}', '', false );
				update_option( 'cleanlinks_command_mutex', '1', false );
				add_option( 'proof_unrelated_option', 'keep' );
				update_option( 'cleanlinks_settings', array( 'proof' => 'keep' ) );
				$store->register_hooks();
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'proof_unrelated_cron' );
			} finally {
				restore_current_blog();
			}
		}
		$unrelated_post = self::factory()->post->create( array( 'post_type' => 'post', 'post_status' => 'publish' ) );
		switch_to_blog( self::$site );
		try {
			$link = self::factory()->post->create( array( 'post_type' => 'cleanlinks', 'post_status' => 'publish' ) );
			update_post_meta( $link, 'cleanlink_redirect_count', '41' );
		} finally {
			restore_current_blog();
		}
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', plugin_basename( CLEANLINKS_PLUGIN_FILE ) );
		}
		require dirname( __DIR__, 2 ) . '/uninstall.php';

		$this->assertSame( $current, get_current_blog_id() );
		$this->assertNotNull( get_post( $unrelated_post ) );
		$this->assertNotFalse( get_userdata( 1 ) );
		foreach ( array( $current, self::$site, self::$other_site ) as $site ) {
			switch_to_blog( $site );
			try {
				$this->assertFalse( get_option( $key ), 'Receipt on site ' . $site );
				$this->assertFalse( get_option( 'cleanlinks_command_mutex' ), 'Mutex on site ' . $site );
				$this->assertFalse( wp_next_scheduled( 'cleanlinks_expire_command_receipts' ) );
				$this->assertNotFalse( wp_next_scheduled( 'proof_unrelated_cron' ) );
				$this->assertSame( 'keep', get_option( 'proof_unrelated_option' ) );
				if ( $site !== $current ) {
					$this->assertSame( array( 'proof' => 'keep' ), get_option( 'cleanlinks_settings' ) );
				}
			} finally {
				restore_current_blog();
			}
		}
		switch_to_blog( self::$site );
		try {
			$this->assertNotNull( get_post( $link ) );
			$this->assertSame( '41', get_post_meta( $link, 'cleanlink_redirect_count', true ) );
		} finally {
			restore_current_blog();
		}
	}

	/** All-site uninstall pages IDs without restricting cleanup to one network. */
	public function test_multisite_uninstall_pages_all_sites_and_restores_context() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires the existing multisite test runtime.' );
		}
		$current = get_current_blog_id();
		$offsets = array();
		$pages = function ( $pre, $query ) use ( $current, &$offsets ) {
			$this->assertEmpty( $query->query_vars['network_id'] );
			$this->assertSame( 100, $query->query_vars['number'] );
			$this->assertSame( 'ids', $query->query_vars['fields'] );
			$offsets[] = $query->query_vars['offset'];
			return 0 === $query->query_vars['offset'] ? array_fill( 0, 100, $current ) : array( self::$other_site );
		};
		add_filter( 'sites_pre_query', $pages, 10, 2 );
		try {
			( new CommandReceipts() )->uninstall_all_sites();
		} finally {
			remove_filter( 'sites_pre_query', $pages, 10 );
		}
		$this->assertSame( array( 0, 100 ), $offsets );
		$this->assertSame( $current, get_current_blog_id() );
	}

	/** A site-only deactivation must leave another site's event alone. */
	public function test_multisite_per_site_deactivation_preserves_other_site_event() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires the existing multisite test runtime.' );
		}
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugin = plugin_basename( CLEANLINKS_PLUGIN_FILE );
		$site = self::$site;
		( new CommandReceipts() )->register_hooks();
		update_option( 'active_plugins', array( $plugin ) );
		switch_to_blog( $site );
		try {
			( new CommandReceipts() )->register_hooks();
			update_option( 'active_plugins', array( $plugin ) );
		} finally {
			restore_current_blog();
		}
		deactivate_plugins( $plugin, false, false );
		$this->assertFalse( wp_next_scheduled( 'cleanlinks_expire_command_receipts' ) );
		switch_to_blog( $site );
		try {
			$this->assertTrue( is_plugin_active( $plugin ) );
			$this->assertNotFalse( wp_next_scheduled( 'cleanlinks_expire_command_receipts' ) );
		} finally {
			restore_current_blog();
		}
	}

	/** Network deactivation must clear every site in the affected network. */
	public function test_multisite_network_deactivation_clears_each_site_event() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires the existing multisite test runtime.' );
		}
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugin = plugin_basename( CLEANLINKS_PLUGIN_FILE );
		$current = get_current_blog_id();
		$site = self::$site;
		$this->assertNotNull( get_site( $site ) );
		$other_site = self::$other_site;
		switch_to_blog( $other_site );
		try {
			( new CommandReceipts() )->register_hooks();
		} finally {
			restore_current_blog();
		}
		update_site_option( 'active_sitewide_plugins', array( $plugin => time() ) );
		foreach ( array( $current, $site ) as $id ) {
			switch_to_blog( $id );
			try {
				( new CommandReceipts() )->register_hooks();
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'proof_unrelated_cron' );
				update_option( 'cleanlinks_command_mutex', '1', false );
			} finally {
				restore_current_blog();
			}
		}
		deactivate_plugins( $plugin, false, true );
		$this->assertFalse( is_plugin_active_for_network( $plugin ) );
		$this->assertSame( $current, get_current_blog_id() );
		foreach ( array( $current, $site ) as $id ) {
			switch_to_blog( $id );
			try {
				$this->assertFalse( wp_next_scheduled( 'cleanlinks_expire_command_receipts' ), 'Site ' . $id );
				$this->assertNotFalse( wp_next_scheduled( 'proof_unrelated_cron' ) );
				$this->assertSame( '1', get_option( 'cleanlinks_command_mutex' ) );
			} finally {
				restore_current_blog();
			}
		}
		switch_to_blog( $other_site );
		try {
			$this->assertNotFalse( wp_next_scheduled( 'cleanlinks_expire_command_receipts' ) );
		} finally {
			restore_current_blog();
		}
	}

	/** Exercise the page boundary without provisioning one hundred test sites. */
	public function test_multisite_network_cleanup_pages_ids_and_restores_context() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires the existing multisite test runtime.' );
		}
		$current = get_current_blog_id();
		$site = self::$site;
		foreach ( array( $current, $site ) as $id ) {
			switch_to_blog( $id );
			try {
				( new CommandReceipts() )->register_hooks();
			} finally {
				restore_current_blog();
			}
		}
		$offsets = array();
		$network = get_current_network_id();
		$pages = function ( $pre, $query ) use ( $current, $site, $network, &$offsets ) {
			$this->assertSame( $network, $query->query_vars['network_id'] );
			$this->assertSame( 100, $query->query_vars['number'] );
			$offsets[] = $query->query_vars['offset'];
			return 0 === $query->query_vars['offset'] ? array_fill( 0, 100, $current ) : array( $site );
		};
		add_filter( 'sites_pre_query', $pages, 10, 2 );
		try {
			( new \MG\CleanLinks\Plugin() )->deactivate( true );
		} finally {
			remove_filter( 'sites_pre_query', $pages, 10 );
		}
		$this->assertSame( array( 0, 100 ), $offsets );
		$this->assertSame( $current, get_current_blog_id() );
		foreach ( array( $current, $site ) as $id ) {
			switch_to_blog( $id );
			try {
				$this->assertFalse( wp_next_scheduled( 'cleanlinks_expire_command_receipts' ) );
			} finally {
				restore_current_blog();
			}
		}
	}

	/** Deactivation stops cleanup without deleting data; registration resumes it once. */
	public function test_deactivation_clears_cleanup_and_registration_restores_one_event() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$hook   = 'cleanlinks_expire_command_receipts';
		$store  = new CommandReceipts();
		$plugin = plugin_basename( CLEANLINKS_PLUGIN_FILE );
		wp_clear_scheduled_hook( $hook );
		$store->register_hooks();
		$this->assertNotFalse( wp_next_scheduled( $hook ) );
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'proof_unrelated_cron' );
		$key = $store->option_name( time() . '_lifecycle_receipt_suffix', 1 );
		add_option( $key, '{"saved":{"id":123}}', '', false );
		add_option( 'cleanlinks_command_mutex', '1', '', false );
		$id = self::factory()->post->create( array( 'post_type' => 'cleanlinks', 'post_status' => 'publish' ) );
		update_post_meta( $id, 'cleanlink_redirect_count', '41' );
		update_option( 'active_plugins', array( $plugin ) );

		deactivate_plugins( $plugin, false, false );

		$this->assertFalse( is_plugin_active( $plugin ) );
		$this->assertFalse( wp_next_scheduled( $hook ) );
		$this->assertNotFalse( wp_next_scheduled( 'proof_unrelated_cron' ) );
		$this->assertSame( '{"saved":{"id":123}}', get_option( $key ) );
		$this->assertSame( '1', get_option( 'cleanlinks_command_mutex' ) );
		$this->assertSame( '41', get_post_meta( $id, 'cleanlink_redirect_count', true ) );

		$store->register_hooks();
		$store->register_hooks();
		$this->assertNotFalse( wp_next_scheduled( $hook ) );
		$events = 0;
		foreach ( _get_cron_array() as $hooks ) {
			$events += isset( $hooks[ $hook ] ) ? count( $hooks[ $hook ] ) : 0;
		}
		$this->assertSame( 1, $events );
	}

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
