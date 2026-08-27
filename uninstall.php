<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

/*
 * Folder terms are read straight from the tables: get_terms() needs the
 * taxonomy to be registered, and on uninstall the plugin is no longer loaded.
 * The query is parameterised and runs once, so caching it would serve nothing.
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
