<?php

use PandocImporter\Plugin;

if ( ! defined( 'ABSPATH' ) || 'local' !== wp_get_environment_type() ) {
    throw new RuntimeException( 'Run this test with WP-CLI against the local test site.' );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

$GLOBALS['pandoc_test_checks'] = 0;
$posts = [];
$tokens = [];
$users = [];
$original_user = get_current_user_id();
wp_set_current_user( 1 );

try {
    ob_start();
    Plugin::page();
    $page = ob_get_clean();
    check( str_contains( $page, '<h1>Collage</h1>' ), 'The import screen uses the Collage brand' );
    check( ! str_contains( strip_tags( $page ), 'Pandoc' ), 'Converter credits stay out of the user interface' );
    preg_match( '/<p class="pandoc-formats">(.*?)<\/p>/s', $page, $format_list );
    $supported_formats = array_keys( Plugin::FORMATS );
    sort( $supported_formats, SORT_STRING );
    check( ( $format_list[1] ?? '' ) === '.' . implode( ' · .', $supported_formats ), 'All supported formats are shown as alphabetically sorted extensions only' );

    $before = count( get_posts( [ 'post_status' => 'any', 'numberposts' => -1 ] ) );
    foreach ( [ 'docx', 'markdown', 'html' ] as $sample ) {
        $result = Plugin::convert( request( [ 'sample' => $sample ] ) );
        check( ! is_wp_error( $result ), "$sample converts" );
        $tokens[] = $result['token'];
        check( $result['blockCount'] > 5, "$sample contains native blocks" );
        check( str_contains( $result['blocks'], '<!-- wp:heading' ), "$sample keeps headings" );
        check( ! str_contains( $result['blocks'], '<colgroup' ), "$sample has no unsupported column widths" );
        if ( 'docx' === $sample ) {
            check( str_contains( $result['blocks'], '"hasFixedLayout":false' ), 'Word tables declare their layout' );
            check( str_contains( $result['blocks'], '<strong>headings stay headings</strong>' ), 'Word bold text survives' );
        }
    }
    check( count( get_posts( [ 'post_status' => 'any', 'numberposts' => -1 ] ) ) === $before, 'Preview does not create posts' );
    check( is_wp_error( Plugin::convert( request( [ 'sample' => '../wp-config.php' ] ) ) ), 'Sample paths cannot escape the sample list' );
    check( is_wp_error( Plugin::convert( request( [ 'sample' => [ 'docx' ] ] ) ) ), 'Array sample names are rejected' );
    check( is_wp_error( Plugin::convert( request( [] ) ) ), 'Missing uploads are rejected' );

    $sanitize = new ReflectionMethod( Plugin::class, 'sanitize_blocks' );
    $safe = $sanitize->invoke( null, '<!-- wp:paragraph --><p onclick="alert(1)">Safe<script>alert(1)</script><a href="javascript:alert(1)">link</a></p><!-- /wp:paragraph -->' );
    check( ! str_contains( $safe, '<script' ) && ! str_contains( $safe, 'onclick' ) && ! str_contains( $safe, 'javascript:' ), 'Active HTML is removed' );
    rejects( static fn() => $sanitize->invoke( null, '<!-- wp:shortcode -->[private_shortcode]<!-- /wp:shortcode -->' ), 'Dynamic blocks are rejected' );
    rejects( static fn() => $sanitize->invoke( null, '<!-- wp:group --><div><!-- wp:query /--></div><!-- /wp:group -->' ), 'Nested dynamic blocks are rejected' );

    $archive = new ReflectionMethod( Plugin::class, 'check_archive' );
    $archive->invoke( null, file_get_contents( dirname( __DIR__ ) . '/samples/a-good-place-to-start.docx' ) );
    check( true, 'Normal Office ZIP metadata is accepted' );
    rejects( static fn() => $archive->invoke( null, 'not a ZIP' ), 'Broken archives are rejected' );
    $temp = tempnam( get_temp_dir(), 'pandoc-test-' );
    $zip = new ZipArchive();
    $zip->open( $temp, ZipArchive::CREATE | ZipArchive::OVERWRITE );
    $zip->addFromString( 'oversized.txt', str_repeat( 'x', 9 * MB_IN_BYTES ) );
    $zip->close();
    rejects( static fn() => $archive->invoke( null, file_get_contents( $temp ) ), 'Highly compressed oversized archives are rejected' );
    $zip->open( $temp, ZipArchive::CREATE | ZipArchive::OVERWRITE );
    for ( $i = 0; $i < 2049; $i++ ) {
        $zip->addFromString( "$i.txt", '' );
    }
    $zip->close();
    rejects( static fn() => $archive->invoke( null, file_get_contents( $temp ) ), 'Archive entry count is bounded' );
    unlink( $temp );

    $conversion = Plugin::convert( request( [ 'sample' => 'markdown' ] ) );
    $tokens[] = $conversion['token'];
    $draft = Plugin::draft( request( [ 'token' => $conversion['token'], 'title' => 'Pandoc integration test' ] ) );
    check( ! is_wp_error( $draft ), 'A preview creates a draft' );
    $posts[] = $draft['id'];
    check( 'draft' === get_post_status( $draft['id'] ), 'The draft is never published' );
    check( $conversion['blocks'] === get_post_field( 'post_content', $draft['id'] ), 'The stored content matches the reviewed blocks' );
    check( 'field-notes.md' === get_post_meta( $draft['id'], '_pandoc_source', true ), 'The source name is recorded' );
    check( ! isset( get_transient( 'pandoc_1_' . $conversion['token'] )['images'] ), 'Saved previews no longer retain image bodies' );
    check( $draft['id'] === Plugin::draft( request( [ 'token' => $conversion['token'], 'title' => 'A retry' ] ) )['id'], 'A completed request can be retried without a second draft' );
    check( is_wp_error( Plugin::draft( request( [ 'token' => wp_generate_uuid4(), 'title' => 'Expired' ] ) ) ), 'Expired previews cannot create drafts' );
    check( is_wp_error( Plugin::draft( request( [ 'token' => [] ] ) ) ), 'Invalid tokens are rejected' );

    $uid = wp_insert_user( [ 'user_login' => 'pandoc-test-' . wp_generate_password( 10, false ), 'user_pass' => wp_generate_password(), 'role' => 'contributor' ] );
    $users[] = $uid;
    wp_set_current_user( $uid );
    check( is_wp_error( Plugin::draft( request( [ 'token' => $tokens[0], 'title' => 'Another user' ] ) ) ), 'A user cannot use someone else’s preview' );
    $own = Plugin::convert( request( [ 'sample' => 'html' ] ) );
    $tokens[] = $own['token'];
    check( ! is_wp_error( $own ), 'Contributors can preview documents' );
    check( is_wp_error( Plugin::draft( request( [ 'token' => $own['token'], 'title' => 'Forbidden page', 'type' => 'page' ] ) ) ), 'Page creation checks page capabilities' );
    check( is_wp_error( Plugin::draft( request( [ 'token' => $own['token'], 'title' => 'Forbidden type', 'type' => 'attachment' ] ) ) ), 'Unsupported post types are rejected' );
    check( is_wp_error( Plugin::draft( request( [ 'token' => $own['token'], 'title' => '' ] ) ) ), 'Blank draft titles are rejected' );

    $token = wp_generate_uuid4();
    $tokens[] = $token;
    $image = imagecreatetruecolor( 10, 10 );
    ob_start();
    imagepng( $image );
    $png = ob_get_clean();
    $pending = [ 'name' => 'image-test.html', 'blocks' => '<!-- wp:image --><figure class="wp-block-image"><img src="pandoc-media/test.png" alt="A test image"/></figure><!-- /wp:image -->', 'images' => [ 'pandoc-media/test.png' => [ 'data' => base64_encode( $png ), 'mime' => 'image/png' ] ] ];
    set_transient( 'pandoc_' . $uid . '_' . $token, $pending, HOUR_IN_SECONDS );
    check( is_wp_error( Plugin::draft( request( [ 'token' => $token, 'title' => 'No upload permission' ] ) ) ), 'Embedded images need upload permission' );
    wp_set_current_user( 1 );
    set_transient( 'pandoc_1_' . $token, $pending, HOUR_IN_SECONDS );
    $draft = Plugin::draft( request( [ 'token' => $token, 'title' => 'Image test' ] ) );
    check( ! is_wp_error( $draft ), 'An embedded image can be saved' );
    $posts[] = $draft['id'];
    $attachments = get_children( [ 'post_parent' => $draft['id'], 'post_type' => 'attachment' ] );
    check( 1 === count( $attachments ), 'The image is attached to its draft' );
    $posts = array_merge( $posts, array_keys( $attachments ) );
    check( str_contains( get_post_field( 'post_content', $draft['id'] ), '/uploads/' ), 'Image paths become Media Library URLs' );
    check( ! str_contains( get_post_field( 'post_content', $draft['id'] ), 'data:image' ), 'Drafts do not store base64 image bodies in their markup' );

    wp_set_current_user( 0 );
    $r = request( [ 'sample' => 'docx' ] );
    $r->set_route( '/pandoc-importer/v1/convert' );
    check( 401 === rest_do_request( $r )->get_status(), 'Anonymous conversion is denied' );
    $r = request( [ 'token' => $token, 'title' => 'Anonymous draft' ] );
    $r->set_route( '/pandoc-importer/v1/draft' );
    check( 401 === rest_do_request( $r )->get_status(), 'Anonymous draft creation is denied' );
    echo 'PASS: ' . $GLOBALS['pandoc_test_checks'] . " checks\n";
} finally {
    wp_set_current_user( 1 );
    foreach ( array_reverse( $posts ) as $id ) {
        if ( 'attachment' === get_post_type( $id ) ) {
            wp_delete_attachment( $id, true );
        } else {
            wp_delete_post( $id, true );
        }
    }
    foreach ( array_merge( [ 1 ], $users ) as $uid ) {
        foreach ( $tokens as $token ) {
            delete_transient( 'pandoc_' . $uid . '_' . $token );
        }
    }
    foreach ( $users as $uid ) {
        wp_delete_user( $uid );
    }
    wp_set_current_user( $original_user );
}

function check( bool $condition, string $label ): void {
    if ( ! $condition ) {
        throw new RuntimeException( 'FAIL: ' . $label );
    }
    $GLOBALS['pandoc_test_checks']++;
    echo "OK: $label\n";
}

function request( array $params ): WP_REST_Request {
    $request = new WP_REST_Request( 'POST' );
    foreach ( $params as $key => $value ) {
        $request->set_param( $key, $value );
    }
    return $request;
}

function rejects( callable $run, string $label ): void {
    try {
        $run();
    } catch ( Throwable $error ) {
        check( true, $label );
        return;
    }
    check( false, $label );
}
