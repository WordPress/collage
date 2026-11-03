<?php
/**
 * Plugin Name: Collage
 * Plugin URI: https://github.com/adamziel/collage
 * Description: Turn Word documents, PDFs, Markdown, and more into editable WordPress drafts.
 * Version: 0.2.0
 * Requires at least: 6.7
 * Requires PHP: 8.2
 * Author: Adam Zieliński
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: collage
 *
 * Conversion powered by the native PHP Pandoc port and MarkerPDF from
 * adamziel/port-libs. See THIRD-PARTY.txt for the pinned source and license.
 */

namespace PandocImporter;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/class-plugin.php';

add_action( 'admin_menu', [ Plugin::class, 'menu' ] );
add_action( 'admin_enqueue_scripts', [ Plugin::class, 'assets' ] );
add_action( 'rest_api_init', [ Plugin::class, 'routes' ] );
