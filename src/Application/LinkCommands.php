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
		if ( self::$running ) {
			return LinkInput::error( 'command_busy', 'input' );
		}
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
		self::$running        = true;
		$this->touched_groups = array();
		$id            = isset( $input['id'] ) ? $input['id'] : 0;
		$result        = null;
		try {
			$result = $this->persist( $input, $id );
		} catch ( \Throwable $exception ) {
			// Do not expose hook exceptions, SQL or rejected inputs in the response.
			$result = LinkInput::error( 'persistence_failed', 'input', 500 );
		} finally {
			$finished      = $transaction->finish( ! is_wp_error( $result ) );
			self::$running = false;
			clean_term_cache( $this->touched_groups, 'cleanlinks_groups' );
			if ( $id ) {
				// A rollback must not leave speculative post/meta/term values in cache.
				clean_post_cache( $id );
				clean_object_term_cache( $id, 'cleanlinks' );
			}
		}
		return $finished ? $result : LinkInput::error( 'commit_failed', 'input', 500 );
	}

	/**
	 * Return current editable state including a concurrency token.
	 *
	 * @param mixed $id Integer CPT ID.
	 * @return array|\WP_Error
	 */
	public function read( $id ) {
		if ( ! is_int( $id ) || $id < 1 || 'cleanlinks' !== get_post_type( $id ) ) {
			return LinkInput::error( 'invalid_id', 'id', 404 );
		}
		if ( ! current_user_can( 'edit_post', $id ) ) {
			return LinkInput::error( 'forbidden', 'id', 403 );
		}
		clean_post_cache( $id );
		$post   = get_post( $id );
		$groups = wp_get_object_terms( $id, 'cleanlinks_groups', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $groups ) ) {
			return LinkInput::error( 'storage_unavailable', 'groups', 503 );
		}
		$groups = array_map( 'intval', $groups );
		sort( $groups );
		$state = array(
			'id'          => $id,
			'title'       => $post->post_title,
			'slug'        => $post->post_name,
			'status'      => $post->post_status,
			'destination' => get_post_meta( $id, 'cleanlink_redirect_url', true ),
			'nofollow'    => '1' === get_post_meta( $id, 'cleanlink_redirect_nofollow', true ),
			'groups'      => $groups,
		);
		// Lifetime clicks are deliberately excluded; a click must not invalidate edits.
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
		$key         = 'cleanlinks_command_' . get_current_user_id() . '_' . hash( 'sha256', $input['request_key'] );
		$fingerprint = hash( 'sha256', wp_json_encode( $input ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Durable receipts must bypass speculative option caches.
		$stored = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) );
		if ( $wpdb->last_error ) {
			return LinkInput::error( 'storage_unavailable', 'request_key', 503 );
		}
		if ( null !== $stored ) {
			$receipt = json_decode( $stored, true );
			if ( ! is_array( $receipt ) || ! isset( $receipt['fingerprint'], $receipt['saved'] ) || ! hash_equals( $receipt['fingerprint'], $fingerprint ) ) {
				return LinkInput::error( 'request_key_conflict', 'request_key' );
			}
			if ( 'cleanlinks' !== get_post_type( $receipt['saved']['id'] ) || ! current_user_can( 'edit_post', $receipt['saved']['id'] ) ) {
				return LinkInput::error( 'replay_unavailable', 'id', 403 );
			}
			return $receipt['saved'];
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
