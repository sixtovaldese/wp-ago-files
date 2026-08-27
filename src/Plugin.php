<?php

namespace AgoLab\Files;

defined( 'ABSPATH' ) || exit;

class Plugin {

    private static ?self $instance = null;

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

        // Init media library hooks.
        MediaLibrary::init();
    }

    /* ───── Admin menu (smart pattern) ───── */

    public function register_admin_menu(): void {
        if ( empty( $GLOBALS['admin_page_hooks']['agolab-tools'] ) ) {
            add_menu_page(
                __( 'aGo Tools', 'ago-files' ),
                __( 'aGo Tools', 'ago-files' ),
                'manage_options',
                'agolab-tools',
                '__return_null',
                'dashicons-hammer',
                81
            );
        }

        add_submenu_page(
            'agolab-tools',
            __( 'aGo Files', 'ago-files' ),
            __( 'Files', 'ago-files' ),
            'manage_options',
            'agofiles',
            [ Admin\Page::class, 'render' ]
        );

        remove_submenu_page( 'agolab-tools', 'agolab-tools' );
    }

    /* ───── REST routes ───── */

    public function register_rest_routes(): void {
        $admin_check = function () {
            return current_user_can( 'upload_files' );
        };

        // GET folders
        register_rest_route( 'ago-files/v1', '/folders', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_get_folders' ],
            'permission_callback' => $admin_check,
        ] );

        // POST create folder
        register_rest_route( 'ago-files/v1', '/folders', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_create_folder' ],
            'permission_callback' => $admin_check,
        ] );

        // PUT rename folder
        register_rest_route( 'ago-files/v1', '/folders/(?P<id>\d+)', [
            'methods'             => 'PUT',
            'callback'            => [ $this, 'handle_rename_folder' ],
            'permission_callback' => $admin_check,
        ] );

        // DELETE folder
        register_rest_route( 'ago-files/v1', '/folders/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [ $this, 'handle_delete_folder' ],
            'permission_callback' => $admin_check,
        ] );

        // POST assign files to folder
        register_rest_route( 'ago-files/v1', '/assign', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_assign' ],
            'permission_callback' => $admin_check,
        ] );

        // POST move files to folder (remove from old, add to new)
        register_rest_route( 'ago-files/v1', '/move', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_move' ],
            'permission_callback' => $admin_check,
        ] );

        // GET counts
        register_rest_route( 'ago-files/v1', '/count', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_count' ],
            'permission_callback' => $admin_check,
        ] );

        // Settings
        register_rest_route( 'ago-files/v1', '/settings', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_get_settings' ],
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_save_settings' ],
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            ],
        ] );
    }

    /* ───── REST handlers: Folders ───── */

    public function handle_get_folders(): \WP_REST_Response {
        return new \WP_REST_Response( [
            'folders'            => Taxonomy::get_folder_tree(),
            'uncategorizedCount' => Taxonomy::get_uncategorized_count(),
            'totalCount'         => Taxonomy::get_total_count(),
        ] );
    }

    public function handle_create_folder( \WP_REST_Request $request ): \WP_REST_Response {
        $name   = sanitize_text_field( $request->get_param( 'name' ) ?? '' );
        $parent = absint( $request->get_param( 'parent' ) ?? 0 );

        if ( '' === $name ) {
            return new \WP_REST_Response( [ 'error' => 'Name is required.' ], 400 );
        }

        $result = wp_insert_term( $name, Taxonomy::TAXONOMY, [ 'parent' => $parent ] );

        if ( is_wp_error( $result ) ) {
            return new \WP_REST_Response( [ 'error' => $result->get_error_message() ], 400 );
        }

        $term = get_term( $result['term_id'], Taxonomy::TAXONOMY );

        return new \WP_REST_Response( [
            'id'     => $term->term_id,
            'name'   => $term->name,
            'slug'   => $term->slug,
            'count'  => 0,
            'parent' => $parent,
        ], 201 );
    }

    public function handle_rename_folder( \WP_REST_Request $request ): \WP_REST_Response {
        $id   = (int) $request->get_param( 'id' );
        $name = sanitize_text_field( $request->get_param( 'name' ) ?? '' );

        if ( '' === $name ) {
            return new \WP_REST_Response( [ 'error' => 'Name is required.' ], 400 );
        }

        $result = wp_update_term( $id, Taxonomy::TAXONOMY, [ 'name' => $name ] );

        if ( is_wp_error( $result ) ) {
            return new \WP_REST_Response( [ 'error' => $result->get_error_message() ], 400 );
        }

        return new \WP_REST_Response( [ 'success' => true, 'id' => $id, 'name' => $name ] );
    }

    public function handle_delete_folder( \WP_REST_Request $request ): \WP_REST_Response {
        $id     = (int) $request->get_param( 'id' );
        $result = wp_delete_term( $id, Taxonomy::TAXONOMY );

        if ( is_wp_error( $result ) ) {
            return new \WP_REST_Response( [ 'error' => $result->get_error_message() ], 400 );
        }

        return new \WP_REST_Response( [ 'success' => true ] );
    }

    /* ───── REST handlers: Assign / Move ───── */

    public function handle_assign( \WP_REST_Request $request ): \WP_REST_Response {
        $attachment_ids = array_map( 'absint', (array) ( $request->get_param( 'attachment_ids' ) ?? [] ) );
        $folder_id      = absint( $request->get_param( 'folder_id' ) ?? 0 );

        if ( empty( $attachment_ids ) || ! $folder_id ) {
            return new \WP_REST_Response( [ 'error' => 'attachment_ids and folder_id are required.' ], 400 );
        }

        foreach ( $attachment_ids as $att_id ) {
            wp_set_object_terms( $att_id, $folder_id, Taxonomy::TAXONOMY, true );
        }

        return new \WP_REST_Response( [ 'success' => true, 'assigned' => count( $attachment_ids ) ] );
    }

    public function handle_move( \WP_REST_Request $request ): \WP_REST_Response {
        $attachment_ids = array_map( 'absint', (array) ( $request->get_param( 'attachment_ids' ) ?? [] ) );
        $folder_id      = absint( $request->get_param( 'folder_id' ) ?? 0 );

        if ( empty( $attachment_ids ) || ! $folder_id ) {
            return new \WP_REST_Response( [ 'error' => 'attachment_ids and folder_id are required.' ], 400 );
        }

        foreach ( $attachment_ids as $att_id ) {
            // Replace (not append), removes from old folders
            wp_set_object_terms( $att_id, $folder_id, Taxonomy::TAXONOMY, false );
        }

        return new \WP_REST_Response( [ 'success' => true, 'moved' => count( $attachment_ids ) ] );
    }

    public function handle_count(): \WP_REST_Response {
        return new \WP_REST_Response( [
            'uncategorized' => Taxonomy::get_uncategorized_count(),
            'total'         => Taxonomy::get_total_count(),
        ] );
    }

    /* ───── REST handlers: Settings ───── */

    public function handle_get_settings(): \WP_REST_Response {
        return new \WP_REST_Response( $this->get_settings() );
    }

    public function handle_save_settings( \WP_REST_Request $request ): \WP_REST_Response {
        $input    = $request->get_json_params();
        $defaults = self::defaults();
        $settings = [];

        $settings['show_sidebar']      = isset( $input['show_sidebar'] ) ? (bool) $input['show_sidebar'] : $defaults['show_sidebar'];
        $settings['show_folder_column'] = isset( $input['show_folder_column'] ) ? (bool) $input['show_folder_column'] : $defaults['show_folder_column'];

        update_option( 'agofiles_settings', $settings );

        return new \WP_REST_Response( [ 'saved' => true, 'settings' => $settings ] );
    }

    /* ───── Assets (settings page) ───── */

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
            'restUrl'  => rest_url( 'ago-files/v1' ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'settings' => $this->get_settings(),
            'folders'  => Taxonomy::get_folder_tree(),
        ] );
    }

    /* ───── Settings helpers ───── */

    public static function defaults(): array {
        return [
            'show_sidebar'       => true,
            'show_folder_column' => true,
        ];
    }

    private function get_settings(): array {
        return wp_parse_args(
            get_option( 'agofiles_settings', [] ),
            self::defaults()
        );
    }
}
