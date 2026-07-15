#!/usr/bin/env php
<?php
/**
 * Validate the contents of a CleanLinks release ZIP.
 *
 * @package CleanLinks
 */

declare(strict_types=1);

if ( 2 !== $argc ) {
	fwrite( STDERR, "Usage: php scripts/validate-release-package.php <release.zip>\n" );
	exit( 2 );
}

$zip_path = $argv[1];

if ( ! is_file( $zip_path ) ) {
	fwrite( STDERR, "Release package not found: {$zip_path}\n" );
	exit( 1 );
}

$zip = new ZipArchive();

if ( true !== $zip->open( $zip_path ) ) {
	fwrite( STDERR, "Unable to open release package: {$zip_path}\n" );
	exit( 1 );
}

$entries = array();

for ( $index = 0; $index < $zip->numFiles; $index++ ) {
	$entry = $zip->getNameIndex( $index );

	if ( false === $entry ) {
		continue;
	}

	while ( './' === substr( $entry, 0, 2 ) ) {
		$entry = substr( $entry, 2 );
	}

	if ( '' !== $entry ) {
		$entries[ $entry ] = true;
	}
}

$required_files = array(
	'cleanlinks.php',
	'config/constants.php',
	'dist/admin.asset.php',
	'dist/admin.css',
	'dist/admin.js',
	'languages/cleanlinks.pot',
	'readme.txt',
	'src/Plugin.php',
	'uninstall.php',
	'vendor/autoload.php',
	'vendor/composer/autoload_classmap.php',
	'vendor/composer/autoload_psr4.php',
	'vendor/composer/installed.json',
	'.wordpress-org/blueprints/blueprint.json',
	'.wordpress-org/icon-128x128.png',
);

$validation_errors = array();

foreach ( $required_files as $required_file ) {
	if ( ! isset( $entries[ $required_file ] ) ) {
		$validation_errors[] = "Missing required file: {$required_file}";
	}
}

$plugin_file = $zip->getFromName( 'cleanlinks.php' );
$readme_file = $zip->getFromName( 'readme.txt' );

if ( false !== $plugin_file && false !== $readme_file ) {
	$metadata_checks = array(
		'plugin version' => array(
			'plugin_pattern' => '/^[ \t]*\*[ \t]+Version:[ \t]*(.+)$/mi',
			'readme_pattern' => '/^Stable tag:[ \t]*(.+)$/mi',
		),
		'minimum WordPress version' => array(
			'plugin_pattern' => '/^[ \t]*\*[ \t]+Requires at least:[ \t]*(.+)$/mi',
			'readme_pattern' => '/^Requires at least:[ \t]*(.+)$/mi',
		),
		'minimum PHP version' => array(
			'plugin_pattern' => '/^[ \t]*\*[ \t]+Requires PHP:[ \t]*(.+)$/mi',
			'readme_pattern' => '/^Requires PHP:[ \t]*(.+)$/mi',
		),
		'license' => array(
			'plugin_pattern' => '/^[ \t]*\*[ \t]+License:[ \t]*(.+)$/mi',
			'readme_pattern' => '/^License:[ \t]*(.+)$/mi',
		),
	);

	foreach ( $metadata_checks as $metadata_name => $metadata_check ) {
		$plugin_match = array();
		$readme_match = array();

		if ( 1 !== preg_match( $metadata_check['plugin_pattern'], $plugin_file, $plugin_match ) ) {
			$validation_errors[] = "Missing packaged plugin {$metadata_name} metadata.";
			continue;
		}

		if ( 1 !== preg_match( $metadata_check['readme_pattern'], $readme_file, $readme_match ) ) {
			$validation_errors[] = "Missing packaged readme {$metadata_name} metadata.";
			continue;
		}

		if ( trim( $plugin_match[1] ) !== trim( $readme_match[1] ) ) {
			$validation_errors[] = "Packaged {$metadata_name} metadata does not match between cleanlinks.php and readme.txt.";
		}
	}
}

$forbidden_patterns = array(
	'#^(?:\.git(?:/|$)|\.github/|\.release/|assets/|node_modules/|scripts/|tests/)#',
	'#^(?:AGENTS\.md|CONTRIBUTING\.md|README\.md|RELEASE\.md|composer\.(?:json|lock)|package(?:-lock)?\.json|phpunit\.xml(?:\.dist)?|webpack\.config\.js|\.distignore|\.npmpackagejsonlintrc\.json)$#',
	'#^vendor/(?:bin/|composer/installers/|antecedent/|automattic/|brain/|dealerdirect/|doctrine/|hamcrest/|mockery/|myclabs/|nikic/|phar-io/|php-parallel-lint/|phpcompatibility/|phpcsstandards/|phpstan/|phpunit/|sebastian/|sirbrillig/|squizlabs/|theseer/|wp-coding-standards/|yoast/)#',
	'#(?:^|/)[^/]+\.zip$#i',
);
$reported_forbidden_patterns = array();

foreach ( array_keys( $entries ) as $entry ) {
	foreach ( $forbidden_patterns as $pattern_index => $pattern ) {
		if ( 1 === preg_match( $pattern, $entry ) ) {
			if ( ! isset( $reported_forbidden_patterns[ $pattern_index ] ) ) {
				$validation_errors[] = "Forbidden package entry: {$entry}";
				$reported_forbidden_patterns[ $pattern_index ] = true;
			}

			break;
		}
	}
}

$installed_json = $zip->getFromName( 'vendor/composer/installed.json' );

if ( false !== $installed_json ) {
	try {
		$installed = json_decode( $installed_json, true, 512, JSON_THROW_ON_ERROR );
	} catch ( JsonException $exception ) {
		$validation_errors[] = 'Invalid vendor/composer/installed.json: ' . $exception->getMessage();
		$installed = array();
	}

	if ( true === ( $installed['dev'] ?? false ) ) {
		$validation_errors[] = 'Composer installed.json marks the package as containing development dependencies.';
	}

	if ( ! empty( $installed['dev-package-names'] ) ) {
		$validation_errors[] = 'Composer installed.json lists development packages.';
	}
}

$autoload_metadata_files = array(
	'vendor/composer/autoload_classmap.php',
	'vendor/composer/autoload_psr4.php',
);

foreach ( $autoload_metadata_files as $autoload_metadata_file ) {
	$autoload_metadata = $zip->getFromName( $autoload_metadata_file );

	if ( false !== $autoload_metadata && false === strpos( $autoload_metadata, '$baseDir = dirname($vendorDir);' ) ) {
		$validation_errors[] = "Composer autoload metadata resolves outside the package root: {$autoload_metadata_file}";
	}
}

$zip->close();

if ( ! empty( $validation_errors ) ) {
	fwrite( STDERR, "Release package validation failed:\n- " . implode( "\n- ", $validation_errors ) . "\n" );
	exit( 1 );
}

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- This is a local CLI file path.
printf( "Validated %d package entries in %s\n", count( $entries ), $zip_path );
