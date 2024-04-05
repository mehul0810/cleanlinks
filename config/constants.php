<?php
/**
 *  Bailout, if accessed directly
 */ 
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SIMPLIFIED_LINKS_VERSION' ) ) {
	define( 'SIMPLIFIED_LINKS_VERSION', '1.0.0' );
}

if ( ! defined( 'SIMPLIFIED_LINKS_PLUGIN_FILE' ) ) {
	define( 'SIMPLIFIED_LINKS_PLUGIN_FILE', dirname( __DIR__ ) . '/simplified-links.php' );
}

if ( ! defined( 'SIMPLIFIED_LINKS_PLUGIN_BASENAME' ) ) {
	define( 'SIMPLIFIED_LINKS_PLUGIN_BASENAME', plugin_basename( SIMPLIFIED_LINKS_PLUGIN_FILE ) );
}

if ( ! defined( 'SIMPLIFIED_LINKS_PLUGIN_DIR' ) ) {
	define( 'SIMPLIFIED_LINKS_PLUGIN_DIR', plugin_dir_path( SIMPLIFIED_LINKS_PLUGIN_FILE ) );
}

if ( ! defined( 'SIMPLIFIED_LINKS_PLUGIN_URL' ) ) {
	define( 'SIMPLIFIED_LINKS_PLUGIN_URL', plugin_dir_url( SIMPLIFIED_LINKS_PLUGIN_FILE ) );
}
