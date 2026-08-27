<?php
/**
 * Plugin Name: aGo Files
 * Plugin URI:  https://ago.cl/herramientas/
 * Description: Virtual folder organization for WordPress media library using a custom taxonomy. Lightweight alternative to HappyFiles/FileBird.
 * Version:     1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author:      aGo Lab
 * Author URI:  https://ago.cl/
 * License:     GPL-2.0-or-later
 * Text Domain: ago-files
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'AGOFILES_VERSION', '1.0.0' );
define( 'AGOFILES_FILE', __FILE__ );
define( 'AGOFILES_PATH', plugin_dir_path( __FILE__ ) );
define( 'AGOFILES_URL', plugin_dir_url( __FILE__ ) );

// PSR-4 Autoloader
spl_autoload_register( function ( string $class ): void {
    $prefix = 'AgoLab\\Files\\';
    if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
        return;
    }
    $relative = substr( $class, strlen( $prefix ) );
    $file     = AGOFILES_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

// Boot
add_action( 'plugins_loaded', [ AgoLab\Files\Plugin::class, 'instance' ] );
