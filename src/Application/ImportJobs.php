<?php
/** Private, bounded import staging and resumable execution. @package CleanLinks */
namespace MG\CleanLinks\Application;

/** Stores bounded pages; never stages an uploaded file on disk. */
class ImportJobs {
	const PREFIX = 'cleanlinks_import_';
	const LIMIT = 10000;
	const PAGE = 50;
	const BYTES = 5242880;

	/** Private identifiers double as stable command-key suffixes. */
	public function key() {
		return time() . '_' . str_replace( '-', '', wp_generate_uuid4() );
	}

	/** Validate a CSV or pasted URL list without creating links. */
	public function parse( $text, $csv = false, $mapping = array() ) {
		if ( ! is_string( $text ) || strlen( $text ) > self::BYTES || ! preg_match( '//u', $text ) ) {
			return LinkInput::error( 'invalid_file', 'file', 400 );
		}
		if ( false !== strpos( $text, "\0" ) ) { return LinkInput::error( 'invalid_file', 'file', 400 ); }
		$text = preg_replace( '/^\xEF\xBB\xBF/', '', $text );
		$stream = fopen( 'php://temp', 'w+' );
		fwrite( $stream, $text );
		rewind( $stream );
		$headers = $csv ? fgetcsv( $stream, 0, ',', '"', '' ) : array( 'destination' );
		if ( ! is_array( $headers ) || ( $csv && ! $this->valid_csv_record( substr( $text, 0, ftell( $stream ) ) ) ) || count( $headers ) > 30 || count( array_unique( $headers ) ) !== count( $headers ) ) {
			fclose( $stream );
			return LinkInput::error( 'invalid_header', 'file', 400 );
		}
		if ( ! is_array( $mapping ) ) { fclose( $stream ); return LinkInput::error( 'invalid_mapping', 'file', 400 ); }
		$aliases = array( 'redirect to' => 'destination', 'url' => 'destination', 'redirect from' => 'slug', 'post_title' => 'title', 'post_name' => 'slug', 'post_status' => 'status' );
		$columns = array();
		foreach ( $headers as $index => $header ) {
			$name = strtolower( trim( $header ) );
			$name = isset( $aliases[ $name ] ) ? $aliases[ $name ] : $name;
			if ( in_array( $name, array( 'destination', 'title', 'slug', 'status', 'nofollow', 'groups' ), true ) ) {
				if ( isset( $columns[ $name ] ) && ! isset( $mapping[ $name ] ) ) { fclose( $stream ); return LinkInput::error( 'invalid_header', 'file', 400 ); }
				$columns[ $name ] = $index;
			}
		}
		if ( ! is_array( $mapping ) ) { fclose( $stream ); return LinkInput::error( 'invalid_mapping', 'file', 400 ); }
		foreach ( $mapping as $name => $index ) {
			if ( ! in_array( $name, array( 'destination', 'title', 'slug', 'status', 'nofollow', 'groups' ), true ) || ! is_scalar( $index ) ) {
				fclose( $stream );
				return LinkInput::error( 'invalid_mapping', 'file', 400 );
			}
			if ( '-1' === (string) $index ) {
				unset( $columns[ $name ] );
			} elseif ( ctype_digit( (string) $index ) && isset( $headers[ (int) $index ] ) ) {
				$columns[ $name ] = (int) $index;
			} else {
				fclose( $stream ); return LinkInput::error( 'invalid_mapping', 'file', 400 );
			}
		}
		if ( ! isset( $columns['destination'] ) ) {
			fclose( $stream );
			return LinkInput::error( 'required_destination_column', 'file', 400 );
		}
		$rows = array();
		$position = ftell( $stream );
		$seen = array();
		while ( false !== ( $values = $csv ? fgetcsv( $stream, 0, ',', '"', '' ) : fgets( $stream ) ) ) {
			$record = substr( $text, $position, ftell( $stream ) - $position );
			$position = ftell( $stream );
			if ( ! $csv ) {
				$values = array( trim( $values ) );
			}
			if ( array( null ) === $values || array( '' ) === $values ) {
				continue;
			}
			if ( count( $rows ) >= self::LIMIT ) {
				fclose( $stream );
				return LinkInput::error( 'row_limit', 'file', 400 );
			}
			$fields = array();
			foreach ( $columns as $name => $index ) {
				$fields[ $name ] = isset( $values[ $index ] ) ? $values[ $index ] : '';
			}
			$row = $this->validate_row( $fields );
			if ( $csv && ( count( $values ) !== count( $headers ) || ! $this->valid_csv_record( $record ) ) ) {
				$row['state'] = 'error';
				$row['error'] = 'column_count';
			}
			$slug = $row['input']['slug'];
			if ( 'ready' === $row['state'] && isset( $seen[ $slug ] ) ) {
				$row['state'] = 'skipped';
				$row['error'] = 'duplicate_slug';
				$row['selected'] = false;
			}
			if ( 'ready' === $row['state'] ) { $seen[ $slug ] = true; }
			$rows[] = $row;
		}
		fclose( $stream );
		global $wpdb;
		foreach ( array_chunk( array_keys( $seen ), self::PAGE ) as $slugs ) {
			$placeholders = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed SQL with one prepared placeholder per bounded slug.
			$existing = $wpdb->get_col( $wpdb->prepare( "SELECT post_name FROM {$wpdb->posts} WHERE post_type='cleanlinks' AND post_name IN ($placeholders)", $slugs ) );
			foreach ( $existing as $slug ) { $seen[ $slug ] = 'existing'; }
		}
		foreach ( $rows as &$row ) {
			if ( 'ready' === $row['state'] && isset( $seen[ $row['input']['slug'] ] ) && 'existing' === $seen[ $row['input']['slug'] ] ) { $row['state'] = 'skipped'; $row['error'] = 'slug_conflict'; $row['selected'] = false; }
		}
		unset( $row );
		return $rows ? $rows : LinkInput::error( 'empty_import', 'file', 400 );
	}

	/** Strict quote placement, including embedded CR/LF inside quoted fields. */
	private function valid_csv_record( $record ) {
		$state = 'start';
		for ( $i = 0; $i < strlen( $record ); $i++ ) {
			$char = $record[ $i ];
			if ( 'quoted' === $state ) {
				if ( '"' === $char ) {
					if ( isset( $record[ $i + 1 ] ) && '"' === $record[ $i + 1 ] ) { $i++; } else { $state = 'closed'; }
				}
				continue;
			}
			if ( ',' === $char ) { $state = 'start'; continue; }
			if ( "\r" === $char || "\n" === $char ) { return '' === trim( substr( $record, $i ), "\r\n" ); }
			if ( 'closed' === $state || ( 'unquoted' === $state && '"' === $char ) ) { return false; }
			$state = 'start' === $state && '"' === $char ? 'quoted' : 'unquoted';
		}
		return 'quoted' !== $state;
	}

	/** Revalidation is repeated by the command at execution time. */
	public function validate_row( $fields, $key = null ) {
		foreach ( $fields as $field => $value ) { if ( 'selected' !== $field && ! is_string( $value ) ) { return array( 'input' => array( 'destination' => '', 'title' => '', 'slug' => '', 'status' => 'draft', 'nofollow' => false, 'groups' => array(), 'request_key' => $key ? $key : $this->key() ), 'state' => 'error', 'error' => 'invalid_type', 'selected' => false ); } }
		$input = array( 'destination' => isset( $fields['destination'] ) ? trim( $fields['destination'] ) : '', 'title' => isset( $fields['title'] ) ? $fields['title'] : '', 'slug' => isset( $fields['slug'] ) ? $fields['slug'] : '', 'status' => ! empty( $fields['status'] ) ? $fields['status'] : 'draft', 'nofollow' => false, 'groups' => array(), 'request_key' => $key ? $key : $this->key() );
		if ( '' === $input['title'] ) {
			$input['title'] = (string) wp_parse_url( $input['destination'], PHP_URL_HOST ) . ' ' . (string) wp_parse_url( $input['destination'], PHP_URL_PATH );
		}
		// Export URLs may be used as slug proposals; IDs are never imported/overwritten.
		if ( false !== strpos( $input['slug'], '://' ) ) {
			$input['slug'] = basename( (string) wp_parse_url( $input['slug'], PHP_URL_PATH ) );
		}
		if ( '' === $input['slug'] ) {
			$input['slug'] = sanitize_title( $input['title'] );
		}
		$flag = isset( $fields['nofollow'] ) ? strtolower( trim( (string) $fields['nofollow'] ) ) : '';
		$input['nofollow'] = in_array( $flag, array( '1', 'true', 'yes' ), true );
		if ( ! empty( $fields['groups'] ) ) {
			$input['groups'] = array_map( 'intval', explode( '|', $fields['groups'] ) );
		}
		$result = ( new LinkInput() )->validate( $input );
		$error = is_wp_error( $result ) ? $result->get_error_code() : '';
		if ( ! $error ) { $input = $result; }
		if ( $input['groups'] && ! current_user_can( get_taxonomy( 'cleanlinks_groups' )->cap->assign_terms ) ) { $error = 'forbidden'; }
		if ( ! in_array( $flag, array( '', '0', 'false', 'no', '1', 'true', 'yes' ), true ) ) {
			$error = 'invalid_nofollow';
		}
		if ( ! $error && ( ! current_user_can( 'edit_posts' ) || ( in_array( $input['status'], array( 'publish', 'private' ), true ) && ! current_user_can( 'publish_posts' ) ) ) ) {
			$error = 'forbidden';
		}
		if ( isset( $fields['groups'] ) && '' !== $fields['groups'] && ! preg_match( '/^[1-9][0-9]*(?:\|[1-9][0-9]*)*$/D', $fields['groups'] ) ) { $error = 'invalid_groups'; }
		return array( 'input' => $input, 'state' => $error ? 'error' : 'ready', 'error' => $error, 'selected' => ! $error );
	}

	/** Persist private staging in pages of at most fifty rows. */
	public function create( $rows ) {
		global $wpdb;
		if ( ! is_array( $rows ) || ! $rows || count( $rows ) > self::LIMIT || ! current_user_can( 'edit_posts' ) ) { return LinkInput::error( 'row_limit', 'job', 400 ); }
		if ( ! CommandTransaction::has_request_local_cache() ) { return LinkInput::error( 'storage_unavailable', 'job', 503 ); }
		$transaction = new CommandTransaction();
		if ( ! $transaction->begin() ) { return LinkInput::error( 'storage_unavailable', 'job', 503 ); }
		$id = str_replace( '-', '', wp_generate_uuid4() );
		$job = array( 'owner' => get_current_user_id(), 'expires' => time() + DAY_IN_SECONDS, 'pages' => (int) ceil( count( $rows ) / self::PAGE ), 'total' => count( $rows ), 'next' => 0, 'state' => 'preview' );
		$success = false;
		try {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixed-length private job headers, not staging pages.
			$existing = $wpdb->get_col( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 101", $wpdb->esc_like( self::PREFIX ) . str_repeat( '_', 32 ) ) );
			$own = 0;
			foreach ( $existing as $value ) { $data = maybe_unserialize( $value ); if ( is_array( $data ) && (int) $data['owner'] === get_current_user_id() ) { $own++; } }
			if ( count( $existing ) >= 100 || $own >= 5 ) { return LinkInput::error( 'job_capacity', 'job', 409 ); }
			if ( ! add_option( self::PREFIX . $id, $job, '', false ) ) { return LinkInput::error( 'storage_unavailable', 'job', 503 ); }
			foreach ( array_chunk( $rows, self::PAGE ) as $page => $chunk ) {
				if ( ! add_option( self::PREFIX . $id . '_part_' . $page, $chunk, '', false ) ) { return LinkInput::error( 'storage_unavailable', 'job', 503 ); }
			}
			$success = true;
			return $id;
		} catch ( \Throwable $exception ) {
			return LinkInput::error( 'storage_unavailable', 'job', 503 );
		} finally {
			$finished = $transaction->finish( $success );
			$this->clear_cache( $id, $job );
			if ( ! $finished ) { return LinkInput::error( 'storage_unavailable', 'job', 503 ); }
		}
	}

	/** No other actor can view or control an import. */
	public function get( $id ) {
		if ( ! is_string( $id ) || ! preg_match( '/^[a-f0-9]{32}$/D', $id ) ) {
			return LinkInput::error( 'invalid_job', 'job', 404 );
		}
		$job = get_option( self::PREFIX . $id );
		return is_array( $job ) && (int) $job['owner'] === get_current_user_id() && $job['expires'] > time() ? $job : LinkInput::error( 'invalid_job', 'job', 404 );
	}

	/** Serialize a bounded batch and its progress journal in one transaction. */
	public function control( $id, $action, $page = 0, $changes = array() ) {
		$job = $this->get( $id );
		if ( ! in_array( $action, array( 'edit', 'start', 'cancel', 'resume', 'retry', 'step', 'discard' ), true ) ) { return LinkInput::error( 'invalid_job_action', 'job', 400 ); }
		if ( is_wp_error( $job ) ) { return $job; }
		if ( ! CommandTransaction::has_request_local_cache() ) { return LinkInput::error( 'storage_unavailable', 'job', 503 ); }
		$transaction = new CommandTransaction();
		if ( ! $transaction->begin() ) { return LinkInput::error( 'storage_unavailable', 'job', 503 ); }
		$success = false;
		$touched = array();
		$groups = array();
		wp_cache_delete( self::PREFIX . $id, 'options' );
		try {
			$job = $this->get( $id );
			if ( is_wp_error( $job ) ) {
				return $job;
			}
			if ( 'edit' === $action && 'preview' === $job['state'] && $page >= 0 && $page < $job['pages'] ) {
				$rows = get_option( self::PREFIX . $id . '_part_' . $page, array() );
				foreach ( $rows as $index => $row ) {
					if ( isset( $changes[ $index ] ) && is_array( $changes[ $index ] ) ) {
						$rows[ $index ] = $this->validate_row( $changes[ $index ], $row['input']['request_key'] );
						$rows[ $index ]['selected'] = isset( $changes[ $index ]['selected'] ) && 'ready' === $rows[ $index ]['state'];
					}
				}
				update_option( self::PREFIX . $id . '_part_' . $page, $rows, false );
				if ( get_option( self::PREFIX . $id . '_part_' . $page ) !== $rows ) { return LinkInput::error( 'storage_unavailable', 'job', 503 ); }
			} elseif ( 'start' === $action && 'preview' === $job['state'] ) {
				$job['state'] = 'running';
			} elseif ( 'discard' === $action && in_array( $job['state'], array( 'preview', 'done', 'cancelled' ), true ) ) {
				$this->remove( $id, $job );
				$success = true;
				return array( 'state' => 'removed' );
			} elseif ( 'cancel' === $action && 'running' === $job['state'] ) {
				$job['state'] = 'cancelled';
			} elseif ( 'resume' === $action && 'cancelled' === $job['state'] ) {
				$job['state'] = 'running';
			} elseif ( 'retry' === $action && 'done' === $job['state'] ) {
				for ( $part = 0; $part < $job['pages']; $part++ ) {
					$rows = get_option( self::PREFIX . $id . '_part_' . $part, array() );
					foreach ( $rows as &$row ) {
						if ( 'failed' === $row['state'] ) {
							$row['state'] = 'ready';
						}
					}
					unset( $row );
					update_option( self::PREFIX . $id . '_part_' . $part, $rows, false );
				if ( get_option( self::PREFIX . $id . '_part_' . $part ) !== $rows ) { return LinkInput::error( 'storage_unavailable', 'job', 503 ); }
				}
				$job['next'] = 0;
				$job['state'] = 'running';
			} elseif ( 'step' === $action && 'running' === $job['state'] ) {
				$part = $job['next'];
				$rows = get_option( self::PREFIX . $id . '_part_' . $part, array() );
				foreach ( $rows as &$row ) {
					if ( 'ready' !== $row['state'] ) {
						continue;
					}
					if ( ! $row['selected'] ) {
						$row['state'] = 'skipped';
						continue;
					}
					$result = ( new LinkCommands() )->execute( $row['input'] );
					$row['state'] = is_wp_error( $result ) ? ( 'slug_conflict' === $result->get_error_code() ? 'skipped' : 'failed' ) : 'created';
					$row['error'] = is_wp_error( $result ) ? $result->get_error_code() : '';
					if ( ! is_wp_error( $result ) ) {
						$row['saved'] = $result;
						$touched[] = $result['id'];
						$groups = array_merge( $groups, $row['input']['groups'] );
					}
				}
				unset( $row );
				update_option( self::PREFIX . $id . '_part_' . $part, $rows, false );
				if ( get_option( self::PREFIX . $id . '_part_' . $part ) !== $rows ) { return LinkInput::error( 'storage_unavailable', 'job', 503 ); }
				$job['next']++;
				if ( $job['next'] >= $job['pages'] ) {
					$job['state'] = 'done';
				}
			}
			update_option( self::PREFIX . $id, $job, false );
			$success = get_option( self::PREFIX . $id ) === $job;
			return $success ? $job : LinkInput::error( 'storage_unavailable', 'job', 503 );
		} catch ( \Throwable $exception ) {
			return LinkInput::error( 'storage_unavailable', 'job', 503 );
		} finally {
			$finished = $transaction->finish( $success );
			if ( ! $success || ! $finished ) {
				foreach ( $touched as $post_id ) { clean_post_cache( $post_id ); clean_object_term_cache( $post_id, 'cleanlinks' ); }
				clean_term_cache( array_unique( $groups ), 'cleanlinks_groups' );
			}
			$this->clear_cache( $id, $job );
			if ( ! $finished ) { return LinkInput::error( 'storage_unavailable', 'job', 503 ); }
		}
	}

	/** Bounded statistics over staging pages, not link-table scans. */
	public function counts( $id, $job ) {
		$counts = array_fill_keys( array( 'ready', 'error', 'skipped', 'failed', 'created' ), 0 );
		for ( $page = 0; $page < $job['pages']; $page++ ) {
			foreach ( get_option( self::PREFIX . $id . '_part_' . $page, array() ) as $row ) {
				$counts[ $row['state'] ]++;
			}
			wp_cache_delete( self::PREFIX . $id . '_part_' . $page, 'options' );
		}
		return $counts;
	}

	/** Remove only this private staging footprint. */
	public function remove( $id, $job ) {
		for ( $part = 0; $part < $job['pages']; $part++ ) {
			delete_option( self::PREFIX . $id . '_part_' . $part );
		}
		delete_option( self::PREFIX . $id . '_lock' );
		delete_option( self::PREFIX . $id );
	}

	/** Native cache entries must not survive a staging rollback. */
	private function clear_cache( $id, $job ) {
		wp_cache_delete( self::PREFIX . $id, 'options' );
		if ( is_array( $job ) ) { for ( $part = 0; $part < $job['pages']; $part++ ) { wp_cache_delete( self::PREFIX . $id . '_part_' . $part, 'options' ); } }
	}

	/** Cursor-based cleanup cannot starve behind live jobs or race batch writes. */
	public function prune() {
		global $wpdb;
		if ( ! CommandTransaction::has_request_local_cache() ) { return LinkInput::error( 'storage_unavailable', 'job', 503 ); }
		$transaction = new CommandTransaction();
		if ( ! $transaction->begin() ) { return; }
		$success = false;
		try {
			$cursor = (int) get_option( self::PREFIX . 'prune_cursor', 0 );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bounded cursor over precisely owned header names.
			$headers = $wpdb->get_results( $wpdb->prepare( "SELECT option_id,option_name,option_value FROM {$wpdb->options} WHERE option_name LIKE %s AND option_id>%d ORDER BY option_id LIMIT 200", $wpdb->esc_like( self::PREFIX ) . str_repeat( '_', 32 ), $cursor ) );
			foreach ( $headers as $header ) {
				$id = substr( $header->option_name, strlen( self::PREFIX ) );
				$job = maybe_unserialize( $header->option_value );
				if ( preg_match( '/^[a-f0-9]{32}$/D', $id ) && is_array( $job ) && $job['expires'] < time() ) { $this->remove( $id, $job ); }
				$cursor = (int) $header->option_id;
			}
			update_option( self::PREFIX . 'prune_cursor', count( $headers ) === 200 ? $cursor : 0, false );
			$success = true;
		} finally { $transaction->finish( $success ); }
	}

	/** Remove private staging only, called during plugin-file uninstall. */
	public function uninstall() {
		global $wpdb;
		wp_clear_scheduled_hook( 'cleanlinks_expire_imports' );
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Owned import prefix; bounded deletion.
			$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 200", $wpdb->esc_like( self::PREFIX ) . '%' ) );
			$removed = 0;
			foreach ( $names as $name ) { $removed += delete_option( $name ) ? 1 : 0; }
		} while ( count( $names ) === 200 && $removed > 0 && ! $wpdb->last_error );
	}
}
