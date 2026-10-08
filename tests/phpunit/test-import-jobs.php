<?php
namespace MG\CleanLinks\Tests;

use MG\CleanLinks\Application\ImportJobs;
use WP_UnitTestCase;

class Test_Import_Jobs extends WP_UnitTestCase {
	private $jobs;
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->jobs = new ImportJobs();
	}
	public function test_preview_has_no_link_writes_and_preserves_quoted_raw_destination() {
		global $wpdb;
		$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" );
		$csv = "destination,title,slug,status,nofollow\r\n\"https://example.org/a?q=1&x=%2F&x=two+words#frag\",\"Quoted, title\nnext line\",quoted,publish,true\r\n";
		$rows = $this->jobs->parse( $csv, true );
		$this->assertIsArray( $rows );
		$this->assertSame( 'ready', $rows[0]['state'] );
		$this->assertSame( 'https://example.org/a?q=1&x=%2F&x=two+words#frag', $rows[0]['input']['destination'] );
		$this->assertTrue( $rows[0]['input']['nofollow'] );
		$this->assertSame( $before, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" ) );
	}
	public function test_strict_csv_and_invalid_row_does_not_suppress_valid_peer() {
		foreach ( array( "destination,title\nhttps://example.org/a,ab\"cd\"ef", "destination,title\nhttps://example.org/a,\"unclosed", "destination,title\nhttps://example.org/a,\"closed\"bad" ) as $csv ) {
			$rows = $this->jobs->parse( $csv, true );
			$this->assertTrue( is_wp_error( $rows ) || 'error' === $rows[0]['state'] );
		}
		$rows = $this->jobs->parse( "destination,slug\nhttp://127.0.0.1/a,same\nhttps://example.org/a,same", true );
		$this->assertSame( array( 'error', 'ready' ), array_column( $rows, 'state' ) );
		$this->assertWPError( $this->jobs->parse( "destination\ndestination\xFF", true ) );
		$this->assertWPError( $this->jobs->parse( 'https://example.org/' . str_repeat( 'a', ImportJobs::BYTES ) ) );
	}
	public function test_mapping_duplicate_policy_and_preview_publication_authority() {
		$rows = $this->jobs->parse( "Target,Name,Alias\nhttps://example.org/a,First,first\nhttps://example.org/a,Second,second", true, array( 'destination' => '0', 'title' => '1', 'slug' => '2' ) );
		$this->assertSame( array( 'ready', 'ready' ), array_column( $rows, 'state' ) );
		$rows = $this->jobs->parse( "https://example.org/a\nhttps://example.org/a" );
		$this->assertSame( 'duplicate_slug', $rows[1]['error'] );
		$this->assertFalse( $rows[1]['selected'] );
		self::factory()->post->create( array( 'post_type' => 'cleanlinks', 'post_name' => 'reserved', 'post_status' => 'draft' ) );
		$rows = $this->jobs->parse( "destination,slug\nhttps://example.org/a,reserved", true );
		$this->assertSame( 'slug_conflict', $rows[0]['error'] );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
		$rows = $this->jobs->parse( "destination,status\nhttps://example.org/a,publish", true );
		$this->assertSame( 'forbidden', $rows[0]['error'] );
	}
	public function test_private_job_expiry_and_capacity() {
		$owner = get_current_user_id();
		$rows = $this->jobs->parse( 'https://example.org/a' );
		$ids = array();
		for ( $i = 0; $i < 5; $i++ ) { $ids[] = $this->jobs->create( $rows ); }
		$this->assertSame( 'job_capacity', $this->jobs->create( $rows )->get_error_code() );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertWPError( $this->jobs->get( $ids[0] ) );
		$this->assertWPError( $this->jobs->control( $ids[0], 'start' ) );
		wp_set_current_user( $owner );
		$this->jobs->control( $ids[0], 'discard' );
		$this->assertWPError( $this->jobs->get( $ids[0] ) );
		$job = $this->jobs->get( $ids[1] );
		$job['expires'] = time() - 1;
		update_option( ImportJobs::PREFIX . $ids[1], $job, false );
		$this->assertWPError( $this->jobs->get( $ids[1] ) );
		$this->jobs->prune();
		$this->assertFalse( get_option( ImportJobs::PREFIX . $ids[1] . '_part_0' ) );
	}
	public function test_205_rows_bounded_batches_cancel_resume_and_repeat() {
		$urls = array();
		for ( $i = 0; $i < 205; $i++ ) { $urls[] = 'https://example.org/bounded-' . $i; }
		$id = $this->jobs->create( $this->jobs->parse( implode( "\n", $urls ) ) );
		$this->assertIsString( $id );
		$job = $this->jobs->control( $id, 'start' );
		$job = $this->jobs->control( $id, 'step' );
		$this->assertSame( 50, $this->jobs->counts( $id, $job )['created'] );
		$this->jobs->control( $id, 'cancel' );
		$job = $this->jobs->control( $id, 'step' );
		$this->assertSame( 50, $this->jobs->counts( $id, $job )['created'] );
		$this->jobs->control( $id, 'resume' );
		for ( $i = 0; $i < 4; $i++ ) { $job = $this->jobs->control( $id, 'step' ); }
		$this->assertSame( 'done', $job['state'] );
		$this->assertSame( 205, $this->jobs->counts( $id, $job )['created'] );
		$job = $this->jobs->control( $id, 'step' );
		$this->assertSame( 205, $this->jobs->counts( $id, $job )['created'] );
	}
	public function test_failed_rows_retry_same_key_and_preserve_partial_success() {
		$id = $this->jobs->create( $this->jobs->parse( "https://example.org/good\nhttps://example.org/fails" ) );
		$this->jobs->control( $id, 'start' );
		$hook = static function( $post_id, $post ) { if ( 'example-org-fails' === $post->post_name ) { throw new \RuntimeException( 'Injected failure' ); } };
		add_action( 'save_post_cleanlinks', $hook, 20, 2 );
		$job = $this->jobs->control( $id, 'step' );
		remove_action( 'save_post_cleanlinks', $hook, 20 );
		$counts = $this->jobs->counts( $id, $job );
		$this->assertSame( 1, $counts['created'] );
		$this->assertSame( 1, $counts['failed'] );
		$rows = get_option( ImportJobs::PREFIX . $id . '_part_0' );
		$key = $rows[1]['input']['request_key'];
		$this->jobs->control( $id, 'retry' );
		$job = $this->jobs->control( $id, 'step' );
		$this->assertSame( 2, $this->jobs->counts( $id, $job )['created'] );
		$rows = get_option( ImportJobs::PREFIX . $id . '_part_0' );
		$this->assertSame( $key, $rows[1]['input']['request_key'] );
	}
	public function test_ambiguous_headers_and_out_of_range_mapping_fail_explicitly() {
		$this->assertSame( 'invalid_header', $this->jobs->parse( "destination,URL\nhttps://example.org/a,https://example.org/b", true )->get_error_code() );
		$this->assertSame( 'invalid_mapping', $this->jobs->parse( "destination\nhttps://example.org/a", true, array( 'destination' => '99' ) )->get_error_code() );
		$this->assertSame( 'ready', $this->jobs->parse( "destination,URL\nhttps://example.org/a,https://example.org/b", true, array( 'destination' => '1' ) )[0]['state'] );
	}
	public function test_persistent_cache_rejects_staging_mutation_before_writes() {
		global $wpdb;
		$rows = $this->jobs->parse( 'https://example.org/cache-test' );
		$id = $this->jobs->create( $rows );
		$before = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'cleanlinks_import_%'" );
		wp_using_ext_object_cache( true );
		try {
			$this->assertSame( 'storage_unavailable', $this->jobs->create( $rows )->get_error_code() );
			$this->assertSame( 'storage_unavailable', $this->jobs->control( $id, 'start' )->get_error_code() );
			$this->jobs->prune();
			$this->assertSame( $before, $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'cleanlinks_import_%'" ) );
		} finally { wp_using_ext_object_cache( false ); }
	}
	public function test_journal_failure_rolls_back_links_receipts_and_repopulated_caches() {
		global $wpdb;
		foreach ( array( 'part', 'header' ) as $where ) {
			$id = $this->jobs->create( $this->jobs->parse( "https://example.org/rollback-{$where}-first\nhttps://example.org/rollback-{$where}-second" ) );
			$this->jobs->control( $id, 'start' );
			$rows = get_option( ImportJobs::PREFIX . $id . '_part_0' );
			$keys = array_column( array_column( $rows, 'input' ), 'request_key' );
			$touched = array();
			$hook = static function( $post_id ) use ( &$touched ) {
				$touched[] = $post_id;
				foreach ( $touched as $other ) { get_post( $other ); get_post_meta( $other ); wp_get_object_terms( $other, 'cleanlinks_groups' ); }
			};
			$option = ImportJobs::PREFIX . $id . ( 'part' === $where ? '_part_0' : '' );
			$block = static function( $new, $old ) { return $old; };
			add_action( 'save_post_cleanlinks', $hook );
			add_filter( 'pre_update_option_' . $option, $block, 10, 2 );
			try { $result = $this->jobs->control( $id, 'step' ); }
			finally { remove_action( 'save_post_cleanlinks', $hook ); remove_filter( 'pre_update_option_' . $option, $block ); }
			$this->assertSame( 'storage_unavailable', $result->get_error_code() );
			$this->assertCount( 2, $touched );
			foreach ( $touched as $post_id ) { $this->assertNull( get_post( $post_id ) ); $this->assertSame( array(), get_post_meta( $post_id ) ); }
			$this->assertSame( 0, $this->jobs->get( $id )['next'] );
			$this->assertSame( 'ready', get_option( ImportJobs::PREFIX . $id . '_part_0' )[0]['state'] );
			$job = $this->jobs->control( $id, 'step' );
			$this->assertSame( 2, $this->jobs->counts( $id, $job )['created'] );
			$this->assertSame( $keys, array_column( array_column( get_option( ImportJobs::PREFIX . $id . '_part_0' ), 'input' ), 'request_key' ) );
		}
	}

	public function test_10000_row_preview_resource_boundary() {
		$urls = array();
		for ( $i = 0; $i < 10000; $i++ ) { $urls[] = 'https://example.org/large-' . $i; }
		$before = memory_get_usage( true );
		$rows = $this->jobs->parse( implode( "\n", $urls ) );
		$this->assertCount( 10000, $rows );
		$this->assertLessThan( 96 * 1024 * 1024, memory_get_usage( true ) - $before );
		$this->assertSame( 'row_limit', $this->jobs->parse( implode( "\n", $urls ) . "\nhttps://example.org/overflow" )->get_error_code() );
	}
}
