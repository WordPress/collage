<?php
/**
 * Plugin Name: Collage demo bridge
 * Description: Lets the Collage demo page add example documents to the importer.
 */

// demo.js writes its own origin over DEMO_ORIGIN before installing this file,
// so only the page that started this Playground can send documents.
add_action( 'admin_footer-tools_page_pandoc-importer', function () {
    ?>
<script>
(() => {
    const demo = 'DEMO_ORIGIN';
    addEventListener('message', (event) => {
        if (event.origin !== demo || event.data?.collage !== 'import') return;
        const transfer = new DataTransfer();
        event.data.files.forEach((file) => transfer.items.add(file));
        // Collage accepts files dropped anywhere on its screen, so an example
        // arrives exactly the way a dropped file would.
        dispatchEvent(new DragEvent('drop', { dataTransfer: transfer, cancelable: true }));
    });
    window.top.postMessage({ collage: 'ready' }, demo);
})();
</script>
    <?php
} );
