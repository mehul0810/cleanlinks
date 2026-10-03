<?php
/**
 * Transaction boundary for link commands.
 *
 * @package CleanLinks
 */
namespace MG\CleanLinks\Application;

/** Serializes command writers and keeps receipts in the same database transaction. */
class CommandTransaction {
	/** @var bool Whether this object owns the outer transaction. */
	private $owns_transaction = false;

	/** @var string Unique internal savepoint scope. */
	private $scope;

	/** @var bool Whether the command savepoint has been established. */
	private $scope_ready = false;

	/**
	 * Whether rollback cache invalidation is limited to this PHP request.
	 *
	 * @return bool
	 */
	public static function has_request_local_cache() {
		if ( ! function_exists( 'wp_using_ext_object_cache' ) || wp_using_ext_object_cache() || ! isset( $GLOBALS['wp_object_cache'] ) || ! is_object( $GLOBALS['wp_object_cache'] ) || ! class_exists( 'WP_Object_Cache', false ) || 'WP_Object_Cache' !== get_class( $GLOBALS['wp_object_cache'] ) ) {
			return false;
		}
		$core = realpath( ABSPATH . WPINC );
		if ( false === $core ) {
			return false;
		}
		$class_file = realpath( $core . '/class-wp-object-cache.php' );
		$cache_file = realpath( $core . '/cache.php' );
		if ( false === $class_file || false === $cache_file ) {
			return false;
		}
		try {
			$class = new \ReflectionClass( 'WP_Object_Cache' );
			if ( $class_file !== realpath( $class->getFileName() ) ) {
				return false;
			}
			foreach ( array( 'wp_cache_get', 'wp_cache_set', 'wp_cache_add', 'wp_cache_delete' ) as $function ) {
				if ( ! function_exists( $function ) || $cache_file !== realpath( ( new \ReflectionFunction( $function ) )->getFileName() ) ) {
					return false;
				}
			}
		} catch ( \ReflectionException $exception ) {
			return false;
		}
		return true;
	}

	/** Allocate a scope distinct from nested caller transactions. */
	public function __construct() {
		$this->scope = wp_unique_id( 'cleanlinks_command_' );
	}

	/**
	 * Begin a transaction and acquire the command-writer row lock.
	 *
	 * @return bool
	 */
	public function begin() {
		try {
			return $this->begin_scope();
		} catch ( \Throwable $exception ) {
			$this->abort();
			return false;
		}
	}

	/** @return bool Whether transaction setup succeeds. */
	private function begin_scope() {
		global $wpdb;
		// All mutable WordPress tables must support rollback before we write.
		if ( ! defined( 'DB_ENGINE' ) || 'sqlite' !== DB_ENGINE ) {
			foreach ( array( $wpdb->posts, $wpdb->postmeta, $wpdb->options, $wpdb->term_relationships, $wpdb->term_taxonomy ) as $table ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Inspect storage guarantees, not cached application data.
				$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $table ) );
				if ( ! $status || 'innodb' !== strtolower( $status->Engine ) ) {
					return false;
				}
			}
		}
		// A permanent, non-autoloaded coordination row; no schema migration.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Unique option_name and database row locks are the concurrency primitive.
		$initialized = $wpdb->query( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES ('cleanlinks_command_mutex', '1', 'no')" );
		if ( false === $initialized ) {
			return false;
		}
		// A SAVEPOINT is discarded outside a transaction on MySQL and the SQLite adapter.
		// Probe the actual connection state rather than assuming @@autocommit describes it.
		$previous_suppression = $wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Internally generated identifier; transaction-state probe has no application mutations.
		$probe = $wpdb->query( 'SAVEPOINT ' . $this->scope . '_probe' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Internally generated identifier; expected failure means there is no caller transaction.
		$released = $wpdb->query( 'RELEASE SAVEPOINT ' . $this->scope . '_probe' );
		$wpdb->suppress_errors( $previous_suppression );
		if ( false === $probe ) {
			return false;
		}
		$this->owns_transaction = false === $released;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Transaction control uses only fixed SQL literals.
		if ( $this->owns_transaction && false === $wpdb->query( 'START TRANSACTION' ) ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Internally generated identifier; savepoints allow rollback without committing a caller's transaction.
		if ( false === $wpdb->query( 'SAVEPOINT ' . $this->scope ) ) {
			if ( $this->owns_transaction ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Failed transaction setup.
				$wpdb->query( 'ROLLBACK' );
			}
			return false;
		}
		$this->scope_ready = true;
		// MySQL locks the row; SQLite ignores FOR UPDATE, so the following UPDATE obtains its writer lock.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Lock coordination requires uncached reads.
		$locked = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'cleanlinks_command_mutex' FOR UPDATE" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Deliberate no-op write obtains SQLite's database writer lock.
		$written = $wpdb->query( "UPDATE {$wpdb->options} SET option_value = option_value WHERE option_name = 'cleanlinks_command_mutex'" );
		if ( null === $locked || false === $written ) {
			$this->finish( false );
			return false;
		}
		return true;
	}

	/**
	 * Finish only the transaction scope owned by this command.
	 *
	 * @param bool $success Whether to commit.
	 * @return bool
	 */
	public function finish( $success ) {
		try {
			return $this->finish_scope( $success );
		} catch ( \Throwable $exception ) {
			$this->abort();
			return false;
		}
	}

	/**
	 * Finish database scope.
	 *
	 * @param bool $success Commit intent.
	 * @return bool
	 */
	private function finish_scope( $success ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Transaction control uses only fixed SQL literals.
		$result = $wpdb->query( $success ? 'RELEASE SAVEPOINT ' . $this->scope : 'ROLLBACK TO SAVEPOINT ' . $this->scope );
		if ( ! $success ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Transaction control uses only fixed SQL literals.
			$wpdb->query( 'RELEASE SAVEPOINT ' . $this->scope );
		}
		$this->scope_ready = false;
		if ( $this->owns_transaction ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Transaction control uses only fixed SQL literals.
			$outer_result = $wpdb->query( $success && false !== $result ? 'COMMIT' : 'ROLLBACK' );
			return false !== $result && false !== $outer_result;
		}
		return false !== $result;
	}
	/** Best-effort rollback must not leak hook exceptions or commit a caller scope. */
	private function abort() {
		global $wpdb;
		try {
			if ( $this->owns_transaction ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Abort only our outer transaction.
				$wpdb->query( 'ROLLBACK' );
			} elseif ( $this->scope_ready ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Internal scope identifier.
				$wpdb->query( 'ROLLBACK TO SAVEPOINT ' . $this->scope );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Internal scope identifier.
				$wpdb->query( 'RELEASE SAVEPOINT ' . $this->scope );
			}
		} catch ( \Throwable $ignored ) {
			// A disconnected database/uncooperative SQL hook remains an explicit failure.
			return;
		} finally {
			$this->scope_ready = false;
		}
	}

}
