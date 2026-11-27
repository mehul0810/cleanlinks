<?php
/** Uninstall integration tests. */

namespace MG\CleanLinks\Tests;

use MG\CleanLinks\Includes\PostTypeRegistrar;
use MG\CleanLinks\Uninstall;
use WP_UnitTestCase;

class Test_Uninstall extends WP_UnitTestCase {
	public function test_deactivated_cleanup_preserves_unrelated_data() {
		global $wpdb;
		$ids = array();
		foreach ( array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ) as $status ) {
			$args = array( 'post_type' => 'cleanlinks', 'post_status' => $status );
			if ( 'future' === $status ) {
				$args['post_date'] = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS );
				$args['post_date_gmt'] = $args['post_date'];
			}
			$id = self::factory()->post->create( $args );
			$this->assertSame( $status, get_post_status( $id ) );
			foreach ( array( 'cleanlink_redirect_url', 'cleanlink_redirect_count', 'cleanlink_redirect_nofollow' ) as $key ) {
				add_post_meta( $id, $key, 'fixture' );
			}
			$ids[] = $id;
		}
		$other = self::factory()->post->create();
		register_taxonomy( 'uninstall_other', 'post' );
		$other_term = wp_insert_term( 'Unrelated', 'uninstall_other' );
		wp_set_object_terms( $other, (int) $other_term['term_id'], 'uninstall_other' );
		update_option( 'uninstall_other_option', 'kept' );
		$group = wp_insert_term( 'Owned', 'cleanlinks_groups' );
		$empty = wp_insert_term( 'Empty', 'cleanlinks_groups' );
		wp_set_object_terms( $other, (int) $group['term_id'], 'cleanlinks_groups' );
		update_option( 'cleanlinks_settings', array( 'test' => true ) );
		$post_type = get_post_type_object( 'cleanlinks' );
		$taxonomy  = get_taxonomy( 'cleanlinks_groups' );
		unregister_taxonomy( 'cleanlinks_groups' );
		unregister_post_type( 'cleanlinks' );
		try {
			$this->assertTrue( Uninstall::run() );
		} finally {
			if ( taxonomy_exists( 'cleanlinks_groups' ) ) {
				unregister_taxonomy( 'cleanlinks_groups' );
			}
			( new PostTypeRegistrar( $post_type->register_meta_box_cb ) )->register();
			register_taxonomy( 'cleanlinks_groups', $taxonomy->object_type, get_object_vars( $taxonomy ) );
		}
		foreach ( $ids as $id ) {
			$this->assertNull( get_post( $id ) );
			$this->assertSame( array(), get_post_meta( $id ) );
		}
		$this->assertNotNull( get_post( $other ) );
		$this->assertTrue( has_term( (int) $other_term['term_id'], 'uninstall_other', $other ) );
		$this->assertSame( 'kept', get_option( 'uninstall_other_option' ) );
		$this->assertFalse( get_option( 'cleanlinks_settings' ) );
		$this->assertSame( '0', $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id IN (%d,%d)", $group['term_taxonomy_id'], $empty['term_taxonomy_id'] ) ) );
	}

	public function test_failed_post_deletion_can_be_retried() {
		$id = self::factory()->post->create( array( 'post_type' => 'cleanlinks' ) );
		$other_id = self::factory()->post->create( array( 'post_type' => 'cleanlinks' ) );
		$group = wp_insert_term( 'Retained until retry', 'cleanlinks_groups' );
		update_option( 'cleanlinks_settings', array( 'test' => true ) );
		$reject = static function ( $result, $post ) use ( $id ) {
			return (int) $post->ID === $id ? false : $result;
		};
		add_filter( 'pre_delete_post', $reject, 10, 2 );
		$this->assertWPError( Uninstall::run() );
		remove_filter( 'pre_delete_post', $reject, 10 );
		$this->assertNotNull( get_post( $id ) );
		$this->assertNull( get_post( $other_id ) );
		$this->assertNotNull( get_term( $group['term_id'], 'cleanlinks_groups' ) );
		$this->assertNotFalse( get_option( 'cleanlinks_settings' ) );
		$this->assertFalse( get_option( Uninstall::RECOVERY_OPTION ) );
		$this->assertTrue( Uninstall::run() );
		$this->assertNull( get_post( $id ) );
	}

	public function test_short_circuit_deletion_cleans_remaining_owned_metadata() {
		global $wpdb;
		$id = self::factory()->post->create( array( 'post_type' => 'cleanlinks' ) );
		add_post_meta( $id, 'cleanlink_redirect_url', 'https://example.org/' );
		$short_circuit = static function ( $check, $post ) use ( $id, $wpdb ) {
			if ( (int) $post->ID === $id ) {
				$wpdb->delete( $wpdb->posts, array( 'ID' => $id ) );
				return true;
			}
			return $check;
		};
		add_filter( 'pre_delete_post', $short_circuit, 10, 2 );
		$result = Uninstall::run();
		remove_filter( 'pre_delete_post', $short_circuit, 10 );
		$this->assertTrue( $result );
		$this->assertSame( '0', $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d", $id ) ) );
	}

	public function test_unverifiable_orphan_metadata_requires_recovery_on_retry() {
		global $wpdb;
		$id = self::factory()->post->create( array( 'post_type' => 'cleanlinks' ) );
		add_post_meta( $id, 'cleanlink_redirect_url', 'https://example.org/' );
		$short_circuit = static function ( $check, $post ) use ( $id, $wpdb ) {
			if ( (int) $post->ID === $id ) {
				$wpdb->delete( $wpdb->posts, array( 'ID' => $id ) );
				return true;
			}
			return $check;
		};
		$pretend = static function ( $check, $object_id, $key ) use ( $id ) {
			return (int) $object_id === $id && 'cleanlink_redirect_url' === $key ? true : $check;
		};
		add_filter( 'pre_delete_post', $short_circuit, 10, 2 );
		add_filter( 'delete_post_metadata', $pretend, 10, 3 );
		$result = Uninstall::run();
		remove_filter( 'pre_delete_post', $short_circuit, 10 );
		remove_filter( 'delete_post_metadata', $pretend, 10 );
		$this->assertWPError( $result );
		$this->assertSame( 'post_metadata_recovery', $result->get_error_message() );
		$this->assertTrue( (bool) get_option( Uninstall::RECOVERY_OPTION ) );
		$this->assertSame( 'recovery_required', Uninstall::run()->get_error_message() );
	}

	public function test_misleading_metadata_success_stops_post_deletion_and_can_retry() {
		$id = self::factory()->post->create( array( 'post_type' => 'cleanlinks' ) );
		add_post_meta( $id, 'cleanlink_redirect_url', 'https://example.org/' );
		$pretend = static function ( $check, $object_id, $key ) use ( $id ) {
			return (int) $object_id === $id && 'cleanlink_redirect_url' === $key ? true : $check;
		};
		add_filter( 'delete_post_metadata', $pretend, 10, 3 );
		$result = Uninstall::run();
		remove_filter( 'delete_post_metadata', $pretend, 10 );
		$this->assertWPError( $result );
		$this->assertSame( 'post_metadata', $result->get_error_message() );
		$this->assertSame( $id, $result->get_error_data()['id'] );
		$this->assertNotNull( get_post( $id ) );
		$this->assertSame( 'https://example.org/', get_post_meta( $id, 'cleanlink_redirect_url', true ) );
		$this->assertTrue( Uninstall::run() );
	}

	public function test_shared_term_metadata_and_other_relationship_are_preserved() {
		global $wpdb;
		register_taxonomy( 'uninstall_shared', 'post' );
		$group = wp_insert_term( 'Shared', 'cleanlinks_groups' );
		$term_id = (int) $group['term_id'];
		add_term_meta( $term_id, 'shared_meta', 'keep' );
		$wpdb->insert( $wpdb->term_taxonomy, array( 'term_id' => $term_id, 'taxonomy' => 'uninstall_shared', 'description' => '', 'parent' => 0, 'count' => 1 ) );
		$other_tt = (int) $wpdb->insert_id;
		$other = self::factory()->post->create();
		$wpdb->insert( $wpdb->term_relationships, array( 'object_id' => $other, 'term_taxonomy_id' => $other_tt ) );
		$wpdb->insert( $wpdb->term_relationships, array( 'object_id' => $other, 'term_taxonomy_id' => $group['term_taxonomy_id'] ) );
		$this->assertTrue( Uninstall::run() );
		$this->assertSame( 'keep', get_term_meta( $term_id, 'shared_meta', true ) );
		$this->assertSame( '1', $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE object_id = %d AND term_taxonomy_id = %d", $other, $other_tt ) ) );
		$this->assertSame( '0', $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE object_id = %d AND term_taxonomy_id = %d", $other, $group['term_taxonomy_id'] ) ) );
		$this->assertSame( '1', $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->terms} WHERE term_id = %d", $term_id ) ) );
	}

	public function test_batches_and_owned_orphan_taxonomy() {
		global $wpdb;
		$ids = array();
		for ( $i = 0; $i < Uninstall::BATCH_SIZE + 1; $i++ ) {
			$ids[] = self::factory()->post->create( array( 'post_type' => 'cleanlinks', 'post_status' => 'draft' ) );
		}
		$owned = wp_insert_term( 'Orphan', 'cleanlinks_groups' );
		add_term_meta( $owned['term_id'], 'orphan_meta', 'owned' );
		$wpdb->insert( $wpdb->term_relationships, array( 'object_id' => 999999, 'term_taxonomy_id' => $owned['term_taxonomy_id'] ) );
		$wpdb->delete( $wpdb->terms, array( 'term_id' => $owned['term_id'] ) );
		$this->assertTrue( Uninstall::run() );
		$this->assertNull( get_post( end( $ids ) ) );
		$this->assertSame( '0', $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d", $owned['term_taxonomy_id'] ) ) );
		$this->assertSame( '0', $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id = %d", $owned['term_taxonomy_id'] ) ) );
		$this->assertSame( '0', $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE term_id = %d", $owned['term_id'] ) ) );
	}
}
