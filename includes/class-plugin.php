<?php

namespace PandocImporter;

use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PandocMediaExtractor;
use PortLibs\Pandoc\ZipPackage;
use WP_Error;
use WP_REST_Request;

final class Plugin {
    public const FORMATS = [
        'docx' => 'docx', 'pdf' => 'pdf', 'md' => 'markdown', 'markdown' => 'markdown',
        'html' => 'html', 'htm' => 'html', 'epub' => 'epub', 'odt' => 'odt',
        'rtf' => 'rtf', 'txt' => 'html', 'pptx' => 'pptx', 'xlsx' => 'xlsx', 'csv' => 'csv',
    ];

    public static function menu(): void {
        add_management_page( 'Collage', 'Collage', 'edit_posts', 'pandoc-importer', [ self::class, 'page' ] );
    }

    public static function page(): void {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }
        $extensions = array_keys( self::FORMATS );
        sort( $extensions, SORT_STRING );
        require dirname( __DIR__ ) . '/templates/importer.php';
    }

    public static function assets( string $hook ): void {
        if ( 'tools_page_pandoc-importer' !== $hook ) {
            return;
        }
        $root = dirname( __DIR__ );
        $url = plugin_dir_url( $root . '/collage.php' );
        wp_enqueue_style( 'pandoc-importer', $url . 'assets/admin.css', [], filemtime( $root . '/assets/admin.css' ) );
        wp_enqueue_script( 'pandoc-importer', $url . 'assets/admin.js', [], filemtime( $root . '/assets/admin.js' ), true );
        wp_add_inline_script( 'pandoc-importer', 'window.pandocImporter = ' . wp_json_encode( [
            'api' => rest_url( 'pandoc-importer/v1/' ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
            'maxBytes' => self::max_bytes(),
            'formats' => array_keys( self::FORMATS ),
            'recent' => self::recent(),
        ] ) . ';', 'before' );
    }

    public static function routes(): void {
        register_rest_route( 'pandoc-importer/v1', '/convert', [
            'methods' => 'POST',
            'callback' => [ self::class, 'convert' ],
            'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
        ] );
        register_rest_route( 'pandoc-importer/v1', '/draft', [
            'methods' => 'POST',
            'callback' => [ self::class, 'draft' ],
            'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
        ] );
    }

    public static function convert( WP_REST_Request $request ) {
        $autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
        if ( ! is_file( $autoload ) ) {
            return new WP_Error( 'pandoc_setup', 'The conversion library is missing. Run scripts/setup.sh first.', [ 'status' => 503 ] );
        }
        require_once $autoload;
        $sample = $request->get_param( 'sample' );
        if ( null !== $sample ) {
            $samples = [ 'docx' => 'a-good-place-to-start.docx', 'markdown' => 'field-notes.md', 'html' => 'the-weekly-edit.html' ];
            if ( ! is_string( $sample ) || ! isset( $samples[ $sample ] ) ) {
                return new WP_Error( 'pandoc_sample', 'Choose one of the sample documents below.', [ 'status' => 400 ] );
            }
            $name = $samples[ $sample ];
            $bytes = file_get_contents( dirname( __DIR__ ) . '/samples/' . $name );
        } else {
            $file = $request->get_file_params()['file'] ?? null;
            if ( is_array( $file ) && in_array( $file['error'] ?? null, [ UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ], true ) ) {
                return new WP_Error( 'pandoc_size', 'This file is too large. The limit is ' . size_format( self::max_bytes() ) . '.', [ 'status' => 413 ] );
            }
            if ( ! is_array( $file ) || UPLOAD_ERR_OK !== ( $file['error'] ?? null ) || ! is_uploaded_file( $file['tmp_name'] ?? '' ) ) {
                return new WP_Error( 'pandoc_upload', 'The upload did not finish. Choose your file and try again.', [ 'status' => 400 ] );
            }
            if ( filesize( $file['tmp_name'] ) > self::max_bytes() ) {
                return new WP_Error( 'pandoc_size', 'This file is too large. The limit is ' . size_format( self::max_bytes() ) . '.', [ 'status' => 413 ] );
            }
            $name = sanitize_file_name( $file['name'] );
            $bytes = file_get_contents( $file['tmp_name'] );
        }
        if ( ! is_string( $bytes ) || '' === trim( $bytes ) ) {
            return new WP_Error( 'pandoc_empty', 'This file is empty. Choose a document with some content.', [ 'status' => 400 ] );
        }
        $extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
        if ( ! isset( self::FORMATS[ $extension ] ) || ! PandocConverter::canRead( self::FORMATS[ $extension ] ) ) {
            return new WP_Error( 'pandoc_format', 'This file type is not supported. Try a Word document, PDF, or Markdown file.', [ 'status' => 415 ] );
        }
        try {
            $warnings = [];
            if ( in_array( $extension, [ 'docx', 'epub', 'odt', 'pptx', 'xlsx' ], true ) ) {
                self::check_archive( $bytes );
            }
            if ( 'pdf' === $extension && ! str_starts_with( $bytes, '%PDF-' ) ) {
                throw new \DomainException( 'This file does not look like a PDF. Try exporting it again.' );
            }
            if ( 'txt' === $extension ) {
                $bytes = '<p>' . nl2br( esc_html( $bytes ) ) . '</p>';
            }
            // Do not pass sourcePath: document references must never read files from the server.
            $document = PandocConverter::read( $bytes, self::FORMATS[ $extension ], [], 'wordpress' );
            $meta = $document->attr( 'meta', [] );
            if ( isset( $meta['docxFallback'] ) ) {
                throw new \DomainException( 'This Word document could not be read. Try saving it as a new .docx file.' );
            }
            $media = ( new PandocMediaExtractor() )->extract( $document, $bytes, self::FORMATS[ $extension ], [ 'destination' => 'pandoc-media' ] );
            $blocks = self::sanitize_blocks( PandocConverter::write( $media['document'], 'wordpress' ) );
            if ( '' === trim( wp_strip_all_tags( $blocks ) ) && ! str_contains( $blocks, '<img' ) ) {
                throw new \DomainException( 'No readable content was found. Scanned PDFs need a text layer; try a Word or Markdown version instead.' );
            }
            $images = [];
            $total = 0;
            foreach ( $media['entries'] as $entry ) {
                $contents = $entry['contents'];
                $info = @getimagesizefromstring( $contents );
                if ( ! $info || ! in_array( $info['mime'], [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ], true ) || strlen( $contents ) > 5 * MB_IN_BYTES || $info[0] * $info[1] > 20000000 ) {
                    $warnings[] = 'An image was left out because its type or size is not supported.';
                    continue;
                }
                $total += strlen( $contents );
                if ( $total > 12 * MB_IN_BYTES ) {
                    throw new \DomainException( 'The images in this document are too large. Try reducing their size first.' );
                }
                $images[ $entry['path'] ] = [ 'data' => base64_encode( $contents ), 'mime' => $info['mime'] ];
            }
            if ( 'pdf' === $extension ) {
                $warnings[] = 'PDF import is experimental. Check the reading order, tables, and missing content against your original before publishing. Scanned pages are not transcribed.';
            }
            if ( str_contains( $blocks, '<!-- wp:html' ) ) {
                $warnings[] = 'Some content uses Custom HTML blocks. Check these blocks in the editor.';
            }
            $preview = self::replace_images( $blocks, array_map( static fn( $image ) => 'data:' . $image['mime'] . ';base64,' . $image['data'], $images ) );
            if ( preg_match( '#<img[^>]+src=[\'"](?:https?:)?//#i', $preview ) ) {
                $warnings[] = 'External images are not loaded in this preview. Their links are kept in the draft.';
            }
            $token = wp_generate_uuid4();
            $data = [ 'name' => $name, 'blocks' => $blocks, 'images' => $images ];
            if ( ! set_transient( self::transient_key( $token ), $data, HOUR_IN_SECONDS ) ) {
                throw new \DomainException( 'WordPress could not save this preview. Try again.' );
            }
            $title = preg_replace( '/[-_]+/', ' ', pathinfo( $name, PATHINFO_FILENAME ) );
            if ( preg_match( '#<h1\b[^>]*>(.*?)</h1>#si', $blocks, $match ) ) {
                $title = html_entity_decode( wp_strip_all_tags( $match[1] ), ENT_QUOTES, 'UTF-8' );
            }
            return [
                'token' => $token, 'name' => $name, 'title' => $title,
                'html' => $preview, 'blocks' => $blocks,
                'blockCount' => self::count_blocks( parse_blocks( $blocks ) ),
                'imageCount' => count( $images ), 'warnings' => array_values( array_unique( $warnings ) ),
            ];
        } catch ( \Throwable $error ) {
            // Parser details can contain source text or local paths; keep them out of REST responses.
            $message = $error instanceof \DomainException
                ? $error->getMessage()
                : 'We could not convert this document. Try saving a new copy, or use another supported format.';
            return new WP_Error( 'pandoc_conversion', $message, [ 'status' => 422 ] );
        }
    }

    public static function draft( WP_REST_Request $request ) {
        $token = $request->get_param( 'token' );
        if ( ! is_string( $token ) || ! wp_is_uuid( $token ) ) {
            return new WP_Error( 'pandoc_token', 'Choose and preview a document first.', [ 'status' => 400 ] );
        }
        $key = self::transient_key( $token );
        $data = get_transient( $key );
        if ( ! is_array( $data ) ) {
            return new WP_Error( 'pandoc_expired', 'This preview has expired. Choose the document again.', [ 'status' => 410 ] );
        }
        if ( isset( $data['postId'] ) ) {
            return [ 'id' => $data['postId'], 'editUrl' => get_edit_post_link( $data['postId'], 'raw' ) ];
        }
        $type = $request->get_param( 'type' ) ?? 'post';
        if ( ! in_array( $type, [ 'post', 'page' ], true ) || ! current_user_can( get_post_type_object( $type )->cap->create_posts ) ) {
            return new WP_Error( 'pandoc_type', 'You cannot create this type of draft.', [ 'status' => 403 ] );
        }
        if ( $data['images'] && ! current_user_can( 'upload_files' ) ) {
            return new WP_Error( 'pandoc_media', 'You need permission to upload images before importing this document.', [ 'status' => 403 ] );
        }
        $title = $request->get_param( 'title' );
        if ( ! is_string( $title ) || '' === trim( $title ) ) {
            return new WP_Error( 'pandoc_title', 'Add a title for your draft.', [ 'status' => 400 ] );
        }
        $attachments = [];
        $urls = [];
        require_once ABSPATH . 'wp-admin/includes/image.php';
        foreach ( $data['images'] as $path => $image ) {
            $extensions = [ 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp' ];
            $upload = wp_upload_bits( 'document-' . substr( hash( 'sha256', $image['data'] ), 0, 12 ) . '.' . $extensions[ $image['mime'] ], null, base64_decode( $image['data'], true ) );
            if ( $upload['error'] ) {
                foreach ( $attachments as $id ) {
                    wp_delete_attachment( $id, true );
                }
                return new WP_Error( 'pandoc_media', 'An image could not be saved. Check the site’s upload folder and try again.', [ 'status' => 500 ] );
            }
            $id = wp_insert_attachment( [ 'post_title' => sanitize_text_field( pathinfo( $path, PATHINFO_FILENAME ) ), 'post_mime_type' => $image['mime'] ], $upload['file'], 0, true );
            if ( is_wp_error( $id ) ) {
                wp_delete_file( $upload['file'] );
                foreach ( $attachments as $attachment ) {
                    wp_delete_attachment( $attachment, true );
                }
                return $id;
            }
            $attachments[] = $id;
            wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
            $urls[ $path ] = $upload['url'];
        }
        $id = wp_insert_post( wp_slash( [
            'post_title' => sanitize_text_field( $title ),
            'post_content' => self::replace_images( $data['blocks'], $urls ),
            'post_status' => 'draft', 'post_type' => $type, 'post_author' => get_current_user_id(),
        ] ), true );
        if ( is_wp_error( $id ) ) {
            foreach ( $attachments as $attachment ) {
                wp_delete_attachment( $attachment, true );
            }
            return $id;
        }
        foreach ( $attachments as $attachment ) {
            wp_update_post( [ 'ID' => $attachment, 'post_parent' => $id ] );
        }
        update_post_meta( $id, '_pandoc_source', $data['name'] );
        set_transient( $key, [ 'postId' => $id ], HOUR_IN_SECONDS );
        return [ 'id' => $id, 'editUrl' => get_edit_post_link( $id, 'raw' ) ];
    }

    public static function max_bytes(): int {
        return min( 8 * MB_IN_BYTES, wp_max_upload_size() );
    }

    private static function transient_key( string $token ): string {
        return 'pandoc_' . get_current_user_id() . '_' . $token;
    }

    private static function check_archive( string $bytes ): void {
        $package = ZipPackage::fromString( $bytes, 2048 );
        $package->assertSizePreflight( 32 * MB_IN_BYTES, 100.0 );
        foreach ( $package->entries() as $entry ) {
            if ( $entry->uncompressedSize > 8 * MB_IN_BYTES ) {
                throw new \DomainException( 'This document contains an oversized archive entry. Reduce the document or image sizes and try again.' );
            }
        }
        // We read entries in memory, never extract paths or apply archive permissions.
        // Check size and CRC integrity without rejecting harmless ZIP metadata from Office apps.
        if ( $package->readIntegrityPreflight( 8 * MB_IN_BYTES )['failedEntryCount'] > 0 ) {
            throw new \DomainException( 'This document archive is damaged or encrypted. Try saving a new, unprotected copy.' );
        }
    }

    private static function sanitize_blocks( string $content ): string {
        $tags = new \WP_HTML_Tag_Processor( $content );
        while ( $tags->next_tag() ) {
            foreach ( $tags->get_attribute_names_with_prefix( 'data-pandoc-' ) ?? [] as $attribute ) {
                $tags->remove_attribute( $attribute );
            }
        }
        $content = $tags->get_updated_html();
        $blocks = parse_blocks( filter_block_content( wp_kses_post( $content ), 'post' ) );
        $prepare = static function ( array $blocks ) use ( &$prepare ): array {
            $allowed = [ 'core/paragraph', 'core/heading', 'core/list', 'core/list-item', 'core/quote', 'core/code', 'core/preformatted', 'core/table', 'core/image', 'core/gallery', 'core/group', 'core/separator', 'core/html', 'core/verse', 'core/details', 'core/columns', 'core/column' ];
            foreach ( $blocks as &$block ) {
                if ( null !== $block['blockName'] && ! in_array( $block['blockName'], $allowed, true ) ) {
                    throw new \DomainException( 'This document contains a block that cannot be safely imported.' );
                }
                if ( 'core/table' === $block['blockName'] ) {
                    // Gutenberg cannot save colgroup widths. Keep the cells editable and let the browser size the columns.
                    $block['attrs']['hasFixedLayout'] = false;
                    foreach ( $block['innerContent'] as &$html ) {
                        if ( is_string( $html ) ) {
                            $html = preg_replace( '#<colgroup\b[^>]*>.*?</colgroup>#si', '', $html );
                            $tags = new \WP_HTML_Tag_Processor( $html );
                            while ( $tags->next_tag( 'TABLE' ) ) {
                                $tags->remove_class( 'has-fixed-layout' );
                            }
                            $html = $tags->get_updated_html();
                        }
                    }
                }
                $block['innerBlocks'] = $prepare( $block['innerBlocks'] );
            }
            return $blocks;
        };
        return serialize_blocks( $prepare( $blocks ) );
    }

    private static function replace_images( string $html, array $urls ): string {
        // The tag processor rejects data: URLs. These replacements come only from
        // validated raster images or WordPress upload URLs, never request parameters.
        foreach ( $urls as $path => $url ) {
            $html = str_replace(
                [ 'src="' . esc_attr( $path ) . '"', "src='" . esc_attr( $path ) . "'" ],
                [ 'src="' . esc_attr( $url ) . '"', "src='" . esc_attr( $url ) . "'" ],
                $html
            );
        }
        return $html;
    }

    private static function count_blocks( array $blocks ): int {
        $count = 0;
        foreach ( $blocks as $block ) {
            $count += ( null !== $block['blockName'] ? 1 : 0 ) + self::count_blocks( $block['innerBlocks'] );
        }
        return $count;
    }

    private static function recent(): array {
        $posts = get_posts( [ 'post_type' => [ 'post', 'page' ], 'post_status' => [ 'draft', 'pending', 'publish', 'private', 'future' ], 'author' => get_current_user_id(), 'meta_key' => '_pandoc_source', 'numberposts' => 20 ] );
        return array_map( static fn( $post ) => [
            'title' => get_the_title( $post ), 'source' => get_post_meta( $post->ID, '_pandoc_source', true ),
            'status' => get_post_status_object( $post->post_status )->label,
            'type' => 'page' === $post->post_type ? 'Page' : 'Post',
            'date' => get_the_date( 'M j, Y', $post ), 'editUrl' => get_edit_post_link( $post->ID, 'raw' ),
        ], $posts );
    }
}
