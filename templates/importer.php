<?php
namespace PandocImporter;

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap pandoc-app" data-step="start">
    <section id="pandoc-import-view">
        <div id="pandoc-start">
            <div class="pandoc-page-heading pandoc-start-heading">
                <div>
                    <h1>Collage</h1>
                    <p>Turn documents into editable WordPress blocks.</p>
                </div>
                <div class="pandoc-header-actions">
                    <button type="button" class="pandoc-text-button" data-view="recent" id="pandoc-recent-link" hidden>Recent imports <span class="pandoc-count" id="pandoc-recent-count">0</span></button>
                    <button type="button" class="pandoc-text-button" id="pandoc-help">Help</button>
                </div>
            </div>

            <div id="pandoc-dropzone" class="pandoc-dropzone">
                <div class="pandoc-sheets" aria-hidden="true">
                    <span class="pandoc-sheet"><b>PDF</b></span>
                    <span class="pandoc-sheet"><b>MD</b></span>
                    <span class="pandoc-sheet"><b>DOCX</b></span>
                </div>
                <h2><span class="pandoc-drop-idle">Drop documents here</span><span class="pandoc-drop-active">Release to import</span></h2>
                <p class="pandoc-drop-formats">Word, PDF, Markdown, HTML, and more. <span class="pandoc-nowrap">Up to <?php echo esc_html( size_format( Plugin::max_bytes() ) ); ?> each.</span></p>
                <div class="pandoc-drop-actions">
                    <button type="button" class="button button-primary pandoc-choose" id="pandoc-choose">Choose files</button>
                    <span>or <button type="button" class="pandoc-text-button" data-sample="docx">try a sample</button></span>
                </div>
                <input type="file" id="pandoc-file" class="screen-reader-text" tabindex="-1" multiple accept="<?php echo esc_attr( '.' . implode( ',.', array_keys( Plugin::FORMATS ) ) ); ?>" aria-label="Choose documents">
            </div>
        </div>

        <div id="pandoc-review" hidden>
            <div class="pandoc-page-heading pandoc-review-heading">
                <div>
                    <button type="button" class="pandoc-text-button pandoc-back" data-reset>← Start over</button>
                    <h1 id="pandoc-review-title">Review your document</h1>
                    <p id="pandoc-review-summary" aria-live="polite"></p>
                </div>
                <button type="button" class="button" id="pandoc-create-all" hidden>Create all drafts</button>
            </div>
            <div class="pandoc-review-body">
                <nav class="pandoc-queue" id="pandoc-queue" aria-label="Documents" hidden>
                    <ul id="pandoc-queue-list"></ul>
                    <button type="button" class="pandoc-text-button pandoc-add" data-add><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>Add documents</button>
                </nav>
                <div class="pandoc-review-main">
                    <div id="pandoc-converting" class="pandoc-state" role="status" aria-live="polite" hidden>
                        <span class="spinner is-active"></span>
                        <h2>Converting document…</h2>
                        <p id="pandoc-converting-name"></p>
                    </div>
                    <div id="pandoc-failed" class="pandoc-state" hidden>
                        <span class="pandoc-state-icon is-error dashicons dashicons-warning" aria-hidden="true"></span>
                        <h2>This document could not be converted</h2>
                        <p id="pandoc-failed-message"></p>
                        <div class="pandoc-state-actions">
                            <button type="button" class="button" id="pandoc-retry">Try again</button>
                            <button type="button" class="pandoc-text-button" id="pandoc-remove">Remove from list</button>
                        </div>
                    </div>
                    <div id="pandoc-saved" class="pandoc-state" hidden>
                        <span class="pandoc-state-icon is-success dashicons dashicons-yes-alt" aria-hidden="true"></span>
                        <h2>Draft created</h2>
                        <p id="pandoc-saved-title"></p>
                        <a class="button" id="pandoc-saved-open">Open in editor</a>
                    </div>
                    <div id="pandoc-ready" class="pandoc-review-layout" hidden>
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
            </div>
        </div>

        <div id="pandoc-success" class="pandoc-success" hidden>
            <span class="pandoc-state-icon is-success dashicons dashicons-yes-alt" aria-hidden="true"></span>
            <h1 id="pandoc-success-heading">Draft created</h1>
            <p id="pandoc-success-title"></p>
            <a class="button button-primary" id="pandoc-open-editor">Open in editor</a>
            <ul id="pandoc-success-list" class="pandoc-success-list" hidden></ul>
            <p id="pandoc-success-note" class="pandoc-muted" hidden></p>
            <button type="button" class="pandoc-text-button" data-reset>Import more documents</button>
        </div>
    </section>

    <section id="pandoc-recent-view" hidden>
        <button type="button" class="pandoc-text-button pandoc-back" data-view="import">← Import a document</button>
        <div class="pandoc-page-heading"><h1>Recent imports</h1><p>Your last 20 imported documents.</p></div>
        <div id="pandoc-recent-empty" class="pandoc-empty"><p>No imports yet.</p><button type="button" class="button button-primary" data-view="import">Import a document</button></div>
        <div id="pandoc-recent-table" class="pandoc-recent-table" hidden><table class="wp-list-table widefat fixed striped"><thead><tr><th>Document</th><th>Original file</th><th>Type</th><th>Status</th><th>Imported</th></tr></thead><tbody id="pandoc-recent-rows"></tbody></table></div>
    </section>
    <div id="pandoc-error" class="notice notice-error inline pandoc-error" role="alert" tabindex="-1" hidden></div>
    <div class="pandoc-drop-overlay" aria-hidden="true">
        <div class="pandoc-drop-card">
            <div class="pandoc-sheets"><span class="pandoc-sheet"><b>PDF</b></span><span class="pandoc-sheet"><b>MD</b></span><span class="pandoc-sheet"><b>DOCX</b></span></div>
            Release to import
        </div>
    </div>

    <dialog id="pandoc-help-dialog" class="pandoc-help-dialog" aria-labelledby="pandoc-help-title">
        <div class="pandoc-dialog-heading"><h2 id="pandoc-help-title">Importing documents</h2><button type="button" class="pandoc-text-button" id="pandoc-close-help" aria-label="Close help"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></div>
        <ol>
            <li><strong>Choose one or more files.</strong> They are converted on your WordPress site. No outside service is used.</li>
            <li><strong>Check the preview.</strong> No post or Media Library item is created yet. The preview is kept on this site for one hour.</li>
            <li><strong>Create a draft.</strong> Choose a post or page, then open the WordPress editor. Nothing is published automatically.</li>
        </ol>
        <h3>Supported formats</h3>
        <p class="pandoc-formats"><?php echo esc_html( '.' . implode( ' · .', $extensions ) ); ?></p>
        <p>Older Word .doc files are not supported. Upload and conversion currently use one request per document. An interrupted conversion must start again. Maximum <?php echo esc_html( size_format( Plugin::max_bytes() ) ); ?> per document.</p>
        <p><strong>PDFs need review.</strong> Reading order, tables, images, and scanned pages may not come through. Compare the draft with your original. There is no OCR.</p>
        <p>Embedded PNG, JPEG, GIF, and WebP images are added to the Media Library when you create a draft. SVG is not imported. External images are not loaded in the preview, but their links remain in the draft. The original document is not kept.</p>
        <p class="pandoc-help-samples">Other samples: <button type="button" class="pandoc-text-button" data-sample="markdown">Markdown</button><span aria-hidden="true"> · </span><button type="button" class="pandoc-text-button" data-sample="html">HTML</button></p>
        <p class="pandoc-muted">Collage</p>
    </dialog>
</div>
