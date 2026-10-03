<?php
/**
 * Bounded, expiry-safe command receipts.
 *
 * @package CleanLinks
 */
namespace MG\CleanLinks\Application;

/** Keys expire independently of stored receipts, so cleanup cannot enable a replay. */
class CommandReceipts {
	const RETENTION_SECONDS = 604800;
	const FUTURE_SKEW       = 300;
	const ACTOR_LIMIT       = 20000;
	const GLOBAL_LIMIT      = 100000;
	const PREFIX            = 'cleanlinks_command_receipt_';

	/**
	 * Validate the UTC issuance timestamp before lookup or mutation.
	 *
	 * @param mixed    $key Request key: Unix timestamp_random suffix.
	 * @param int|null $now Injectable UTC time for boundary checks.
	 * @return int|\WP_Error Issuance time.
	 */
	public function issued_at( $key, $now = null ) {
		$now = null === $now ? time() : $now;
		if ( ! is_string( $key ) || ! preg_match( '/^([0-9]{10})_[A-Za-z0-9_-]{16,64}$/D', $key, $parts ) ) {
			return LinkInput::error( 'invalid_request_key', 'request_key', 400 );
		}
		$issued = (int) $parts[1];
		if ( $issued > $now + self::FUTURE_SKEW ) {
			return LinkInput::error( 'invalid_request_key', 'request_key', 400 );
		}
		if ( $issued <= $now - self::RETENTION_SECONDS ) {
			return LinkInput::error( 'expired_request_key', 'request_key', 409 );
		}
		return $issued;
	}

	/**
	 * Build a sortable private, non-autoloaded option name.
	 *
	 * @param string $key Validated request key.
	 * @param int    $actor Current actor ID.
	 * @return string
	 */
	public function option_name( $key, $actor ) {
		return self::PREFIX . substr( $key, 0, 10 ) . '_' . $actor . '_' . hash( 'sha256', $key );
	}

	/**
	 * Remove a bounded page of expired receipts; mutex/other options are excluded.
	 *
	 * @param int      $limit Bounded page size, maximum 2000.
	 * @param int|null $now UTC timestamp.
	 * @return int|false
	 */
	public function prune( $limit = 200, $now = null ) {
		global $wpdb;
		$now   = null === $now ? time() : $now;
		$limit = max( 1, min( 2000, (int) $limit ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bounded maintenance over indexed, plugin-owned receipt names.
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name < %s ORDER BY option_name LIMIT %d", $wpdb->esc_like( self::PREFIX ) . '%', self::PREFIX . ( $now - self::RETENTION_SECONDS + 1 ) . '_', $limit ) );
		if ( $wpdb->last_error ) {
			return false;
		}
		$removed = 0;
		foreach ( $names as $name ) {
			// Only a correctly shaped issuance/user/hash receipt belongs to this lifecycle.
			if ( ! preg_match( '/^cleanlinks_command_receipt_[0-9]{10}_[1-9][0-9]*_[a-f0-9]{64}$/D', $name ) ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Receipt options bypass the option cache in the command path.
			$deleted = $wpdb->delete( $wpdb->options, array( 'option_name' => $name ) );
			if ( false === $deleted ) {
				return false;
			}
			$removed += $deleted;
			wp_cache_delete( $name, 'options' );
		}
		return $removed;
	}

	/**
	 * Enforce capacity before mutation while the command mutex is held.
	 *
	 * @param int $actor Actor ID.
	 * @param int $actor_limit Optional lower actor ceiling.
	 * @param int $global_limit Optional lower global ceiling.
	 * @return bool
	 */
	public function has_capacity( $actor, $actor_limit = self::ACTOR_LIMIT, $global_limit = self::GLOBAL_LIMIT ) {
		global $wpdb;
		// Include expired backlog until it is actually removed.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Capacity is checked under the writer lock and cannot be cached.
		$global = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s FOR UPDATE", $wpdb->esc_like( self::PREFIX ) . '%' ) );
		if ( null === $global || (int) $global >= min( self::GLOBAL_LIMIT, $global_limit ) ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Indexed receipt-name prefix and actor scope, not a public option query.
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s FOR UPDATE", $wpdb->esc_like( self::PREFIX ) . '%' . $wpdb->esc_like( '_' . $actor . '_' ) . '%' ) );
		return null !== $count && (int) $count < min( self::ACTOR_LIMIT, $actor_limit );
	}

	/** Register bounded cleanup; no UI or remote automation. */
	public function register_hooks() {
		add_action( 'cleanlinks_expire_command_receipts', array( $this, 'scheduled_prune' ) );
		if ( ! wp_next_scheduled( 'cleanlinks_expire_command_receipts' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'cleanlinks_expire_command_receipts' );
		}
	}

	/** Remove one bounded maintenance page. */
	public function scheduled_prune() {
		$this->prune( 2000 );
	}

	/** Plugin-file removal clears owned command state across the installation. */
	public function uninstall_all_sites() {
		if ( ! is_multisite() ) {
			$this->uninstall();
			return;
		}
		$offset = 0;
		do {
			$sites = get_sites( array( 'fields' => 'ids', 'number' => 100, 'offset' => $offset, 'orderby' => 'id', 'order' => 'ASC' ) );
			foreach ( $sites as $site ) {
				switch_to_blog( $site );
				try {
					$this->uninstall();
				} finally {
					restore_current_blog();
				}
			}
			$offset += 100;
		} while ( count( $sites ) === 100 );
	}

	/** Clear only owned command options on the current site. */
	public function uninstall() {
		global $wpdb;
		wp_clear_scheduled_hook( 'cleanlinks_expire_command_receipts' );
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bounded uninstall of the precise owned prefix, not unrelated options.
			$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 200", $wpdb->esc_like( self::PREFIX ) . '%' ) );
			$removed = 0;
			foreach ( $names as $name ) {
				$removed += delete_option( $name ) ? 1 : 0;
			}
		} while ( count( $names ) === 200 && $removed > 0 && ! $wpdb->last_error );
		delete_option( 'cleanlinks_command_mutex' );
	}
}
