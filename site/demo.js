import { startPlaygroundWeb } from 'https://playground.wordpress.net/client/index.js';

const playgroundOrigin = 'https://playground.wordpress.net';
const importerPath = '/wp-admin/tools.php?page=pandoc-importer';
const iframe = document.getElementById('playground');
const status = document.getElementById('status');
const announcer = document.getElementById('announcer');
const examples = [...document.querySelectorAll('.example')];

// collage-demo.php says "ready" each time the Collage screen loads. Examples
// wait in `pending` until a Collage screen is there to receive them.
let importer = null;
const pending = [];

// WordPress runs in a frame nested inside the Playground iframe, on a sandbox
// origin of its own. Trust a window by where it sits, not by its origin.
const insidePlayground = (source) => {
    for (let frame = source; frame && frame !== window; frame = frame.parent) {
        if (frame === iframe.contentWindow) return true;
    }
    return false;
};
const send = () => importer.window.postMessage({ collage: 'import', files: pending.splice(0) }, importer.origin);

addEventListener('message', (event) => {
    if (event.data?.collage !== 'ready' || !insidePlayground(event.source)) return;
    importer = { window: event.source, origin: event.origin };
    if (pending.length) send();
});

async function boot() {
    const [plugin, bridge] = await Promise.all([
        fetch('collage.zip', { cache: 'no-cache' }).then((response) => response.arrayBuffer()),
        fetch('collage-demo.php').then((response) => response.text()),
    ]);
    const client = await startPlaygroundWeb({
        iframe,
        remoteUrl: `${playgroundOrigin}/remote.html`,
        blueprint: {
            preferredVersions: { php: '8.4', wp: 'latest' },
            features: { intl: true },
            // PDF conversion runs in a REST request, not an admin page.
            constants: { WP_MEMORY_LIMIT: '512M' },
            login: true,
            landingPage: importerPath,
            steps: [
                {
                    step: 'installPlugin',
                    pluginData: { resource: 'literal', name: 'collage.zip', contents: new Uint8Array(plugin) },
                    options: { activate: true },
                },
                {
                    step: 'writeFile',
                    path: '/wordpress/wp-content/mu-plugins/collage-demo.php',
                    data: bridge.replaceAll('DEMO_ORIGIN', location.origin),
                },
            ],
        },
    });
    await client.isReady();
    return client;
}

const playground = boot();
playground.then(() => {
    examples.forEach((example) => { example.disabled = false; });
    status.textContent = 'Choose a document to import it.';
}, (error) => {
    console.error(error);
    status.textContent = 'WordPress could not start. Reload the page to try again.';
});

examples.forEach((example) => example.addEventListener('click', async () => {
    const kind = example.querySelector('.kind');
    const label = kind.dataset.label ??= kind.textContent;
    clearTimeout(example.reset);
    delete example.dataset.state;
    example.disabled = true;
    kind.textContent = 'Opening in Collage…';
    const name = example.querySelector('.name').textContent;
    let file;
    try {
        const path = example.dataset.file;
        const response = await fetch(path);
        if (!response.ok) throw new Error(`Example download returned ${response.status}`);
        file = new File([await response.blob()], path.split('/').pop());
        const client = await playground;
        // The frame that said "ready" may since have moved on to another page.
        // Open Collage again in that case; it takes the file once it has loaded.
        if (!(await client.getCurrentURL()).startsWith(importerPath)) {
            importer = null;
            pending.push(file);
            await client.goTo(importerPath);
        } else {
            pending.push(file);
            if (importer) send();
        }
        status.textContent = 'Choose a document to import it.';
        announcer.textContent = `Sent “${name}” to Collage.`;
        kind.textContent = 'Sent to Collage';
        example.dataset.state = 'sent';
        example.reset = setTimeout(() => {
            kind.textContent = label;
            delete example.dataset.state;
        }, 2500);
    } catch (error) {
        const index = pending.indexOf(file);
        if (index !== -1) pending.splice(index, 1);
        console.error(error);
        kind.textContent = label;
        status.textContent = `Couldn’t open “${name}”. Click the example again to retry.`;
    } finally {
        example.disabled = false;
    }
}));
