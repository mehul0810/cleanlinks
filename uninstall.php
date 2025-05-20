<?php
/**
 * CleanLinks | Uninstall.
 *
 * @package WordPress
 * @subpackage CleanLinks
 * @since 1.0.0
 *
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete all posts of custom post type
$clean_links = get_posts([
    'post_type' => 'clean_links',
    'numberposts' => -1,
]);
foreach ($clean_links as $link) {
    wp_delete_post($link->ID, true);
}

// Delete custom taxonomy terms
$terms = get_terms([
    'taxonomy' => 'cleanlinks_groups',
    'hide_empty' => false,
]);
foreach ($terms as $term) {
    wp_delete_term($term->term_id, 'cleanlinks_groups');
}

// Delete plugin options
delete_option('cleanlinks_settings');
