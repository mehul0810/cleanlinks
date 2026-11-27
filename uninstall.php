<?php
/** CleanLinks uninstall entry point. */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) || WP_UNINSTALL_PLUGIN !== plugin_basename( __DIR__ . '/cleanlinks.php' ) ) {
	exit;
}

require_once __DIR__ . '/src/Uninstall.php';

$cleanlinks_result = \MG\CleanLinks\Uninstall::run();
if ( is_wp_error( $cleanlinks_result ) ) {
	$cleanlinks_details = $cleanlinks_result->get_error_data();
	if ( 'recovery_required' === $cleanlinks_result->get_error_message() || get_option( \MG\CleanLinks\Uninstall::RECOVERY_OPTION ) ) {
		wp_die( esc_html__( 'CleanLinks cleanup could not be verified. The plugin files remain. Restore a database backup before attempting deletion again; reinstalling or reverting code does not restore deleted data.', 'cleanlinks' ) );
	}
	wp_die( esc_html( sprintf( /* translators: 1: cleanup stage, 2: item ID. */ __( 'CleanLinks cleanup stopped at %1$s (ID %2$d). The plugin files remain. Resolve the problem and retry deletion. Restore a backup if deleted data is needed; reinstalling or reverting code does not restore it.', 'cleanlinks' ), $cleanlinks_result->get_error_message(), $cleanlinks_details['id'] ) ) );
}
