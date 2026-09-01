<?php
/**
 * Third-party integrations.
 *
 * @package BareTOC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers optional compatibility hooks without loading third-party code.
 */
final class BareTOC_Integrations {

	/**
	 * Registers integrations.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'rank_math/researches/toc_plugins', array( $this, 'register_with_rank_math' ) );
	}

	/**
	 * Lets Rank Math recognize BareTOC during content analysis.
	 *
	 * @param array<string,string> $plugins Known table-of-contents plugins.
	 * @return array<string,string>
	 */
	public function register_with_rank_math( $plugins ) {
		$plugins[ plugin_basename( BARETOC_FILE ) ] = 'BareTOC';

		return $plugins;
	}
}
