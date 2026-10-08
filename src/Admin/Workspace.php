<?php
/** WordPress-native link management without changing CPT storage. @package CleanLinks */
namespace MG\CleanLinks\Admin;

use MG\CleanLinks\Application\LinkCommands;
use MG\CleanLinks\Application\LinkInput;
use MG\CleanLinks\Application\CommandTransaction;
use MG\CleanLinks\Application\ImportJobs;

/** Server-rendered forms remain functional without JavaScript. */
class Workspace {
	const SLUG = 'cleanlinks';

	/** Register navigation, controls and private maintenance. */
	public function register_hooks() {
		add_action( 'admin_init', array( $this, 'legacy_entry' ) );
		add_filter( 'admin_url', array( $this, 'classic_url' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_cleanlinks_workspace', array( $this, 'handle' ) );
		add_action( 'wp_ajax_cleanlinks_import_step', array( $this, 'ajax_step' ) );
		add_action( 'cleanlinks_expire_imports', array( new ImportJobs(), 'prune' ) );
		if ( ! wp_next_scheduled( 'cleanlinks_expire_imports' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'cleanlinks_expire_imports' );
		}
	}

	/** Keep an explicit legacy fallback rather than disabling the CPT editor. */
	public function menu() {
		remove_menu_page( 'edit.php?post_type=cleanlinks' );
		add_menu_page( __( 'CleanLinks', 'cleanlinks' ), __( 'CleanLinks', 'cleanlinks' ), 'edit_posts', self::SLUG, array( $this, 'render' ), 'dashicons-admin-links' );
		add_submenu_page( self::SLUG, __( 'All links', 'cleanlinks' ), __( 'All links', 'cleanlinks' ), 'edit_posts', self::SLUG, array( $this, 'render' ) );
		add_submenu_page( self::SLUG, __( 'Add link', 'cleanlinks' ), __( 'Add link', 'cleanlinks' ), 'edit_posts', 'cleanlinks-add', array( $this, 'render' ) );
		add_submenu_page( self::SLUG, __( 'Bulk add', 'cleanlinks' ), __( 'Bulk add', 'cleanlinks' ), 'edit_posts', 'cleanlinks-bulk', array( $this, 'render' ) );
		add_submenu_page( self::SLUG, __( 'Groups', 'cleanlinks' ), __( 'Groups', 'cleanlinks' ), 'manage_categories', 'edit-tags.php?taxonomy=cleanlinks_groups&post_type=cleanlinks' );
		add_submenu_page( self::SLUG, __( 'Export', 'cleanlinks' ), __( 'Export', 'cleanlinks' ), 'manage_options', 'cleanlinks-export', array( $this, 'export' ) );
		add_submenu_page( self::SLUG, __( 'Classic editor', 'cleanlinks' ), __( 'Classic editor', 'cleanlinks' ), 'edit_posts', 'edit.php?post_type=cleanlinks&cleanlinks_classic=1' );
	}

	/** Preserve explicit classic mode on the core list/editor navigation. */
	public function classic_url( $url, $path ) {
		if ( isset( $_GET['cleanlinks_classic'] ) && ( 0 === strpos( $path, 'post-new.php?post_type=cleanlinks' ) || 0 === strpos( $path, 'post.php?post=' ) || 0 === strpos( $path, 'edit.php?post_type=cleanlinks' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return add_query_arg( 'cleanlinks_classic', '1', $url );
		}
		return $url;
	}

	/** Legacy bookmarks enter the workspace; explicit fallback bypasses routing. */
	public function legacy_entry() {
		global $pagenow;
		if ( 'GET' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) || isset( $_GET['cleanlinks_classic'] ) || ! current_user_can( 'edit_posts' ) || ! CommandTransaction::has_request_local_cache() ) { return; } // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_type = isset( $_GET['post_type'] ) && is_string( $_GET['post_type'] ) ? $_GET['post_type'] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'cleanlinks' === $post_type && in_array( $pagenow, array( 'edit.php', 'post-new.php' ), true ) && ! isset( $_GET['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( self::url( array( 'page' => 'post-new.php' === $pagenow ? 'cleanlinks-add' : self::SLUG ) ) ); exit;
		}
	}

	/** Scoped assets, with the normal WordPress focus and color defaults. */
	public function assets() {
		$page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $page, array( self::SLUG, 'cleanlinks-add', 'cleanlinks-bulk', 'cleanlinks-export' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'cleanlinks-workspace', CLEANLINKS_PLUGIN_URL . 'dist/workspace.css', array(), CLEANLINKS_VERSION );
		wp_enqueue_script( 'cleanlinks-workspace', CLEANLINKS_PLUGIN_URL . 'dist/workspace.js', array(), CLEANLINKS_VERSION, true );
		wp_localize_script( 'cleanlinks-workspace', 'cleanlinksWorkspace', array( 'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'cleanlinks_workspace' ), 'copied' => __( 'Copied', 'cleanlinks' ), 'copyFailed' => __( 'Could not copy. Select the URL and copy it manually.', 'cleanlinks' ), 'ignore' => __( 'Ignore / default', 'cleanlinks' ), 'progress' => __( '%1$s / %2$s batches; %3$s created', 'cleanlinks' ), 'failed' => __( 'Request interrupted. Resume safely; saved links will not be created again.', 'cleanlinks' ) ) );
	}

	/** Stable custom record URLs. */
	public static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	/** Shared form nonce and dispatch fields. */
	private function fields( $operation ) {
		echo '<input type="hidden" name="action" value="cleanlinks_workspace"><input type="hidden" name="operation" value="' . esc_attr( $operation ) . '">';
		wp_nonce_field( 'cleanlinks_workspace' );
	}

	/** Explicit error explanations rather than exposing rejected values. */
	public static function error_text( $code ) {
		$messages = array(
			'forbidden' => __( 'You do not have permission to make this change. Choose a permitted status or contact a site administrator.', 'cleanlinks' ),
			'invalid_file' => __( 'Use a UTF-8 CSV file up to 5 MB. Reselect uploaded files after an error.', 'cleanlinks' ),
			'invalid_header' => __( 'CSV headers must be distinct and unambiguous. Map duplicated destination columns explicitly.', 'cleanlinks' ),
			'invalid_mapping' => __( 'Choose valid CSV columns and map the destination field before previewing.', 'cleanlinks' ),
			'column_count' => __( 'This row has malformed CSV quotes or a different number of columns than the header.', 'cleanlinks' ),
			'invalid_slug' => __( 'Use a valid WordPress slug up to 200 characters. Try lowercase words separated by hyphens.', 'cleanlinks' ),
			'invalid_groups' => __( 'Choose existing groups. CSV group IDs must be positive numbers separated by |.', 'cleanlinks' ),
			'invalid_nofollow' => __( 'Use true or false for the nofollow value.', 'cleanlinks' ),
			'invalid_status' => __( 'Choose Draft, Pending, Published or Private.', 'cleanlinks' ),
			'confirmation_required' => __( 'Confirm the selected action before continuing.', 'cleanlinks' ),
			'expired_request_key' => __( 'This save request expired. Review the existing links before starting another save.', 'cleanlinks' ),
			'request_key_conflict' => __( 'This request was already saved with different values. Review the existing links before saving again.', 'cleanlinks' ),
			'unsafe_destination' => __( 'Enter a valid HTTP or HTTPS destination. Unsafe destinations are blocked by WordPress URL validation.', 'cleanlinks' ),
			'slug_conflict' => __( 'This slug already exists. Choose a different slug; the existing link has not been overwritten.', 'cleanlinks' ),
			'duplicate_slug' => __( 'Another preview row uses this slug. Change it or leave this row skipped.', 'cleanlinks' ),
			'stale_version' => __( 'This link changed since you opened it. Reload the current values before saving again.', 'cleanlinks' ),
			'slug_confirmation_required' => __( 'Confirm the slug change. Existing bookmarks to the old URL may stop working.', 'cleanlinks' ),
			'storage_unavailable' => __( 'This workspace needs transactional storage and the native WordPress request-local cache. Persistent or custom object caches are not supported. Use the classic editor; its saves do not provide atomic rollback.', 'cleanlinks' ),
			'invalid_session' => __( 'Your session expired. Sign in and reload before trying again.', 'cleanlinks' ),
			'job_capacity' => __( 'Private import storage is full (5 jobs per account, 100 per site). Existing jobs expire after 24 hours; remove a finished job or try again after cleanup.', 'cleanlinks' ),
			'job_busy' => __( 'An import request is already running. Wait a moment, then refresh or resume.', 'cleanlinks' ),
			'invalid_job' => __( 'This private import is unavailable or expired. Imports are retained for 24 hours.', 'cleanlinks' ),
			'required_destination_column' => __( 'Map a destination column, or use a CSV header named destination.', 'cleanlinks' ),
			'row_limit' => __( 'Use at most 10,000 rows per import.', 'cleanlinks' ),
		);
		return isset( $messages[ $code ] ) ? $messages[ $code ] : sprintf( __( 'Check this value and try again (%s).', 'cleanlinks' ), $code );
	}

	/** Root renderer is protected even for direct admin URLs. */
	public function render() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You cannot manage links.', 'cleanlinks' ), '', array( 'response' => 403 ) );
		}
		$page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : self::SLUG; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$view = isset( $_GET['view'] ) && is_string( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="wrap cl-workspace"><header class="cl-header"><div><p class="cl-eyebrow">CleanLinks</p><h1>' . esc_html__( 'Your links, under control.', 'cleanlinks' ) . '</h1><p>' . esc_html__( 'Branded short links. Clear destinations. Lifetime click counts.', 'cleanlinks' ) . '</p></div><div class="cl-header-actions"><a class="button" href="' . esc_url( self::url( array( 'page' => 'cleanlinks-bulk' ) ) ) . '">' . esc_html__( 'Bulk add', 'cleanlinks' ) . '</a> <a class="button button-primary" href="' . esc_url( self::url( array( 'page' => 'cleanlinks-add' ) ) ) . '">' . esc_html__( 'Add link', 'cleanlinks' ) . '</a></div></header>';
		echo '<nav class="cl-nav" aria-label="' . esc_attr__( 'Link workspace', 'cleanlinks' ) . '"><a href="' . esc_url( self::url() ) . '">' . esc_html__( 'All links', 'cleanlinks' ) . '</a><a href="' . esc_url( self::url( array( 'page' => 'cleanlinks-add' ) ) ) . '">' . esc_html__( 'Add link', 'cleanlinks' ) . '</a><a href="' . esc_url( self::url( array( 'page' => 'cleanlinks-bulk' ) ) ) . '">' . esc_html__( 'Bulk add', 'cleanlinks' ) . '</a></nav>';
		$this->notice();
		if ( ! CommandTransaction::has_request_local_cache() ) {
			echo '<div class="notice notice-warning"><p>' . esc_html( self::error_text( 'storage_unavailable' ) ) . ' <a href="' . esc_url( admin_url( 'edit.php?post_type=cleanlinks&cleanlinks_classic=1' ) ) . '">' . esc_html__( 'Open classic editor', 'cleanlinks' ) . '</a></p></div>';
		}
		if ( 'cleanlinks-bulk' === $page ) {
			$this->bulk();
		} elseif ( 'cleanlinks-add' === $page || 'edit' === $view ) {
			$this->editor();
		} else {
			$this->listing();
		}
		echo '</div>';
	}

	/** Notices carry no raw destination values in URLs. */
	private function notice() {
		$token = isset( $_GET['notice'] ) && is_string( $_GET['notice'] ) ? sanitize_key( $_GET['notice'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$data = $token ? get_transient( 'cl_notice_' . get_current_user_id() . '_' . $token ) : false;
		if ( is_array( $data ) ) {
			echo '<div class="notice ' . ( empty( $data['error'] ) ? 'notice-success' : 'notice-error' ) . '" role="' . ( empty( $data['error'] ) ? 'status' : 'alert' ) . '" tabindex="-1" id="cl-error-summary" data-cl-notice><p>' . esc_html( $data['message'] ) . '</p></div>';
		}
	}

	/** Paginated, capability-scoped real CPT data. */
	private function listing() {
		$search = isset( $_GET['s'] ) && is_string( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['status'] ) && is_string( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$group = isset( $_GET['group'] ) ? absint( $_GET['group'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$statuses = array( 'publish', 'draft', 'pending', 'private', 'trash' );
		$args = array( 'post_type' => 'cleanlinks', 'post_status' => in_array( $status, $statuses, true ) ? $status : array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => 20, 'paged' => $paged, 's' => $search, 'orderby' => 'ID', 'order' => 'DESC' );
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			$args['author'] = get_current_user_id();
		}
		if ( $group ) {
			$args['tax_query'] = array( array( 'taxonomy' => 'cleanlinks_groups', 'field' => 'term_id', 'terms' => $group ) );
		}
		$query = new \WP_Query( $args );
		echo '<section class="cl-panel"><h2>' . esc_html__( 'All links', 'cleanlinks' ) . '</h2><form method="get" class="cl-filter"><input type="hidden" name="page" value="cleanlinks"><label>' . esc_html__( 'Search links', 'cleanlinks' ) . '<input type="search" name="s" value="' . esc_attr( $search ) . '"></label><label>' . esc_html__( 'Status', 'cleanlinks' ) . '<select name="status"><option value="">' . esc_html__( 'All active statuses', 'cleanlinks' ) . '</option>';
		foreach ( $statuses as $item ) {
			echo '<option value="' . esc_attr( $item ) . '" ' . selected( $status, $item, false ) . '>' . esc_html( ucfirst( $item ) ) . '</option>';
		}
		echo '</select></label><label>' . esc_html__( 'Group', 'cleanlinks' );
		wp_dropdown_categories( array( 'taxonomy' => 'cleanlinks_groups', 'name' => 'group', 'hide_empty' => false, 'show_option_all' => __( 'All groups', 'cleanlinks' ), 'selected' => $group ) );
		echo '</label><button class="button">' . esc_html__( 'Filter', 'cleanlinks' ) . '</button><a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Reset', 'cleanlinks' ) . '</a></form>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$this->fields( 'selection' );
		echo '<input type="hidden" name="return" value="' . esc_attr( self::url( array( 's' => $search, 'status' => $status, 'group' => $group, 'paged' => $paged ) ) ) . '"><div class="cl-selection"><label>' . esc_html__( 'Selected links', 'cleanlinks' ) . '<select name="selection_action"><option value="trash">' . esc_html__( 'Move to trash', 'cleanlinks' ) . '</option><option value="restore">' . esc_html__( 'Restore from trash', 'cleanlinks' ) . '</option></select></label><label><input type="checkbox" name="confirmed" value="1" required> ' . esc_html__( 'Confirm this action on selected links', 'cleanlinks' ) . '</label><button class="button">' . esc_html__( 'Apply', 'cleanlinks' ) . '</button></div>';
		echo '<table class="cl-table"><thead><tr><th><input type="checkbox" data-cl-select-all aria-label="' . esc_attr__( 'Select all links on this page', 'cleanlinks' ) . '"></th><th>' . esc_html__( 'Link', 'cleanlinks' ) . '</th><th>' . esc_html__( 'Destination', 'cleanlinks' ) . '</th><th>' . esc_html__( 'Status / groups', 'cleanlinks' ) . '</th><th>' . esc_html__( 'Lifetime clicks', 'cleanlinks' ) . '</th></tr></thead><tbody>';
		foreach ( $query->posts as $post ) {
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}
			$url = get_permalink( $post->ID );
			$edit = self::url( array( 'view' => 'edit', 'id' => $post->ID, 's' => $search, 'status' => $status, 'group' => $group, 'paged' => $paged ) );
			$terms = wp_get_object_terms( $post->ID, 'cleanlinks_groups', array( 'fields' => 'names' ) );
			echo '<tr><td><input type="checkbox" name="ids[]" value="' . esc_attr( $post->ID ) . '" aria-label="' . esc_attr( sprintf( __( 'Select %s', 'cleanlinks' ), $post->post_title ) ) . '"></td><td data-label="' . esc_attr__( 'Link', 'cleanlinks' ) . '"><a class="cl-link-title" href="' . esc_url( $edit ) . '">' . esc_html( $post->post_title ? $post->post_title : $post->post_name ) . '</a><span class="cl-short-url">' . esc_html( $url ) . '</span><button type="button" class="button-link cl-copy" data-cl-copy="' . esc_attr( $url ) . '">' . esc_html__( 'Copy URL', 'cleanlinks' ) . '</button></td><td data-label="' . esc_attr__( 'Destination', 'cleanlinks' ) . '"><span class="cl-destination">' . esc_html( get_post_meta( $post->ID, 'cleanlink_redirect_url', true ) ) . '</span></td><td data-label="' . esc_attr__( 'Status / groups', 'cleanlinks' ) . '"><span class="cl-chip">' . esc_html( ucfirst( $post->post_status ) ) . '</span><span class="cl-groups">' . esc_html( is_wp_error( $terms ) ? '' : implode( ', ', $terms ) ) . '</span></td><td data-label="' . esc_attr__( 'Lifetime clicks', 'cleanlinks' ) . '">' . esc_html( number_format_i18n( (int) get_post_meta( $post->ID, 'cleanlink_redirect_count', true ) ) ) . '</td></tr>';
		}
		if ( ! $query->posts ) {
			echo '<tr><td colspan="5"><div class="cl-empty"><h3>' . esc_html__( 'No links to show', 'cleanlinks' ) . '</h3><p>' . esc_html__( 'Try another filter, or add your first branded link.', 'cleanlinks' ) . '</p></div></td></tr>';
		}
		echo '</tbody></table></form><div class="cl-pagination">' . esc_html( sprintf( __( '%s links', 'cleanlinks' ), number_format_i18n( $query->found_posts ) ) ) . ' ';
		echo wp_kses_post( (string) paginate_links( array( 'base' => add_query_arg( 'paged', '%#%', self::url( array( 's' => $search, 'status' => $status, 'group' => $group ) ) ), 'current' => $paged, 'total' => $query->max_num_pages ) ) );
		echo '</div></section><p id="cl-live" role="status" aria-live="polite"></p>';
	}

	/** Standalone add/edit screen retains an uncertain request's exact key and input. */
	private function editor() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$input = array( 'destination' => '', 'title' => '', 'slug' => '', 'status' => 'draft', 'nofollow' => false, 'groups' => array(), 'request_key' => ( new ImportJobs() )->key() );
		if ( $id ) {
			$state = ( new LinkCommands() )->read( $id );
			if ( is_wp_error( $state ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( self::error_text( $state->get_error_code() ) ) . '</p><a class="button" href="' . esc_url( admin_url( 'post.php?post=' . $id . '&action=edit&cleanlinks_classic=1' ) ) . '">' . esc_html__( 'Open classic editor', 'cleanlinks' ) . '</a></div>';
				return;
			}
			$input = array_merge( $input, $state, array( 'expected_version' => $state['version'] ) );
		}
		$token = isset( $_GET['notice'] ) && is_string( $_GET['notice'] ) ? sanitize_key( $_GET['notice'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice = $token ? get_transient( 'cl_notice_' . get_current_user_id() . '_' . $token ) : false;
		if ( is_array( $notice ) && ! empty( $notice['input'] ) && (int) $notice['id'] === $id ) {
			$input = array_merge( $input, $notice['input'] );
			echo '<p><a class="button" href="' . esc_url( remove_query_arg( 'notice' ) ) . '">' . esc_html__( 'Reload current saved values', 'cleanlinks' ) . '</a></p>';
		}
		$back = self::url( array( 's' => isset( $_GET['s'] ) && is_string( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '', 'status' => isset( $_GET['status'] ) && is_string( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '', 'group' => isset( $_GET['group'] ) ? absint( $_GET['group'] ) : 0, 'paged' => isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<section class="cl-panel cl-editor"><a href="' . esc_url( $back ) . '">← ' . esc_html__( 'Back to links', 'cleanlinks' ) . '</a><h2>' . esc_html( $id ? __( 'Edit link', 'cleanlinks' ) : __( 'Add a link', 'cleanlinks' ) ) . '</h2><p>' . esc_html__( 'Choose a destination and a memorable short URL. Draft links do not redirect publicly.', 'cleanlinks' ) . '</p><form data-cl-editor data-cl-new="' . ( $id ? '0' : '1' ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$this->fields( 'save' );
		echo '<input type="hidden" name="id" value="' . esc_attr( $id ) . '"><input type="hidden" name="request_key" value="' . esc_attr( $input['request_key'] ) . '"><input type="hidden" name="expected_version" value="' . esc_attr( isset( $input['expected_version'] ) ? $input['expected_version'] : '' ) . '"><input type="hidden" name="return" value="' . esc_attr( add_query_arg( array( 'view' => 'edit', 'id' => $id ), $back ) ) . '">';
		foreach ( array( 'destination' => __( 'Destination URL', 'cleanlinks' ), 'title' => __( 'Link title', 'cleanlinks' ), 'slug' => __( 'Short URL slug', 'cleanlinks' ) ) as $name => $label ) {
			echo '<div class="cl-field"><label for="cl-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label><input id="cl-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" type="' . ( 'destination' === $name ? 'url' : 'text' ) . '" value="' . esc_attr( $input[ $name ] ) . '" ' . ( 'destination' === $name ? 'required' : '' ) . ( is_array( $notice ) && ! empty( $notice['error'] ) && isset( $notice['field'] ) && $notice['field'] === $name ? ' aria-invalid="true"' : '' ) . ' aria-describedby="cl-' . esc_attr( $name ) . '-hint' . ( is_array( $notice ) && ! empty( $notice['error'] ) && isset( $notice['field'] ) && $notice['field'] === $name ? ' cl-error-summary' : '' ) . '"><small id="cl-' . esc_attr( $name ) . '-hint">' . esc_html( 'destination' === $name ? __( 'Public http:// or https:// URL. Query parameters are preserved.', 'cleanlinks' ) : ( 'slug' === $name ? __( 'Leave blank to generate from the title. Changing a saved slug changes its public URL.', 'cleanlinks' ) : __( 'A name that helps you recognize this link.', 'cleanlinks' ) ) ) . '</small></div>';
		}
		if ( ! $id ) {
			global $wp_rewrite;
			$structure = $wp_rewrite->get_extra_permastruct( 'cleanlinks' );
			$preview = $structure ? home_url( user_trailingslashit( str_replace( '%cleanlinks%', 'cl-slug-placeholder', $structure ) ) ) : add_query_arg( 'cleanlinks', 'cl-slug-placeholder', home_url( '/' ) );
			echo '<p><strong>' . esc_html__( 'URL preview (confirmed when saved):', 'cleanlinks' ) . '</strong> <span data-cl-preview-pattern="' . esc_attr( $preview ) . '" id="cl-url-preview" role="status">' . esc_html__( 'Enter a destination or slug to preview.', 'cleanlinks' ) . '</span></p>';
		}
		echo '<div class="cl-field"><label for="cl-status">' . esc_html__( 'Publication status', 'cleanlinks' ) . '</label><select id="cl-status" name="link_status">';
		foreach ( array( 'draft', 'pending', 'publish', 'private' ) as $status ) {
			if ( in_array( $status, array( 'publish', 'private' ), true ) && ! current_user_can( 'publish_posts' ) ) {
				continue;
			}
			echo '<option value="' . esc_attr( $status ) . '" ' . selected( $input['status'], $status, false ) . '>' . esc_html( ucfirst( $status ) ) . '</option>';
		}
		echo '</select></div><fieldset class="cl-field"><legend>' . esc_html__( 'Existing groups', 'cleanlinks' ) . '</legend>';
		$terms = get_terms( array( 'taxonomy' => 'cleanlinks_groups', 'hide_empty' => false ) );
		foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
			echo '<label class="cl-check"><input type="checkbox" name="groups[]" value="' . esc_attr( $term->term_id ) . '" ' . checked( in_array( (int) $term->term_id, $input['groups'], true ), true, false ) . '> ' . esc_html( $term->name ) . '</label>';
		}
		if ( ! $terms ) {
			echo '<p>' . esc_html__( 'No groups yet. Manage groups from the CleanLinks menu.', 'cleanlinks' ) . '</p>';
		}
		echo '</fieldset><label class="cl-check"><input type="checkbox" name="nofollow" value="1" ' . checked( $input['nofollow'], true, false ) . '> ' . esc_html__( 'Mark this redirect as nofollow', 'cleanlinks' ) . '</label>';
		if ( $id ) {
			echo '<label class="cl-check"><input type="checkbox" name="confirm_slug_change" value="1"> ' . esc_html__( 'I understand that changing the slug changes the public URL.', 'cleanlinks' ) . '</label><p>' . esc_html__( 'Current short URL:', 'cleanlinks' ) . ' ' . esc_html( get_permalink( $id ) ) . ' <button type="button" class="button-link" data-cl-copy="' . esc_attr( get_permalink( $id ) ) . '">' . esc_html__( 'Copy URL', 'cleanlinks' ) . '</button></p><p>' . esc_html( sprintf( __( 'Lifetime clicks: %s. Only published links redirect publicly.', 'cleanlinks' ), number_format_i18n( (int) get_post_meta( $id, 'cleanlink_redirect_count', true ) ) ) ) . '</p><p id="cl-live" role="status" aria-live="polite"></p>';
		}
		echo '<div class="cl-form-actions"><button class="button button-primary" ' . disabled( CommandTransaction::has_request_local_cache(), false, false ) . '>' . esc_html__( 'Save link', 'cleanlinks' ) . '</button><a href="' . esc_url( $back ) . '">' . esc_html__( 'Cancel', 'cleanlinks' ) . '</a></div></form></section>';
	}

	/** Bounded private preview/results and explicit publication confirmation. */
	private function bulk() {
		$id = isset( $_GET['job'] ) && is_string( $_GET['job'] ) ? sanitize_key( $_GET['job'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$jobs = new ImportJobs();
		if ( ! $id ) {
			$token = isset( $_GET['notice'] ) && is_string( $_GET['notice'] ) ? sanitize_key( $_GET['notice'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$notice = $token ? get_transient( 'cl_notice_' . get_current_user_id() . '_' . $token ) : false;
			$urls = is_array( $notice ) && isset( $notice['urls'] ) ? $notice['urls'] : '';
			echo '<section class="cl-panel cl-editor"><h2>' . esc_html__( 'Bulk add links', 'cleanlinks' ) . '</h2><p>' . esc_html__( '1. Add URLs or CSV · 2. Review and correct · 3. Create selected links', 'cleanlinks' ) . '</p><p>' . esc_html__( 'Up to 10,000 rows / 5 MB. Draft by default. Existing slugs are skipped, never overwritten. Duplicate destinations are allowed with different slugs.', 'cleanlinks' ) . '</p><form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			$this->fields( 'preview' );
			echo '<label class="cl-field">' . esc_html__( 'Paste URLs, one per line', 'cleanlinks' ) . '<textarea name="urls" rows="7" placeholder="https://example.org/your-page">' . esc_textarea( $urls ) . '</textarea></label><p>' . esc_html__( 'Or upload a UTF-8 CSV. Quoted commas and newlines are supported.', 'cleanlinks' ) . '</p><label class="cl-field">' . esc_html__( 'CSV file', 'cleanlinks' ) . '<input type="file" name="csv" accept=".csv,text/csv" data-cl-csv></label><p>' . esc_html__( 'Columns: destination (required), title, slug, status, nofollow (true/false), groups (existing IDs separated by |). Export columns Title, Redirect From and Redirect To are recognized; IDs are ignored.', 'cleanlinks' ) . '</p><details><summary>' . esc_html__( 'Map CSV columns', 'cleanlinks' ) . '</summary><div class="cl-mapping">';
			foreach ( array( 'destination', 'title', 'slug', 'status', 'nofollow', 'groups' ) as $field ) {
				echo '<label>' . esc_html( ucfirst( $field ) ) . '<select name="mapping[' . esc_attr( $field ) . ']" data-cl-map="' . esc_attr( $field ) . '" disabled><option value="">' . esc_html__( 'Detect automatically', 'cleanlinks' ) . '</option></select></label>';
			}
			echo '</div></details><div class="cl-form-actions"><button class="button button-primary">' . esc_html__( 'Preview without creating links', 'cleanlinks' ) . '</button></div></form><pre class="cl-template">destination,title,slug,status,nofollow,groups&#10;https://example.org/page,Example,example,draft,false,</pre><p>' . esc_html__( 'Preview and results are private to your account and expire after 24 hours. Destinations are not fetched.', 'cleanlinks' ) . '</p></section>';
			return;
		}
		$job = $jobs->get( $id );
		if ( is_wp_error( $job ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( self::error_text( $job->get_error_code() ) ) . '</p></div>';
			return;
		}
		$page = min( $job['pages'] - 1, max( 0, isset( $_GET['part'] ) ? absint( $_GET['part'] ) : 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rows = get_option( ImportJobs::PREFIX . $id . '_part_' . $page, array() );
		$counts = $jobs->counts( $id, $job );
		echo '<section class="cl-panel"><h2>' . esc_html( 'preview' === $job['state'] ? __( 'Review your import', 'cleanlinks' ) : __( 'Import progress and results', 'cleanlinks' ) ) . '</h2><p class="cl-import-summary" role="status">' . esc_html( sprintf( __( '%1$d created · %2$d skipped · %3$d failed · %4$d invalid · %5$d ready', 'cleanlinks' ), $counts['created'], $counts['skipped'], $counts['failed'], $counts['error'], $counts['ready'] ) ) . '</p><p>' . esc_html__( 'Save corrections and selection on each page before starting. Selected ready rows across all pages will be created. Each batch contains at most 50 links. Cancellation stops future batches; already-created links stay. Resume or retry keeps the original request keys.', 'cleanlinks' ) . '</p>';
		if ( 'preview' === $job['state'] ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			$this->fields( 'job' );
			echo '<input type="hidden" name="job" value="' . esc_attr( $id ) . '"><input type="hidden" name="job_action" value="edit"><input type="hidden" name="part" value="' . esc_attr( $page ) . '">';
		}
		echo '<div class="cl-preview-scroll"><table class="cl-table"><thead><tr><th>' . esc_html__( 'Row', 'cleanlinks' ) . '</th><th>' . esc_html__( 'Destination / title', 'cleanlinks' ) . '</th><th>' . esc_html__( 'Slug / publication', 'cleanlinks' ) . '</th><th>' . esc_html__( 'Result', 'cleanlinks' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $index => $row ) {
			$number = $page * ImportJobs::PAGE + $index + 1;
			echo '<tr><td>' . esc_html( $number );
			if ( 'preview' === $job['state'] ) {
				echo '<label><input type="checkbox" name="rows[' . esc_attr( $index ) . '][selected]" value="1" ' . checked( $row['selected'], true, false ) . '> <span class="screen-reader-text">' . esc_html( sprintf( __( 'Include row %d', 'cleanlinks' ), $number ) ) . '</span></label>';
			}
			echo '</td><td data-label="' . esc_attr__( 'Destination / title', 'cleanlinks' ) . '">';
			foreach ( array( 'destination', 'title' ) as $field ) {
				if ( 'preview' === $job['state'] ) {
					echo '<label class="cl-field"><span class="screen-reader-text">' . esc_html( $field . ' ' . $number ) . '</span><input name="rows[' . esc_attr( $index ) . '][' . esc_attr( $field ) . ']" value="' . esc_attr( $row['input'][ $field ] ) . '"></label>';
				} else {
					echo '<span class="cl-destination">' . esc_html( $row['input'][ $field ] ) . '</span>';
				}
			}
			echo '</td><td data-label="' . esc_attr__( 'Slug / publication', 'cleanlinks' ) . '">';
			if ( 'preview' === $job['state'] ) {
				echo '<label class="cl-field"><span class="screen-reader-text">' . esc_html( 'Slug ' . $number ) . '</span><input name="rows[' . esc_attr( $index ) . '][slug]" value="' . esc_attr( $row['input']['slug'] ) . '"></label><label class="cl-field"><span class="screen-reader-text">' . esc_html( 'Status ' . $number ) . '</span><select name="rows[' . esc_attr( $index ) . '][status]">';
				foreach ( array( 'draft', 'pending', 'publish', 'private' ) as $status ) {
					echo '<option value="' . esc_attr( $status ) . '" ' . selected( $row['input']['status'], $status, false ) . '>' . esc_html( ucfirst( $status ) ) . '</option>';
				}
				echo '</select></label><label class="cl-field">' . esc_html__( 'Group IDs (separate with |)', 'cleanlinks' ) . '<input name="rows[' . esc_attr( $index ) . '][groups]" value="' . esc_attr( implode( '|', $row['input']['groups'] ) ) . '"></label><label class="cl-field">' . esc_html__( 'Nofollow', 'cleanlinks' ) . '<select name="rows[' . esc_attr( $index ) . '][nofollow]"><option value="false" ' . selected( $row['input']['nofollow'], false, false ) . '>' . esc_html__( 'No', 'cleanlinks' ) . '</option><option value="true" ' . selected( $row['input']['nofollow'], true, false ) . '>' . esc_html__( 'Yes', 'cleanlinks' ) . '</option></select></label>';
			} else {
				echo esc_html( $row['input']['slug'] . ' / ' . $row['input']['status'] );
			}
			echo '</td><td data-label="' . esc_attr__( 'Result', 'cleanlinks' ) . '"><strong>' . esc_html( ucfirst( $row['state'] ) ) . '</strong>';
			if ( $row['error'] ) {
				echo '<p>' . esc_html( self::error_text( $row['error'] ) ) . '</p>';
			}
			if ( isset( $row['saved'] ) ) {
				echo '<p><a href="' . esc_url( self::url( array( 'view' => 'edit', 'id' => $row['saved']['id'] ) ) ) . '">' . esc_html( $row['saved']['url'] ) . '</a></p>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
		if ( 'preview' === $job['state'] ) {
			echo '<button class="button">' . esc_html__( 'Save corrections and selection on this page', 'cleanlinks' ) . '</button></form>';
		}
		echo '<nav class="cl-pagination" aria-label="' . esc_attr__( 'Import pages', 'cleanlinks' ) . '">';
		for ( $part = max( 0, $page - 2 ); $part < min( $job['pages'], $page + 3 ); $part++ ) {
			echo '<a class="button" ' . ( $part === $page ? 'aria-current="page"' : '' ) . ' href="' . esc_url( self::url( array( 'page' => 'cleanlinks-bulk', 'job' => $id, 'part' => $part ) ) ) . '">' . esc_html( $part + 1 ) . '</a> ';
		}
		echo '</nav><div class="cl-job-actions">';
		$controls = 'preview' === $job['state'] ? array( 'start' => __( 'Create selected links', 'cleanlinks' ) ) : ( 'running' === $job['state'] ? array( 'step' => __( 'Process next 50 links', 'cleanlinks' ), 'cancel' => __( 'Cancel remaining batches', 'cleanlinks' ) ) : ( 'cancelled' === $job['state'] ? array( 'resume' => __( 'Resume remaining batches', 'cleanlinks' ) ) : array( 'retry' => __( 'Retry failed rows only', 'cleanlinks' ), 'download' => __( 'Download error rows', 'cleanlinks' ) ) ) );
		if ( 'running' !== $job['state'] ) { $controls['discard'] = __( 'Remove private preview/results', 'cleanlinks' ); }
		foreach ( $controls as $operation => $label ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			$this->fields( 'job' );
			echo '<input type="hidden" name="job" value="' . esc_attr( $id ) . '"><input type="hidden" name="job_action" value="' . esc_attr( $operation ) . '">';
			if ( 'start' === $operation ) {
				echo '<label class="cl-check"><input type="checkbox" name="confirmed" value="1" required> ' . esc_html__( 'Create the selected valid rows with their previewed publication statuses', 'cleanlinks' ) . '</label>';
			}
			echo '<button class="button ' . ( in_array( $operation, array( 'start', 'step' ), true ) ? 'button-primary' : '' ) . '">' . esc_html( $label ) . '</button></form>';
		}
		if ( 'running' === $job['state'] ) {
			echo '<button type="button" class="button" data-cl-run="' . esc_attr( $id ) . '">' . esc_html__( 'Run remaining batches', 'cleanlinks' ) . '</button><p id="cl-import-live" role="status" aria-live="polite"></p>';
		}
		echo '</div></section>';
	}

	/** Published-only export stays separate and retains its existing policy. */
	public function export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot export links.', 'cleanlinks' ), '', array( 'response' => 403 ) );
		}
		echo '<div class="wrap cl-workspace"><section class="cl-panel"><h1>' . esc_html__( 'Export links', 'cleanlinks' ) . '</h1><p>' . esc_html__( 'Exports published links only: ID, Title, Redirect From and Redirect To. Lifetime click counts are not included.', 'cleanlinks' ) . '</p>';
		( new Export() )->render_ui();
		echo '</section></div>';
	}

	/** Parse explicit scalar fields; arrays cannot become strings or forged IDs. */
	private function save_input( $raw ) {
		$input = array();
		foreach ( array( 'destination', 'title', 'slug', 'request_key', 'expected_version' ) as $field ) {
			if ( isset( $raw[ $field ] ) && ! is_string( $raw[ $field ] ) ) {
				return LinkInput::error( 'invalid_type', $field, 400 );
			}
			$input[ $field ] = isset( $raw[ $field ] ) ? $raw[ $field ] : '';
		}
		if ( isset( $raw['id'] ) && ! is_string( $raw['id'] ) ) { return LinkInput::error( 'invalid_id', 'id', 400 ); }
		if ( empty( $raw['id'] ) ) {
			unset( $input['expected_version'] );
		} elseif ( ! is_scalar( $raw['id'] ) || ! ctype_digit( (string) $raw['id'] ) ) {
			return LinkInput::error( 'invalid_id', 'id', 400 );
		} else {
			$input['id'] = (int) $raw['id'];
		}
		if ( isset( $raw['link_status'] ) && ! is_string( $raw['link_status'] ) ) { return LinkInput::error( 'invalid_type', 'status', 400 ); }
		foreach ( array( 'nofollow', 'confirm_slug_change' ) as $flag ) { if ( isset( $raw[ $flag ] ) && '1' !== $raw[ $flag ] ) { return LinkInput::error( 'invalid_type', $flag, 400 ); } }
		$input['status'] = isset( $raw['link_status'] ) && is_string( $raw['link_status'] ) ? $raw['link_status'] : 'draft';
		$input['nofollow'] = isset( $raw['nofollow'] );
		$input['confirm_slug_change'] = isset( $raw['confirm_slug_change'] );
		if ( isset( $raw['groups'] ) ) { if ( ! is_array( $raw['groups'] ) || count( $raw['groups'] ) > 50 ) { return LinkInput::error( 'invalid_groups', 'groups', 400 ); } foreach ( $raw['groups'] as $value ) { if ( ! is_string( $value ) || ! ctype_digit( $value ) || (int) $value < 1 ) { return LinkInput::error( 'invalid_groups', 'groups', 400 ); } } }
		$input['groups'] = isset( $raw['groups'] ) && is_array( $raw['groups'] ) ? array_map( 'absint', $raw['groups'] ) : array();
		return $input;
	}

	/** Nonce-protected transport with post/redirect/get and actor-private notices. */
	public function handle() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You cannot manage links.', 'cleanlinks' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'cleanlinks_workspace' );
		$raw = wp_unslash( $_POST );
		$operation = isset( $raw['operation'] ) && is_string( $raw['operation'] ) ? $raw['operation'] : '';
		$return = isset( $raw['return'] ) && is_string( $raw['return'] ) ? wp_validate_redirect( $raw['return'], self::url() ) : self::url();
		$result = LinkInput::error( 'unsupported_input', 'input', 400 );
		$input = array();
		$jobs = new ImportJobs();
		if ( 'save' === $operation ) {
			$input = $this->save_input( $raw );
			$result = is_wp_error( $input ) ? $input : ( new LinkCommands() )->execute( $input );
			if ( ! is_wp_error( $result ) ) {
				$return = add_query_arg( array( 'page' => self::SLUG, 'view' => 'edit', 'id' => $result['id'] ), $return );
			}
		} elseif ( 'preview' === $operation ) {
			$text = isset( $raw['urls'] ) && is_string( $raw['urls'] ) ? $raw['urls'] : '';
			$csv = ! empty( $_FILES['csv']['name'] );
			if ( $csv ) {
				$file = $_FILES['csv'];
				if ( UPLOAD_ERR_OK !== $file['error'] || ! is_string( $file['name'] ) || 'csv' !== strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) || $file['size'] > ImportJobs::BYTES || ! is_uploaded_file( $file['tmp_name'] ) ) {
					$result = LinkInput::error( 'invalid_file', 'file', 400 );
				} else {
					$text = file_get_contents( $file['tmp_name'] );
					$result = $jobs->parse( $text, true, isset( $raw['mapping'] ) && is_array( $raw['mapping'] ) ? $raw['mapping'] : array() );
				}
			} else {
				$result = $jobs->parse( $text );
			}
			if ( ! is_wp_error( $result ) ) {
				$result = $jobs->create( $result );
				if ( ! is_wp_error( $result ) ) {
					wp_safe_redirect( self::url( array( 'page' => 'cleanlinks-bulk', 'job' => $result ) ) );
					exit;
				}
			}
			$return = self::url( array( 'page' => 'cleanlinks-bulk' ) );
		} elseif ( 'job' === $operation && isset( $raw['job'], $raw['job_action'] ) && is_string( $raw['job'] ) && is_string( $raw['job_action'] ) ) {
			if ( 'download' === $raw['job_action'] ) {
				$this->download( $raw['job'] );
			}
			$result = 'start' === $raw['job_action'] && empty( $raw['confirmed'] ) ? LinkInput::error( 'confirmation_required', 'job', 400 ) : $jobs->control( $raw['job'], $raw['job_action'], isset( $raw['part'] ) ? absint( $raw['part'] ) : 0, isset( $raw['rows'] ) && is_array( $raw['rows'] ) ? $raw['rows'] : array() );
			$return = self::url( array( 'page' => 'cleanlinks-bulk', 'job' => 'discard' === $raw['job_action'] && ! is_wp_error( $result ) ? '' : $raw['job'], 'part' => isset( $raw['part'] ) ? absint( $raw['part'] ) : 0 ) );
		} elseif ( 'selection' === $operation ) {
			$result = $this->selection( $raw );
		}
		$token = str_replace( '-', '', wp_generate_uuid4() );
		$notice = array( 'error' => is_wp_error( $result ), 'field' => is_wp_error( $result ) && is_array( $result->get_error_data() ) ? ( $result->get_error_data()['field'] ?? '' ) : '', 'message' => is_wp_error( $result ) ? self::error_text( $result->get_error_code() ) : ( 'selection' === $operation ? $result : __( 'Saved. Your data is up to date.', 'cleanlinks' ) ) );
		if ( 'save' === $operation && is_wp_error( $result ) && is_array( $input ) ) {
			$notice['input'] = $input;
			$notice['id'] = isset( $input['id'] ) ? $input['id'] : 0;
		}
		if ( 'preview' === $operation && is_wp_error( $result ) && isset( $raw['urls'] ) && is_string( $raw['urls'] ) && strlen( $raw['urls'] ) <= ImportJobs::BYTES ) { $notice['urls'] = $raw['urls']; }
		set_transient( 'cl_notice_' . get_current_user_id() . '_' . $token, $notice, 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'notice', $token, $return ) );
		exit;
	}

	/** Reversible, individually authorized selection actions. */
	private function selection( $raw ) {
		if ( empty( $raw['confirmed'] ) || ! isset( $raw['ids'] ) || ! is_array( $raw['ids'] ) || count( $raw['ids'] ) > 50 || ! isset( $raw['selection_action'] ) || ! in_array( $raw['selection_action'], array( 'trash', 'restore' ), true ) ) {
			return LinkInput::error( 'confirmation_required', 'selection', 400 );
		}
		foreach ( $raw['ids'] as $value ) { if ( ! is_string( $value ) || ! ctype_digit( $value ) || (int) $value < 1 ) { return LinkInput::error( 'invalid_id', 'selection', 400 ); } }
		$done = 0;
		$failed = 0;
		$failed_ids = array();
		foreach ( array_unique( array_map( 'absint', $raw['ids'] ) ) as $id ) {
			$post = get_post( $id );
			if ( ! $post || 'cleanlinks' !== $post->post_type || ! current_user_can( 'delete_post', $id ) || ( 'trash' === $raw['selection_action'] && 'trash' === $post->post_status ) || ( 'restore' === $raw['selection_action'] && 'trash' !== $post->post_status ) ) {
				$failed++;
				$failed_ids[] = $id;
				continue;
			}
			if ( 'trash' === $raw['selection_action'] && ( ! defined( 'EMPTY_TRASH_DAYS' ) || ! EMPTY_TRASH_DAYS ) ) {
				$failed++;
				$failed_ids[] = $id;
				continue;
			}
			$result = 'trash' === $raw['selection_action'] ? wp_trash_post( $id ) : wp_untrash_post( $id );
			$result ? $done++ : $failed++;
			if ( ! $result ) { $failed_ids[] = $id; }
		}
		return sprintf( __( '%1$d links updated; %2$d could not be updated. Restored links may return as drafts; review before publishing.', 'cleanlinks' ), $done, $failed ) . ( $failed_ids ? ' ' . sprintf( __( 'Links to review (IDs): %s.', 'cleanlinks' ), implode( ', ', $failed_ids ) ) : '' );
	}

	/** AJAX does exactly one bounded batch; refresh/resume uses the same server job. */
	public function ajax_step() {
		check_ajax_referer( 'cleanlinks_workspace', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) || ! isset( $_POST['job'] ) || ! is_string( $_POST['job'] ) ) {
			wp_send_json_error( array( 'message' => self::error_text( 'invalid_session' ) ), 403 );
		}
		$id = sanitize_key( wp_unslash( $_POST['job'] ) );
		$jobs = new ImportJobs();
		$result = $jobs->control( $id, 'step' );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => self::error_text( $result->get_error_code() ) ), 409 );
		}
		wp_send_json_success( array( 'state' => $result['state'], 'next' => $result['next'], 'pages' => $result['pages'], 'counts' => $jobs->counts( $id, $result ) ) );
	}

	/** Stream private error rows with existing formula-neutralizing serializer. */
	private function download( $id ) {
		$jobs = new ImportJobs();
		$job = $jobs->get( $id );
		if ( is_wp_error( $job ) ) {
			wp_die( esc_html( self::error_text( 'invalid_job' ) ), '', array( 'response' => 404 ) );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="cleanlinks-import-errors.csv"' );
		$serializer = new ExportCsvSerializer();
		echo $serializer->serialize_row( array( 'row', 'destination', 'title', 'slug', 'status', 'error' ) ) . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV cells are escaped and formula-neutralized by serializer.
		for ( $page = 0; $page < $job['pages']; $page++ ) {
			foreach ( get_option( ImportJobs::PREFIX . $id . '_part_' . $page, array() ) as $index => $row ) {
				if ( in_array( $row['state'], array( 'error', 'failed', 'skipped' ), true ) ) {
					echo $serializer->serialize_row( array( $page * ImportJobs::PAGE + $index + 1, $row['input']['destination'], $row['input']['title'], $row['input']['slug'], $row['input']['status'], $row['error'] ) ) . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV serializer escapes each cell.
				}
			}
		}
		exit;
	}
}
