<?php
/**
 *  Bailout, if accessed directly
 */ 
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'CLEAN_LINKS_VERSION' ) ) {
	define( 'CLEAN_LINKS_VERSION', '1.0.0' );
}

if ( ! defined( 'CLEAN_LINKS_PLUGIN_FILE' ) ) {
	define( 'CLEAN_LINKS_PLUGIN_FILE', dirname( __DIR__ ) . '/cleanlinks.php' );
}

if ( ! defined( 'CLEAN_LINKS_PLUGIN_BASENAME' ) ) {
	define( 'CLEAN_LINKS_PLUGIN_BASENAME', plugin_basename( CLEAN_LINKS_PLUGIN_FILE ) );
}

if ( ! defined( 'CLEAN_LINKS_PLUGIN_DIR' ) ) {
	define( 'CLEAN_LINKS_PLUGIN_DIR', plugin_dir_path( CLEAN_LINKS_PLUGIN_FILE ) );
}

if ( ! defined( 'CLEAN_LINKS_PLUGIN_URL' ) ) {
	define( 'CLEAN_LINKS_PLUGIN_URL', plugin_dir_url( CLEAN_LINKS_PLUGIN_FILE ) );
}
