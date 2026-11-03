<?php
namespace PandocImporter;

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap pandoc-app">
    <section id="pandoc-import-view">
        <div id="pandoc-start">
            <div class="pandoc-page-heading">
                <h1>Collage</h1>
                <p>Turn Word, PDF, Markdown, and other documents into editable WordPress blocks.</p>
            </div>

            <div id="pandoc-dropzone" class="pandoc-dropzone" aria-label="Document dropzone">
                <span class="dashicons dashicons-media-document" aria-hidden="true"></span>
                <h2>Drop a document here</h2>
                <button type="button" class="button button-primary pandoc-choose" id="pandoc-choose">Choose a file</button>
                <input type="file" id="pandoc-file" class="screen-reader-text" tabindex="-1" accept="<?php echo esc_attr( '.' . implode( ',.', array_keys( Plugin::FORMATS ) ) ); ?>" aria-label="Choose a document">
                <span class="pandoc-upload-limit">Maximum <?php echo esc_html( size_format( Plugin::max_bytes() ) ); ?></span>
            </div>
            <p class="pandoc-formats"><?php echo esc_html( '.' . implode( ' · .', $extensions ) ); ?></p>
            <p class="pandoc-format-note">PDF import is experimental. Scanned pages need a text layer. Older Word .doc files are not supported.</p>
            <p class="pandoc-import-note">Preview first. Nothing is published automatically.</p>
            <p class="pandoc-sample-link"><button type="button" class="pandoc-text-button" data-sample="docx">Try a sample document</button></p>
            <div class="pandoc-secondary-actions">
                <button type="button" class="pandoc-text-button" data-view="recent">Recent imports <span id="pandoc-recent-count">0</span></button>
                <button type="button" class="pandoc-text-button" id="pandoc-help">Help</button>
            </div>
        </div>

        <div id="pandoc-loading" class="pandoc-loading" hidden role="status" aria-live="polite">
            <span class="spinner is-active"></span>
            <h1>Converting document…</h1>
            <p id="pandoc-loading-name"></p>
        </div>

        <div id="pandoc-review" hidden>
            <button type="button" class="pandoc-text-button pandoc-back" data-reset>← Choose another document</button>
            <div class="pandoc-page-heading"><h1>Review your document</h1><p id="pandoc-file-description"></p></div>
            <div class="pandoc-review-layout">
                <section class="pandoc-preview-card">
                    <div class="pandoc-preview-toolbar" role="tablist" aria-label="Preview mode">
                        <button type="button" class="is-active" role="tab" aria-selected="true" id="pandoc-preview-tab" aria-controls="pandoc-preview-panel">Preview</button>
                        <button type="button" role="tab" aria-selected="false" tabindex="-1" id="pandoc-code-tab" aria-controls="pandoc-code-panel">Block markup</button>
                    </div>
                    <div id="pandoc-preview-panel" role="tabpanel" aria-labelledby="pandoc-preview-tab"><iframe id="pandoc-preview" title="Converted document preview" sandbox="" referrerpolicy="no-referrer"></iframe></div>
                    <div id="pandoc-code-panel" role="tabpanel" aria-labelledby="pandoc-code-tab" hidden><pre id="pandoc-code" tabindex="0"></pre><button type="button" class="button" id="pandoc-copy">Copy block markup</button></div>
                </section>
                <aside class="pandoc-draft-settings">
                    <h2>Draft details</h2>
                    <form id="pandoc-draft-form">
                        <label for="pandoc-title">Title</label>
                        <input id="pandoc-title" type="text" class="regular-text" required maxlength="250">
                        <label for="pandoc-type">Create as</label>
                        <select id="pandoc-type"><option value="post">Post</option><?php if ( current_user_can( 'edit_pages' ) ) : ?><option value="page">Page</option><?php endif; ?></select>
                        <div id="pandoc-warnings" class="pandoc-warnings"></div>
                        <p class="pandoc-settings-note">Check the preview. Formatting may differ from your original.</p>
                        <button type="submit" class="button button-primary pandoc-create" id="pandoc-create">Create draft</button>
                        <p class="pandoc-not-published">Nothing is published automatically.</p>
                    </form>
                </aside>
            </div>
        </div>

        <div id="pandoc-success" class="pandoc-success" hidden>
            <span class="pandoc-success-icon dashicons dashicons-yes-alt" aria-hidden="true"></span>
            <h1>Draft created</h1>
            <p id="pandoc-success-title"></p>
            <a class="button button-primary" id="pandoc-open-editor">Open in editor</a>
            <button type="button" class="pandoc-text-button" data-reset>Import another document</button>
        </div>
    </section>

    <section id="pandoc-recent-view" hidden>
        <button type="button" class="pandoc-text-button pandoc-back" data-view="import">← Import a document</button>
        <div class="pandoc-page-heading"><h1>Recent imports</h1><p>Your last 20 imported documents.</p></div>
        <div id="pandoc-recent-empty" class="pandoc-empty"><p>No imports yet.</p><button type="button" class="button button-primary" data-view="import">Import a document</button></div>
        <div id="pandoc-recent-table" class="pandoc-recent-table" hidden><table class="wp-list-table widefat fixed striped"><thead><tr><th>Document</th><th>Original file</th><th>Type</th><th>Status</th><th>Imported</th></tr></thead><tbody id="pandoc-recent-rows"></tbody></table></div>
    </section>
    <div id="pandoc-error" class="notice notice-error pandoc-error" role="alert" tabindex="-1" hidden><p></p></div>

    <dialog id="pandoc-help-dialog" class="pandoc-help-dialog" aria-labelledby="pandoc-help-title">
        <div class="pandoc-dialog-heading"><h2 id="pandoc-help-title">Importing documents</h2><button type="button" class="pandoc-text-button" id="pandoc-close-help" aria-label="Close help"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></div>
        <ol>
            <li><strong>Choose a file.</strong> It is converted on your WordPress site. No outside service is used.</li>
            <li><strong>Check the preview.</strong> No post or Media Library item is created yet. The preview is kept on this site for one hour.</li>
            <li><strong>Create a draft.</strong> Choose a post or page, then open the WordPress editor. Nothing is published automatically.</li>
        </ol>
        <p>Embedded PNG, JPEG, GIF, and WebP images are added to the Media Library when you create a draft. SVG is not imported. External images are not loaded in the preview, but their links remain in the draft. The original document is not kept.</p>
        <p><strong>PDFs need review.</strong> Reading order, tables, images, and scanned pages may not come through. Compare the draft with your original. There is no OCR.</p>
        <p>Upload and conversion currently use one request. An interrupted conversion must start again. Maximum <?php echo esc_html( size_format( Plugin::max_bytes() ) ); ?> per document.</p>
        <p class="pandoc-help-samples">Other samples: <button type="button" class="pandoc-text-button" data-sample="markdown">Markdown</button><span aria-hidden="true"> · </span><button type="button" class="pandoc-text-button" data-sample="html">HTML</button></p>
        <p class="pandoc-muted">Collage</p>
    </dialog>
</div>
