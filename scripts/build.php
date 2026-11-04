<?php

$root = dirname( __DIR__ );
if ( ! is_file( $root . '/vendor/autoload.php' ) ) {
    fwrite( STDERR, "Run composer dump-autoload --no-dev --optimize first.\n" );
    exit( 1 );
}

$files = [ 'collage.php', 'readme.txt', 'README.md', 'LICENSE', 'THIRD-PARTY.txt', 'composer.json' ];
foreach ( [ 'assets', 'includes', 'templates', 'samples', 'vendor/composer', 'lib' ] as $directory ) {
    $entries = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $directory, FilesystemIterator::SKIP_DOTS ) );
    foreach ( $entries as $entry ) {
        $path = substr( $entry->getPathname(), strlen( $root ) + 1 );
        if ( $entry->isFile() && ! preg_match( '#(?:^|/)\.#', $path ) ) {
            $files[] = $path;
        }
    }
}
$files[] = 'vendor/autoload.php';
sort( $files, SORT_STRING );

$dist = $root . '/dist';
if ( ! is_dir( $dist ) ) {
    mkdir( $dist );
}
$zip = new ZipArchive();
if ( true !== $zip->open( $dist . '/collage.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
    throw new RuntimeException( 'Could not open the plugin ZIP for writing.' );
}
foreach ( $files as $file ) {
    $name = 'collage/' . $file;
    if ( ! $zip->addFile( $root . '/' . $file, $name ) ) {
        throw new RuntimeException( 'Could not package ' . $file );
    }
    // A fresh checkout must not change the ZIP solely through file timestamps.
    $zip->setMtimeName( $name, 315532800 );
}
if ( ! $zip->close() ) {
    throw new RuntimeException( 'Could not finish the plugin ZIP.' );
}
echo $dist . '/collage.zip', "\n";
