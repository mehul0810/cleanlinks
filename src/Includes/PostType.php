<?php
/**
 * CleanLinks | Post Type.
 *
 * @package WordPress
 * @subpackage CleanLinks
 * @since 1.0.0
 */

namespace MG\CleanLinks\Includes;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates the CleanLinks post type collaborators.
 *
 * The public methods remain as compatibility entry points for existing hooks,
 * activation code, and extensions while the individual responsibilities live
 * in focused collaborators.
 *
 * @since 1.0.0
 */
class PostType {
	/**
	 * Post type registration collaborator.
	 *
	 * @var PostTypeRegistrar
	 */
	private $registrar;

	/**
	 * Link metadata persistence collaborator.
	 *
	 * @var LinkMetaSaver
	 */
	private $meta_saver;

	/**
	 * Link metadata box collaborator.
	 *
	 * @var LinkMetaBox
	 */
	private $meta_box;

	/**
	 * Initiate post type.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function __construct() {
		$this->meta_box   = new LinkMetaBox();
		$this->registrar  = new PostTypeRegistrar( array( $this, 'action_add_url_metabox' ) );
		$this->meta_saver = new LinkMetaSaver();

		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'save_post', array( $this, 'save_link_meta' ), 10, 2 );
	}

	/**
	 * Get the labels for the post type.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_labels() {
		return $this->registrar->get_labels();
	}

	/**
	 * Get the arguments for the post type.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_args() {
		return $this->registrar->get_args();
	}

	/**
	 * Register the CleanLinks post type.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_post_type() {
		$this->registrar->register();
	}

	/**
	 * Save metadata for a CleanLinks post.
	 *
	 * @since 1.0.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function save_link_meta( $post_id, $post ) {
		$this->meta_saver->save( $post_id, $post );
	}

	/**
	 * Register the CleanLinks metadata box.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function action_add_url_metabox() {
		$this->meta_box->register( array( $this, 'link_metabox' ) );
	}

	/**
	 * Render the CleanLinks metadata box.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function link_metabox( $post ) {
		$this->meta_box->render( $post );
	}
}
