<?php
/**
 * Simplified Links | Database.
 *
 * @package WordPress
 * @subpackage Simplified Links
 * @since 1.0.0
 */

namespace SimplifiedWP\Links\Includes;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Database {
	/**
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function __construct() {
		global $wpdb;

		$this->dbname         = $wpdb->dbname;
		$this->prefix         = $wpdb->prefix;
		
		// ? WP Tables
		$this->posts          = $wpdb->posts;
		$this->postmeta       = $wpdb->postmeta;
		$this->options        = $wpdb->options;
	}

	/**
	 * Get importable url query
	 *
	 * @param string $filter_plugin    Plugin name.
	 */
	public function get_import_urls_query( $filter_plugin = null ) {
		
		global $wpdb;

		$sql = '';
		$lasso_post_type = 'surl';
		// ? Simple urls plugin 
		if ( empty( $filter_plugin ) || 'simple-urls' === $filter_plugin ) {
			$sql = "
				SELECT
					po.ID as id,
					po.post_type,
					'Simple Urls' as import_source,
					CONVERT(po.post_name USING utf8) as post_name,
					CONVERT(po.post_title USING utf8) as post_title,
					'' as check_status,
					'' as check_disabled
				FROM " . $this->posts . ' as po
				WHERE po.post_type = %s 
			';

			$sql = $wpdb->prepare( $sql, $lasso_post_type );
		}

		return $sql;
	}

	/**
	 * Paginate items by a sql query
	 *
	 * @param string $sql   Sql query.
	 * @param int    $page  Number of page.
	 * @param int    $limit Number of results. Default to 10.
	 */
	public function paginate( $sql, $page, $limit = 10 ) {
		$start_index = ( $page - 1 ) * $limit;
		return $sql . ' LIMIT ' . $start_index . ', ' . $limit;
	}

	/**
	 * Process import
	 *
	 * @param int    $id      Post id.
	 * @param string $slug    Link slug.
	 * @param string $old_uri Old URI.
	 * @param string $post_type  Post Type.
	 */
	public function process_import( $id, $slug, $old_uri, $post_type ) {
		if ( empty( $id ) || empty( $slug ) ) {
			return false;
		}

		global $wpdb;
		clean_post_cache( $id );
		
		$result1 = true;
		if ( 'surl' === $post_type ) {
			// ? Flip post time and potentially the slug
			
			$update_sql = '
				UPDATE ' . $this->posts . '
				SET
					post_name = %s,
					post_type = %s,
					post_modified = NOW(),
					post_modified_gmt = NOW()
				WHERE ID = %d;
			';
			$update_sql = $wpdb->prepare( $update_sql, $slug, 'simplifiedwp_links', $id ); // phpcs:ignore

			$wpdb->query( $update_sql );
			
			$result1 = 'simplifiedwp_links' === get_post_type( $id );
		}

		return $result1;
	}
	
}
