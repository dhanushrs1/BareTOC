<?php
/**
 * Small integration smoke test for local development.
 *
 * Run with: composer test
 *
 * @package BareTOC
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'BARETOC_FILE', dirname( __DIR__ ) . '/baretoc.php' );
define( 'BARETOC_URL', 'https://example.com/wp-content/plugins/baretoc/' );
define( 'BARETOC_VERSION', '1.3.4' );

/** Minimal WordPress function doubles needed by the isolated smoke test. */
function __( $text ) {
	return $text;
}

function esc_attr__( $text ) {
	return $text;
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function sanitize_html_class( $class ) {
	return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );
}

function sanitize_text_field( $text ) {
	return trim( strip_tags( (string) $text ) );
}

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $key ) );
}

function sanitize_title( $title ) {
	$title = strtolower( html_entity_decode( strip_tags( (string) $title ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	$title = preg_replace( '/[^a-z0-9]+/', '-', $title );

	return trim( (string) $title, '-' );
}

function wp_strip_all_tags( $text ) {
	return strip_tags( (string) $text );
}

function apply_filters( $hook_name, $value ) {
	return $value;
}

function absint( $value ) {
	return abs( (int) $value );
}

function get_option( $name, $default = false ) {
	return isset( $GLOBALS['baretoc_test_option'] ) ? $GLOBALS['baretoc_test_option'] : $default;
}

function get_permalink() {
	return 'https://example.com/article/';
}

function metadata_exists() {
	return false;
}

function get_post_meta() {
	return '';
}

function get_the_ID() {
	return 0;
}

function get_queried_object_id() {
	return 0;
}

function doing_filter( $hook_name ) {
	return 'the_content' === $hook_name && ( ! isset( $GLOBALS['baretoc_test_doing_content_filter'] ) || $GLOBALS['baretoc_test_doing_content_filter'] );
}

function wp_json_encode( $value ) {
	return json_encode( $value );
}

function is_admin() {
	return false;
}

function is_feed() {
	return false;
}

function is_singular() {
	return true;
}

function in_the_loop() {
	return true;
}

function is_main_query() {
	return true;
}

function shortcode_atts( $pairs, $attributes ) {
	$output = array();

	foreach ( $pairs as $name => $default ) {
		$output[ $name ] = array_key_exists( $name, $attributes ) ? $attributes[ $name ] : $default;
	}

	return $output;
}

function wp_unique_id( $prefix = '' ) {
	static $id = 0;
	++$id;

	return $prefix . $id;
}

function plugin_basename( $file ) {
	return basename( dirname( $file ) ) . '/' . basename( $file );
}

function wp_enqueue_style( $handle ) {
	$GLOBALS['baretoc_test_styles'][] = $handle;
}

function wp_enqueue_script( $handle ) {
	$GLOBALS['baretoc_test_scripts'][] = $handle;
}

require dirname( __DIR__ ) . '/includes/class-baretoc-parser.php';
require dirname( __DIR__ ) . '/includes/class-baretoc-renderer.php';
require dirname( __DIR__ ) . '/includes/class-baretoc-settings.php';
require dirname( __DIR__ ) . '/includes/class-baretoc-shortcode.php';
require dirname( __DIR__ ) . '/includes/class-baretoc-integrations.php';

/**
 * Stops the smoke test with a useful message.
 *
 * @param string $message Failure message.
 * @return void
 */
function baretoc_test_fail( $message ) {
	fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
	exit( 1 );
}

$parser = new BareTOC_Parser();
$html   = '<h2>Intro</h2><h3 class="topic">Child <em>topic</em></h3><h3 id="kept" class="special">Kept</h3><h2>Intro</h2><h2 class="no-toc">Skip</h2><div id="intro"></div>';
$parsed = $parser->parse( $html, true, array( 2, 3 ) );

if ( 4 !== count( $parsed['headings'] ) ) {
	baretoc_test_fail( 'Expected four usable headings.' );
}

foreach ( array( 'id="intro-2"', 'id="child-topic"', 'id="intro-3"' ) as $needle ) {
	if ( false === strpos( $parsed['content'], $needle ) ) {
		baretoc_test_fail( 'Missing generated anchor ' . $needle );
	}
}

if ( false === strpos( $parsed['content'], '<h3 id="kept" class="special">' ) ) {
	baretoc_test_fail( 'Existing heading attributes changed.' );
}

if ( false !== strpos( $parsed['content'], 'class="no-toc" id=' ) ) {
	baretoc_test_fail( 'Ignored heading received an ID.' );
}

$renderer                    = new BareTOC_Renderer();
$render_args                 = BareTOC_Settings::defaults();
$render_args['minimum_headings'] = 1;
$output                      = $renderer->render( $parsed['headings'], $render_args );

foreach ( array( '<nav class="baretoc"', 'aria-label="Table of contents"', 'baretoc-sublist', 'href="#kept"', 'type="application/ld+json"', '"@type":"ItemList"', '"numberOfItems":4', 'https:\/\/example.com\/article\/#intro-2' ) as $needle ) {
	if ( false === strpos( $output, $needle ) ) {
		baretoc_test_fail( 'Missing renderer output ' . $needle );
	}
}

if ( false === strpos( $output, '<div class="baretoc-header"><div class="baretoc-title">Table of Contents</div></div><ol' ) ) {
	baretoc_test_fail( 'Default output did not keep the title inside the header container.' );
}

if ( false !== strpos( $output, 'baretoc-toggle' ) ) {
	baretoc_test_fail( 'Default output unexpectedly contains the shortcode-only toggle.' );
}

$toggle_args                     = $render_args;
$toggle_args['collapsible']      = true;
$toggle_args['initially_open']   = false;
$toggle_args['smooth_toggle']    = true;
$toggle_output                   = $renderer->render( $parsed['headings'], $toggle_args );

foreach ( array( 'baretoc--collapsible', 'baretoc--smooth-toggle', 'class="baretoc-header baretoc-toggle"', 'role="button"', 'tabindex="0"', 'data-baretoc-initial="closed"', 'data-baretoc-smooth="yes"', 'class="baretoc-toggle-icon"', 'baretoc-toggle-icon__vertical' ) as $needle ) {
	if ( false === strpos( $toggle_output, $needle ) ) {
		baretoc_test_fail( 'Collapsible renderer output missing ' . $needle );
	}
}

if ( false === strpos( $toggle_output, '<div class="baretoc-header baretoc-toggle" role="button"' ) ) {
	baretoc_test_fail( 'The collapsible header was not rendered as the single disclosure trigger.' );
}

if ( false !== strpos( $toggle_output, '<button' ) || false !== strpos( $toggle_output, '<span class="baretoc-toggle-icon' ) ) {
	baretoc_test_fail( 'Collapsible output unexpectedly contains a separate button or duplicate icon wrapper.' );
}

$minimum_args                     = $render_args;
$minimum_args['minimum_headings'] = 5;

if ( '' !== $renderer->render( $parsed['headings'], $minimum_args ) ) {
	baretoc_test_fail( 'Minimum heading count was not enforced.' );
}

$numbered_html   = '<h2>1. Scope</h2><h2>2 - Delivery</h2><h3>2.1 Refunds</h3><h2>IV. Terms</h2><h2>A) Appendix</h2><h2>2026 Trends</h2>';
$numbered_parsed = $parser->parse( $numbered_html, true, array( 2, 3 ) );
$clean_args      = BareTOC_Settings::defaults();
$clean_args['minimum_headings'] = 1;
$clean_output                  = $renderer->render( $numbered_parsed['headings'], $clean_args );

foreach ( array( '>Scope<', '>Delivery<', '>Refunds<', '>Terms<', '>Appendix<', '>2026 Trends<' ) as $needle ) {
	if ( false === strpos( $clean_output, $needle ) ) {
		baretoc_test_fail( 'Smart numbering cleanup missed ' . $needle );
	}
}

if ( false === strpos( $numbered_parsed['content'], '>1. Scope</h2>' ) ) {
	baretoc_test_fail( 'Smart numbering cleanup changed the original heading.' );
}

$clean_args['clean_numbering'] = false;
$unclean_output                = $renderer->render( $numbered_parsed['headings'], $clean_args );

if ( false === strpos( $unclean_output, '>1. Scope</a>' ) ) {
	baretoc_test_fail( 'Disabling smart numbering cleanup did not preserve the TOC label.' );
}

$settings  = new BareTOC_Settings();
$sanitized = $settings->sanitize(
	array(
		'title'            => 'Contents',
		'headings'         => array( 2, 3 ),
		'minimum_headings' => 1,
		'list_style'       => 'numbered',
		'placement'        => 'shortcode',
		'css'              => 'none',
	)
);

if ( true === $sanitized['generate_ids'] ) {
	baretoc_test_fail( 'Unchecked generate IDs setting was not saved as false.' );
}

if ( true === $sanitized['smooth_scroll'] ) {
	baretoc_test_fail( 'Smooth scrolling should remain disabled when unchecked.' );
}

if ( true === $sanitized['smooth_toggle'] ) {
	baretoc_test_fail( 'Smooth open/close should remain disabled when unchecked.' );
}

if ( true === $sanitized['clean_numbering'] ) {
	baretoc_test_fail( 'Smart numbering cleanup should be disabled when unchecked.' );
}

$GLOBALS['baretoc_test_option'] = array_merge(
	BareTOC_Settings::defaults(),
	array(
		'minimum_headings' => 1,
		'smooth_scroll'    => true,
	)
);
$GLOBALS['baretoc_test_scripts'] = array();
$GLOBALS['baretoc_test_styles']  = array();
$assets                          = new BareTOC_Shortcode( $parser, $renderer, new BareTOC_Settings() );
$assets->enqueue_frontend_assets();
$asset_placeholder               = $assets->shortcode( array( 'minimum' => '1' ) );
$assets->filter_content( $asset_placeholder . '<h2>Assets</h2>' );

if ( ! in_array( 'baretoc-structure', $GLOBALS['baretoc_test_styles'], true ) ) {
	baretoc_test_fail( 'Default structural header CSS was not enqueued.' );
}

if ( in_array( 'baretoc', $GLOBALS['baretoc_test_styles'], true ) ) {
	baretoc_test_fail( 'Optional appearance CSS was unexpectedly enqueued by default.' );
}

if ( ! in_array( 'baretoc-smooth-scroll', $GLOBALS['baretoc_test_scripts'], true ) ) {
	baretoc_test_fail( 'Enabled smooth scrolling did not enqueue its script.' );
}

if ( in_array( 'baretoc-toggle', $GLOBALS['baretoc_test_scripts'], true ) ) {
	baretoc_test_fail( 'Default shortcode unexpectedly enqueued the toggle script.' );
}

unset( $GLOBALS['baretoc_test_option'], $GLOBALS['baretoc_test_scripts'], $GLOBALS['baretoc_test_styles'] );

$shortcode   = new BareTOC_Shortcode( $parser, $renderer, $settings );
$placeholder = $shortcode->shortcode(
	array(
		'headings' => 'h2,h3',
		'title'    => 'On this page',
		'list'     => 'bullets',
		'minimum'  => '1',
	)
);
$filtered = $shortcode->filter_content( $placeholder . '<h2>Alpha</h2><h3>Beta</h3>' );

foreach ( array( 'On this page', 'baretoc-list--bullets', 'id="alpha"', 'href="#beta"' ) as $needle ) {
	if ( false === strpos( $filtered, $needle ) ) {
		baretoc_test_fail( 'Shortcode flow missing ' . $needle );
	}
}

$unclean_placeholder = $shortcode->shortcode(
	array(
		'clean_numbers' => 'no',
		'minimum'       => '1',
	)
);
$unclean_shortcode = $shortcode->filter_content( $unclean_placeholder . '<h2>1. Numbered heading</h2>' );

if ( false === strpos( $unclean_shortcode, '>1. Numbered heading</a>' ) ) {
	baretoc_test_fail( 'The clean_numbers shortcode override did not disable cleanup.' );
}

$GLOBALS['baretoc_test_doing_content_filter'] = false;
$GLOBALS['baretoc_test_scripts']              = array();
$GLOBALS['baretoc_test_option']               = array_merge(
	BareTOC_Settings::defaults(),
	array(
		'smooth_toggle' => true,
	)
);
$template_shortcode                           = new BareTOC_Shortcode( $parser, $renderer, $settings );
$template_output                              = $template_shortcode->shortcode(
	array(
		'container' => '.entry-content',
		'initial'   => 'closed',
		'minimum'   => '4',
		'list'      => 'bullets',
		'toggle'    => 'yes',
	)
);

if ( false === strpos( $template_output, 'class="baretoc-runtime"' ) || false === strpos( $template_output, ' hidden ' ) ) {
	baretoc_test_fail( 'Template shortcode did not return a hidden runtime target.' );
}

if ( ! in_array( 'baretoc-template', $GLOBALS['baretoc_test_scripts'], true ) ) {
	baretoc_test_fail( 'Template shortcode did not enqueue its runtime script.' );
}

if ( ! in_array( 'baretoc-toggle', $GLOBALS['baretoc_test_scripts'], true ) ) {
	baretoc_test_fail( 'Enabled template toggle did not enqueue its script.' );
}

if ( ! preg_match( '/data-baretoc-config="([^"]+)"/', $template_output, $template_match ) ) {
	baretoc_test_fail( 'Template shortcode configuration is missing.' );
}

$template_config = json_decode( html_entity_decode( $template_match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ), true );

if ( ! is_array( $template_config ) || 4 !== $template_config['minimumHeadings'] || '.entry-content' !== $template_config['container'] || 'bullets' !== $template_config['listStyle'] || true !== $template_config['collapsible'] || false !== $template_config['initiallyOpen'] || true !== $template_config['smoothToggle'] ) {
	baretoc_test_fail( 'Template shortcode configuration is incorrect.' );
}

unset( $GLOBALS['baretoc_test_doing_content_filter'], $GLOBALS['baretoc_test_scripts'], $GLOBALS['baretoc_test_option'] );

$GLOBALS['baretoc_test_option'] = array_merge(
	BareTOC_Settings::defaults(),
	array(
		'minimum_headings' => 1,
		'placement'        => 'after_first_paragraph',
	)
);
$automatic = new BareTOC_Shortcode( $parser, $renderer, new BareTOC_Settings() );
$automatic = $automatic->filter_content( '<p>Lead.</p><h2>Automatic</h2>' );

if ( false === strpos( $automatic, '</p><nav class="baretoc"' ) || false === strpos( $automatic, '<h2 id="automatic">' ) ) {
	baretoc_test_fail( 'Automatic placement after the first paragraph failed.' );
}

unset( $GLOBALS['baretoc_test_option'] );

$integration = new BareTOC_Integrations();
$plugins     = $integration->register_with_rank_math( array() );

if ( ! isset( $plugins[ plugin_basename( BARETOC_FILE ) ] ) ) {
	baretoc_test_fail( 'Rank Math registration missing.' );
}

fwrite( STDOUT, 'BareTOC smoke tests passed.' . PHP_EOL );
