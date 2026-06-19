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
$cleanlinks = get_posts([
    'post_type' => 'cleanlinks',
    'numberposts' => -1,
]);
foreach ($cleanlinks as $cleanlink) {
    wp_delete_post($cleanlink->ID, true);
}

// Delete custom taxonomy terms
$terms = get_terms([
    'taxonomy' => 'cleanlinks_groups',
    'hide_empty' => false,
]);
foreach ($terms as $cleanlinks_group) {
    wp_delete_term($cleanlinks_group->term_id, 'cleanlinks_groups');
}

// Delete plugin options
delete_option('cleanlinks_settings');
