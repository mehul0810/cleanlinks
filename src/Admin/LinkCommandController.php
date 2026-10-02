<?php
/**
 * Session-protected REST adapter for explicit link commands.
 *
 * @package CleanLinks
 */
namespace MG\CleanLinks\Admin;

use MG\CleanLinks\Application\LinkCommands;
use MG\CleanLinks\Application\LinkInput;

/** Exposes only the fields and operations owned by the link application service. */
class LinkCommandController {
	/** Register the endpoint hook. */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/** Register scoped routes with explicit permission callbacks. */
	public function register_routes() {
		register_rest_route( 'cleanlinks/v1', '/links', array(
			'methods' => 'POST', 'callback' => array( $this, 'save' ), 'permission_callback' => array( $this, 'permission' ),
		) );
		register_rest_route( 'cleanlinks/v1', '/links/(?P<id>[1-9][0-9]*)', array(
			array( 'methods' => 'GET', 'callback' => array( $this, 'read' ), 'permission_callback' => array( $this, 'permission' ) ),
			array( 'methods' => 'PUT,PATCH', 'callback' => array( $this, 'save' ), 'permission_callback' => array( $this, 'permission' ) ),
		) );
		register_rest_route( 'cleanlinks/v1', '/link-commands', array(
			'methods' => 'POST', 'callback' => array( $this, 'rows' ), 'permission_callback' => array( $this, 'permission' ),
		) );
	}

	/**
	 * Require an authenticated session nonce and create/object edit authority.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function permission( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! get_current_user_id() || ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return LinkInput::error( 'invalid_session', 'nonce', 403 );
		}
		$id = $request->get_url_params();
		if ( isset( $id['id'] ) ) {
			return is_wp_error( ( new LinkCommands() )->read( (int) $id['id'] ) ) ? LinkInput::error( 'forbidden', 'id', 403 ) : true;
		}
		$type = get_post_type_object( 'cleanlinks' );
		return $type && current_user_can( $type->cap->create_posts ) ? true : LinkInput::error( 'forbidden', 'id', 403 );
	}

	/**
	 * Save a JSON command with route-owned object identity.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function save( $request ) {
		$input = $request->get_json_params();
		if ( ! is_array( $input ) || array_key_exists( 'id', $input ) ) {
			return LinkInput::error( 'unsupported_input', 'id', 400 );
		}
		$params = $request->get_url_params();
		if ( isset( $params['id'] ) ) {
			$input['id'] = (int) $params['id'];
		}
		return ( new LinkCommands() )->execute( $input );
	}

	/**
	 * Read authorized current state and its update token.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function read( $request ) {
		$params = $request->get_url_params();
		return ( new LinkCommands() )->read( (int) $params['id'] );
	}

	/**
	 * Execute bounded rows with individual errors and stable receipts.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function rows( $request ) {
		$input = $request->get_json_params();
		if ( ! is_array( $input ) || array_keys( $input ) !== array( 'rows' ) ) {
			return LinkInput::error( 'unsupported_input', 'rows', 400 );
		}
		return ( new LinkCommands() )->execute_rows( $input['rows'] );
	}
}
