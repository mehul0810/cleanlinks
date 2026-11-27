<?php
/** Cleanup of current-site CleanLinks data. */

namespace MG\CleanLinks;

class Uninstall {
	const BATCH_SIZE = 200;
	const RECOVERY_OPTION = 'cleanlinks_uninstall_recovery';

	/** Delete owned data or return the first incomplete stage. */
	public static function run() {
		$missing = new \stdClass();
		if ( $missing !== get_option( self::RECOVERY_OPTION, $missing ) ) {
			return self::error( 'recovery_required', 0 );
		}
		if ( ! add_option( self::RECOVERY_OPTION, true, '', false ) || ! get_option( self::RECOVERY_OPTION ) ) {
			return self::error( 'recovery_required', 0 );
		}
		$recovery = false;
		if ( ! taxonomy_exists( 'cleanlinks_groups' ) ) {
			$taxonomy = register_taxonomy( 'cleanlinks_groups', 'cleanlinks', array( 'public' => false, 'rewrite' => false, 'query_var' => false ) );
			if ( is_wp_error( $taxonomy ) || false === $taxonomy ) {
				return self::finish( self::error( 'taxonomy_registration', 0 ), false );
			}
		}
		$result = self::posts( $recovery );
		if ( is_wp_error( $result ) ) {
			return self::finish( $result, $recovery );
		}
		$result = self::terms();
		if ( is_wp_error( $result ) ) {
			return self::finish( $result, false );
		}
		delete_option( 'cleanlinks_settings' );
		$result = $missing === get_option( 'cleanlinks_settings', $missing ) ? true : self::error( 'settings', 0 );
		return self::finish( $result, false );
	}

	/** Retain the marker only when post ownership was lost ambiguously. */
	private static function finish( $result, $recovery ) {
		if ( $recovery ) {
			return $result;
		}
		delete_option( self::RECOVERY_OPTION );
		$missing = new \stdClass();
		return $missing === get_option( self::RECOVERY_OPTION, $missing ) ? $result : self::error( 'recovery_required', 0 );
	}

	/** Delete posts in bounded keyset batches, including all statuses. */
	private static function posts( &$recovery ) {
		global $wpdb;
		$max = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(ID) FROM {$wpdb->posts} WHERE post_type = %s", 'cleanlinks' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->last_error ) {
			return self::error( 'post_read', 0 );
		}
		$last = 0;
		$first_error = null;
		while ( $last < (int) $max ) {
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND ID > %d AND ID <= %d ORDER BY ID ASC LIMIT %d", 'cleanlinks', $last, $max, self::BATCH_SIZE ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( $wpdb->last_error ) {
				return $first_error ? $first_error : self::error( 'post_read', $last );
			}
			if ( ! $ids ) {
				break;
			}
			foreach ( $ids as $id ) {
				$last = (int) $id;
				$meta_failed = false;
				$guard = static function ( $check, $post ) use ( $last, &$meta_failed ) {
					if ( null !== $check || (int) $post->ID !== $last ) {
						return $check;
					}
					if ( 'cleanlinks' !== $post->post_type ) {
						return false;
					}
					foreach ( array( 'cleanlink_redirect_url', 'cleanlink_redirect_count', 'cleanlink_redirect_nofollow' ) as $key ) {
						delete_post_meta( $last, $key );
					}
					global $wpdb;
					$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key IN (%s, %s, %s)", $last, 'cleanlink_redirect_url', 'cleanlink_redirect_count', 'cleanlink_redirect_nofollow' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					if ( $wpdb->last_error || (int) $count ) {
						$meta_failed = true;
						return false;
					}
					return null;
				};
				add_filter( 'pre_delete_post', $guard, PHP_INT_MAX, 2 );
				try {
					$deleted = wp_delete_post( $last, true );
				} finally {
					remove_filter( 'pre_delete_post', $guard, PHP_INT_MAX );
				}
				$remaining = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID = %d", $last ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				if ( $wpdb->last_error ) {
					$recovery = true;
					return $first_error ? $first_error : self::error( 'post_verification', $last );
				}
				if ( ! (int) $remaining ) {
					foreach ( array( 'cleanlink_redirect_url', 'cleanlink_redirect_count', 'cleanlink_redirect_nofollow' ) as $key ) {
						delete_post_meta( $last, $key );
					}
				}
				$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key IN (%s, %s, %s)", $last, 'cleanlink_redirect_url', 'cleanlink_redirect_count', 'cleanlink_redirect_nofollow' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				if ( ! (int) $remaining && ( $wpdb->last_error || (int) $count ) ) {
					$recovery = true;
					return $first_error ? $first_error : self::error( 'post_metadata_recovery', $last );
				}
				if ( $meta_failed || ! $deleted || is_wp_error( $deleted ) || $wpdb->last_error || (int) $remaining || (int) $count ) {
					if ( null === $first_error ) {
						$first_error = self::error( $meta_failed ? 'post_metadata' : 'post_deletion', $last );
					}
				}
			}
		}
		return $first_error ? $first_error : true;
	}

	/** Delete taxonomy rows only while their live ownership is identifiable. */
	private static function terms() {
		global $wpdb;
		$max = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(term_taxonomy_id) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", 'cleanlinks_groups' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->last_error ) {
			return self::error( 'term_read', 0 );
		}
		$last = 0;
		$first_error = null;
		while ( $last < (int) $max ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT tt.term_taxonomy_id, tt.term_id, t.term_id AS existing_term FROM {$wpdb->term_taxonomy} tt LEFT JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE tt.taxonomy = %s AND tt.term_taxonomy_id > %d AND tt.term_taxonomy_id <= %d ORDER BY tt.term_taxonomy_id ASC LIMIT %d", 'cleanlinks_groups', $last, $max, self::BATCH_SIZE ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( $wpdb->last_error ) {
				return $first_error ? $first_error : self::error( 'term_read', $last );
			}
			if ( ! $rows ) {
				break;
			}
			foreach ( $rows as $row ) {
				$last = (int) $row->term_taxonomy_id;
				$result = self::term( $row );
				if ( is_wp_error( $result ) && null === $first_error ) {
					$first_error = $result;
				}
			}
		}
		return $first_error ? $first_error : true;
	}

	/** Remove relationships and, only for an unshared term, metadata and base row. */
	private static function term( $row ) {
		global $wpdb;
		$id = (int) $row->term_taxonomy_id;
		$term_id = (int) $row->term_id;
		$owned = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id = %d AND term_id = %d AND taxonomy = %s", $id, $term_id, 'cleanlinks_groups' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->last_error || 1 !== (int) $owned ) {
			return self::error( 'term_read', $id );
		}
		$shared = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND term_taxonomy_id <> %d", $term_id, $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->last_error ) {
			return self::error( 'term_read', $id );
		}
		$cursor = 0;
		do {
			$objects = $wpdb->get_col( $wpdb->prepare( "SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d AND object_id > %d ORDER BY object_id ASC LIMIT %d", $id, $cursor, self::BATCH_SIZE ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( $wpdb->last_error ) {
				return self::error( 'term_relationships', $id );
			}
			foreach ( $objects as $object ) {
				$cursor = (int) $object;
				if ( null !== $row->existing_term && ! (int) $shared ) {
					$result = wp_remove_object_terms( $cursor, $term_id, 'cleanlinks_groups' );
				} else {
					// Shared legacy terms and missing base terms cannot be resolved by the term API.
					$result = $wpdb->delete( $wpdb->term_relationships, array( 'object_id' => $cursor, 'term_taxonomy_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					wp_cache_delete( $cursor, 'cleanlinks_groups_relationships' );
					wp_cache_set_terms_last_changed();
				}
				if ( ! $result || is_wp_error( $result ) ) {
					return self::error( 'term_relationships', $id );
				}
			}
		} while ( count( $objects ) === self::BATCH_SIZE );
		$remaining = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->last_error || (int) $remaining ) {
			return self::error( 'term_relationships', $id );
		}
		$shared = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND term_taxonomy_id <> %d", $term_id, $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->last_error ) {
			return self::error( 'term_read', $id );
		}
		if ( ! (int) $shared ) {
			$cursor = 0;
			do {
				$meta = $wpdb->get_col( $wpdb->prepare( "SELECT meta_id FROM {$wpdb->termmeta} WHERE term_id = %d AND meta_id > %d ORDER BY meta_id ASC LIMIT %d", $term_id, $cursor, self::BATCH_SIZE ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				if ( $wpdb->last_error ) {
					return self::error( 'term_metadata', $id );
				}
				foreach ( $meta as $meta_id ) {
					$cursor = (int) $meta_id;
					if ( ! delete_metadata_by_mid( 'term', $cursor ) ) {
						return self::error( 'term_metadata', $id );
					}
				}
			} while ( count( $meta ) === self::BATCH_SIZE );
			$remaining = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE term_id = %d", $term_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( $wpdb->last_error || (int) $remaining ) {
				return self::error( 'term_metadata', $id );
			}
			$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->terms} WHERE term_id = %d AND NOT EXISTS (SELECT 1 FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND term_taxonomy_id <> %d)", $term_id, $term_id, $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			if ( false === $deleted || ( null !== $row->existing_term && 1 !== $deleted ) ) {
				return self::error( 'term_base', $id );
			}
		}
		$deleted = $wpdb->delete( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => $id, 'term_id' => $term_id, 'taxonomy' => 'cleanlinks_groups' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		clean_term_cache( $term_id, 'cleanlinks_groups' );
		wp_cache_set_terms_last_changed();
		return 1 === $deleted ? true : self::error( 'term_taxonomy', $id );
	}

	/** Return an actionable stage and identifier without database details. */
	private static function error( $stage, $id ) {
		return new \WP_Error( 'cleanlinks_uninstall_incomplete', $stage, array( 'id' => (int) $id ) );
	}
}
