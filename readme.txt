=== Collage ===
Contributors: adamziel
Tags: import, documents, docx, markdown, blocks
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 0.2.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Turn documents into editable WordPress blocks. Preview the result and save it as a post or page draft.

== Description ==

**Collage is an experimental project.** Try it in Playground or on a test site first. Some formatting may change during import, so review the draft before publishing.

Open Tools → Collage and drop in a document. Check the preview, give it a title, and save it as a draft. Open the draft in the block editor to revise it before publishing.

Supported extensions: .csv, .docx, .epub, .htm, .html, .markdown, .md, .odt, .pdf, .pptx, .rtf, .txt, and .xlsx.

Collage reads documents on your WordPress server. It does not send them to a conversion service. The plugin bundles the native PHP readers and runs without Composer or a Pandoc executable.

[Source code and build instructions](https://github.com/WordPress/collage) · [Try the demo](https://wordpress.github.io/collage/)

== Installation ==

1. Install and activate Collage.
2. Go to Tools → Collage.
3. Drop in one or more documents, or choose try a sample.
4. Review the preview, then create a draft.

== Frequently Asked Questions ==

= What are the server requirements? =

WordPress 6.7 and PHP 8.2 or newer. The readers use PHP DOM, Intl, Mbstring, Zip, and Zlib. GD or Imagick lets WordPress generate image sizes. The upload limit is 8 MB or your site's lower limit. Compressed documents have separate limits on their unpacked size and entry count.

= Does it publish anything automatically? =

No. A preview does not create a post or media item. Creating a draft adds supported embedded images to the Media Library. Like other Media Library files, these images may be accessible by their URLs even before the draft is published.

= What happens to my original file? =

PHP discards the temporary upload after conversion. The converted preview and its embedded images are stored on your site for up to one hour. Creating a draft saves its blocks and supported images and replaces the preview with a reference to the draft.

= Will every document look the same? =

No. Review the converted document before publishing. PDF support is experimental and does not transcribe scanned pages. Collage does not import SVG. External images are blocked in the preview; their links can still load in the editor or published post.

= Can a large conversion resume after an interruption? =

No. Upload and conversion happen in one request. If conversion stops before you get a preview, retry the import or use a smaller file.

== Screenshots ==

1. Drop in one or more documents, or try a built-in sample.
2. Review each converted document, then create drafts one at a time or all together.

== Changelog ==

= 0.2.3 =
* Center the draft confirmation in the WordPress admin content area.

= 0.2.2 =
* Select newly added documents automatically and show clearer messages when an import is interrupted.
* Build from the reader source in Collage and run reader and block-writer tests in CI.

= 0.2.1 =
* Remove unused OCR code from the plugin ZIP. PDF text extraction and Adobe character maps are unchanged.
* Update bundled third-party notices.

= 0.2.0 =
* Import several documents at once. Review each one, then create drafts one at a time or all together.
* Redesigned upload screen. Drop files anywhere on the page; file type and size problems appear only when a file has one.
* Fix: an error while creating a draft was not shown.
* New plugin icon and directory banners.

= 0.1.0 =
* First release. Preview documents as WordPress blocks and import them into post or page drafts.
