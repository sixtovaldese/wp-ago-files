<?php
/**
 * Smoke tests for aGo Files.
 *
 * Covers what the WordPress.org review rules of 2026 ask for (no bundled
 * translations, identifiers with a single prefix token, own parent menu, no
 * inline script or style, translatable messages) and the security of every
 * REST route: who may read, create, rename and delete folders, and that files
 * are only moved by users who may edit each of them. It also checks that the
 * folder filter really filters, that both settings do something, that folder
 * names are not published through the core REST API, and that uninstall
 * removes the folders.
 *
 * Run with the plugin deployed:
 *   docker compose run --rm wpcli eval-file \
 *     //var/www/html/wp-content/plugins/ago-files/tests/smoke.php
 */

use AgoLab\Files\Admin\Page;
use AgoLab\Files\MediaLibrary;
use AgoLab\Files\Plugin;
use AgoLab\Files\Taxonomy;

class AgoFilesSmoke {

    public static int $ok = 0;
    public static int $fail = 0;

    private const NS = '/agofiles/v1';

    /** @var array<string, int> */
    private static array $users = [];

    /** @var int[] */
    private static array $attachments = [];

    /** @var int[] */
    private static array $terms = [];

    public static function check( string $name, bool $cond, string $detail = '' ): void {
        if ( $cond ) {
            self::$ok++;
            echo "  ok    {$name}\n";
        } else {
            self::$fail++;
            echo "  FALLA {$name}" . ( $detail ? " ({$detail})" : '' ) . "\n";
        }
    }

    public static function run(): void {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/user.php';

        $saved_settings = get_option( 'agofiles_settings', null );

        try {
            self::users();
            self::packaging();
            self::identifiers();
            self::admin_menu();
            self::page();
            self::folder_routes();
            self::file_routes();
            self::settings_route();
            self::filters();
            self::settings_take_effect();
            self::not_public();
            self::messages();
        } finally {
            wp_set_current_user( 0 );
            foreach ( self::$attachments as $id ) {
                wp_delete_attachment( $id, true );
            }
            foreach ( self::$terms as $id ) {
                if ( term_exists( $id, Taxonomy::TAXONOMY ) ) {
                    wp_delete_term( $id, Taxonomy::TAXONOMY );
                }
            }
            foreach ( self::$users as $id ) {
                wp_delete_user( $id );
            }
            if ( null === $saved_settings ) {
                delete_option( 'agofiles_settings' );
            } else {
                update_option( 'agofiles_settings', $saved_settings );
            }
        }

        // Last, because it removes every folder of the site.
        self::uninstall();

        echo "\n" . self::$ok . ' ok, ' . self::$fail . " fallas\n";
        if ( self::$fail ) {
            exit( 1 );
        }
    }

    private static function users(): void {
        $admin = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
        foreach ( [ 'subscriber', 'contributor', 'author', 'editor' ] as $role ) {
            self::$users[ $role ] = (int) wp_insert_user( [
                'user_login' => 'agofiles_smoke_' . $role . '_' . wp_rand(),
                'user_pass'  => wp_generate_password(),
                'role'       => $role,
            ] );
        }
        $GLOBALS['agofiles_smoke_admin'] = (int) $admin[0];
    }

    private static function admin(): int {
        return (int) $GLOBALS['agofiles_smoke_admin'];
    }

    private static function attachment( int $author ): int {
        $id = (int) wp_insert_attachment( [
            'post_title'     => 'agofiles smoke ' . wp_rand(),
            'post_mime_type' => 'image/png',
            'post_status'    => 'inherit',
            'post_author'    => $author,
        ] );
        self::$attachments[] = $id;
        return $id;
    }

    private static function folder( string $name, int $parent = 0 ): int {
        $t = wp_insert_term( $name . ' ' . wp_rand(), Taxonomy::TAXONOMY, [ 'parent' => $parent ] );
        $id = is_wp_error( $t ) ? 0 : (int) $t['term_id'];
        self::$terms[] = $id;
        return $id;
    }

    private static function call( string $method, string $route, ?array $body, int $user ): WP_REST_Response {
        wp_set_current_user( $user );
        $request = new WP_REST_Request( $method, self::NS . $route );
        if ( null !== $body ) {
            $request->set_header( 'Content-Type', 'application/json' );
            $request->set_body( wp_json_encode( $body ) );
        }
        $response = rest_do_request( $request );
        wp_set_current_user( 0 );
        return $response;
    }

    /** @return int[] */
    private static function folders_of( int $attachment ): array {
        clean_object_term_cache( $attachment, 'attachment' );
        $ids = wp_get_object_terms( $attachment, Taxonomy::TAXONOMY, [ 'fields' => 'ids' ] );
        return is_wp_error( $ids ) ? [] : array_map( 'intval', $ids );
    }

    /** @return string[] */
    private static function php_files(): array {
        $files = [ AGOFILES_FILE, AGOFILES_PATH . 'uninstall.php' ];
        $it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( AGOFILES_PATH . 'src' ) );
        foreach ( $it as $f ) {
            if ( $f->isFile() && 'php' === $f->getExtension() ) {
                $files[] = $f->getPathname();
            }
        }
        return $files;
    }

    private static function packaging(): void {
        $data = get_plugin_data( AGOFILES_FILE, false, false );
        self::check( 'el paquete no lleva carpeta languages', ! is_dir( AGOFILES_PATH . 'languages' ) );
        self::check( 'el encabezado no declara Domain Path', '' === $data['DomainPath'], $data['DomainPath'] );
        self::check( 'el text domain es el slug', 'ago-files' === $data['TextDomain'] );
        self::check( 'el nombre del plugin arma el slug ago-files', 'ago-files' === sanitize_title( $data['Name'] ), sanitize_title( $data['Name'] ) );

        $source = '';
        foreach ( self::php_files() as $file ) {
            $source .= file_get_contents( $file );
        }
        self::check( 'nadie llama load_plugin_textdomain', ! str_contains( $source, 'load_plugin_textdomain' ) );

        $readme = (string) file_get_contents( AGOFILES_PATH . 'readme.txt' );
        self::check( 'el readme declara Tested up to 7.1', (bool) preg_match( '/^Tested up to:\s*7\.1\s*$/m', $readme ) );
        self::check( 'el readme tiene Stable tag numerico', (bool) preg_match( '/^Stable tag:\s*\d+\.\d+\.\d+\s*$/m', $readme ) );
        self::check( 'el readme tiene External services y Privacy', str_contains( $readme, '== External services ==' ) && str_contains( $readme, '== Privacy ==' ) );
    }

    private static function identifiers(): void {
        $routes = array_keys( rest_get_server()->get_routes() );
        $own    = array_values( array_filter( $routes, static fn ( $r ) => str_starts_with( $r, self::NS ) ) );
        $old    = array_filter( $routes, static fn ( $r ) => str_starts_with( $r, '/ago-files/' ) );
        self::check( 'las rutas REST usan el namespace agofiles/v1', count( $own ) >= 5, implode( ',', $own ) );
        self::check( 'no queda el namespace ago-files/v1', [] === $old, implode( ',', $old ) );

        wp_set_current_user( self::admin() );
        Plugin::instance()->enqueue_assets( 'agofiles-tools_page_agofiles' );
        $data = (string) wp_scripts()->get_data( 'agofiles-admin', 'data' );
        self::check( 'el script de ajustes se encola con el handle agofiles-admin', wp_script_is( 'agofiles-admin', 'enqueued' ) && wp_style_is( 'agofiles-admin', 'enqueued' ) );
        self::check( 'el objeto JS de ajustes apunta a agofiles/v1', str_ends_with( (string) ( json_decode( (string) preg_replace( '/^var agofilesAdmin = (.*);$/s', '$1', $data ), true )['restUrl'] ?? '' ), '/agofiles/v1' ), substr( $data, 0, 160 ) );
        preg_match( '/"nonce":"([a-f0-9]+)"/', $data, $m );
        self::check( 'el nonce entregado al JS es de wp_rest', ! empty( $m[1] ) && false !== wp_verify_nonce( $m[1], 'wp_rest' ) );
        self::check( 'los textos del JS de ajustes se entregan traducibles', str_contains( $data, '"i18n":{' ) );

        MediaLibrary::enqueue_on_media_pages( 'upload.php' );
        $media = (string) wp_scripts()->get_data( 'agofiles-media', 'data' );
        self::check( 'el script de la biblioteca apunta a agofiles/v1', str_ends_with( (string) ( json_decode( (string) preg_replace( '/^var agofilesData = (.*);$/s', '$1', $media ), true )['restUrl'] ?? '' ), '/agofiles/v1' ), substr( $media, 0, 160 ) );
        wp_set_current_user( 0 );
    }

    private static function reset_menu(): void {
        $GLOBALS['admin_page_hooks']  = [];
        $GLOBALS['menu']              = [];
        $GLOBALS['submenu']           = [];
        $GLOBALS['_registered_pages'] = [];
        $GLOBALS['_parent_pages']     = [];
    }

    /** @return string[] */
    private static function submenu_slugs( string $parent ): array {
        return array_values( array_map( static fn ( $item ) => $item[2], $GLOBALS['submenu'][ $parent ] ?? [] ) );
    }

    private static function admin_menu(): void {
        wp_set_current_user( self::admin() );

        self::reset_menu();
        Plugin::instance()->register_admin_menu();
        self::check( 'sin el menu compartido crea su propio padre agofiles-tools', isset( $GLOBALS['admin_page_hooks']['agofiles-tools'] ) );
        self::check( 'nunca define el slug compartido agolab-tools', ! isset( $GLOBALS['admin_page_hooks']['agolab-tools'] ) );
        self::check( 'la pagina agofiles cuelga de agofiles-tools', [ 'agofiles' ] === self::submenu_slugs( 'agofiles-tools' ), implode( ',', self::submenu_slugs( 'agofiles-tools' ) ) );

        self::reset_menu();
        add_menu_page( 'aGo Tools', 'aGo Tools', 'manage_options', 'agolab-tools', '__return_null' );
        Plugin::instance()->register_admin_menu();
        self::check( 'con el menu compartido se engancha a el', in_array( 'agofiles', self::submenu_slugs( 'agolab-tools' ), true ) );
        self::check( 'con el menu compartido no crea un segundo padre', ! isset( $GLOBALS['admin_page_hooks']['agofiles-tools'] ) );

        self::reset_menu();
        wp_set_current_user( 0 );
    }

    private static function page(): void {
        wp_set_current_user( self::admin() );
        ob_start();
        Page::render();
        $html = (string) ob_get_clean();
        wp_set_current_user( 0 );

        self::check( 'la pagina no imprime script en linea', ! str_contains( $html, '<script' ) );
        self::check( 'la pagina no imprime estilos en linea ni link directo', ! str_contains( $html, '<style' ) && ! str_contains( $html, '<link' ) );
        self::check( 'el sidebar trae Quick links, About, donacion y pie', str_contains( $html, 'Quick links' ) && str_contains( $html, 'About' ) && str_contains( $html, 'ago-donation' ) && str_contains( $html, 'ago-footer' ) );
        self::check( 'la pagina no ofrece interruptores deshabilitados', ! preg_match( '/<input[^>]*disabled/', $html ) );
    }

    private static function folder_routes(): void {
        $r = self::call( 'GET', '/folders', null, 0 );
        self::check( 'sin sesion listar carpetas responde 401', 401 === $r->get_status(), (string) $r->get_status() );
        $r = self::call( 'GET', '/folders', null, self::$users['subscriber'] );
        self::check( 'un suscriptor no lista carpetas', 403 === $r->get_status(), (string) $r->get_status() );
        $r = self::call( 'GET', '/folders', null, self::$users['author'] );
        self::check( 'un autor lista carpetas', 200 === $r->get_status(), (string) $r->get_status() );

        foreach ( [ 0 => 401, self::$users['subscriber'] => 403, self::$users['contributor'] => 403 ] as $user => $status ) {
            $r = self::call( 'POST', '/folders', [ 'name' => 'agofiles smoke nope' ], $user );
            self::check( "sin upload_files crear carpeta responde {$status}", $status === $r->get_status(), (string) $r->get_status() );
        }
        self::check( 'y no se creo la carpeta', ! term_exists( 'agofiles smoke nope', Taxonomy::TAXONOMY ) );

        $r = self::call( 'POST', '/folders', [ 'name' => 'agofiles smoke author' ], self::$users['author'] );
        self::check( 'un autor crea una carpeta', 201 === $r->get_status(), (string) $r->get_status() );
        $author_folder  = (int) ( $r->get_data()['id'] ?? 0 );
        self::$terms[] = $author_folder;

        $r = self::call( 'POST', '/folders', [ 'name' => '<b>Hi</b><script>x</script>' ], self::admin() );
        $xss = (int) ( $r->get_data()['id'] ?? 0 );
        self::$terms[] = $xss;
        $term = get_term( $xss, Taxonomy::TAXONOMY );
        self::check( 'el nombre de la carpeta se guarda sin HTML', $term instanceof WP_Term && ! str_contains( $term->name, '<' ), $term instanceof WP_Term ? $term->name : '' );

        $r = self::call( 'POST', '/folders', [ 'name' => 'Food & drinks smoke' ], self::admin() );
        $amp = (int) ( $r->get_data()['id'] ?? 0 );
        self::$terms[] = $amp;
        $names = array_column( Taxonomy::get_folder_tree(), 'name' );
        self::check( 'el arbol entrega el nombre tal como se escribio, sin &amp;', in_array( 'Food & drinks smoke', $names, true ), implode( ' | ', $names ) );
        $r = self::call( 'GET', '/folders', null, self::admin() );
        self::check( 'la ruta de carpetas tambien lo entrega sin &amp;', in_array( 'Food & drinks smoke', array_column( $r->get_data()['folders'] ?? [], 'name' ), true ) );

        $r = self::call( 'POST', '/folders', [ 'name' => '' ], self::admin() );
        self::check( 'sin nombre responde 400', 400 === $r->get_status(), (string) $r->get_status() );

        $category = (int) wp_insert_term( 'agofiles smoke category ' . wp_rand(), 'category' )['term_id'];
        $r = self::call( 'POST', '/folders', [ 'name' => 'agofiles smoke child', 'parent' => $category ], self::admin() );
        self::check( 'un padre que no es carpeta se rechaza', 400 === $r->get_status(), (string) $r->get_status() );

        foreach ( [ self::$users['author'], self::$users['contributor'] ] as $user ) {
            $r = self::call( 'PUT', '/folders/' . $author_folder, [ 'name' => 'renamed' ], $user );
            self::check( 'sin manage_categories no se renombra una carpeta compartida', 403 === $r->get_status(), (string) $r->get_status() );
            $r = self::call( 'DELETE', '/folders/' . $author_folder, null, $user );
            self::check( 'sin manage_categories no se borra una carpeta compartida', 403 === $r->get_status(), (string) $r->get_status() );
        }
        self::check( 'la carpeta sigue ahi', (bool) term_exists( $author_folder, Taxonomy::TAXONOMY ) );

        $r = self::call( 'PUT', '/folders/' . $category, [ 'name' => 'hijacked' ], self::admin() );
        self::check( 'renombrar un termino ajeno a las carpetas responde 404', 404 === $r->get_status(), (string) $r->get_status() );
        $r = self::call( 'DELETE', '/folders/' . $category, null, self::admin() );
        self::check( 'borrar un termino ajeno a las carpetas responde 404', 404 === $r->get_status(), (string) $r->get_status() );
        self::check( 'la categoria sigue intacta', get_term( $category, 'category' ) instanceof WP_Term && str_starts_with( get_term( $category, 'category' )->name, 'agofiles smoke category' ) );
        wp_delete_term( $category, 'category' );

        $r = self::call( 'PUT', '/folders/' . $author_folder, [ 'name' => 'agofiles smoke renamed' ], self::$users['editor'] );
        self::check( 'un editor renombra una carpeta', 200 === $r->get_status() && 'agofiles smoke renamed' === get_term( $author_folder )->name, (string) $r->get_status() );
        $r = self::call( 'DELETE', '/folders/' . $author_folder, null, self::$users['editor'] );
        self::check( 'un editor borra una carpeta', 200 === $r->get_status() && ! term_exists( $author_folder, Taxonomy::TAXONOMY ), (string) $r->get_status() );
    }

    private static function file_routes(): void {
        $folder  = self::folder( 'agofiles smoke target' );
        $other   = self::folder( 'agofiles smoke other' );
        $own     = self::attachment( self::$users['author'] );
        $foreign = self::attachment( self::admin() );
        $post    = (int) wp_insert_post( [ 'post_title' => 'agofiles smoke post', 'post_status' => 'publish', 'post_author' => self::$users['author'] ] );

        foreach ( [ '/move', '/assign' ] as $route ) {
            $r = self::call( 'POST', $route, [ 'attachment_ids' => [ $own ], 'folder_id' => $folder ], 0 );
            self::check( "sin sesion {$route} responde 401", 401 === $r->get_status(), (string) $r->get_status() );
            $r = self::call( 'POST', $route, [ 'attachment_ids' => [ $own ], 'folder_id' => $folder ], self::$users['subscriber'] );
            self::check( "un suscriptor recibe 403 en {$route}", 403 === $r->get_status(), (string) $r->get_status() );

            $r = self::call( 'POST', $route, [ 'attachment_ids' => [ $own, $foreign ], 'folder_id' => $folder ], self::$users['author'] );
            self::check( "un autor no mueve un archivo ajeno con {$route}", 403 === $r->get_status(), (string) $r->get_status() );
            self::check( "y {$route} no toca ninguno de los dos", [] === self::folders_of( $own ) && [] === self::folders_of( $foreign ) );

            $r = self::call( 'POST', $route, [ 'attachment_ids' => [ $post ], 'folder_id' => $folder ], self::$users['author'] );
            self::check( "{$route} rechaza lo que no es un archivo de la biblioteca", 400 === $r->get_status(), (string) $r->get_status() );
            self::check( "y {$route} no etiqueta la entrada", [] === self::folders_of( $post ) );

            $r = self::call( 'POST', $route, [ 'attachment_ids' => [ $own ], 'folder_id' => 987654321 ], self::$users['author'] );
            self::check( "{$route} a una carpeta inexistente responde 404", 404 === $r->get_status(), (string) $r->get_status() );
            self::check( "y {$route} no crea una carpeta con ese numero", ! term_exists( '987654321', Taxonomy::TAXONOMY ) );
        }

        $r = self::call( 'POST', '/assign', [ 'attachment_ids' => [ $own ], 'folder_id' => $other ], self::$users['author'] );
        self::check( 'un autor asigna su propio archivo', 200 === $r->get_status() && [ $other ] === self::folders_of( $own ), (string) $r->get_status() );
        $r = self::call( 'POST', '/assign', [ 'attachment_ids' => [ $own ], 'folder_id' => $folder ], self::$users['author'] );
        $f = self::folders_of( $own );
        sort( $f );
        $expected = [ $folder, $other ];
        sort( $expected );
        self::check( 'asignar agrega la carpeta sin quitar las otras', $expected === $f, implode( ',', $f ) );

        $r = self::call( 'POST', '/move', [ 'attachment_ids' => [ $own ], 'folder_id' => $folder ], self::$users['author'] );
        self::check( 'mover deja el archivo solo en la carpeta destino', 200 === $r->get_status() && [ $folder ] === self::folders_of( $own ), (string) $r->get_status() );

        $r = self::call( 'POST', '/move', [ 'attachment_ids' => [ $own ], 'folder_id' => 0 ], self::$users['author'] );
        self::check( 'mover a la carpeta 0 deja el archivo sin carpeta', 200 === $r->get_status() && [] === self::folders_of( $own ), (string) $r->get_status() );

        $r = self::call( 'POST', '/move', [ 'attachment_ids' => [ $own, $foreign ], 'folder_id' => $folder ], self::$users['editor'] );
        self::check( 'un editor mueve archivos de otros', 200 === $r->get_status() && [ $folder ] === self::folders_of( $foreign ), (string) $r->get_status() );

        wp_delete_post( $post, true );
    }

    private static function settings_route(): void {
        foreach ( [ 0 => 401, self::$users['author'] => 403, self::$users['editor'] => 403 ] as $user => $status ) {
            $r = self::call( 'POST', '/settings', [ 'show_sidebar' => false ], $user );
            self::check( "sin manage_options guardar ajustes responde {$status}", $status === $r->get_status(), (string) $r->get_status() );
        }
        $r = self::call( 'POST', '/settings', [ 'show_sidebar' => true, 'show_folder_column' => false, 'evil' => '<script>' ], self::admin() );
        $saved = get_option( 'agofiles_settings' );
        self::check( 'el administrador guarda ajustes', 200 === $r->get_status() && false === ( $saved['show_folder_column'] ?? null ), (string) $r->get_status() );
        self::check( 'solo se guardan las claves conocidas', [ 'show_folder_column', 'show_sidebar' ] === ( static function ( $k ) { sort( $k ); return $k; } )( array_keys( (array) $saved ) ) );
    }

    private static function filters(): void {
        global $pagenow;
        $folder = self::folder( 'agofiles smoke filter' );

        $saved_pagenow = $pagenow;
        $pagenow       = 'upload.php';
        set_current_screen( 'upload' );
        $_GET['agofiles_folder'] = (string) $folder;
        $query = new WP_Query();
        $GLOBALS['wp_the_query'] = $query;
        MediaLibrary::filter_by_folder( $query );
        $tax = $query->get( 'tax_query' );
        self::check( 'la vista de lista filtra por la carpeta elegida', is_array( $tax ) && (int) ( $tax[0]['terms'] ?? 0 ) === $folder, wp_json_encode( $tax ) );
        unset( $_GET['agofiles_folder'] );
        $pagenow = $saved_pagenow;

        // wp.media sends its parameters inside query[], which is what reaches the filter.
        $_REQUEST['query'] = [ 'agofiles_folder' => (string) $folder ];
        $args = MediaLibrary::filter_grid_by_folder( [ 'post_type' => 'attachment' ] );
        self::check( 'la vista de cuadricula filtra por la carpeta que manda wp.media', (int) ( $args['tax_query'][0]['terms'] ?? 0 ) === $folder, wp_json_encode( $args ) );
        $_REQUEST['query'] = [ 'agofiles_folder' => 'uncategorized' ];
        $args = MediaLibrary::filter_grid_by_folder( [ 'post_type' => 'attachment' ] );
        self::check( 'la cuadricula filtra los archivos sin carpeta', 'NOT EXISTS' === ( $args['tax_query'][0]['operator'] ?? '' ) );
        unset( $_REQUEST['query'] );

        $before = Taxonomy::get_uncategorized_count();
        $filed  = self::attachment( self::admin() );
        $after_new = Taxonomy::get_uncategorized_count();
        wp_set_object_terms( $filed, [ $folder ], Taxonomy::TAXONOMY );
        wp_set_object_terms( $filed, [ 'agofiles-smoke-tag' ], 'post_tag' );
        self::check( 'un archivo nuevo cuenta como sin carpeta', $after_new === $before + 1, "{$before} -> {$after_new}" );
        self::check( 'un archivo con carpeta y otra etiqueta no cuenta como sin carpeta', Taxonomy::get_uncategorized_count() === $before, (string) Taxonomy::get_uncategorized_count() );
        wp_delete_object_term_relationships( $filed, 'post_tag' );
        $tag = get_term_by( 'slug', 'agofiles-smoke-tag', 'post_tag' );
        if ( $tag ) {
            wp_delete_term( $tag->term_id, 'post_tag' );
        }

        $js = (string) file_get_contents( AGOFILES_PATH . 'assets/js/media-library.js' );
        self::check( 'el JS manda el mismo parametro que lee PHP', str_contains( $js, 'agofiles_folder' ) && ! preg_match( '/[\'"]ago_folder[\'"]|\.ago_folder\b/', $js ) );
        self::check( 'el JS no escribe en una taxonomia con otro nombre', ! str_contains( $js, 'ago_media_folder' ) );
        // A flex container squeezed the toolbar and the grid of the Media Library into a column.
        self::check( 'la cuadricula no se convierte en flex al agregar la barra', ! str_contains( $js, "style.display = 'flex'" ) && str_contains( $js, 'agofiles-has-sidebar' ) );
    }

    private static function settings_take_effect(): void {
        wp_set_current_user( self::admin() );

        update_option( 'agofiles_settings', [ 'show_sidebar' => false, 'show_folder_column' => true ] );
        wp_dequeue_script( 'agofiles-media' );
        wp_deregister_script( 'agofiles-media' );
        MediaLibrary::enqueue_on_media_pages( 'upload.php' );
        self::check( 'con la barra de carpetas apagada no se carga en la biblioteca', ! wp_script_is( 'agofiles-media', 'enqueued' ) );

        update_option( 'agofiles_settings', [ 'show_sidebar' => true, 'show_folder_column' => false ] );
        Taxonomy::init();
        self::check( 'con la columna apagada la lista no muestra la carpeta', false === get_taxonomy( Taxonomy::TAXONOMY )->show_admin_column );

        update_option( 'agofiles_settings', [ 'show_sidebar' => true, 'show_folder_column' => true ] );
        Taxonomy::init();
        self::check( 'con la columna encendida la lista muestra la carpeta', true === get_taxonomy( Taxonomy::TAXONOMY )->show_admin_column );

        wp_set_current_user( 0 );
    }

    private static function not_public(): void {
        $tax = get_taxonomy( Taxonomy::TAXONOMY );
        self::check( 'los nombres de carpeta no se publican en /wp/v2', false === $tax->show_in_rest );
        self::check( 'la taxonomia no es publica', false === $tax->public );
    }

    private static function messages(): void {
        $tag = static fn ( $translation, $text, $domain ) => 'ago-files' === $domain ? '[t] ' . $translation : $translation;
        add_filter( 'gettext', $tag, 10, 3 );
        $r = self::call( 'POST', '/folders', [ 'name' => '' ], self::admin() );
        remove_filter( 'gettext', $tag, 10 );
        $data = $r->get_data();
        $msg  = (string) ( $data['message'] ?? $data['error'] ?? '' );
        self::check( 'el error de la API pasa por traduccion', str_starts_with( $msg, '[t] ' ), $msg );

        $bare = [];
        foreach ( self::php_files() as $file ) {
            foreach ( file( $file ) as $n => $line ) {
                $trim = ltrim( $line );
                if ( str_starts_with( $trim, '//' ) || str_starts_with( $trim, '*' ) || str_starts_with( $trim, '/*' ) ) {
                    continue;
                }
                if ( ! preg_match_all( "/'([A-Z][a-z]+[ :.][^']*)'/", $line, $m, PREG_OFFSET_CAPTURE ) ) {
                    continue;
                }
                foreach ( $m[0] as $hit ) {
                    $before = substr( $line, 0, $hit[1] );
                    if ( ! preg_match( '/(__|_e|_x|_n|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*$/', $before ) ) {
                        $bare[] = basename( $file ) . ':' . ( $n + 1 ) . ' ' . $hit[0];
                    }
                }
            }
        }
        self::check( 'ningun texto en ingles queda fuera de una funcion de traduccion', [] === $bare, implode( ' | ', array_slice( $bare, 0, 6 ) ) );

        foreach ( [ 'admin.js', 'media-library.js' ] as $file ) {
            $js = (string) file_get_contents( AGOFILES_PATH . 'assets/js/' . $file );
            self::check( "{$file} no trae textos fijos en ingles", ! preg_match( "/'[A-Z][a-z]+[ :!.\x{2026}]/u", $js ) && ! preg_match( "/textContent = '[A-Z]/", $js ) );
        }
    }

    /**
     * On uninstall the plugin is not loaded, so its taxonomy is not registered.
     * The folders must go anyway.
     */
    private static function uninstall(): void {
        $folder = self::folder( 'agofiles smoke uninstall' );
        update_option( 'agofiles_settings', Plugin::defaults() );
        unregister_taxonomy( Taxonomy::TAXONOMY );

        if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
            define( 'WP_UNINSTALL_PLUGIN', plugin_basename( AGOFILES_FILE ) );
        }
        include AGOFILES_PATH . 'uninstall.php';

        global $wpdb;
        $left = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", Taxonomy::TAXONOMY ) );
        self::check( 'desinstalar borra las carpetas aunque la taxonomia no este registrada', 0 === $left, (string) $left );
        self::check( 'desinstalar borra los ajustes', false === get_option( 'agofiles_settings' ) );
        self::check( 'la carpeta de prueba ya no existe', null === $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM {$wpdb->terms} WHERE term_id = %d", $folder ) ) );

        Taxonomy::init();
    }
}

AgoFilesSmoke::run();
