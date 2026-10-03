<?php
/**
 * Shared create/update application service.
 *
 * @package CleanLinks
 */
namespace MG\CleanLinks\Application;

/** Applies explicit commands to the existing CPT and its owned metadata. */
class LinkCommands {
	/** @var bool Reject reentrant commands from persistence hooks. */
	private static $running = false;

	/** @var array Term IDs whose cached counts may need rollback invalidation. */
	private $touched_groups = array();

	/** @var array Post IDs whose speculative caches need invalidation. */
	private $touched_ids = array();

	/** @var array Final inserted row candidates, captured before core writes. */
	private $insert_candidates = array();

	/**
	 * Whether an explicit command owns the current save.
	 *
	 * @return bool
	 */
	public static function is_running() {
		return self::$running;
	}

	/**
	 * Run a create or update command, independent of request globals.
	 *
	 * @param mixed $raw_input Explicit unslashed input; id distinguishes update.
	 * @return array|\WP_Error Persisted identity/state or a structured rejection.
	 */
	public function execute( $raw_input ) {
		if ( self::$running || \MG\CleanLinks\Includes\LinkMetadataCommand::is_running() ) {
			return LinkInput::error( 'command_busy', 'input' );
		}
		if ( ! CommandTransaction::has_request_local_cache() ) {
			return LinkInput::error( 'storage_unavailable', 'input', 503 );
		}
		self::$running           = true;
		$this->touched_groups    = array();
		$this->touched_ids       = array();
		$this->insert_candidates = array();
		$transaction             = null;
		$begun                   = false;
		$id                      = 0;
		$result                  = LinkInput::error( 'persistence_failed', 'input', 500 );
		// Capture IDs before metadata/save hooks can throw, not only after wp_insert_post returns.
		$observe_id = function ( $check, $post_id ) {
			$this->touched_ids[] = (int) $post_id;
			return $check;
		};
		$observe_row = function ( $data ) {
			$this->insert_candidates[] = array( 'type' => $data['post_type'], 'slug' => wp_unslash( $data['post_name'] ), 'author' => (int) $data['post_author'] );
			return $data;
		};
		add_filter( 'add_post_metadata', $observe_id, PHP_INT_MIN, 2 );
		add_filter( 'update_post_metadata', $observe_id, PHP_INT_MIN, 2 );
		add_filter( 'wp_insert_post_data', $observe_row, PHP_INT_MAX );
		try {
			$input = ( new LinkInput() )->validate( $raw_input );
			if ( is_wp_error( $input ) ) {
				return $input;
			}
			$permission = $this->authorize( $input );
			if ( is_wp_error( $permission ) ) {
				return $permission;
			}
			$transaction = new CommandTransaction();
			if ( ! $transaction->begin() ) {
				return LinkInput::error( 'storage_unavailable', 'input', 503 );
			}
			$begun  = true;
			$id     = isset( $input['id'] ) ? $input['id'] : 0;
			$result = $this->persist( $input, $id );
		} catch ( \Throwable $exception ) {
			$result = LinkInput::error( 'persistence_failed', 'input', 500 );
			if ( $begun ) {
				// Recovery covers exceptions in term/meta hooks before save_post is reached.
				$this->recover_cache_ids();
			}
		} finally {
			remove_filter( 'add_post_metadata', $observe_id, PHP_INT_MIN );
			remove_filter( 'update_post_metadata', $observe_id, PHP_INT_MIN );
			remove_filter( 'wp_insert_post_data', $observe_row, PHP_INT_MAX );
			if ( $begun && ! $transaction->finish( ! is_wp_error( $result ) ) ) {
				$result = LinkInput::error( 'commit_failed', 'input', 500 );
			}
			self::$running = false;
			clean_term_cache( $this->touched_groups, 'cleanlinks_groups' );
			foreach ( array_unique( array_merge( $this->touched_ids, array( $id ) ) ) as $touched_id ) {
				if ( $touched_id ) {
					clean_post_cache( $touched_id );
					clean_object_term_cache( $touched_id, 'cleanlinks' );
				}
			}
		}
		return $result;
	}

	/** Recover allocated IDs while rolled-back rows are still visible to this connection. */
	private function recover_cache_ids() {
		global $wpdb;
		foreach ( $this->insert_candidates as $candidate ) {
			try {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Uncached lookup strictly for post-rollback cache invalidation.
				$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_name = %s AND post_author = %d FOR UPDATE", $candidate['type'], $candidate['slug'], $candidate['author'] ) );
				$this->touched_ids = array_merge( $this->touched_ids, array_map( 'intval', $ids ) );
			} catch ( \Throwable $ignored ) {
				// An uncooperative SQL hook must not prevent rollback/known-ID cleanup.
				continue;
			}
		}
	}

	/**
	 * Return current editable state including a concurrency token.
	 *
	 * @param mixed $id Integer CPT ID.
	 * @return array|\WP_Error
	 */
	public function read( $id ) {
		global $wpdb;
		if ( ! is_int( $id ) || $id < 1 ) {
			return LinkInput::error( 'invalid_id', 'id', 404 );
		}
		if ( ! CommandTransaction::has_request_local_cache() ) {
			return LinkInput::error( 'storage_unavailable', 'input', 503 );
		}
		clean_post_cache( $id );
		$lock = self::$running ? ' FOR UPDATE' : '';
		// Current/locking reads avoid a caller's older MySQL repeatable-read snapshot.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Fixed lock clause and prepared object ID.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID = %d", $id ) . $lock );
		if ( ! $row || 'cleanlinks' !== $row->post_type ) {
			return LinkInput::error( 'invalid_id', 'id', 404 );
		}
		$post = new \WP_Post( $row );
		wp_cache_set( $id, $post, 'posts' );
		if ( ! current_user_can( 'edit_post', $id ) ) {
			return LinkInput::error( 'forbidden', 'id', 403 );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Lock authoritative metadata rows within the same transaction.
		$meta_rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key IN ('cleanlink_redirect_url', 'cleanlink_redirect_nofollow') ORDER BY meta_id", $id ) . $lock );
		if ( $wpdb->last_error ) {
			return LinkInput::error( 'storage_unavailable', 'destination', 503 );
		}
		$metadata = array();
		foreach ( $meta_rows as $meta ) {
			if ( ! isset( $metadata[ $meta->meta_key ] ) ) {
				$metadata[ $meta->meta_key ] = $meta->meta_value;
			}
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Read actual membership rather than a stale snapshot/term cache.
		$groups = $wpdb->get_col( $wpdb->prepare( "SELECT t.term_id FROM {$wpdb->term_relationships} r INNER JOIN {$wpdb->term_taxonomy} t ON r.term_taxonomy_id = t.term_taxonomy_id WHERE r.object_id = %d AND t.taxonomy = 'cleanlinks_groups' ORDER BY t.term_id", $id ) . $lock );
		if ( $wpdb->last_error ) {
			return LinkInput::error( 'storage_unavailable', 'groups', 503 );
		}
		$state = array(
			'id'          => $id,
			'title'       => $post->post_title,
			'slug'        => $post->post_name,
			'status'      => $post->post_status,
			'destination' => isset( $metadata['cleanlink_redirect_url'] ) ? $metadata['cleanlink_redirect_url'] : '',
			'nofollow'    => isset( $metadata['cleanlink_redirect_nofollow'] ) && '1' === $metadata['cleanlink_redirect_nofollow'],
			'groups'      => array_map( 'intval', $groups ),
		);
		$state['version'] = hash( 'sha256', wp_json_encode( array( $state, $post->post_author, $post->post_modified_gmt ) ) );
		$state['url']     = get_permalink( $id );
		return $state;
	}

	/**
	 * Execute bounded independent rows; never claim transactional bulk undo.
	 *
	 * @param mixed $rows List of commands, each with its own stable request key.
	 * @return array|\WP_Error
	 */
	public function execute_rows( $rows ) {
		if ( ! is_array( $rows ) || array_values( $rows ) !== $rows || count( $rows ) < 1 || count( $rows ) > 50 ) {
			return LinkInput::error( 'invalid_rows', 'rows', 400 );
		}
		$results = array();
		foreach ( $rows as $number => $input ) {
			$result    = $this->execute( $input );
			$results[] = is_wp_error( $result ) ? array(
				'row'   => $number + 1,
				'error' => array( 'code' => $result->get_error_code(), 'data' => $result->get_error_data() ),
			) : array( 'row' => $number + 1, 'saved' => $result );
		}
		return $results;
	}

	/**
	 * Check object, publication and group capabilities.
	 *
	 * @param array $input Validated input.
	 * @return true|\WP_Error
	 */
	private function authorize( $input ) {
		$type = get_post_type_object( 'cleanlinks' );
		if ( ! $type || ! get_current_user_id() ) {
			return LinkInput::error( 'forbidden', 'id', 403 );
		}
		if ( isset( $input['id'] ) ) {
			if ( 'cleanlinks' !== get_post_type( $input['id'] ) || 'trash' === get_post_status( $input['id'] ) ) {
				return LinkInput::error( 'invalid_id', 'id', 404 );
			}
			if ( ! current_user_can( 'edit_post', $input['id'] ) ) {
				return LinkInput::error( 'forbidden', 'id', 403 );
			}
		} elseif ( ! current_user_can( $type->cap->create_posts ) ) {
			return LinkInput::error( 'forbidden', 'id', 403 );
		}
		if ( isset( $input['status'] ) && in_array( $input['status'], array( 'publish', 'private' ), true ) && ! current_user_can( $type->cap->publish_posts ) ) {
			return LinkInput::error( 'forbidden_status', 'status', 403 );
		}
		$taxonomy = get_taxonomy( 'cleanlinks_groups' );
		if ( isset( $input['groups'] ) && ( ! $taxonomy || ! current_user_can( $taxonomy->cap->assign_terms ) ) ) {
			return LinkInput::error( 'forbidden_groups', 'groups', 403 );
		}
		return true;
	}

	/**
	 * Persist inside the writer lock and database transaction.
	 *
	 * @param array $input Validated input.
	 * @param int   $id ID updated by reference for rollback cache cleanup.
	 * @return array|\WP_Error
	 */
	private function persist( $input, &$id ) {
		global $wpdb;
		$valid_key = ( new CommandReceipts() )->issued_at( $input['request_key'] );
		if ( is_wp_error( $valid_key ) ) {
			return $valid_key;
		}
		$receipts    = new CommandReceipts();
		$key         = $receipts->option_name( $input['request_key'], get_current_user_id() );
		$fingerprint = hash( 'sha256', wp_json_encode( $input ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Durable receipts must bypass speculative option caches.
		$stored = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s FOR UPDATE", $key ) );
		if ( $wpdb->last_error ) {
			return LinkInput::error( 'storage_unavailable', 'request_key', 503 );
		}
		if ( null !== $stored ) {
			$receipt = json_decode( $stored, true );
			if ( ! is_array( $receipt ) || ! isset( $receipt['fingerprint'], $receipt['saved'] ) || ! hash_equals( $receipt['fingerprint'], $fingerprint ) ) {
				return LinkInput::error( 'request_key_conflict', 'request_key' );
			}
			$current = $this->read( $receipt['saved']['id'] );
			if ( is_wp_error( $current ) || ! current_user_can( 'edit_post', $receipt['saved']['id'] ) ) {
				return LinkInput::error( 'replay_unavailable', 'id', 403 );
			}
			return $receipt['saved'];
		}
		if ( false === $receipts->prune() ) {
			return LinkInput::error( 'storage_unavailable', 'request_key', 503 );
		}
		if ( ! $receipts->has_capacity( get_current_user_id() ) ) {
			return LinkInput::error( 'receipt_capacity', 'request_key', 429 );
		}
		if ( $id ) {
			// Prevent core post updates from changing this row during the stale-token check and save.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Lock must be taken in the transaction and cannot be cached.
			$locked_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID = %d FOR UPDATE", $id ) );
			if ( (int) $locked_id !== $id ) {
				return LinkInput::error( 'storage_unavailable', 'id', 503 );
			}
			$state = $this->read( $id );
			if ( is_wp_error( $state ) ) {
				return $state;
			}
			if ( ! hash_equals( $state['version'], $input['expected_version'] ) ) {
				return LinkInput::error( 'stale_version', 'expected_version' );
			}
		} else {
			$state = array( 'title' => '', 'slug' => '', 'status' => 'draft', 'nofollow' => false, 'groups' => array() );
		}
		$permission = $this->authorize( $input );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		$values               = array_merge( $state, $input );
		$this->touched_groups = array_unique( array_merge( $state['groups'], $values['groups'] ) );
		if ( '' === $values['title'] ) {
			$values['title'] = (string) wp_parse_url( $values['destination'], PHP_URL_HOST );
		}
		if ( $id && isset( $input['slug'] ) && $input['slug'] !== $state['slug'] && empty( $input['confirm_slug_change'] ) ) {
			return LinkInput::error( 'slug_confirmation_required', 'slug' );
		}
		$slug = '' === $values['slug'] ? sanitize_title( $values['title'] ) : $values['slug'];
		if ( '' === $slug ) {
			$slug = 'link';
		}
		// Reserve explicit slugs across drafts and published links; never overwrite an existing link.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Command mutex protects this uncached collision check.
		$collision = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'cleanlinks' AND post_name = %s AND ID != %d LIMIT 1 FOR UPDATE", $slug, $id ) );
		if ( $wpdb->last_error ) {
			return LinkInput::error( 'storage_unavailable', 'slug', 503 );
		}
		if ( $collision ) {
			return LinkInput::error( 'slug_conflict', 'slug' );
		}
		$post_data = array( 'post_type' => 'cleanlinks', 'post_title' => $values['title'], 'post_name' => $slug, 'post_status' => $values['status'] );
		if ( $id ) {
			$post_data['ID'] = $id;
		} else {
			$post_data['post_author'] = get_current_user_id();
		}
		// Core persists owned metadata and terms before save_post hooks run.
		$post_data['meta_input'] = array( 'cleanlink_redirect_url' => $values['destination'], 'cleanlink_redirect_nofollow' => $values['nofollow'] ? '1' : '0' );
		if ( isset( $input['groups'] ) ) {
			$post_data['tax_input'] = array( 'cleanlinks_groups' => $values['groups'] );
		}
		$saved_id = wp_insert_post( wp_slash( $post_data ), true );
		if ( is_wp_error( $saved_id ) || ! $saved_id ) {
			return LinkInput::error( 'persistence_failed', 'input', 500 );
		}
		$id = (int) $saved_id;
		$saved = $this->read( $id );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		foreach ( array( 'title', 'status', 'destination', 'nofollow', 'groups' ) as $field ) {
			if ( $saved[ $field ] !== $values[ $field ] ) {
				return LinkInput::error( 'persistence_failed', $field, 500 );
			}
		}
		if ( $saved['slug'] !== $slug ) {
			return LinkInput::error( 'slug_conflict', 'slug' );
		}
		// Receipt and link commit together; a failed receipt cannot leave an untracked creation.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Raw insert avoids option-cache state escaping a rollback.
		$receipt_saved = $wpdb->insert( $wpdb->options, array( 'option_name' => $key, 'option_value' => wp_json_encode( array( 'fingerprint' => $fingerprint, 'saved' => $saved ) ), 'autoload' => 'no' ) );
		return false === $receipt_saved ? LinkInput::error( 'persistence_failed', 'request_key', 500 ) : $saved;
	}
}
