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
        // The Media Library screen, and the editor screens where the media modal opens.
        $media_pages = [ 'upload.php', 'post.php', 'post-new.php' ];
        if ( ! in_array( $hook, $media_pages, true ) ) {
            return;
        }

        if ( ! Plugin::settings()['show_sidebar'] || ! current_user_can( 'upload_files' ) ) {
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
            'restUrl'            => rest_url( Plugin::REST_NAMESPACE ),
            'nonce'              => wp_create_nonce( 'wp_rest' ),
            'folders'            => Taxonomy::get_folder_tree(),
            'uncategorizedCount' => Taxonomy::get_uncategorized_count(),
            'totalCount'         => Taxonomy::get_total_count(),
            'canEditFolders'     => current_user_can( Taxonomy::cap( 'edit_terms' ) ),
            'i18n'               => [
                'folders'       => __( 'Folders', 'ago-files' ),
                'allFiles'      => __( 'All Files', 'ago-files' ),
                'uncategorized' => __( 'Uncategorized', 'ago-files' ),
                'newFolder'     => __( 'New Folder', 'ago-files' ),
                'rename'        => __( 'Rename', 'ago-files' ),
                'delete'        => __( 'Delete', 'ago-files' ),
                'deleteConfirm' => __( 'Delete this folder? Files will not be deleted.', 'ago-files' ),
                'moveTo'        => __( 'Move to folder', 'ago-files' ),
                'folderName'    => __( 'Folder name', 'ago-files' ),
                'createSub'     => __( 'Create Subfolder', 'ago-files' ),
                'bulkMove'      => __( 'Move', 'ago-files' ),
                'error'         => __( 'Something went wrong. Please try again.', 'ago-files' ),
            ],
        ] );
    }

    /**
     * The folder chosen in the sidebar, or '' for all files. It only narrows
     * the list the current user is already allowed to see.
     */
    private static function requested_folder( string $folder ): string {
        if ( 'uncategorized' === $folder ) {
            return $folder;
        }
        return absint( $folder ) ? (string) absint( $folder ) : '';
    }

    /** @return array<int, array<string, mixed>> */
    private static function tax_query( string $folder ): array {
        if ( 'uncategorized' === $folder ) {
            return [ [
                'taxonomy' => Taxonomy::TAXONOMY,
                'operator' => 'NOT EXISTS',
            ] ];
        }
        return [ [
            'taxonomy' => Taxonomy::TAXONOMY,
            'field'    => 'term_id',
            'terms'    => (int) $folder,
        ] ];
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
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $raw    = isset( $_GET['agofiles_folder'] ) ? sanitize_text_field( wp_unslash( $_GET['agofiles_folder'] ) ) : '';
        $folder = self::requested_folder( $raw );
        if ( '' === $folder ) {
            return;
        }

        $query->set( 'tax_query', self::tax_query( $folder ) );
    }

    /**
     * Filter grid view (AJAX) by folder. The media grid sends its parameters
     * inside query[], which is where the folder comes from.
     */
    public static function filter_grid_by_folder( array $args ): array {
        // Read-only filter applied to the media grid query; core checks the request before this filter runs.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $raw    = isset( $_REQUEST['query']['agofiles_folder'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['query']['agofiles_folder'] ) ) : '';
        $folder = self::requested_folder( $raw );
        if ( '' === $folder ) {
            return $args;
        }

        // Filtering by folder is what the plugin does, and it only runs on
        // the media grid in the admin.
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
        $args['tax_query'] = self::tax_query( $folder );

        return $args;
    }
}
