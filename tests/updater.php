<?php
/**
 * Isolated update-client integration test.
 *
 * Run with: composer test
 *
 * @package BareTOC
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'BARETOC_FILE', dirname( __DIR__ ) . '/baretoc/baretoc.php' );
define( 'BARETOC_UPDATE_ACCESS_KEY', 'test-access-key' );

/** Minimal WordPress error object used by the updater. */
class WP_Error {

	/** Error code. */
	public $code;

	/** Error message. */
	public $message;

	/**
	 * Creates an error.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 */
	public function __construct( $code, $message ) {
		$this->code    = $code;
		$this->message = $message;
	}

	/** Returns the error message. */
	public function get_error_message() {
		return $this->message;
	}
}

/** Records a WordPress filter registration. */
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['baretoc_updater_hooks']['filters'][ $hook ] = array( $callback, $priority, $accepted_args );
}

/** Records a WordPress action registration. */
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['baretoc_updater_hooks']['actions'][ $hook ] = array( $callback, $priority, $accepted_args );
}

/** Returns the unmodified filter value for this isolated test. */
function apply_filters( $hook, $value ) {
	unset( $hook );

	return $value;
}

/** Returns a translated string unchanged. */
function __( $text ) {
	return $text;
}

/** Escapes text for the isolated admin-notice test surface. */
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

/** Reports whether a value is a WordPress error. */
function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

/** Reads the updater's network-wide transient cache. */
function get_site_transient( $key ) {
	return $GLOBALS['baretoc_updater_transients'][ $key ] ?? false;
}

/** Writes the updater's network-wide transient cache. */
function set_site_transient( $key, $value, $expiration ) {
	$GLOBALS['baretoc_updater_transients'][ $key ] = $value;
	$GLOBALS['baretoc_updater_expirations'][ $key ] = $expiration;

	return true;
}

/** Deletes one updater transient. */
function delete_site_transient( $key ) {
	unset( $GLOBALS['baretoc_updater_transients'][ $key ], $GLOBALS['baretoc_updater_expirations'][ $key ] );

	return true;
}

/** Adds encoded query arguments to a URL. */
function add_query_arg( $arguments, $url ) {
	$separator = false === strpos( $url, '?' ) ? '?' : '&';

	return $url . $separator . http_build_query( $arguments, '', '&', PHP_QUERY_RFC3986 );
}

/** Returns the test WordPress version. */
function get_bloginfo( $field ) {
	return 'version' === $field ? '6.8' : '';
}

/** Returns the prepared mock HTTP response. */
function wp_safe_remote_get( $url, $arguments ) {
	$GLOBALS['baretoc_updater_requests'][] = array( 'url' => $url, 'arguments' => $arguments );

	return $GLOBALS['baretoc_updater_response'];
}

/** Retrieves a mock HTTP status code. */
function wp_remote_retrieve_response_code( $response ) {
	return (int) ( $response['response']['code'] ?? 0 );
}

/** Retrieves a mock HTTP body. */
function wp_remote_retrieve_body( $response ) {
	return (string) ( $response['body'] ?? '' );
}

/** WordPress-compatible URL parser double. */
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

/** Accepts valid public HTTPS URLs. */
function wp_http_validate_url( $url ) {
	return false !== filter_var( $url, FILTER_VALIDATE_URL ) && 'https' === strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );
}

/** WordPress query parser double. */
function wp_parse_str( $input, &$result ) {
	parse_str( $input, $result );
}

/** Sanitizes an HTTPS URL for the updater test. */
function esc_url_raw( $url, $protocols = null ) {
	unset( $protocols );

	return wp_http_validate_url( $url ) ? $url : '';
}

/** Adds one trailing slash. */
function trailingslashit( $path ) {
	return rtrim( $path, '/\\' ) . '/';
}

/** Removes trailing slashes. */
function untrailingslashit( $path ) {
	return rtrim( $path, '/\\' );
}

/** Denies capabilities because no notice should render during this test. */
function current_user_can( $capability ) {
	unset( $capability );

	return false;
}

/** Minimal filesystem adapter for package-layout checks. */
class BareTOC_Test_Filesystem {

	/** Known file paths. */
	private $files;

	/** Known directory paths. */
	private $directories;

	/**
	 * Creates the fake filesystem.
	 *
	 * @param string[] $files       Existing files.
	 * @param string[] $directories Existing directories.
	 */
	public function __construct( $files, $directories ) {
		$this->files       = array_map( array( $this, 'normalize' ), $files );
		$this->directories = array_map( array( $this, 'normalize' ), $directories );
	}

	/** Checks whether a path exists. */
	public function exists( $path ) {
		return in_array( $this->normalize( $path ), $this->files, true ) || $this->is_dir( $path );
	}

	/** Checks whether a path is a directory. */
	public function is_dir( $path ) {
		return in_array( $this->normalize( $path ), $this->directories, true );
	}

	/** Normalizes a portable test path. */
	public function normalize( $path ) {
		return strtolower( rtrim( str_replace( '\\', '/', (string) $path ), '/' ) );
	}
}

/** Stops the updater test with a useful message. */
function baretoc_updater_test_fail( $message ) {
	fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
	exit( 1 );
}

/** Builds a signed-download-shaped API response. */
function baretoc_updater_response( $version = '1.4.0', $host = 'api.sitefueler.com' ) {
	$download = 'https://' . $host . '/updater/index.php?' . http_build_query(
		array(
			'action'    => 'download',
			'slug'      => 'baretoc',
			'version'   => $version,
			'expires'   => time() + 7200,
			'checksum'  => str_repeat( 'a', 64 ),
			'signature' => str_repeat( 'b', 64 ),
		),
		'',
		'&',
		PHP_QUERY_RFC3986
	);

	return array(
		'response' => array( 'code' => 200 ),
		'body'     => json_encode(
			array(
				'success' => true,
				'data'    => array(
					'slug'          => 'baretoc',
					'name'          => 'BareTOC – Lightweight Table of Contents',
					'version'       => $version,
					'author'        => 'SiteFueler',
					'homepage'      => 'https://sitefueler.com/baretoc/',
					'requires'      => '6.2',
					'tested'        => '6.8',
					'requires_php'  => '7.4',
					'last_updated'  => '2026-09-01 00:00:00',
					'download_url'  => $download,
					'sections'      => array( 'description' => '<p>Test release.</p>' ),
					'icons'         => array( '1x' => 'https://sitefueler.com/icon.png' ),
					'banners'       => array(),
				),
			),
			JSON_UNESCAPED_SLASHES
		),
	);
}

require dirname( __DIR__ ) . '/includes/class-baretoc-updater.php';

$GLOBALS['baretoc_updater_hooks']       = array( 'filters' => array(), 'actions' => array() );
$GLOBALS['baretoc_updater_transients']  = array();
$GLOBALS['baretoc_updater_expirations'] = array();
$GLOBALS['baretoc_updater_requests']    = array();
$GLOBALS['baretoc_updater_response']    = baretoc_updater_response();

$updater = new BareTOC_Updater( 'baretoc', 'baretoc/baretoc.php', '1.3.0' );
$updater->register();

foreach ( array( 'update_plugins_api.sitefueler.com', 'plugins_api', 'upgrader_source_selection' ) as $hook ) {
	if ( ! isset( $GLOBALS['baretoc_updater_hooks']['filters'][ $hook ] ) ) {
		baretoc_updater_test_fail( 'Missing updater filter ' . $hook );
	}
}

foreach ( array( 'upgrader_process_complete', 'admin_notices' ) as $hook ) {
	if ( ! isset( $GLOBALS['baretoc_updater_hooks']['actions'][ $hook ] ) ) {
		baretoc_updater_test_fail( 'Missing updater action ' . $hook );
	}
}

$offer = $updater->get_update( array( 'PluginURI' => 'https://sitefueler.com/baretoc/' ), true );
if ( ! is_array( $offer ) || '1.4.0' !== $offer['new_version'] || 'baretoc/baretoc.php' !== $offer['plugin'] ) {
	baretoc_updater_test_fail( 'A valid newer release did not create a WordPress update offer.' );
}

$request = $GLOBALS['baretoc_updater_requests'][0];
if ( false === strpos( $request['url'], 'action=check' ) || false === strpos( $request['url'], 'slug=baretoc' ) || true !== $request['arguments']['sslverify'] ) {
	baretoc_updater_test_fail( 'The metadata request was not constructed securely.' );
}

if ( 'Bearer test-access-key' !== ( $request['arguments']['headers']['Authorization'] ?? '' ) ) {
	baretoc_updater_test_fail( 'The optional update access key was not sent as a Bearer token.' );
}

if ( false !== strpos( $request['arguments']['headers']['User-Agent'], 'example.com' ) || 262144 !== $request['arguments']['limit_response_size'] ) {
	baretoc_updater_test_fail( 'The metadata request leaked a site identity or omitted its response limit.' );
}

$cached_offer = $updater->get_update();
if ( ! is_array( $cached_offer ) || 1 !== count( $GLOBALS['baretoc_updater_requests'] ) ) {
	baretoc_updater_test_fail( 'Valid release metadata was not reused from the cache.' );
}

$filtered_offer = $updater->filter_update( false, array(), 'baretoc/baretoc.php', array( 'en_US' ) );
if ( ! is_array( $filtered_offer ) || 'baretoc' !== $filtered_offer['slug'] ) {
	baretoc_updater_test_fail( 'The Update URI filter did not return BareTOC metadata.' );
}

if ( 'untouched' !== $updater->filter_update( 'untouched', array(), 'other/plugin.php', array() ) ) {
	baretoc_updater_test_fail( 'The updater changed another plugin response.' );
}

$information = $updater->get_plugin_information( true );
if ( ! is_object( $information ) || '1.4.0' !== $information->version || empty( $information->sections['description'] ) ) {
	baretoc_updater_test_fail( 'The plugin-information response is incomplete.' );
}

$filtered_information = $updater->filter_plugin_information( false, 'plugin_information', (object) array( 'slug' => 'baretoc' ) );
if ( ! is_object( $filtered_information ) || 'baretoc' !== $filtered_information->slug ) {
	baretoc_updater_test_fail( 'The plugin-information filter did not return BareTOC details.' );
}

$GLOBALS['baretoc_updater_response'] = baretoc_updater_response( '1.4.0', 'downloads.example.com' );
$untrusted = $updater->get_plugin_information( true );
if ( ! is_wp_error( $untrusted ) || 'baretoc_update_package' !== $untrusted->code ) {
	baretoc_updater_test_fail( 'An update package from another host was trusted.' );
}

$GLOBALS['baretoc_updater_response'] = baretoc_updater_response( '1.3.0' );
if ( false !== $updater->get_update( array(), true ) ) {
	baretoc_updater_test_fail( 'The installed version was incorrectly offered as an update.' );
}

$GLOBALS['wp_filesystem'] = new BareTOC_Test_Filesystem(
	array(
		'C:/upgrade/baretoc/baretoc.php',
		'C:/upgrade/archive/baretoc/baretoc.php',
	),
	array(
		'C:/upgrade/baretoc',
		'C:/upgrade/archive',
		'C:/upgrade/archive/baretoc',
	)
);
$upgrade_context = array( 'type' => 'plugin', 'plugin' => 'baretoc/baretoc.php' );

$canonical = $updater->validate_update_source( 'C:/upgrade/baretoc/', '', null, $upgrade_context );
if ( 'C:/upgrade/baretoc/' !== $canonical ) {
	baretoc_updater_test_fail( 'The canonical BareTOC package root was rejected.' );
}

$nested = $updater->validate_update_source( 'C:/upgrade/archive/', '', null, $upgrade_context );
if ( 'C:/upgrade/archive/baretoc/' !== $nested ) {
	baretoc_updater_test_fail( 'A valid nested BareTOC package root was not selected.' );
}

$invalid = $updater->validate_update_source( 'C:/upgrade/wrong/', '', null, $upgrade_context );
if ( ! is_wp_error( $invalid ) || 'baretoc_update_package_layout' !== $invalid->code ) {
	baretoc_updater_test_fail( 'An invalid BareTOC ZIP layout was accepted.' );
}

$unrelated = $updater->validate_update_source( 'C:/upgrade/other/', '', null, array( 'type' => 'plugin', 'plugin' => 'other/plugin.php' ) );
if ( 'C:/upgrade/other/' !== $unrelated ) {
	baretoc_updater_test_fail( 'Package validation changed an unrelated plugin source.' );
}

$GLOBALS['baretoc_updater_response'] = baretoc_updater_response();
$updater->get_plugin_information( true );
$updater->get_update( array(), true );
$updater->clear_cache_after_upgrade( null, $upgrade_context );
if ( array() !== $GLOBALS['baretoc_updater_transients'] ) {
	baretoc_updater_test_fail( 'Updater metadata remained cached after a BareTOC upgrade.' );
}

fwrite( STDOUT, 'BareTOC updater tests passed.' . PHP_EOL );
