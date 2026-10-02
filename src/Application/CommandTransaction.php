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

	/**
	 * Begin a transaction and acquire the command-writer row lock.
	 *
	 * @return bool
	 */
	public function begin() {
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Transaction-state probe has no application mutations.
		$probe = $wpdb->query( 'SAVEPOINT cleanlinks_transaction_probe' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Expected failure means there is no caller transaction.
		$released = $wpdb->query( 'RELEASE SAVEPOINT cleanlinks_transaction_probe' );
		$wpdb->suppress_errors( $previous_suppression );
		if ( false === $probe ) {
			return false;
		}
		$this->owns_transaction = false === $released;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Transaction control uses only fixed SQL literals.
		if ( $this->owns_transaction && false === $wpdb->query( 'START TRANSACTION' ) ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Savepoints allow rollback without committing a caller's transaction.
		if ( false === $wpdb->query( 'SAVEPOINT cleanlinks_command' ) ) {
			if ( $this->owns_transaction ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Failed transaction setup.
				$wpdb->query( 'ROLLBACK' );
			}
			return false;
		}
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
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Transaction control uses only fixed SQL literals.
		$result = $wpdb->query( $success ? 'RELEASE SAVEPOINT cleanlinks_command' : 'ROLLBACK TO SAVEPOINT cleanlinks_command' );
		if ( ! $success ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Transaction control uses only fixed SQL literals.
			$wpdb->query( 'RELEASE SAVEPOINT cleanlinks_command' );
		}
		if ( $this->owns_transaction ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Transaction control uses only fixed SQL literals.
			$outer_result = $wpdb->query( $success && false !== $result ? 'COMMIT' : 'ROLLBACK' );
			return false !== $result && false !== $outer_result;
		}
		return false !== $result;
	}
}
