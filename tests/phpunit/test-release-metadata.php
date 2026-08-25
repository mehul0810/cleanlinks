<?php
/**
 * CleanLinks | Release metadata tests.
 *
 * @package WordPress
 * @subpackage CleanLinks
 * @since 1.1.1
 */

namespace MG\CleanLinks\Tests;

use WP_UnitTestCase;

class Test_Release_Metadata extends WP_UnitTestCase {
	/**
	 * Verify all release-critical version metadata stays synchronized.
	 *
	 * @since 1.1.1
	 *
	 * @return void
	 */
	public function test_release_metadata_is_synchronized() {
		$plugin_file   = $this->read_metadata_file( 'cleanlinks.php' );
		$constants     = $this->read_metadata_file( 'config/constants.php' );
		$readme        = $this->read_metadata_file( 'readme.txt' );
		$composer      = json_decode( $this->read_metadata_file( 'composer.json' ), true, 512, JSON_THROW_ON_ERROR );
		$package       = json_decode( $this->read_metadata_file( 'package.json' ), true, 512, JSON_THROW_ON_ERROR );
		$package_lock  = json_decode( $this->read_metadata_file( 'package-lock.json' ), true, 512, JSON_THROW_ON_ERROR );

		$this->assertStringContainsString( '* Version: 1.1.1', $plugin_file );
		$this->assertStringContainsString( "define( 'CLEANLINKS_VERSION', '1.1.1' );", $constants );
		$this->assertTrue( defined( 'CLEANLINKS_VERSION' ) );
		$this->assertSame( '1.1.1', CLEANLINKS_VERSION );
		$this->assertMatchesRegularExpression( '/^Stable tag:\s+1\.1\.1$/m', $readme );
		$this->assertMatchesRegularExpression( '/^Tested up to:\s+7\.1$/m', $readme );
		$this->assertSame( '1.1.1', $composer['version'] );
		$this->assertSame( '1.1.1', $package['version'] );
		$this->assertSame( '1.1.1', $package_lock['version'] );
		$this->assertSame( '1.1.1', $package_lock['packages']['']['version'] );
	}

	/**
	 * Read a repository metadata file.
	 *
	 * @since 1.1.1
	 *
	 * @param string $relative_path Repository-relative file path.
	 * @return string File contents.
	 */
	private function read_metadata_file( $relative_path ) {
		$path = dirname( __DIR__, 2 ) . '/' . $relative_path;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read local repository metadata for this regression test.
		$contents = file_get_contents( $path );

		$this->assertNotFalse( $contents, 'Expected metadata file is readable.' );

		return $contents;
	}
}
