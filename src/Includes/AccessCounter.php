<?php
/**
 * CleanLinks | Access Counter.
 *
 * @package WordPress
 * @subpackage CleanLinks
 * @since 1.1.1
 */

namespace MG\CleanLinks\Includes;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns persistence and caching for cleanlink access counts.
 *
 * @since 1.1.1
 */
class AccessCounter {
	/**
	 * Meta key used for the persisted access count.
	 *
	 * @var string
	 */
	private const COUNT_META_KEY = 'cleanlink_redirect_count';

	/**
	 * Value used when initializing a missing access-count row.
	 *
	 * @var int
	 */
	private const INITIAL_COUNT = 1;

	/**
	 * Maximum time to wait for the per-link initialization lock.
	 *
	 * @var int
	 */
	private const LOCK_TIMEOUT_SECONDS = 2;

	/**
	 * Poll interval for the portable lock fallback.
	 *
	 * @var int
	 */
	private const LOCK_POLL_MICROSECONDS = 10000;

	/**
	 * Optional lock callbacks used by deterministic tests and integrations.
	 *
	 * @var callable|null
	 */
	private $lock_acquirer;

	/**
	 * Optional lock release callback used by deterministic tests and integrations.
	 *
	 * @var callable|null
	 */
	private $lock_releaser;

	/**
	 * Initialize the counter.
	 *
	 * The callbacks are intentionally optional. Production requests use the
	 * database advisory lock, while SQLite uses the portable local fallback;
	 * tests can inject a bounded lock failure without depending on a database
	 * driver. An injected integer zero represents GET_LOCK() contention.
	 *
	 * @since 1.1.1
	 *
	 * @param callable|null $lock_acquirer Lock callback receiving post ID and timeout.
	 * @param callable|null $lock_releaser Release callback receiving the lock token.
	 * @return void
	 */
	public function __construct( $lock_acquirer = null, $lock_releaser = null ) {
		$this->lock_acquirer = is_callable( $lock_acquirer ) ? $lock_acquirer : null;
		$this->lock_releaser = is_callable( $lock_releaser ) ? $lock_releaser : null;
	}

	/**
	 * Get the total access count for a cleanlink.
	 *
	 * @since 1.1.1
	 *
	 * @param int $post_id Post ID.
	 * @return int The number of times the link has been accessed.
	 */
	public function get_total_access_count( $post_id ) {
		$cache_key = $this->get_cache_key( $post_id );
		$cached    = wp_cache_get( $cache_key );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		$access_count = get_post_meta( $post_id, self::COUNT_META_KEY, true );
		$count        = $access_count ?: 0;
		wp_cache_set( $cache_key, $count, '', HOUR_IN_SECONDS );

		return $count;
	}

	/**
	 * Increment the access count for a published cleanlink.
	 *
	 * Draft and non-cleanlink posts retain their current count. This keeps the
	 * collaborator safe when it is used outside the template_redirect callback.
	 *
	 * @since 1.1.1
	 *
	 * @param int $post_id Post ID.
	 * @return int The current or new count value.
	 */
	public function increment( $post_id ) {
		if ( ! $this->is_published_cleanlink( $post_id ) ) {
			return (int) get_post_meta( $post_id, self::COUNT_META_KEY, true );
		}

		/*
		 * When an extension observes or short-circuits post-meta updates, use the
		 * WordPress API while a bounded per-link lock is held. This preserves the
		 * complete metadata contract without reintroducing a read-modify-write race.
		 */
		$lock_failed = false;

		if ( $this->requires_metadata_contract() ) {
			$lock = $this->acquire_count_lock( $post_id );

			if ( false !== $lock ) {
				try {
					return $this->increment_with_metadata_api( $post_id );
				} finally {
					$this->release_count_lock( $lock );
				}
			}

			$lock_failed = true;
		}

		/*
		 * A metadata short-circuit cannot be evaluated safely from a stale
		 * snapshot after the lock was unavailable. Leave the value untouched
		 * rather than approving one value and persisting another.
		 */
		if ( $lock_failed && false !== has_filter( 'update_post_metadata' ) ) {
			$this->invalidate_count_caches( $post_id );

			return (int) get_post_meta( $post_id, self::COUNT_META_KEY, true );
		}

		/*
		 * A lock timeout or unsupported lock driver must not suppress the click
		 * unless a metadata filter requires the unavailable API contract. The
		 * action mirror and database-side atomic path remain the bounded fallback.
		 */
		if ( null !== $this->apply_update_metadata_filter( $post_id ) ) {
			return (int) get_post_meta( $post_id, self::COUNT_META_KEY, true );
		}

		$updated = $this->increment_persisted_count( $post_id );

		if ( false === $updated ) {
			return (int) get_post_meta( $post_id, self::COUNT_META_KEY, true );
		}

		if ( 0 === $updated && ! $this->initialize_count( $post_id, $lock_failed ) ) {
			return (int) get_post_meta( $post_id, self::COUNT_META_KEY, true );
		}

		$this->invalidate_count_caches( $post_id );

		// Read back the stored value so callers never expose an unpersisted count.
		return (int) get_post_meta( $post_id, self::COUNT_META_KEY, true );
	}

	/**
	 * Increment through WordPress metadata APIs while a per-link lock is held.
	 *
	 * @since 1.1.1
	 *
	 * @param int $post_id Post ID.
	 * @return int The current or new count value.
	 */
	private function increment_with_metadata_api( $post_id ) {
		// Discard a local metadata cache populated before the lock was acquired.
		wp_cache_delete( $post_id, 'post_meta' );

		$count = (int) get_post_meta( $post_id, self::COUNT_META_KEY, true );
		update_post_meta( $post_id, self::COUNT_META_KEY, $count + 1 );

		$this->invalidate_count_caches( $post_id );

		return (int) get_post_meta( $post_id, self::COUNT_META_KEY, true );
	}

	/**
	 * Check whether metadata filters allow the count update.
	 *
	 * The direct SQL increment cannot call update_post_meta(), but the
	 * update_post_metadata short-circuit is a supported extension point that
	 * must retain its existing behavior.
	 *
	 * @since 1.1.1
	 *
	 * @param int $post_id Post ID.
	 * @return mixed Null when the update may continue, otherwise the filter's
	 *               short-circuit value.
	 */
	private function apply_update_metadata_filter( $post_id ) {
		if ( false === has_filter( 'update_post_metadata' ) ) {
			return null;
		}

		$count = (int) get_post_meta( $post_id, self::COUNT_META_KEY, true );

		return apply_filters(
			'update_post_metadata',
			null,
			$post_id,
			self::COUNT_META_KEY,
			$count + 1,
			''
		);
	}

	/**
	 * Increment every existing count row atomically.
	 *
	 * WordPress post meta is intentionally retained as the storage contract,
	 * while the database performs the arithmetic in one statement. This avoids
	 * two requests reading the same value and subsequently losing an increment.
	 *
	 * @since 1.1.1
	 *
	 * @param int $post_id Post ID.
	 * @return int|false Number of changed rows, or false on a database error.
	 */
	private function increment_persisted_count( $post_id ) {
		$meta_rows = null;

		if ( $this->has_count_meta_actions() ) {
			$meta_rows = $this->get_count_meta_rows( $post_id );

			if ( false === $meta_rows ) {
				return false;
			}

			if ( empty( $meta_rows ) ) {
				return 0;
			}

			$this->fire_count_meta_actions( $post_id, $meta_rows, false );
		}

		$updated = $this->execute_atomic_count_update( $post_id );

		if ( false === $updated || 0 === $updated ) {
			return $updated;
		}

		// Match update_metadata() by invalidating the core cache before after-actions.
		wp_cache_delete( $post_id, 'post_meta' );

		if ( null !== $meta_rows ) {
			$this->fire_count_meta_actions( $post_id, $meta_rows, true );
		}

		return $updated;
	}

	/**
	 * Execute the database-side count increment.
	 *
	 * @since 1.1.1
	 *
	 * @param int $post_id Post ID.
	 * @return int|false Number of changed rows, or false on a database error.
	 */
	private function execute_atomic_count_update( $post_id ) {
		global $wpdb;

		return $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Atomic increment.
			$wpdb->prepare(
				// The table name is supplied by WordPress; count values use placeholders.
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"UPDATE {$wpdb->postmeta}
				SET meta_value = CAST(meta_value AS SIGNED) + 1
				WHERE post_id = %d AND meta_key = %s",
				$post_id,
				self::COUNT_META_KEY
			)
		);
	}

	/**
	 * Check whether metadata update actions need to be mirrored.
	 *
	 * @since 1.1.1
	 *
	 * @return bool True when a metadata action is registered.
	 */
	private function has_count_meta_actions() {
		return false !== has_action( 'update_post_meta' )
			|| false !== has_action( 'update_postmeta' )
			|| false !== has_action( 'updated_post_meta' )
			|| false !== has_action( 'updated_postmeta' );
	}

	/**
	 * Check whether the full metadata contract must be serialized.
	 *
	 * @since 1.1.1
	 *
	 * @return bool True when the WordPress metadata API path is required.
	 */
	private function requires_metadata_contract() {
		return false !== has_filter( 'update_post_metadata' ) || $this->has_external_count_meta_actions();
	}

	/**
	 * Check whether a non-core listener requires the metadata API path.
	 *
	 * WordPress always registers wp_cache_set_posts_last_changed on
	 * updated_post_meta. The atomic path mirrors that action and invalidates the
	 * same metadata cache, so the core cache callback alone must not force every
	 * increment through a serialized read-modify-write update.
	 *
	 * @since 1.1.1
	 *
	 * @return bool True when an extension metadata action is registered.
	 */
	private function has_external_count_meta_actions() {
		$hooks = array(
			'update_post_meta',
			'update_postmeta',
			'updated_post_meta',
			'updated_postmeta',
		);

		if ( ! isset( $GLOBALS['wp_filter'] ) ) {
			return false;
		}

		foreach ( $hooks as $hook ) {
			if ( ! isset( $GLOBALS['wp_filter'][ $hook ]->callbacks ) ) {
				continue;
			}

			foreach ( $GLOBALS['wp_filter'][ $hook ]->callbacks as $callbacks ) {
				foreach ( $callbacks as $callback ) {
					if ( 'updated_post_meta' === $hook
						&& isset( $callback['function'] )
						&& 'wp_cache_set_posts_last_changed' === $callback['function']
					) {
						continue;
					}

					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Acquire a bounded per-link lock.
	 *
	 * MySQL and MariaDB use database advisory locks. SQLite deliberately uses a
	 * local file lock; a database contention or adapter error never switches
	 * lock domains and falls through to the atomic/optimistic write path.
	 *
	 * @since 1.1.1
	 *
	 * @param int $post_id Post ID.
	 * @return mixed Lock token, or false when no lock was acquired.
	 */
	private function acquire_count_lock( $post_id ) {
		if ( null !== $this->lock_acquirer ) {
			$acquired = ( $this->lock_acquirer )( $post_id, self::LOCK_TIMEOUT_SECONDS );

			// Integer zero models a valid GET_LOCK() contention result.
			if ( 0 === $acquired || '0' === $acquired || false === $acquired || null === $acquired ) {
				return false;
			}

			return $acquired;
		}

		global $wpdb;

		$lock_name = $this->get_lock_name( $post_id );

		if ( $this->is_sqlite_database() ) {
			return $this->acquire_file_lock( $lock_name );
		}

		$acquired = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Per-link advisory lock.
			$wpdb->prepare(
				'SELECT GET_LOCK(%s, %d)',
				$lock_name,
				self::LOCK_TIMEOUT_SECONDS
			)
		);

		if ( 1 === (int) $acquired ) {
			return array(
				'type' => 'database',
				'name' => $lock_name,
			);
		}

		/*
		 * A MySQL/MariaDB zero means another request owns this lock. A NULL or
		 * false result indicates an adapter error. Neither result may switch to
		 * an independent flock domain after the database lock was selected.
		 */
		return false;
	}

	/**
	 * Release a lock returned by acquire_count_lock().
	 *
	 * @since 1.1.1
	 *
	 * @param mixed $lock Lock token.
	 * @return void
	 */
	private function release_count_lock( $lock ) {
		if ( null !== $this->lock_releaser ) {
			( $this->lock_releaser )( $lock );
			return;
		}

		if ( ! is_array( $lock ) || ! isset( $lock['type'] ) ) {
			return;
		}

		if ( 'database' === $lock['type'] && isset( $lock['name'] ) ) {
			global $wpdb;

			$wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Release per-link advisory lock.
				$wpdb->prepare(
					'SELECT RELEASE_LOCK(%s)',
					$lock['name']
				)
			);
			return;
		}

		if ( 'file' === $lock['type'] && isset( $lock['handle'] ) ) {
			flock( $lock['handle'], LOCK_UN ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_flock -- Release local lock.
			fclose( $lock['handle'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Release local lock.
		}
	}

	/**
	 * Acquire the portable local lock fallback without waiting indefinitely.
	 *
	 * @since 1.1.1
	 *
	 * @param string $lock_name Database-independent lock name.
	 * @return array|false Lock token, or false when the fallback cannot be used.
	 */
	private function acquire_file_lock( $lock_name ) {
		$lock_path = trailingslashit( sys_get_temp_dir() ) . 'cleanlinks-count-' . md5( $lock_name ) . '.lock';
		$handle    = @fopen( $lock_path, 'c' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Lock fallback must fail closed without emitting a request warning.

		if ( false === $handle ) {
			return false;
		}

		$deadline = microtime( true ) + self::LOCK_TIMEOUT_SECONDS;

		while ( microtime( true ) < $deadline ) {
			if ( flock( $handle, LOCK_EX | LOCK_NB ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_flock -- Acquire local lock fallback.
				return array(
					'type'   => 'file',
					'handle' => $handle,
				);
			}

			usleep( self::LOCK_POLL_MICROSECONDS );
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Release failed local lock attempt.

		return false;
	}

	/**
	 * Detect SQLite before issuing the MySQL-only advisory-lock query.
	 *
	 * @since 1.1.1
	 *
	 * @return bool True when the active database adapter is SQLite.
	 */
	private function is_sqlite_database() {
		global $wpdb;

		return ( defined( 'DB_ENGINE' ) && 'sqlite' === strtolower( DB_ENGINE ) )
			|| false !== stripos( get_class( $wpdb ), 'sqlite' );
	}

	/**
	 * Get count metadata rows for action compatibility.
	 *
	 * @since 1.1.1
	 *
	 * @param int $post_id Post ID.
	 * @return array|false Metadata rows, or false on a database error.
	 */
	private function get_count_meta_rows( $post_id ) {
		global $wpdb;

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Read metadata rows.
			$wpdb->prepare(
				// The table name is supplied by WordPress; values use placeholders.
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT meta_id, meta_value FROM {$wpdb->postmeta}
				WHERE post_id = %d AND meta_key = %s",
				$post_id,
				self::COUNT_META_KEY
			),
			ARRAY_A
		);
	}

	/**
	 * Fire the metadata actions that update_post_meta() would dispatch.
	 *
	 * @since 1.1.1
	 *
	 * @param int   $post_id  Post ID.
	 * @param array $meta_rows Existing metadata rows.
	 * @param bool  $updated   Whether to fire after-update actions.
	 * @return void
	 */
	private function fire_count_meta_actions( $post_id, $meta_rows, $updated ) {
		foreach ( $meta_rows as $meta_row ) {
			$meta_id     = (int) $meta_row['meta_id'];
			$new_count   = (int) $meta_row['meta_value'] + 1;
			$hook        = $updated ? 'updated_post_meta' : 'update_post_meta';
			$legacy_hook = $updated ? 'updated_postmeta' : 'update_postmeta';

			do_action( $hook, $meta_id, $post_id, self::COUNT_META_KEY, $new_count );
			do_action( $legacy_hook, $meta_id, $post_id, self::COUNT_META_KEY, $new_count );
		}
	}

	/**
	 * Initialize a missing count row without racing another first request.
	 *
	 * A normal CleanLinks post may not have a count row until its first
	 * redirect. The advisory lock only runs on that cold path; established links
	 * use the single atomic UPDATE above and do not pay this extra round trip.
	 *
	 * @since 1.1.1
	 *
	 * @param int  $post_id    Post ID.
	 * @param bool $lock_failed Whether the caller already attempted and failed to
	 *                          acquire the lock.
	 * @return bool True when a count was persisted.
	 */
	private function initialize_count( $post_id, $lock_failed = false ) {
		$lock = $lock_failed ? false : $this->acquire_count_lock( $post_id );

		if ( false !== $lock ) {
			try {
				return $this->initialize_count_under_lock( $post_id );
			} finally {
				$this->release_count_lock( $lock );
			}
		}

		/*
		 * A database adapter may not support advisory locks and a local lock may
		 * be unavailable. Retry the atomic read path around one standard insert so
		 * a concurrent initializer is observed instead of losing this click.
		 */
		return $this->initialize_count_without_lock( $post_id );
	}

	/**
	 * Initialize a missing count while the caller owns the per-link lock.
	 *
	 * @since 1.1.1
	 *
	 * @param int $post_id Post ID.
	 * @return bool True when a count was persisted.
	 */
	private function initialize_count_under_lock( $post_id ) {
		$updated = $this->increment_persisted_count( $post_id );

		if ( false === $updated ) {
			return false;
		}

		if ( 0 < $updated ) {
			return true;
		}

		$added = add_post_meta( $post_id, self::COUNT_META_KEY, self::INITIAL_COUNT, true );

		if ( false !== $added ) {
			return true;
		}

		// A concurrent writer outside this lock may have created the row.
		return 0 < $this->increment_persisted_count( $post_id );
	}

	/**
	 * Initialize a missing count after lock acquisition failed.
	 *
	 * @since 1.1.1
	 *
	 * @param int $post_id Post ID.
	 * @return bool True when a count was persisted.
	 */
	private function initialize_count_without_lock( $post_id ) {
		$updated = $this->increment_persisted_count( $post_id );

		if ( false === $updated ) {
			return false;
		}

		if ( 0 < $updated ) {
			return true;
		}

		$added = add_post_meta( $post_id, self::COUNT_META_KEY, self::INITIAL_COUNT, true );

		if ( false !== $added ) {
			return true;
		}

		// A concurrent writer may have inserted the row after the first probe.
		return 0 < $this->increment_persisted_count( $post_id );
	}

	/**
	 * Invalidate WordPress and CleanLinks count caches after a direct update.
	 *
	 * @since 1.1.1
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function invalidate_count_caches( $post_id ) {
		wp_cache_delete( $post_id, 'post_meta' );
		wp_cache_delete( $this->get_cache_key( $post_id ) );
	}

	/**
	 * Build a database-wide advisory lock name for one CleanLink.
	 *
	 * @since 1.1.1
	 *
	 * @param int $post_id Post ID.
	 * @return string Lock name.
	 */
	private function get_lock_name( $post_id ) {
		global $wpdb;

		$database = defined( 'DB_NAME' ) ? DB_NAME : '';

		return 'cleanlinks_count_' . md5( $database . ':' . $wpdb->postmeta . ':' . (int) $post_id );
	}

	/**
	 * Check whether a post is a published cleanlink.
	 *
	 * @since 1.1.1
	 *
	 * @param int $post_id Post ID.
	 * @return bool True when the post is a published cleanlink.
	 */
	private function is_published_cleanlink( $post_id ) {
		return 'cleanlinks' === get_post_type( $post_id ) && 'publish' === get_post_status( $post_id );
	}

	/**
	 * Build the cache key used for a cleanlink count.
	 *
	 * @since 1.1.1
	 *
	 * @param int $post_id Post ID.
	 * @return string Cache key.
	 */
	private function get_cache_key( $post_id ) {
		return 'cleanlink_count_' . $post_id;
	}
}
