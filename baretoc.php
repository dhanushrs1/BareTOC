<?php
/**
 * Plugin Name:       BareTOC – Lightweight Table of Contents
 * Plugin URI:        https://sitefueler.com/baretoc/
 * Description:       A lightweight, unstyled, and SEO-friendly table of contents for WordPress.
 * Version:           1.3.4
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            SiteFueler
 * Author URI:        https://sitefueler.com/
 * Update URI:        https://api.sitefueler.com/updater/baretoc/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       baretoc
 * Domain Path:       /languages
 *
 * @package BareTOC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BARETOC_VERSION', '1.3.4' );
define( 'BARETOC_FILE', __FILE__ );
define( 'BARETOC_PATH', plugin_dir_path( __FILE__ ) );
define( 'BARETOC_URL', plugin_dir_url( __FILE__ ) );
define( 'BARETOC_BASENAME', plugin_basename( __FILE__ ) );

require_once BARETOC_PATH . 'includes/class-baretoc-parser.php';
require_once BARETOC_PATH . 'includes/class-baretoc-renderer.php';
require_once BARETOC_PATH . 'includes/class-baretoc-settings.php';
require_once BARETOC_PATH . 'includes/class-baretoc-shortcode.php';
require_once BARETOC_PATH . 'includes/class-baretoc-integrations.php';
require_once BARETOC_PATH . 'includes/class-baretoc-updater.php';
require_once BARETOC_PATH . 'includes/class-baretoc-plugin.php';

/**
 * Returns the main plugin instance.
 *
 * @return BareTOC_Plugin
 */
function baretoc() {
	return BareTOC_Plugin::instance();
}

baretoc();
