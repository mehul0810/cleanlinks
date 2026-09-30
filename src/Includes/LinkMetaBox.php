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

		$this->render_link_overview( $post, $url );
		$this->render_redirect_url_field( $url, $nofollow );
	}

	/**
	 * Show the currently saved link without replacing WordPress publish controls.
	 *
	 * @param WP_Post $post The link post.
	 * @param string  $url  The saved destination URL.
	 * @return void
	 */
	private function render_link_overview( $post, $url ) {
		$is_published = 'publish' === $post->post_status;
		$short_url    = $is_published ? get_permalink( $post->ID ) : '';
		$count        = Helpers::get_total_access_count( $post->ID );
		$status       = get_post_status_object( $post->post_status );
		$status_label = 'auto-draft' === $post->post_status ? __( 'Draft', 'cleanlinks' ) : ( $status ? $status->label : __( 'Not published', 'cleanlinks' ) );
		?>
		<section class="cleanlinks-link-overview" aria-label="<?php esc_attr_e( 'Saved link overview', 'cleanlinks' ); ?>">
			<div class="cleanlinks-link-overview__url">
				<span class="cleanlinks-link-overview__label"><?php esc_html_e( 'Short URL', 'cleanlinks' ); ?></span>
				<?php if ( $short_url ) : ?>
					<div class="cleanlinks-link-overview__value-row">
						<code><?php echo esc_html( $short_url ); ?></code>
						<button type="button" class="button cleanlinks--copy-button" data-url="<?php echo esc_url( $short_url ); ?>" data-default-text="<?php esc_attr_e( 'Copy short URL', 'cleanlinks' ); ?>" data-copied-text="<?php esc_attr_e( 'Copied', 'cleanlinks' ); ?>" data-copy-failed-text="<?php esc_attr_e( 'Copy failed', 'cleanlinks' ); ?>">
							<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
							<span class="cleanlinks--copy-button-text" aria-live="polite"><?php esc_html_e( 'Copy short URL', 'cleanlinks' ); ?></span>
						</button>
					</div>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'The short URL is available after publishing.', 'cleanlinks' ); ?></p>
				<?php endif; ?>
			</div>
			<dl class="cleanlinks-link-overview__facts">
				<div><dt><?php esc_html_e( 'Status', 'cleanlinks' ); ?></dt><dd><?php echo esc_html( $status_label ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Destination', 'cleanlinks' ); ?></dt><dd><?php echo $url ? esc_html( $url ) : esc_html__( 'Not set', 'cleanlinks' ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Default redirect', 'cleanlinks' ); ?></dt><dd><?php echo $is_published ? ( $url ? esc_html__( '301 permanent', 'cleanlinks' ) : esc_html__( '302 to site home', 'cleanlinks' ) ) : esc_html__( 'Available after publishing', 'cleanlinks' ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Total clicks', 'cleanlinks' ); ?></dt><dd><?php echo esc_html( number_format_i18n( absint( $count ) ) ); ?></dd></div>
			</dl>
		</section>
		<?php
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
			<label for="cleanlink_redirect_url"><strong><?php esc_html_e( 'Destination URL', 'cleanlinks' ); ?></strong></label>
			<input placeholder="https://example.com" class="widefat" type="url" inputmode="url" name="cleanlink_redirect_url" id="cleanlink_redirect_url" aria-describedby="cleanlink_redirect_url_help cleanlink_redirect_url_error" value="<?php echo esc_attr( $url ); ?>" />
			<span id="cleanlink_redirect_url_help" class="description"><?php esc_html_e( 'Enter a full http:// or https:// URL. A published link without a destination redirects to the site home page.', 'cleanlinks' ); ?></span>
			<span id="cleanlink_redirect_url_error" class="cleanlinks-link-error" role="alert" hidden><?php esc_html_e( 'Enter a full URL starting with https:// or http://.', 'cleanlinks' ); ?></span>
		</p>

		<p>
			<label for="cleanlink_redirect_nofollow">
				<input type="checkbox" name="cleanlink_redirect_nofollow" id="cleanlink_redirect_nofollow" value="1" <?php checked( $nofollow, '1' ); ?> />
				<?php esc_html_e( 'Ask search engines not to follow this link', 'cleanlinks' ); ?>
			</label>
			<span class="description"><?php esc_html_e( 'Sends an X-Robots-Tag: nofollow header with the redirect.', 'cleanlinks' ); ?></span>
		</p>
		<?php
	}
}
