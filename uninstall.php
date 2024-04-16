<?php

/**
 * Simplified Links | Uninstall.
 *
 * @package WordPress
 * @subpackage Simplified Links
 * @since 1.0.0
 *
 */

// Exit if accessed directly.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Delete All the Custom Post Types.
$simplified_taxonomy = 'simplifiedwp_groups';
$simplified_post_type = 'simplifiedwp_links';

$items = get_posts(
    [
        'post_type'   => $simplified_post_type,
        'post_status' => 'any',
        'numberposts' => -1,
        'fields'      => 'ids',
    ]
);

if ($items) {
    foreach ($items as $item) {
        wp_delete_post($item, true);
    }
}

// Delete All the Terms & Taxonomies.
$terms = $wpdb->get_results(
    $wpdb->prepare(
        "
        SELECT t.*, tt.*
        FROM $wpdb->terms AS t
        INNER JOIN $wpdb->term_taxonomy AS tt
            ON t.term_id = tt.term_id
        WHERE tt.taxonomy IN ('%s')
        ORDER BY t.name ASC
        ",
        $simplified_taxonomy
    )
);

// Delete Terms.
if ($terms) {
    foreach ($terms as $term) {
        $wpdb->delete($wpdb->term_taxonomy, ['term_taxonomy_id' => $term->term_taxonomy_id]);
        $wpdb->delete($wpdb->terms, ['term_id' => $term->term_id]);
    }
}

// Delete Taxonomies.
$wpdb->delete($wpdb->term_taxonomy, ['taxonomy' => $simplified_taxonomy], ['%s']);
