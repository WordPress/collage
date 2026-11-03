(() => {
    'use strict';

    const config = window.pandocImporter;
    const $ = (id) => document.getElementById(`pandoc-${id}`);
    const app = document.querySelector('.pandoc-app');
    const open = ['queued', 'converting', 'ready'];
    const samples = { docx: 'Sample document', markdown: 'Markdown sample', html: 'HTML sample' };
    // Each chosen file becomes one item. Conversions run one at a time: each is a
    // single long request, and parallel ones would compete for PHP workers and memory.
    let items = [];
    let selected = null;
    let shown = null;
    let converting = false;
    let saving = false;
    let defaultType = 'post';
    let nextId = 1;

    function addFiles(files) {
        if (saving) return;
        const problems = [];
        const accepted = [];
        for (const file of files) {
            const problem = check(file);
            if (problem) problems.push(files.length > 1 ? `${file.name}: ${problem}` : problem);
            else accepted.push({ file, name: file.name });
        }
        enqueue(accepted);
        if (problems.length) showError(...problems);
    }

    function check(file) {
        const extension = file.name.includes('.') ? file.name.split('.').pop().toLowerCase() : '';
        if (extension === 'doc') return 'Older Word .doc files are not supported. Save the file as .docx and try again.';
        if (!config.formats.includes(extension)) return 'This file type is not supported. Try Word, PDF, Markdown, HTML, EPUB, OpenDocument, RTF, text, PowerPoint, Excel, or CSV.';
        if (file.size > config.maxBytes) return `This file is too large. Choose one smaller than ${Math.round(config.maxBytes / 1024 / 1024)} MB.`;
        if (!file.size) return 'This file is empty. Choose a document with some content.';
        return '';
    }

    function enqueue(entries) {
        if (!entries.length || saving) return;
        clearError();
        if (isDone()) items = [];
        const entering = $('review').hidden;
        const added = entries.map((entry) => ({ id: nextId++, status: 'queued', title: '', type: null, ...entry }));
        items.push(...added);
        selected = added[0];
        render();
        if (entering) focusHeading('review');
        convertNext();
    }

    async function convertNext() {
        if (converting) return;
        converting = true;
        let item;
        while ((item = items.find((candidate) => candidate.status === 'queued'))) {
            item.status = 'converting';
            render();
            let body = { sample: item.sample };
            if (item.file) {
                body = new FormData();
                body.append('file', item.file);
            }
            try {
                item.conversion = await request('convert', body);
                item.name = item.conversion.name;
                item.title = item.conversion.title;
                item.status = 'ready';
            } catch (error) {
                item.error = error.message;
                item.status = 'failed';
            }
            // The user may have removed this document or started over meanwhile.
            if (!items.includes(item)) continue;
            if (items.length === 1 && item.status === 'failed') {
                // A single document that fails sends the user back to the dropzone.
                items = [];
                render();
                showError(item.error);
            } else render();
        }
        converting = false;
    }

    async function createDraft(event) {
        event.preventDefault();
        if (saving || selected?.status !== 'ready') return;
        selected.title = $('title').value.trim();
        if (!selected.title) return showError('Add a title for your draft.');
        await save([selected], $('create'), 'Creating your draft…');
    }

    async function save(list, button, label) {
        saving = true;
        clearError();
        const idle = button.textContent;
        button.disabled = true;
        button.textContent = label;
        try {
            for (const item of list) {
                const type = item.type || defaultType;
                const title = item.title.trim() || item.conversion.title;
                let draft;
                try {
                    draft = await request('draft', { token: item.conversion.token, title, type });
                } catch (error) {
                    throw new Error(list.length > 1 ? `${item.name}: ${error.message}` : error.message);
                }
                item.status = 'saved';
                item.title = title;
                item.editUrl = draft.editUrl;
                config.recent = [{ title, source: item.name, type: type === 'page' ? 'Page' : 'Post', status: 'Draft', date: new Intl.DateTimeFormat('en', { month: 'short', day: 'numeric', year: 'numeric' }).format(new Date()), editUrl: draft.editUrl }, ...config.recent.filter((recent) => recent.editUrl !== draft.editUrl)].slice(0, 20);
                if (list.length > 1) renderQueue();
            }
        } catch (error) {
            showError(error.message);
        } finally {
            saving = false;
            button.disabled = false;
            button.textContent = idle;
            renderRecent();
            if (selected.status === 'saved') selected = items.find((item) => item.status === 'ready') || items.find((item) => open.includes(item.status)) || selected;
            render();
            if (!$('success').hidden) focusHeading('success');
        }
    }

    async function request(path, body) {
        const isFile = body instanceof FormData;
        let response;
        try {
            response = await fetch(config.api + path, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': config.nonce, ...(isFile ? {} : { 'Content-Type': 'application/json' }) },
                body: isFile ? body : JSON.stringify(body),
            });
        } catch {
            if (path === 'convert' && isFile && /\.pdf$/i.test(body.get('file').name)) {
                throw new Error('WordPress stopped responding while reading this PDF. Try exporting a smaller page range as a new PDF, or import the original document instead.');
            }
            throw new Error(path === 'convert'
                ? 'Collage couldn’t reach WordPress. Try the document again. If it keeps failing in the demo, reload the demo page.'
                : 'Collage lost contact with WordPress while saving. Check your drafts before trying again.');
        }
        let data;
        try {
            data = await response.json();
        } catch {
            throw new Error('The server could not finish this request. Try a smaller document or check your connection.');
        }
        if (!response.ok) throw new Error(data.message || 'Something went wrong. Please try again.');
        return data;
    }

    function isDone() {
        return !items.some((item) => open.includes(item.status)) && items.some((item) => item.status === 'saved');
    }

    function render() {
        if (!items.length) return showStep('start');
        if (isDone()) return renderDone();
        showStep('review');
        const many = items.length > 1;
        const ready = items.filter((item) => item.status === 'ready');
        $('review-title').textContent = many ? `Review ${items.length} documents` : 'Review your document';
        $('review-summary').textContent = many ? summarize() : [selected.name, ...facts(selected)].join(' · ');
        $('create-all').hidden = ready.length < 2;
        $('create-all').textContent = saving ? $('create-all').textContent : `Create ${ready.length} drafts`;
        $('queue').hidden = !many;
        $('review').classList.toggle('has-queue', many);
        renderQueue();

        const status = selected.status;
        $('converting').hidden = !['queued', 'converting'].includes(status);
        $('failed').hidden = status !== 'failed';
        $('saved').hidden = status !== 'saved';
        $('ready').hidden = status !== 'ready';
        $('converting-name').textContent = selected.name;
        $('converting-name').hidden = !many;
        $('failed-message').textContent = selected.error || '';
        $('saved-title').textContent = selected.title;
        if (selected.editUrl) $('saved-open').href = selected.editUrl;
        if (status === 'ready' && shown !== selected) show(selected);
    }

    function show(item) {
        shown = item;
        $('title').value = item.title;
        $('type').value = item.type || defaultType;
        $('code').textContent = item.conversion.blocks;
        $('copy').textContent = 'Copy block markup';
        $('preview').srcdoc = previewDocument(item.conversion.html);
        $('warnings').replaceChildren(...item.conversion.warnings.map((warning) => {
            const p = document.createElement('p');
            p.textContent = warning;
            return p;
        }));
        selectPreview(false);
    }

    function renderQueue() {
        const focused = document.activeElement?.closest('.pandoc-queue-item')?.dataset.id;
        $('queue-list').replaceChildren(...items.map((item) => {
            const row = document.createElement('li');
            row.className = `pandoc-queue-item is-${item.status}${item === selected ? ' is-selected' : ''}`;
            row.dataset.id = item.id;
            const select = document.createElement('button');
            select.type = 'button';
            select.className = 'pandoc-queue-select';
            if (item === selected) select.setAttribute('aria-current', 'true');
            const icon = document.createElement('span');
            icon.className = `pandoc-queue-icon ${{ queued: 'dashicons dashicons-clock', converting: 'pandoc-spinner', ready: 'dashicons dashicons-media-document', failed: 'dashicons dashicons-warning', saved: 'dashicons dashicons-yes-alt' }[item.status]}`;
            icon.setAttribute('aria-hidden', 'true');
            const name = document.createElement('span');
            name.className = 'pandoc-queue-name';
            name.textContent = item.name;
            const meta = document.createElement('span');
            meta.className = 'pandoc-queue-meta';
            meta.textContent = { queued: 'Waiting', converting: 'Converting…', failed: 'Could not convert', saved: 'Draft created' }[item.status] || facts(item).join(' · ');
            select.append(icon, name, meta);
            select.addEventListener('click', () => {
                selected = item;
                render();
            });
            row.append(select);
            if (item.status !== 'saved') {
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'pandoc-queue-remove';
                remove.setAttribute('aria-label', `Remove ${item.name}`);
                remove.innerHTML = '<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>';
                remove.addEventListener('click', () => removeItem(item));
                row.append(remove);
            }
            return row;
        }));
        if (focused) $('queue-list').querySelector(`[data-id="${focused}"] .pandoc-queue-select`)?.focus();
    }

    function renderDone() {
        showStep('success');
        const saved = items.filter((item) => item.status === 'saved');
        const failed = items.filter((item) => item.status === 'failed').length;
        const one = saved.length === 1;
        $('success-heading').textContent = one ? 'Draft created' : `${saved.length} drafts created`;
        $('success-title').hidden = !one;
        $('success-title').textContent = saved[0].title;
        $('open-editor').hidden = !one;
        $('open-editor').href = saved[0].editUrl;
        $('success-list').hidden = one;
        $('success-list').replaceChildren(...saved.map((item) => {
            const row = document.createElement('li');
            const title = document.createElement('span');
            title.textContent = item.title;
            const link = document.createElement('a');
            link.href = item.editUrl;
            link.textContent = 'Open in editor';
            link.setAttribute('aria-label', `Open ${item.title} in the editor`);
            row.append(title, link);
            return row;
        }));
        $('success-note').hidden = !failed;
        $('success-note').textContent = `${failed} ${failed === 1 ? 'document was' : 'documents were'} not imported.`;
    }

    function facts(item) {
        if (!item.conversion) return [];
        const { blockCount: blocks, imageCount: images } = item.conversion;
        return [`${blocks} ${blocks === 1 ? 'block' : 'blocks'}`, ...(images ? [`${images} ${images === 1 ? 'image' : 'images'}`] : [])];
    }

    function summarize() {
        const count = (...statuses) => items.filter((item) => statuses.includes(item.status)).length;
        const saved = count('saved');
        return [[count('ready'), 'ready'], [count('queued', 'converting'), 'converting'], [saved, saved === 1 ? 'draft created' : 'drafts created'], [count('failed'), 'failed']]
            .filter(([n]) => n)
            .map(([n, label]) => `${n} ${label}`)
            .join(' · ');
    }

    function removeItem(item) {
        if (saving) return;
        const index = items.indexOf(item);
        items.splice(index, 1);
        if (selected === item) selected = items[index] || items[index - 1] || null;
        clearError();
        render();
        if (!items.length) $('choose').focus();
        else if (!$('queue').hidden) $('queue-list').querySelector(`[data-id="${selected.id}"] .pandoc-queue-select`)?.focus();
    }

    function showStep(step) {
        ['start', 'review', 'success'].forEach((name) => { $(name).hidden = name !== step; });
        app.dataset.step = step;
    }

    function currentStep() {
        return [$('start'), $('review'), $('success')].find((element) => !element.hidden);
    }

    function switchView(view) {
        if (saving) return;
        clearError();
        $('import-view').hidden = view !== 'import';
        $('recent-view').hidden = view !== 'recent';
        app.dataset.step = view === 'recent' ? 'recent' : currentStep().id.replace('pandoc-', '');
        const heading = (view === 'import' ? currentStep() : $('recent-view')).querySelector('h1');
        heading.tabIndex = -1;
        heading.focus({ preventScroll: true });
    }

    function reset() {
        if (saving) return;
        clearError();
        items = [];
        selected = null;
        shown = null;
        $('preview').srcdoc = '';
        $('code').textContent = '';
        showStep('start');
        $('choose').focus();
    }

    function showError(...messages) {
        // Keep the notice under the heading of whichever screen is showing.
        currentStep().querySelector('.pandoc-page-heading, h1').after($('error'));
        $('error').replaceChildren(...messages.map((message) => {
            const p = document.createElement('p');
            p.textContent = message;
            return p;
        }));
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
        $('recent-link').hidden = config.recent.length === 0;
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

    const pick = () => $('file').click();
    $('choose').addEventListener('click', pick);
    $('dropzone').addEventListener('click', (event) => { if (!event.target.closest('button, a, input')) pick(); });
    app.querySelectorAll('[data-add]').forEach((button) => button.addEventListener('click', pick));
    $('file').addEventListener('change', (event) => { addFiles([...event.target.files]); event.target.value = ''; });
    app.querySelectorAll('[data-sample]').forEach((button) => button.addEventListener('click', () => {
        $('help-dialog').close();
        enqueue([{ sample: button.dataset.sample, name: samples[button.dataset.sample] }]);
    }));
    app.querySelectorAll('[data-reset]').forEach((button) => button.addEventListener('click', reset));
    app.querySelectorAll('[data-view]').forEach((button) => button.addEventListener('click', () => switchView(button.dataset.view)));
    $('draft-form').addEventListener('submit', createDraft);
    $('create-all').addEventListener('click', () => save(items.filter((item) => item.status === 'ready'), $('create-all'), 'Creating drafts…'));
    $('title').addEventListener('input', (event) => { if (shown) shown.title = event.target.value; });
    $('type').addEventListener('change', (event) => {
        defaultType = event.target.value;
        if (shown) shown.type = defaultType;
    });
    $('retry').addEventListener('click', () => {
        selected.status = 'queued';
        render();
        convertNext();
    });
    $('remove').addEventListener('click', () => removeItem(selected));
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
            await navigator.clipboard.writeText(shown.conversion.blocks);
            $('copy').textContent = 'Copied!';
        } catch {
            showError('Your browser blocked copying. Select the block markup and copy it by hand.');
        }
    });

    // The whole window accepts dropped files, on every import screen.
    // This also keeps a missed drop from navigating away and losing the previews.
    let dragDepth = 0;
    const carriesFiles = (event) => event.dataTransfer?.types.includes('Files');
    const accepting = () => !saving && !$('help-dialog').open;
    const endDrag = () => {
        dragDepth = 0;
        app.classList.remove('is-dragging');
    };
    window.addEventListener('dragenter', (event) => {
        if (!carriesFiles(event)) return;
        event.preventDefault();
        if (dragDepth++ === 0 && accepting()) app.classList.add('is-dragging');
    });
    window.addEventListener('dragleave', (event) => {
        if (carriesFiles(event) && --dragDepth <= 0) endDrag();
    });
    window.addEventListener('dragover', (event) => {
        if (!carriesFiles(event)) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = accepting() ? 'copy' : 'none';
    });
    window.addEventListener('drop', (event) => {
        endDrag();
        if (!carriesFiles(event)) return;
        event.preventDefault();
        if (!accepting()) return;
        if (!$('recent-view').hidden) switchView('import');
        addFiles([...event.dataTransfer.files]);
    });
    renderRecent();
})();
