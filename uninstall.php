<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Remove all terms in our taxonomy
$terms = $wpdb->get_col( $wpdb->prepare(
    "SELECT t.term_id FROM {$wpdb->terms} t
     INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
     WHERE tt.taxonomy = %s",
    'ago_media_folder'
) );

foreach ( $terms as $term_id ) {
    wp_delete_term( (int) $term_id, 'ago_media_folder' );
}

delete_option( 'ago_files_settings' );
