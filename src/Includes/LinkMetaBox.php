<?php
/**
 * CleanLinks | Link Metadata Box.
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
 * Registers and renders the CleanLinks link metadata box.
 *
 * @since 1.1.1
 */
class LinkMetaBox {
	/**
	 * Register the CleanLinks metadata box.
	 *
	 * @since 1.1.1
	 *
	 * @param callable|null $render_callback Callback used to render the box.
	 * @return void
	 */
	public function register( $render_callback = null ) {
		if ( null === $render_callback ) {
			$render_callback = array( $this, 'render' );
		}

		add_meta_box( 'cleanlink_redirection_settings', esc_html__( 'Redirect Link Settings', 'cleanlinks' ), $render_callback, 'cleanlinks', 'normal', 'core' );
	}

	/**
	 * Render the CleanLinks metadata box.
	 *
	 * @since 1.1.1
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function render( $post ) {
		wp_nonce_field( 'cleanlink-save-redirect-meta', 'cleanlink_redirect_nonce' );

		$url      = get_post_meta( $post->ID, 'cleanlink_redirect_url', true );
		$nofollow = get_post_meta( $post->ID, 'cleanlink_redirect_nofollow', true );

		$this->render_redirect_url_field( $url, $nofollow );
		$this->render_access_count( $post->ID );
	}

	/**
	 * Render the redirect URL field.
	 *
	 * @since 1.1.1
	 *
	 * @param string $url The current redirect URL.
	 * @param string $nofollow The current nofollow value.
	 * @return void
	 */
	private function render_redirect_url_field( $url, $nofollow = '0' ) {
		?>
		<p>
			<label for="cleanlink_redirect_url"><strong><?php esc_html_e( 'Destination URL:', 'cleanlinks' ); ?></strong>
			<input placeholder="<?php esc_attr_e( 'Enter the full destination URL (e.g., https://example.com)', 'cleanlinks' ); ?>" class="widefat" type="url" name="cleanlink_redirect_url" id="cleanlink_redirect_url" value="<?php echo esc_attr( $url ); ?>" />
			</label>
			<span class="description">
				<?php esc_html_e( 'Visitors will be redirected to this URL when they access your link.', 'cleanlinks' ); ?>
			</span>
		</p>

		<p>
			<label for="cleanlink_redirect_nofollow">
				<input type="checkbox" name="cleanlink_redirect_nofollow" id="cleanlink_redirect_nofollow" value="1" <?php checked( $nofollow, '1' ); ?> />
				<?php esc_html_e( 'Add nofollow to this redirect', 'cleanlinks' ); ?>
			</label>
			<span class="description"><?php esc_html_e( 'Check this option to prevent search engines from following this redirect.', 'cleanlinks' ); ?> </span>
		</p>
		<?php
	}

	/**
	 * Render the access count information.
	 *
	 * @since 1.1.1
	 *
	 * @param int $post_id The post ID.
	 * @return void
	 */
	private function render_access_count( $post_id ) {
		$count = Helpers::get_total_access_count( $post_id );
		?>
		<div class="cleanlinks--access-count">
			<span class="dashicons dashicons-chart-bar"></span>
			<?php
			/* Translators: %1$s is the text before the count, %2$d is the count, %3$s is the text after the count. */
			printf(
				'%1$s %2$d %3$s',
				esc_html__( 'This link has been visited', 'cleanlinks' ),
				absint( $count ),
				esc_html__( 'times', 'cleanlinks' )
			);
			?>
		</div>
		<?php
	}
}
