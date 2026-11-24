<?php

namespace AgoLab\Files;

defined( 'ABSPATH' ) || exit;

class Plugin {

    private static ?self $instance = null;

    public const REST_NAMESPACE = 'agofiles/v1';

    private const MENU_SLUG = 'agofiles-tools';

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', [ Taxonomy::class, 'init' ] );
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        MediaLibrary::init();
    }

    public function register_admin_menu(): void {
        /*
         * Other plugins from the same author may already have added a shared
         * parent menu. When one is there the page goes under it, so the
         * dashboard does not grow a second top level entry for the same set of
         * tools. When there is none, this plugin creates its own parent under
         * its own prefix and never defines the shared slug.
         */
        $parent = 'agolab-tools';

        if ( empty( $GLOBALS['admin_page_hooks'][ $parent ] ) ) {
            $parent = self::MENU_SLUG;

            add_menu_page(
                __( 'aGo Tools', 'ago-files' ),
                __( 'aGo Tools', 'ago-files' ),
                'manage_options',
                $parent,
                '__return_null',
                'dashicons-hammer',
                81
            );
        }

        add_submenu_page(
            $parent,
            __( 'aGo Files', 'ago-files' ),
            __( 'Files', 'ago-files' ),
            'manage_options',
            'agofiles',
            [ Admin\Page::class, 'render' ]
        );

        remove_submenu_page( $parent, $parent );
    }

    public function register_rest_routes(): void {
        register_rest_route( self::REST_NAMESPACE, '/folders', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_get_folders' ],
                'permission_callback' => static fn () => current_user_can( 'upload_files' ),
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_create_folder' ],
                'permission_callback' => static fn () => current_user_can( Taxonomy::cap( 'manage_terms' ) ),
            ],
        ] );

        register_rest_route( self::REST_NAMESPACE, '/folders/(?P<id>\d+)', [
            [
                'methods'             => 'PUT',
                'callback'            => [ $this, 'handle_rename_folder' ],
                'permission_callback' => static fn () => current_user_can( Taxonomy::cap( 'edit_terms' ) ),
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ $this, 'handle_delete_folder' ],
                'permission_callback' => static fn () => current_user_can( Taxonomy::cap( 'delete_terms' ) ),
            ],
        ] );

        // Adds a folder to each file and keeps the folders it already had.
        register_rest_route( self::REST_NAMESPACE, '/assign', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_assign' ],
            'permission_callback' => static fn () => current_user_can( Taxonomy::cap( 'assign_terms' ) ),
        ] );

        // Leaves each file in one folder only, or in none when folder_id is 0.
        register_rest_route( self::REST_NAMESPACE, '/move', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_move' ],
            'permission_callback' => static fn () => current_user_can( Taxonomy::cap( 'assign_terms' ) ),
        ] );

        register_rest_route( self::REST_NAMESPACE, '/settings', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_get_settings' ],
                'permission_callback' => static fn () => current_user_can( 'manage_options' ),
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_save_settings' ],
                'permission_callback' => static fn () => current_user_can( 'manage_options' ),
            ],
        ] );
    }

    private static function error( string $code, string $message, int $status ): \WP_Error {
        return new \WP_Error( $code, $message, [ 'status' => $status ] );
    }

    public function handle_get_folders(): \WP_REST_Response {
        return new \WP_REST_Response( [
            'folders'            => Taxonomy::get_folder_tree(),
            'uncategorizedCount' => Taxonomy::get_uncategorized_count(),
            'totalCount'         => Taxonomy::get_total_count(),
        ] );
    }

    /** @return \WP_REST_Response|\WP_Error */
    public function handle_create_folder( \WP_REST_Request $request ) {
        $name   = sanitize_text_field( (string) ( $request->get_param( 'name' ) ?? '' ) );
        $parent = absint( $request->get_param( 'parent' ) ?? 0 );

        if ( '' === $name ) {
            return self::error( 'agofiles_name_required', __( 'The folder name is required.', 'ago-files' ), 400 );
        }

        if ( $parent && ! Taxonomy::is_folder( $parent ) ) {
            return self::error( 'agofiles_invalid_parent', __( 'The parent folder does not exist.', 'ago-files' ), 400 );
        }

        $result = wp_insert_term( $name, Taxonomy::TAXONOMY, [ 'parent' => $parent ] );

        if ( is_wp_error( $result ) ) {
            return self::error( 'agofiles_create_failed', $result->get_error_message(), 400 );
        }

        $term = get_term( (int) $result['term_id'], Taxonomy::TAXONOMY );

        return new \WP_REST_Response( [
            'id'     => $term->term_id,
            'name'   => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
            'slug'   => $term->slug,
            'count'  => 0,
            'parent' => $parent,
        ], 201 );
    }

    /** @return \WP_REST_Response|\WP_Error */
    public function handle_rename_folder( \WP_REST_Request $request ) {
        $id   = absint( $request->get_param( 'id' ) );
        $name = sanitize_text_field( (string) ( $request->get_param( 'name' ) ?? '' ) );

        if ( ! Taxonomy::is_folder( $id ) ) {
            return self::error( 'agofiles_not_found', __( 'The folder does not exist.', 'ago-files' ), 404 );
        }

        if ( '' === $name ) {
            return self::error( 'agofiles_name_required', __( 'The folder name is required.', 'ago-files' ), 400 );
        }

        $result = wp_update_term( $id, Taxonomy::TAXONOMY, [ 'name' => $name ] );

        if ( is_wp_error( $result ) ) {
            return self::error( 'agofiles_rename_failed', $result->get_error_message(), 400 );
        }

        return new \WP_REST_Response( [ 'success' => true, 'id' => $id, 'name' => $name ] );
    }

    /** @return \WP_REST_Response|\WP_Error */
    public function handle_delete_folder( \WP_REST_Request $request ) {
        $id = absint( $request->get_param( 'id' ) );

        if ( ! Taxonomy::is_folder( $id ) ) {
            return self::error( 'agofiles_not_found', __( 'The folder does not exist.', 'ago-files' ), 404 );
        }

        $result = wp_delete_term( $id, Taxonomy::TAXONOMY );

        if ( is_wp_error( $result ) || ! $result ) {
            return self::error( 'agofiles_delete_failed', __( 'The folder could not be deleted.', 'ago-files' ), 400 );
        }

        return new \WP_REST_Response( [ 'success' => true ] );
    }

    /** @return \WP_REST_Response|\WP_Error */
    public function handle_assign( \WP_REST_Request $request ) {
        return $this->set_folder( $request, true );
    }

    /** @return \WP_REST_Response|\WP_Error */
    public function handle_move( \WP_REST_Request $request ) {
        return $this->set_folder( $request, false );
    }

    /**
     * Checks every file before changing any: each one must be a Media Library
     * item the current user may edit, so a request that mixes in someone
     * else's file changes nothing.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    private function set_folder( \WP_REST_Request $request, bool $append ) {
        $ids       = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $request->get_param( 'attachment_ids' ) ?? [] ) ) ) ) );
        $folder_id = absint( $request->get_param( 'folder_id' ) ?? 0 );

        if ( empty( $ids ) ) {
            return self::error( 'agofiles_files_required', __( 'Select at least one file.', 'ago-files' ), 400 );
        }

        if ( $folder_id && ! Taxonomy::is_folder( $folder_id ) ) {
            return self::error( 'agofiles_not_found', __( 'The folder does not exist.', 'ago-files' ), 404 );
        }

        if ( ! $folder_id && $append ) {
            return self::error( 'agofiles_folder_required', __( 'Choose a folder.', 'ago-files' ), 400 );
        }

        foreach ( $ids as $id ) {
            if ( 'attachment' !== get_post_type( $id ) ) {
                return self::error( 'agofiles_not_a_file', __( 'Only Media Library files can be placed in folders.', 'ago-files' ), 400 );
            }
            if ( ! current_user_can( 'edit_post', $id ) ) {
                return self::error( 'agofiles_forbidden', __( 'You are not allowed to edit one of the selected files.', 'ago-files' ), 403 );
            }
        }

        foreach ( $ids as $id ) {
            wp_set_object_terms( $id, $folder_id ? [ $folder_id ] : [], Taxonomy::TAXONOMY, $append );
        }

        return new \WP_REST_Response( [ 'success' => true, 'count' => count( $ids ) ] );
    }

    public function handle_get_settings(): \WP_REST_Response {
        return new \WP_REST_Response( self::settings() );
    }

    public function handle_save_settings( \WP_REST_Request $request ): \WP_REST_Response {
        $input    = (array) $request->get_json_params();
        $settings = [];

        foreach ( self::defaults() as $key => $default ) {
            $settings[ $key ] = isset( $input[ $key ] ) ? rest_sanitize_boolean( $input[ $key ] ) : $default;
        }

        update_option( 'agofiles_settings', $settings );

        return new \WP_REST_Response( [ 'saved' => true, 'settings' => $settings ] );
    }

    public function enqueue_assets( string $hook ): void {
        if ( ! str_ends_with( $hook, '_page_agofiles' ) ) {
            return;
        }

        wp_enqueue_style(
            'agofiles-admin',
            AGOFILES_URL . 'assets/css/admin.css',
            [],
            AGOFILES_VERSION
        );

        wp_enqueue_script(
            'agofiles-admin',
            AGOFILES_URL . 'assets/js/admin.js',
            [],
            AGOFILES_VERSION,
            true
        );

        wp_localize_script( 'agofiles-admin', 'agofilesAdmin', [
            'restUrl'  => rest_url( self::REST_NAMESPACE ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'settings' => self::settings(),
            'folders'  => Taxonomy::get_folder_tree(),
            'i18n'     => [
                'saved'         => __( 'Settings saved.', 'ago-files' ),
                'saveError'     => __( 'The settings could not be saved.', 'ago-files' ),
                'created'       => __( 'Folder created.', 'ago-files' ),
                'renamed'       => __( 'Folder renamed.', 'ago-files' ),
                'deleted'       => __( 'Folder deleted.', 'ago-files' ),
                'error'         => __( 'Something went wrong. Please try again.', 'ago-files' ),
                'rename'        => __( 'Rename', 'ago-files' ),
                'delete'        => __( 'Delete', 'ago-files' ),
                'noParent'      => __( '(No parent)', 'ago-files' ),
                /* translators: %s: folder name */
                'deleteConfirm' => __( 'Delete the folder "%s"? The files in it are not deleted.', 'ago-files' ),
            ],
        ] );
    }

    /** @return array<string, bool> */
    public static function defaults(): array {
        return [
            'show_sidebar'       => true,
            'show_folder_column' => true,
        ];
    }

    /** @return array<string, bool> */
    public static function settings(): array {
        return wp_parse_args(
            (array) get_option( 'agofiles_settings', [] ),
            self::defaults()
        );
    }
}
