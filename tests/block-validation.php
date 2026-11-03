<?php

// Mount this file in the local site with the symlink described in README.md.
require dirname( __DIR__ ) . '/.local/wordpress/wp-load.php';
if ( 'local' !== wp_get_environment_type() || ! current_user_can( 'edit_posts' ) ) {
    status_header( 403 );
    exit( 'Local test site login required.' );
}

$documents = [];
foreach ( [ 'docx', 'markdown', 'html' ] as $sample ) {
    $request = new WP_REST_Request( 'POST' );
    $request->set_param( 'sample', $sample );
    $result = PandocImporter\Plugin::convert( $request );
    if ( is_wp_error( $result ) ) {
        exit( esc_html( $result->get_error_message() ) );
    }
    $documents[ $sample ] = $result['blocks'];
    delete_transient( 'pandoc_' . get_current_user_id() . '_' . $result['token'] );
}
$posts = get_posts( [ 'post_type' => [ 'post', 'page' ], 'post_status' => 'draft', 'author' => get_current_user_id(), 'meta_key' => '_pandoc_source', 'numberposts' => 20 ] );
foreach ( $posts as $post ) {
    $documents[ 'Draft ' . $post->ID . ': ' . $post->post_title ] = $post->post_content;
}
wp_enqueue_script( 'wp-block-library' );
wp_enqueue_script( 'wp-format-library' );
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Collage block validation</title><style>body{font:15px/1.6 system-ui;max-width:1000px;margin:40px auto;padding:0 20px}table{border-collapse:collapse;width:100%}td,th{border:1px solid #ccc;padding:12px;text-align:left}.pass{color:#23713b}.fail{color:#b32d2e}pre{white-space:pre-wrap;overflow-wrap:anywhere}</style><?php wp_head(); ?></head><body><h1>Native WordPress block validation</h1><p>Runs the installed WordPress block parser and each block’s save function. Includes the samples and the current user’s imported drafts.</p><p id="summary" role="status">Checking…</p><table><thead><tr><th>Document</th><th>Blocks</th><th>Result</th></tr></thead><tbody id="results"></tbody></table><?php wp_footer(); ?><script>
wp.blockLibrary.registerCoreBlocks();
const documents = <?php echo wp_json_encode( $documents, JSON_HEX_TAG | JSON_HEX_AMP ); ?>;
let failures = 0;
let total = 0;
for (const [name, content] of Object.entries(documents)) {
    const invalid = [];
    let count = 0;
    function inspect(blocks) {
        for (const block of blocks) {
            count++;
            if (!block.isValid) invalid.push(block.name);
            inspect(block.innerBlocks);
        }
    }
    inspect(wp.blocks.parse(content));
    total += count;
    failures += invalid.length;
    const row = document.createElement('tr');
    for (const value of [name, String(count), invalid.length ? 'FAIL: ' + invalid.join(', ') : 'PASS']) {
        const cell = document.createElement('td');
        cell.textContent = value;
        row.append(cell);
    }
    row.className = invalid.length ? 'fail' : 'pass';
    document.getElementById('results').append(row);
}
document.getElementById('summary').textContent = failures ? `FAIL: ${failures} invalid blocks out of ${total}.` : `PASS: ${total} blocks accepted by WordPress.`;
</script></body></html>
