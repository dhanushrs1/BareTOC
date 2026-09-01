<?php
/**
 * BareTOC uninstall cleanup.
 *
 * @package BareTOC
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'baretoc_settings' );
delete_post_meta_by_key( '_baretoc_disabled' );
delete_post_meta_by_key( '_baretoc_heading_levels' );
