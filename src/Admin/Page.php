<?php

namespace AgoLab\Files\Admin;

defined( 'ABSPATH' ) || exit;

class Page {

    public static function render(): void {
        ?>
        <div class="wrap">
            <h1>
                <img src="<?php echo esc_url( AGOFILES_URL . 'assets/img/agolab.webp' ); ?>" alt="aGo Lab" style="height:28px;width:auto;vertical-align:middle;margin-right:8px">
                <?php esc_html_e( 'aGo Files', 'ago-files' ); ?>
                <span style="font-size:12px;color:#999;margin-left:8px">v<?php echo esc_html( AGOFILES_VERSION ); ?></span>
            </h1>

            <div class="ago-layout">
                <div class="ago-main">

                    <div class="card ago-card">
                        <h2><?php esc_html_e( 'Settings', 'ago-files' ); ?></h2>
                        <p><?php esc_html_e( 'Configure the media folder sidebar behavior. Changes are saved immediately.', 'ago-files' ); ?></p>

                        <div id="ago-files-status" style="display:none"></div>

                        <div class="ago-section">
                            <h3><?php esc_html_e( 'Media Library', 'ago-files' ); ?></h3>

                            <label class="ago-toggle-row">
                                <span class="ago-toggle-label">
                                    <strong><?php esc_html_e( 'Show Folder Sidebar', 'ago-files' ); ?></strong>
                                    <span class="ago-toggle-desc"><?php esc_html_e( 'Display the folder sidebar in the media library', 'ago-files' ); ?></span>
                                </span>
                                <span class="ago-switch">
                                    <input type="checkbox" data-key="show_sidebar">
                                    <span class="ago-slider"></span>
                                </span>
                            </label>

                            <label class="ago-toggle-row">
                                <span class="ago-toggle-label">
                                    <strong><?php esc_html_e( 'Show Folder Column', 'ago-files' ); ?></strong>
                                    <span class="ago-toggle-desc"><?php esc_html_e( 'Show the folder column in the media list view', 'ago-files' ); ?></span>
                                </span>
                                <span class="ago-switch">
                                    <input type="checkbox" data-key="show_folder_column">
                                    <span class="ago-slider"></span>
                                </span>
                            </label>
                        </div>

                        <div class="ago-actions">
                            <button id="ago-save-settings" class="button button-primary" type="button">
                                <?php esc_html_e( 'Save Settings', 'ago-files' ); ?>
                            </button>
                        </div>
                    </div>

                    <div class="card ago-card">
                        <h2><?php esc_html_e( 'Folder Management', 'ago-files' ); ?></h2>
                        <p><?php esc_html_e( 'Create, rename, or delete media folders. You can also manage folders directly from the media library sidebar.', 'ago-files' ); ?></p>

                        <div class="ago-section">
                            <div class="ago-folder-create">
                                <input type="text" id="ago-new-folder-name" placeholder="<?php esc_attr_e( 'New folder name...', 'ago-files' ); ?>" class="regular-text">
                                <select id="ago-new-folder-parent">
                                    <option value="0"><?php esc_html_e( '(No parent)', 'ago-files' ); ?></option>
                                </select>
                                <button id="ago-create-folder" class="button button-secondary" type="button">
                                    <?php esc_html_e( 'Create Folder', 'ago-files' ); ?>
                                </button>
                            </div>
                        </div>

                        <div class="ago-section">
                            <div id="ago-folder-tree-admin" class="ago-folder-tree-admin">
                                <p class="ago-empty-folders" style="display:none"><?php esc_html_e( 'No folders yet. Create one above.', 'ago-files' ); ?></p>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="ago-sidebar">

                    <div class="card ago-card">
                        <h3><?php esc_html_e( 'About', 'ago-files' ); ?></h3>
                        <p style="font-size:13px;color:#666">
                            <?php esc_html_e( 'Virtual folder organization for your WordPress media library. Lightweight and fast.', 'ago-files' ); ?>
                        </p>
                        <ul class="ago-features">
                            <li><?php esc_html_e( 'Drag & drop files into folders', 'ago-files' ); ?></li>
                            <li><?php esc_html_e( 'Hierarchical subfolders', 'ago-files' ); ?></li>
                            <li><?php esc_html_e( 'Works in list and grid view', 'ago-files' ); ?></li>
                            <li><?php esc_html_e( 'Bulk move files', 'ago-files' ); ?></li>
                            <li><?php esc_html_e( 'Right-click context menu', 'ago-files' ); ?></li>
                            <li><?php esc_html_e( 'Uncategorized files filter', 'ago-files' ); ?></li>
                            <li><?php esc_html_e( 'REST API for all operations', 'ago-files' ); ?></li>
                        </ul>
                        <p style="margin-top:12px">
                            <a href="https://ago.cl/herramientas/wordpress/ago-files/docs" target="_blank" rel="noopener">
                                <span class="dashicons dashicons-book" style="vertical-align:middle;margin-right:4px"></span>
                                <?php esc_html_e( 'Documentation', 'ago-files' ); ?>
                            </a>
                        </p>
                    </div>

                    <div class="card ago-card ago-donation">
                        <h3><?php esc_html_e( 'Support Open Source', 'ago-files' ); ?></h3>
                        <p style="font-size:13px;color:#666">
                            <?php esc_html_e( 'If this plugin saves you time, consider supporting our open-source work.', 'ago-files' ); ?>
                        </p>
                        <div class="ago-donation-amounts">
                            <a href="https://paypal.me/sixtovaldes/3" class="ago-amount" target="_blank" rel="noopener">$3</a>
                            <a href="https://paypal.me/sixtovaldes/5" class="ago-amount" target="_blank" rel="noopener">$5</a>
                            <a href="https://paypal.me/sixtovaldes/10" class="ago-amount" target="_blank" rel="noopener">$10</a>
                        </div>
                        <a href="https://paypal.me/sixtovaldes" class="ago-coffee-btn" target="_blank" rel="noopener">
                            <span class="dashicons dashicons-coffee" style="margin-right:6px"></span>
                            <?php esc_html_e( 'Buy us a coffee', 'ago-files' ); ?>
                        </a>
                        <p class="ago-donation-note">
                            <?php esc_html_e( 'Voluntary donation. Thank you!', 'ago-files' ); ?>
                        </p>
                    </div>

                    <div class="ago-footer">
                        <a href="https://ago.cl" target="_blank" rel="noopener" class="ago-footer-logo">
                            <img src="<?php echo esc_url( AGOFILES_URL . 'assets/img/agolab.webp' ); ?>" alt="aGo Lab" style="height:40px;width:auto">
                        </a>
                        <p>
                            <?php
                            echo wp_kses_post(
                                sprintf(
                                    /* translators: 1: heart icon HTML, 2: aGo Lab link HTML */
                                    __( 'Developed with %1$s by %2$s', 'ago-files' ),
                                    '<span style="color:#e25555">&#10084;</span>',
                                    '<a href="https://ago.cl" target="_blank" rel="noopener"><strong>aGo Lab</strong></a>'
                                )
                            );
                            ?>
                        </p>
                        <p style="font-size:11px;color:#999">
                            <?php esc_html_e( 'Building tools for the web, one plugin at a time.', 'ago-files' ); ?>
                        </p>
                    </div>

                </div>
            </div>

        </div>
        <?php
    }
}
