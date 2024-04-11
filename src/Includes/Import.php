<?php
/**
 * Simplified Links | Import
 *
 * @package WordPress
 * @subpackage Simplified Links
 * @since 1.0.0
 */

namespace SimplifiedWP\Links\Includes;

use SimplifiedWP\Links\includes\Database;
use SimplifiedWP\Links\includes\Helpers;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Import {

	protected $data = array();
    
	/**
	 * Import a single post into SimplifiedWP
	 *
	 * @param int    $import_id  Post id.
	 * @param string $post_type  Type of post (earnist, thirstylink, affiliate_url, aawp,...).
	 * @param string $post_title Title.
	 * @param string $import_permalink Import permalink.
	 */
	public function process_single_link_data_import( $import_id, $post_type ) {
		if ( 'surl' === $post_type ) {
			$import_data = $this->get_simple_url_link_data( $import_id );
		} 
		$import_data['post_type'] = $post_type;
		
		// ? Make a Lasso Link
		list($status, $import_data) = $this->import_into_simplified( $import_data, $post_type );
		
		// ? Return if status is false
		if ( ! $status ) {
			return array( $status, $import_data );
		}

		return array( $status, $import_data );
	}

    /**
	 * Get post data of Simple Urls plugin
	 *
	 * @param string $import_id  Post id.
	 * @param string $post_title Post title.
	 * @param string $import_permalink Import permalink.
	 */
	public function get_simple_url_link_data( $import_id ) {
		$post         = get_post( $import_id );
		$redirect_url = get_post_meta( $import_id, '_surl_redirect', true );
		//$terms        = get_the_terms( $import_id, 'eafl_category' );
		//$cat_ids      = $terms && ! is_wp_error( $terms ) ? wp_list_pluck( $terms, 'name' ) : null;

		$target           = get_post_meta( $import_id, '_open_new_tab', true );
		$description      = get_post_meta( $import_id, '_description', true );
		
		$data = array(
			'post'             => $post,
			'redirect_url'     => $redirect_url,
			'cat_ids'          => '',
			'old_uri'          => get_permalink( $import_id ),
			'description'      => $description,
			'open_new_tab'     => $target,
			'enable_nofollow'  => get_post_meta( $import_id, '_enable_nofollow', true ),
			'enable_sponsored' => get_post_meta( $import_id, '_enable_sponsored', true ),
		);

		return $data;
	}

	/**
	 * Get post data of Easy Affiliate Links plugin
	 *
	 * @param string $import_id  Post id.
	 * @param string $post_title Post title.
	 * @param string $import_permalink Import permalink.
	 */
	public function get_easy_affiliate_link_data( $import_id, $post_title, $import_permalink ) {
		$post         = get_post( $import_id );
		$redirect_url = get_post_meta( $import_id, 'eafl_url', true );
		$terms        = get_the_terms( $import_id, 'eafl_category' );
		$cat_ids      = $terms && ! is_wp_error( $terms ) ? wp_list_pluck( $terms, 'name' ) : null;

		$default_settings = get_option( 'eafl_settings' );
		$target           = get_post_meta( $import_id, 'eafl_target', true );
		$description      = get_post_meta( $import_id, 'eafl_description', true );
		if ( 'default' === $target ) {
			$target = $default_settings['default_target'] ?? '_blank';
		}
		$nofollow = get_post_meta( $import_id, 'eafl_nofollow', true );
		if ( 'default' === $nofollow ) {
			$nofollow = $default_settings['default_nofollow'] ?? 'nofollow';
		}
		$sponsored = get_post_meta( $import_id, 'eafl_sponsored', true );

		$data = array(
			'post'             => $post,
			'redirect_url'     => $redirect_url,
			'cat_ids'          => $cat_ids,
			'old_uri'          => get_permalink( $import_id ),
			'description'      => $description,
			'open_new_tab'     => '_blank' === $target ? 1 : 0,
			'enable_nofollow'  => 'nofollow' === $nofollow ? 1 : 0,
			'enable_sponsored' => '1' === $sponsored ? 1 : 0,
		);

		return $data;
	}

	/**
	 * Import post data from other plugins into Simplified
	 *
	 * @param array  $import_data Array contains post data.
	 * @param string $post_type   Type of post (post, page, surl, simple_url,...).
	 */
	private function import_into_simplified( $import_data, $post_type ) {
		$simplified_db = new Database();
		//$lasso_affiliate_link = new Lasso_Affiliate_Link();

		//$lasso_settings = Setting::get_settings();

		// ? Make sure slug is correct
		$post_id = $import_data['post']->ID ?? '';
		$slug    = $import_data['post']->post_name ?? '';
		$slug    = Helpers::simplified_unique_post_name( $post_id, $slug );
		$title   = $import_data['post']->post_title ?? '';
		
		$data['post_id']      = $post_id;
		//$data['settings']     = $affiliate_link;
		$data['thumbnail_id'] = $import_data['thumbnail_id'][0] ?? '';
		$data['old_uri']      = $import_data['old_uri'] ?? '';
		$data['is_importing'] = true;
		$data['surl_redirect'] = $import_data['redirect_url'];
		
		if( isset( $data['surl_redirect'] ) && !empty ( $data['surl_redirect'] ) ) {
			update_post_meta( $post_id , 'simplified_redirect_url' , $data['surl_redirect'] );
		}
		// $post_id                 = $lasso_affiliate_link->save_lasso_url( $data );
		
		// $import_data['post']->ID = $post_id;

		$post      = get_post( $post_id );
		$post_name = $post->post_name ?? '';
		$slug      = ! empty( $post_name ) && empty( $slug ) ? $post_name : $slug;

		$old_uri = $import_data['old_uri'] ?? '';
		
		$status = $simplified_db->process_import( $post_id, $slug, $old_uri, $post_type );
		
		// ? clear cache after importing
		if ( $status ) {
			$this->un_set( 'simplified_import_' . $post_id );
			$this->un_set( 'wp_post_' . $post_id );
		}

		return array( $status, $import_data );
	}

	/**
	 * Unset cache by key
	 *
	 * @param Cache key $key cache key.
	 */
	public function un_set( $key ) {
		if ( isset( $this->data[ $key ] ) ) {
			unset( $this->data[ $key ] );
		}
	}

}
