<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

/*
 * On uninstall the plugin is not loaded, so its taxonomy is not registered and
 * wp_delete_term() would refuse to delete the folders. A bare registration is
 * enough for it to work; it lives only for this request.
 */
if ( ! taxonomy_exists( 'agofiles_media_folder' ) ) {
    register_taxonomy( 'agofiles_media_folder', 'attachment', [
        'hierarchical' => true,
        'public'       => false,
        'rewrite'      => false,
    ] );
}

/*
 * Folder terms are read straight from the tables so that every one is found,
 * however deep it is nested. The query is parameterised and runs once, so
 * caching it would serve nothing.
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$agofiles_terms = $wpdb->get_col( $wpdb->prepare(
    "SELECT t.term_id FROM {$wpdb->terms} t
     INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
     WHERE tt.taxonomy = %s",
    'agofiles_media_folder'
) );

foreach ( $agofiles_terms as $agofiles_term_id ) {
    wp_delete_term( (int) $agofiles_term_id, 'agofiles_media_folder' );
}

delete_option( 'agofiles_settings' );
