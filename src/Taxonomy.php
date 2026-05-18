<?php

namespace AgoLab\Files;

defined( 'ABSPATH' ) || exit;

class Taxonomy {

    const TAXONOMY = 'ago_media_folder';

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
            'show_in_rest'          => true,
            'show_admin_column'     => true,
            'query_var'             => false,
            'rewrite'               => false,
            'update_count_callback' => '_update_generic_term_count',
        ] );
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
                    'name'     => $term->name,
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
        $taxonomy = self::TAXONOMY;
        return (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
             LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = '{$taxonomy}'
             WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND tt.term_taxonomy_id IS NULL"
        );
    }

    public static function get_total_count(): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit'"
        );
    }
}
