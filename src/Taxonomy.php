<?php

namespace AgoLab\Files;

defined( 'ABSPATH' ) || exit;

class Taxonomy {

    const TAXONOMY = 'agofiles_media_folder';

    /**
     * Capabilities of the folder taxonomy. Anyone who can upload files may
     * create folders and file their own uploads; renaming and deleting a
     * folder changes how the whole team finds its files, so it takes the same
     * capability WordPress asks for to manage categories.
     */
    private const CAPS = [
        'manage_terms' => 'upload_files',
        'assign_terms' => 'upload_files',
        'edit_terms'   => 'manage_categories',
        'delete_terms' => 'manage_categories',
    ];

    public static function init(): void {
        register_taxonomy( self::TAXONOMY, 'attachment', [
            'labels' => [
                'name'          => __( 'Media Folders', 'ago-files' ),
                'singular_name' => __( 'Media Folder', 'ago-files' ),
                'add_new_item'  => __( 'Add New Folder', 'ago-files' ),
                'new_item_name' => __( 'New Folder Name', 'ago-files' ),
                'edit_item'     => __( 'Edit Folder', 'ago-files' ),
                'search_items'  => __( 'Search Folders', 'ago-files' ),
                'parent_item'   => __( 'Parent Folder', 'ago-files' ),
            ],
            'hierarchical'          => true,
            'public'                => false,
            'show_ui'               => false,
            // The plugin has its own routes; the core one would list folder names to anyone.
            'show_in_rest'          => false,
            'show_admin_column'     => (bool) Plugin::settings()['show_folder_column'],
            'query_var'             => false,
            'rewrite'               => false,
            'capabilities'          => self::CAPS,
            'update_count_callback' => '_update_generic_term_count',
        ] );
    }

    public static function cap( string $action ): string {
        return self::CAPS[ $action ];
    }

    public static function is_folder( int $term_id ): bool {
        return $term_id > 0 && (bool) term_exists( $term_id, self::TAXONOMY );
    }

    public static function get_folder_tree(): array {
        $terms = get_terms( [
            'taxonomy'   => self::TAXONOMY,
            'hide_empty' => false,
            'orderby'    => 'name',
        ] );
        if ( is_wp_error( $terms ) ) {
            return [];
        }
        return self::build_tree( $terms );
    }

    private static function build_tree( array $terms, int $parent = 0 ): array {
        $tree = [];
        foreach ( $terms as $term ) {
            if ( (int) $term->parent === $parent ) {
                $node = [
                    'id'       => $term->term_id,
                    // WordPress stores "&" as "&amp;"; the scripts print names as text.
                    'name'     => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
                    'slug'     => $term->slug,
                    'count'    => (int) $term->count,
                    'parent'   => $parent,
                    'children' => self::build_tree( $terms, $term->term_id ),
                ];
                $tree[] = $node;
            }
        }
        return $tree;
    }

    public static function get_uncategorized_count(): int {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(p.ID) FROM {$wpdb->posts} p
                 WHERE p.post_type = 'attachment' AND p.post_status = 'inherit'
                 AND NOT EXISTS (
                     SELECT 1 FROM {$wpdb->term_relationships} tr
                     INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                     WHERE tr.object_id = p.ID AND tt.taxonomy = %s
                 )",
                self::TAXONOMY
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery
    }

    public static function get_total_count(): int {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery
        return (int) $wpdb->get_var(
            "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit'"
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery
    }
}
