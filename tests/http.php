<?php

// These requests only touch the isolated loopback test site made by setup.sh.
$root = dirname( __DIR__ );
$base = 'http://127.0.0.1:8890';
$cookie = tempnam( sys_get_temp_dir(), 'pandoc-http-cookie-' );
$checks = 0;
$fixtures = [];
$created = [];

try {
    http( '/wp-login.php' );
    http( '/wp-login.php', [ 'log' => 'admin', 'pwd' => 'pandoc-local-only', 'wp-submit' => 'Log In', 'redirect_to' => $base . '/wp-admin/', 'testcookie' => '1' ], [], false );
    [ $status, $html ] = http( '/wp-admin/tools.php?page=pandoc-importer' );
    preg_match( '/window\.pandocImporter = (.*?);/', $html, $match );
    $config = json_decode( $match[1] ?? '', true );
    verify( 200 === $status && isset( $config['nonce'] ), 'Admin page loads with a REST nonce' );
    $auth = [ 'X-WP-Nonce: ' . $config['nonce'] ];

    foreach ( [ 'a-good-place-to-start.docx', 'field-notes.md', 'the-weekly-edit.html' ] as $sample ) {
        [ $status, $body ] = http( '/?rest_route=/pandoc-importer/v1/convert', [ 'file' => new CURLFile( $root . '/samples/' . $sample ) ], $auth );
        $result = json_decode( $body, true );
        verify( 200 === $status && ( $result['blockCount'] ?? 0 ) > 5, "Real multipart upload: $sample" );
        verify( ! str_contains( $result['blocks'], '<colgroup' ), "Editable tables: $sample" );
    }

    $image = imagecreatetruecolor( 30, 20 );
    ob_start();
    imagepng( $image );
    $png = ob_get_clean();
    $fixtures['images.html'] = '<h1>Image import</h1><p>A document with a picture.</p><img alt="Embedded test picture" src="data:image/png;base64,' . base64_encode( $png ) . '">';
    $fixtures['unsafe.html'] = '<h1>Safe content</h1><p onclick="alert(1)">Hello<script>alert(1)</script></p><a href="javascript:alert(1)">Link</a>';
    $fixtures['remote-image.html'] = '<h1>Remote picture</h1><img src="https://example.test/not-requested.png" alt="A remote image">';
    $fixtures['invalid.docx'] = 'This is not a Word document.';
    $fixtures['invalid.pdf'] = 'This is not a PDF.';
    $fixtures['empty.md'] = '';
    $fixtures['unsupported.exe'] = 'not executable';
    $fixtures['oversized.md'] = str_repeat( 'x', 8 * 1024 * 1024 + 1 );
    $fixtures['plain.txt'] = '<script>This must stay plain text.</script>';
    $fixtures['minimal.pdf'] = make_pdf();
    foreach ( $fixtures as $name => $contents ) {
        file_put_contents( $root . '/.local/' . $name, $contents );
    }
    foreach ( [ 'images.html', 'unsafe.html', 'remote-image.html', 'plain.txt', 'minimal.pdf' ] as $name ) {
        [ $status, $body ] = http( '/?rest_route=/pandoc-importer/v1/convert', [ 'file' => new CURLFile( $root . '/.local/' . $name ) ], $auth );
        $result = json_decode( $body, true );
        verify( 200 === $status, "$name converts over HTTP: " . ( $result['message'] ?? '' ) );
        if ( 'images.html' === $name ) {
            verify( 1 === $result['imageCount'] && str_contains( $result['html'], 'data:image/png;base64,' ), 'Embedded images are visible in the preview' );
            [ $status, $body ] = http( '/?rest_route=/pandoc-importer/v1/draft', [ 'token' => $result['token'], 'title' => 'HTTP image test', 'type' => 'post' ], $auth );
            $draft = json_decode( $body, true );
            verify( 200 === $status && isset( $draft['id'] ), 'An uploaded document creates a draft over HTTP' );
            $created[] = 'posts/' . $draft['id'];
            [ , $body ] = http( '/?rest_route=/wp/v2/posts/' . $draft['id'] . '&context=edit', null, $auth );
            $post = json_decode( $body, true );
            verify( 'draft' === $post['status'] && str_contains( $post['content']['raw'], '/uploads/' ), 'The saved draft uses a Media Library image URL' );
            [ , $body ] = http( '/?rest_route=/wp/v2/media&parent=' . $draft['id'] . '&_fields=id', null, $auth );
            $media = json_decode( $body, true );
            foreach ( $media as $item ) {
                $created[] = 'media/' . $item['id'];
            }
            verify( 1 === count( $media ), 'Exactly one attachment is created for the uploaded image' );
            [ , $body ] = http( '/?rest_route=/pandoc-importer/v1/draft', [ 'token' => $result['token'], 'title' => 'Retry', 'type' => 'post' ], $auth );
            verify( $draft['id'] === json_decode( $body, true )['id'], 'A repeated HTTP save returns the existing draft' );
        }
        if ( 'unsafe.html' === $name ) {
            verify( ! str_contains( $result['html'], '<script' ) && ! str_contains( $result['html'], 'onclick' ) && ! str_contains( $result['html'], 'javascript:' ), 'Uploaded HTML is filtered' );
        }
        if ( 'remote-image.html' === $name ) {
            verify( count( $result['warnings'] ) > 0 && 0 === $result['imageCount'], 'External images stay external and show a warning' );
        }
        if ( 'plain.txt' === $name ) {
            verify( str_contains( $result['html'], '&lt;script&gt;' ), 'Plain text is not treated as HTML' );
        }
        if ( 'minimal.pdf' === $name ) {
            verify( str_contains( $result['html'], 'A local PDF import' ) && count( $result['warnings'] ) > 0, 'PDF text imports with a review warning' );
        }
    }
    foreach ( [ 'invalid.docx' => 422, 'invalid.pdf' => 422, 'empty.md' => 400, 'unsupported.exe' => 415, 'oversized.md' => 413 ] as $name => $expected ) {
        [ $status ] = http( '/?rest_route=/pandoc-importer/v1/convert', [ 'file' => new CURLFile( $root . '/.local/' . $name ) ], $auth );
        verify( $expected === $status, "$name returns HTTP $expected (got $status)" );
    }
    [ $status ] = http( '/?rest_route=/pandoc-importer/v1/convert', [ 'sample' => 'docx' ], [ 'X-WP-Nonce: invalid' ] );
    verify( 403 === $status, 'Invalid REST nonce is rejected' );
    [ $status ] = http( '/?rest_route=/pandoc-importer/v1/convert', [ 'sample' => 'docx' ], [] );
    verify( 401 === $status, 'Cookie without a REST nonce is not enough' );
    echo "PASS: $checks HTTP checks\n";
} finally {
    foreach ( array_reverse( $created ) as $path ) {
        http( '/?rest_route=/wp/v2/' . $path . '&force=true', null, $auth, true, 'DELETE' );
    }
    unlink( $cookie );
    // Keep small image/PDF fixtures for manual browser testing; remove the oversized fixture.
    if ( is_file( $root . '/.local/oversized.md' ) ) {
        unlink( $root . '/.local/oversized.md' );
    }
}

function http( string $path, ?array $body = null, array $headers = [], bool $follow = true, ?string $method = null ): array {
    global $base, $cookie;
    $curl = curl_init( $base . $path );
    curl_setopt_array( $curl, [ CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => $follow, CURLOPT_COOKIEFILE => $cookie, CURLOPT_COOKIEJAR => $cookie, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 60 ] );
    if ( null !== $body ) {
        curl_setopt( $curl, CURLOPT_POSTFIELDS, $body );
    }
    if ( null !== $method ) {
        curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, $method );
    }
    $body = curl_exec( $curl );
    if ( false === $body ) {
        throw new RuntimeException( curl_error( $curl ) );
    }
    return [ curl_getinfo( $curl, CURLINFO_RESPONSE_CODE ), $body ];
}

function verify( bool $condition, string $label ): void {
    global $checks;
    if ( ! $condition ) {
        throw new RuntimeException( 'FAIL: ' . $label );
    }
    $checks++;
    echo "OK: $label\n";
}

function make_pdf(): string {
    $stream = 'BT /F1 18 Tf 72 720 Td (A local PDF import) Tj ET';
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        '<< /Length ' . strlen( $stream ) . ">>\nstream\n" . $stream . "\nendstream",
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ( $objects as $index => $object ) {
        $offsets[] = strlen( $pdf );
        $pdf .= ( $index + 1 ) . " 0 obj\n$object\nendobj\n";
    }
    $xref = strlen( $pdf );
    $pdf .= "xref\n0 6\n0000000000 65535 f \n";
    foreach ( $offsets as $offset ) {
        $pdf .= sprintf( "%010d 00000 n \n", $offset );
    }
    return $pdf . "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
}
