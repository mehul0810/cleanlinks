<?php
/**
 *  Bailout, if accessed directly
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'CLEANLINKS_VERSION' ) ) {
	define( 'CLEANLINKS_VERSION', '1.1.1' );
}

if ( ! defined( 'CLEANLINKS_PLUGIN_FILE' ) ) {
	define( 'CLEANLINKS_PLUGIN_FILE', dirname( __DIR__ ) . '/cleanlinks.php' );
}

if ( ! defined( 'CLEANLINKS_PLUGIN_BASENAME' ) ) {
	define( 'CLEANLINKS_PLUGIN_BASENAME', plugin_basename( CLEANLINKS_PLUGIN_FILE ) );
}

if ( ! defined( 'CLEANLINKS_PLUGIN_DIR' ) ) {
	define( 'CLEANLINKS_PLUGIN_DIR', plugin_dir_path( CLEANLINKS_PLUGIN_FILE ) );
}

if ( ! defined( 'CLEANLINKS_PLUGIN_URL' ) ) {
	define( 'CLEANLINKS_PLUGIN_URL', plugin_dir_url( CLEANLINKS_PLUGIN_FILE ) );
}
