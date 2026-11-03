(() => {
    'use strict';

    const config = window.pandocImporter;
    const $ = (id) => document.getElementById(`pandoc-${id}`);
    const app = document.querySelector('.pandoc-app');
    let conversion = null;
    let busy = false;

    async function convert(body, name) {
        if (busy) return;
        busy = true;
        conversion = null;
        clearError();
        showStep('loading');
        $('loading-name').textContent = name;
        try {
            conversion = await request('convert', body);
            $('title').value = conversion.title;
            $('file-description').textContent = conversion.name;
            $('code').textContent = conversion.blocks;
            $('copy').textContent = 'Copy block markup';
            $('preview').srcdoc = previewDocument(conversion.html);
            $('warnings').replaceChildren(...conversion.warnings.map((warning) => {
                const p = document.createElement('p');
                p.textContent = warning;
                return p;
            }));
            selectPreview(false);
            showStep('review');
            focusHeading('review');
        } catch (error) {
            showStep('start');
            showError(error.message);
        } finally {
            busy = false;
        }
    }

    async function createDraft(event) {
        event.preventDefault();
        if (busy || !conversion) return;
        busy = true;
        clearError();
        const button = $('create');
        button.disabled = true;
        button.textContent = 'Creating your draft…';
        try {
            const title = $('title').value.trim();
            const type = $('type').value;
            if (!title) throw new Error('Add a title for your draft.');
            const draft = await request('draft', { token: conversion.token, title, type });
            $('open-editor').href = draft.editUrl;
            $('success-title').textContent = title;
            config.recent = [{ title, source: conversion.name, type: type === 'page' ? 'Page' : 'Post', status: 'Draft', date: new Intl.DateTimeFormat('en', { month: 'short', day: 'numeric', year: 'numeric' }).format(new Date()), editUrl: draft.editUrl }, ...config.recent.filter((item) => item.editUrl !== draft.editUrl)].slice(0, 20);
            renderRecent();
            showStep('success');
            focusHeading('success');
        } catch (error) {
            showError(error.message);
        } finally {
            busy = false;
            button.disabled = false;
            button.textContent = 'Create draft';
        }
    }

    async function request(path, body) {
        const isFile = body instanceof FormData;
        const response = await fetch(config.api + path, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-WP-Nonce': config.nonce, ...(isFile ? {} : { 'Content-Type': 'application/json' }) },
            body: isFile ? body : JSON.stringify(body),
        });
        let data;
        try {
            data = await response.json();
        } catch {
            throw new Error('The server could not finish this request. Try a smaller document or check your connection.');
        }
        if (!response.ok) throw new Error(data.message || 'Something went wrong. Please try again.');
        return data;
    }

    function upload(files) {
        if (!files.length || busy) return;
        if (files.length > 1) return showError('Choose one document at a time. You can import another after creating a draft.');
        const file = files[0];
        const extension = file.name.split('.').pop().toLowerCase();
        if (!config.formats.includes(extension)) return showError('This file type is not supported. See the formats listed below the dropzone.');
        if (file.size > config.maxBytes) return showError(`This file is too large. Choose one smaller than ${Math.round(config.maxBytes / 1024 / 1024)} MB.`);
        if (!file.size) return showError('This file is empty. Choose a document with some content.');
        const body = new FormData();
        body.append('file', file);
        convert(body, file.name);
    }

    function showStep(step) {
        ['start', 'loading', 'review', 'success'].forEach((name) => { $(name).hidden = name !== step; });
        $('import-view').setAttribute('aria-busy', step === 'loading' ? 'true' : 'false');
    }

    function switchView(view) {
        if (busy) return;
        clearError();
        $('import-view').hidden = view !== 'import';
        $('recent-view').hidden = view !== 'recent';
        const heading = $(view === 'import' ? 'start' : 'recent-view').querySelector('h1');
        heading.tabIndex = -1;
        heading.focus({ preventScroll: true });
    }

    function reset() {
        if (busy) return;
        clearError();
        conversion = null;
        $('file').value = '';
        $('preview').srcdoc = '';
        $('code').textContent = '';
        showStep('start');
        $('choose').focus();
    }

    function showError(message) {
        $('error').querySelector('p').textContent = message;
        $('error').hidden = false;
        $('error').focus();
    }

    function clearError() {
        $('error').hidden = true;
    }

    function focusHeading(step) {
        const heading = $(step).querySelector('h1');
        heading.tabIndex = -1;
        heading.focus({ preventScroll: true });
    }

    function selectPreview(code) {
        $('preview-panel').hidden = code;
        $('code-panel').hidden = !code;
        ['preview-tab', 'code-tab'].forEach((name, index) => {
            const active = code === Boolean(index);
            $(name).classList.toggle('is-active', active);
            $(name).setAttribute('aria-selected', String(active));
            $(name).tabIndex = active ? 0 : -1;
        });
    }

    function renderRecent() {
        $('recent-count').textContent = config.recent.length;
        $('recent-empty').hidden = config.recent.length > 0;
        $('recent-table').hidden = config.recent.length === 0;
        $('recent-rows').replaceChildren(...config.recent.map((item) => {
            const row = document.createElement('tr');
            ['title', 'source', 'type', 'status', 'date'].forEach((key) => {
                const cell = document.createElement('td');
                if (key === 'title') {
                    const link = document.createElement('a');
                    link.href = item.editUrl;
                    link.textContent = item.title;
                    cell.append(link);
                } else cell.textContent = item[key];
                row.append(cell);
            });
            return row;
        }));
    }

    function previewDocument(html) {
        // The sandbox and CSP also block external images, fonts, scripts, and embeds.
        return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta http-equiv="Content-Security-Policy" content="default-src 'none'; img-src data:; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none';"><style>*{box-sizing:border-box}body{font-family:Georgia,'Times New Roman',serif;color:#282828;line-height:1.8;font-size:15px;padding:40px 42px 50px;max-width:760px;margin:auto;overflow-wrap:anywhere}h1,h2,h3,h4,h5,h6{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;line-height:1.35;letter-spacing:-.5px;color:#1e1e1e}h1{font-size:32px;font-weight:500;margin:0 0 24px}h2{font-size:22px;font-weight:500;margin-top:32px}h3{font-size:18px}p{margin:0 0 20px}a{color:#2271b1;pointer-events:none}blockquote{border-left:3px solid #1e1e1e;margin:25px 0;padding:3px 0 3px 23px;font-style:italic;color:#50575e}blockquote p{margin:0}figure{margin:24px 0}img{max-width:100%;height:auto}table{border-collapse:collapse;width:100%;font:13px/1.6 -apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}th,td{border:1px solid #dcdcde;text-align:left;padding:10px}th{background:#f6f7f7}pre{white-space:pre-wrap;overflow-wrap:anywhere;padding:16px;background:#f6f7f7;font-size:12px}hr{border:0;border-top:1px solid #dcdcde;margin:30px 0}figcaption{font-size:12px;color:#646970}svg,iframe,form,object,embed{display:none}@media(max-width:450px){body{padding:28px 22px}h1{font-size:27px}}</style></head><body>${html}</body></html>`;
    }

    $('choose').addEventListener('click', () => $('file').click());
    $('file').addEventListener('change', (event) => { upload(event.target.files); event.target.value = ''; });
    app.querySelectorAll('[data-sample]').forEach((button) => button.addEventListener('click', () => {
        $('help-dialog').close();
        convert({ sample: button.dataset.sample }, 'Sample document');
    }));
    app.querySelectorAll('[data-reset]').forEach((button) => button.addEventListener('click', reset));
    app.querySelectorAll('[data-view]').forEach((button) => button.addEventListener('click', () => switchView(button.dataset.view)));
    $('draft-form').addEventListener('submit', createDraft);
    $('help').addEventListener('click', () => $('help-dialog').showModal());
    $('close-help').addEventListener('click', () => $('help-dialog').close());
    $('preview-tab').addEventListener('click', () => selectPreview(false));
    $('code-tab').addEventListener('click', () => selectPreview(true));
    [$('preview-tab'), $('code-tab')].forEach((tab) => tab.addEventListener('keydown', (event) => {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        const code = event.key === 'End' || (event.key !== 'Home' && tab === $('preview-tab'));
        selectPreview(code);
        $(code ? 'code-tab' : 'preview-tab').focus();
    }));
    $('copy').addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(conversion.blocks);
            $('copy').textContent = 'Copied!';
        } catch {
            showError('Your browser blocked copying. Select the block markup and copy it by hand.');
        }
    });
    let dragDepth = 0;
    const zone = $('dropzone');
    zone.addEventListener('dragenter', (event) => { event.preventDefault(); dragDepth++; zone.classList.add('is-dragover'); });
    zone.addEventListener('dragover', (event) => { event.preventDefault(); event.dataTransfer.dropEffect = 'copy'; });
    zone.addEventListener('dragleave', () => { dragDepth--; if (dragDepth === 0) zone.classList.remove('is-dragover'); });
    zone.addEventListener('drop', (event) => {
        event.preventDefault();
        dragDepth = 0;
        zone.classList.remove('is-dragover');
        upload(event.dataTransfer.files);
    });
    // Keep a missed drop from navigating away and losing the current preview.
    window.addEventListener('dragover', (event) => { if (event.dataTransfer.types.includes('Files')) event.preventDefault(); });
    window.addEventListener('drop', (event) => { if (event.dataTransfer.types.includes('Files')) event.preventDefault(); });
    renderRecent();
})();
