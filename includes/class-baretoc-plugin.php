<?php
/**
 * Main plugin coordinator.
 *
 * @package BareTOC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots the plugin's small, independent components.
 */
final class BareTOC_Plugin {

	/**
	 * Plugin instance.
	 *
	 * @var BareTOC_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Settings component.
	 *
	 * @var BareTOC_Settings
	 */
	private $settings;

	/**
	 * Shortcode/content component.
	 *
	 * @var BareTOC_Shortcode
	 */
	private $shortcode;

	/**
	 * Returns the singleton instance.
	 *
	 * @return BareTOC_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Creates and registers plugin components.
	 */
	private function __construct() {
		$parser          = new BareTOC_Parser();
		$renderer        = new BareTOC_Renderer();
		$this->settings  = new BareTOC_Settings();
		$this->shortcode = new BareTOC_Shortcode( $parser, $renderer, $this->settings );
		$integrations    = new BareTOC_Integrations();

		$this->settings->register();
		$this->shortcode->register();
		$integrations->register();

		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Loads translations supplied by WordPress.org or the plugin's language folder.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'baretoc', false, dirname( plugin_basename( BARETOC_FILE ) ) . '/languages' );
	}
}
