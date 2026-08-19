<?php

namespace AgoLab\Files;

defined( 'ABSPATH' ) || exit;

class MediaLibrary {

    public static function init(): void {
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_on_media_pages' ] );
        add_action( 'pre_get_posts', [ __CLASS__, 'filter_by_folder' ] );
        add_filter( 'ajax_query_attachments_args', [ __CLASS__, 'filter_grid_by_folder' ] );
    }

    public static function enqueue_on_media_pages( string $hook ): void {
        // Load on upload.php (media library) and post.php/post-new.php (media modal)
        $media_pages = [ 'upload.php', 'post.php', 'post-new.php' ];
        if ( ! in_array( $hook, $media_pages, true ) ) {
            return;
        }

        wp_enqueue_style(
            'agofiles-media',
            AGOFILES_URL . 'assets/css/media-library.css',
            [],
            AGOFILES_VERSION
        );

        wp_enqueue_script(
            'agofiles-media',
            AGOFILES_URL . 'assets/js/media-library.js',
            [ 'jquery' ],
            AGOFILES_VERSION,
            true
        );

        wp_localize_script( 'agofiles-media', 'agofilesData', [
            'restUrl'            => rest_url( 'ago-files/v1' ),
            'nonce'              => wp_create_nonce( 'wp_rest' ),
            'taxonomy'           => Taxonomy::TAXONOMY,
            'folders'            => Taxonomy::get_folder_tree(),
            'uncategorizedCount' => Taxonomy::get_uncategorized_count(),
            'totalCount'         => Taxonomy::get_total_count(),
            'i18n'               => [
                'allFiles'      => __( 'All Files', 'ago-files' ),
                'uncategorized' => __( 'Uncategorized', 'ago-files' ),
                'newFolder'     => __( 'New Folder', 'ago-files' ),
                'rename'        => __( 'Rename', 'ago-files' ),
                'delete'        => __( 'Delete', 'ago-files' ),
                'deleteConfirm' => __( 'Delete this folder? Files will not be deleted.', 'ago-files' ),
                'moveTo'        => __( 'Move to folder', 'ago-files' ),
                'folderName'    => __( 'Folder name', 'ago-files' ),
                'noFiles'       => __( 'No files in this folder.', 'ago-files' ),
                'createSub'     => __( 'Create Subfolder', 'ago-files' ),
                'bulkMove'      => __( 'Move selected to...', 'ago-files' ),
            ],
        ] );
    }

    /**
     * Filter list view (upload.php) by folder.
     */
    public static function filter_by_folder( \WP_Query $query ): void {
        global $pagenow;
        if ( ! is_admin() || 'upload.php' !== $pagenow || ! $query->is_main_query() ) {
            return;
        }

        // Read-only filter from a folder link in the media list; no state change, nonce not required.
        $folder = isset( $_GET['agofiles_folder'] ) ? sanitize_text_field( wp_unslash( $_GET['agofiles_folder'] ) ) : '';
        if ( '' === $folder ) {
            return;
        }

        if ( 'uncategorized' === $folder ) {
            $query->set( 'tax_query', [ [
                'taxonomy' => Taxonomy::TAXONOMY,
                'operator' => 'NOT EXISTS',
            ] ] );
        } else {
            $query->set( 'tax_query', [ [
                'taxonomy' => Taxonomy::TAXONOMY,
                'field'    => 'term_id',
                'terms'    => (int) $folder,
            ] ] );
        }
    }

    /**
     * Filter grid view (AJAX) by folder.
     */
    public static function filter_grid_by_folder( array $args ): array {
        // Read-only filter applied to the media grid query; no state change, nonce not required.
        $folder = isset( $_REQUEST['agofiles_folder'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['agofiles_folder'] ) ) : '';
        if ( '' === $folder ) {
            return $args;
        }

        if ( 'uncategorized' === $folder ) {
            $args['tax_query'] = [ [
                'taxonomy' => Taxonomy::TAXONOMY,
                'operator' => 'NOT EXISTS',
            ] ];
        } else {
            $args['tax_query'] = [ [
                'taxonomy' => Taxonomy::TAXONOMY,
                'field'    => 'term_id',
                'terms'    => (int) $folder,
            ] ];
        }

        return $args;
    }
}
