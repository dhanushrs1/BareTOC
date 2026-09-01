<?php
/**
 * Native WordPress update integration.
 *
 * @package BareTOC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Connects BareTOC to the SiteFueler update service.
 */
final class BareTOC_Updater {

	/**
	 * Default metadata endpoint.
	 *
	 * @var string
	 */
	const DEFAULT_API_URL = 'https://api.sitefueler.com/updater/index.php';

	/**
	 * Valid update channels.
	 *
	 * @var string[]
	 */
	const CHANNELS = array( 'stable', 'beta' );

	/**
	 * Metadata cache lifetime in seconds.
	 *
	 * @var int
	 */
	const CACHE_TTL = 21600;

	/**
	 * Maximum accepted metadata response size in bytes.
	 *
	 * @var int
	 */
	const MAX_RESPONSE_SIZE = 262144;

	/**
	 * Canonical plugin slug.
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Plugin basename used by WordPress.
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Installed plugin version.
	 *
	 * @var string
	 */
	private $installed_version;

	/**
	 * Creates the updater.
	 *
	 * @param string $slug              Canonical plugin slug.
	 * @param string $plugin_file       Plugin basename.
	 * @param string $installed_version Installed version.
	 */
	public function __construct( $slug, $plugin_file, $installed_version ) {
		$this->slug              = (string) $slug;
		$this->plugin_file       = (string) $plugin_file;
		$this->installed_version = (string) $installed_version;
	}

	/**
	 * Registers native update and package-validation hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'update_plugins_api.sitefueler.com', array( $this, 'filter_update' ), 10, 4 );
		add_filter( 'plugins_api', array( $this, 'filter_plugin_information' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'validate_update_source' ), 9, 4 );
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache_after_upgrade' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'render_installation_notice' ) );
	}

	/**
	 * Supplies update data to WordPress for BareTOC's Update URI host.
	 *
	 * @param array|false $update      Existing update value.
	 * @param array       $plugin_data Installed plugin headers.
	 * @param string      $plugin_file Installed plugin basename.
	 * @param string[]    $locales     Installed locales.
	 * @return array|false
	 */
	public function filter_update( $update, $plugin_data, $plugin_file, $locales ) {
		unset( $locales );

		if ( $this->plugin_file !== $plugin_file ) {
			return $update;
		}

		$offer = $this->get_update( is_array( $plugin_data ) ? $plugin_data : array() );

		return false !== $offer ? $offer : $update;
	}

	/**
	 * Returns a native WordPress update offer when a newer release exists.
	 *
	 * @param array $plugin_data Installed plugin headers.
	 * @param bool  $force       Whether to bypass cached metadata.
	 * @return array|false
	 */
	public function get_update( $plugin_data = array(), $force = false ) {
		if ( ! $this->is_canonical_install() ) {
			return false;
		}

		$release = $this->request( 'check', $force );
		if ( is_wp_error( $release ) || ! version_compare( (string) $release['version'], $this->installed_version, '>' ) ) {
			return false;
		}

		return $this->to_update_offer( $release, $plugin_data );
	}

	/**
	 * Supplies the plugin-information modal shown from the update screen.
	 *
	 * @param mixed  $result Existing API result.
	 * @param string $action Plugin API action.
	 * @param object $args   Plugin API arguments.
	 * @return mixed
	 */
	public function filter_plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! is_object( $args ) || (string) ( $args->slug ?? '' ) !== $this->slug ) {
			return $result;
		}

		return $this->get_plugin_information();
	}

	/**
	 * Fetches full metadata for WordPress's plugin-information modal.
	 *
	 * @param bool $force Whether to bypass cached metadata.
	 * @return object|WP_Error
	 */
	public function get_plugin_information( $force = false ) {
		$release = $this->request( 'info', $force );
		if ( is_wp_error( $release ) ) {
			return $release;
		}

		return (object) array(
			'name'          => (string) ( $release['name'] ?? 'BareTOC' ),
			'slug'          => $this->slug,
			'version'       => (string) $release['version'],
			'author'        => (string) ( $release['author'] ?? 'SiteFueler' ),
			'homepage'      => (string) ( $release['homepage'] ?? 'https://sitefueler.com/baretoc/' ),
			'requires'      => (string) ( $release['requires'] ?? '' ),
			'tested'        => (string) ( $release['tested'] ?? '' ),
			'requires_php'  => (string) ( $release['requires_php'] ?? '' ),
			'last_updated'  => (string) ( $release['last_updated'] ?? '' ),
			'download_link' => (string) $release['download_url'],
			'sections'      => is_array( $release['sections'] ?? null ) ? $release['sections'] : array(),
			'banners'       => $this->remote_assets( $release['banners'] ?? array() ),
			'icons'         => $this->remote_assets( $release['icons'] ?? array() ),
			'external'      => true,
		);
	}

	/**
	 * Stops an update ZIP with an unexpected root directory or main file.
	 *
	 * @param mixed  $source        Extracted source directory or error.
	 * @param string $remote_source Temporary extraction directory.
	 * @param mixed  $upgrader      WordPress upgrader instance.
	 * @param array  $hook_extra    Upgrade context.
	 * @return mixed
	 */
	public function validate_update_source( $source, $remote_source, $upgrader, $hook_extra ) {
		unset( $remote_source, $upgrader );

		if ( ! $this->is_plugin_upgrade( $hook_extra ) || is_wp_error( $source ) || ! is_string( $source ) ) {
			return $source;
		}

		global $wp_filesystem;

		if ( ! is_object( $wp_filesystem ) ) {
			return new WP_Error(
				'baretoc_update_filesystem',
				__( 'WordPress filesystem access is unavailable for the BareTOC update.', 'baretoc' )
			);
		}

		$source    = trailingslashit( $source );
		$main_file = basename( $this->plugin_file );

		if ( basename( untrailingslashit( $source ) ) === $this->slug && $wp_filesystem->exists( $source . $main_file ) ) {
			return $source;
		}

		$canonical_source = $source . $this->slug . '/';
		if ( $wp_filesystem->is_dir( $canonical_source ) && $wp_filesystem->exists( $canonical_source . $main_file ) ) {
			return $canonical_source;
		}

		return new WP_Error(
			'baretoc_update_package_layout',
			__( 'The BareTOC update was stopped because its ZIP root is not baretoc/baretoc.php.', 'baretoc' )
		);
	}

	/**
	 * Clears cached metadata after BareTOC is upgraded.
	 *
	 * @param mixed $upgrader   WordPress upgrader instance.
	 * @param array $hook_extra Upgrade context.
	 * @return void
	 */
	public function clear_cache_after_upgrade( $upgrader, $hook_extra ) {
		unset( $upgrader );

		if ( $this->is_plugin_upgrade( $hook_extra ) ) {
			$this->clear_cache();
		}
	}

	/**
	 * Clears all cached release metadata.
	 *
	 * @return void
	 */
	public function clear_cache() {
		delete_site_transient( $this->cache_key( 'check' ) );
		delete_site_transient( $this->cache_key( 'info' ) );
	}

	/**
	 * Warns administrators when the plugin directory cannot be updated safely.
	 *
	 * @return void
	 */
	public function render_installation_notice() {
		if ( $this->is_canonical_install() || ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		$directory = basename( rtrim( dirname( BARETOC_FILE ), '/\\' ) );
		$message   = sprintf(
			/* translators: 1: Current plugin directory. 2: Required plugin directory. */
			__( 'BareTOC is installed in the “%1$s” directory. Reinstall the official ZIP so its directory is “%2$s” before using dashboard updates.', 'baretoc' ),
			$directory,
			$this->slug
		);

		printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html( $message ) );
	}

	/**
	 * Requests and validates release metadata.
	 *
	 * @param string $action Metadata action.
	 * @param bool   $force  Whether to bypass the cache.
	 * @return array|WP_Error
	 */
	private function request( $action, $force ) {
		$cache_key = $this->cache_key( $action );
		if ( ! $force ) {
			$cached = get_site_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$endpoint = $this->endpoint();
		if ( '' === $endpoint ) {
			return new WP_Error(
				'baretoc_update_endpoint',
				__( 'The BareTOC update endpoint is not configured correctly.', 'baretoc' )
			);
		}

		$url = add_query_arg(
			array(
				'action'  => $action,
				'slug'    => $this->slug,
				'version' => $this->installed_version,
				'channel' => $this->channel(),
				'wp'      => get_bloginfo( 'version' ),
				'php'     => PHP_VERSION,
			),
			$endpoint
		);

		$headers    = array(
			'Accept'     => 'application/json',
			'User-Agent' => 'BareTOC/' . $this->installed_version . '; WordPress/' . get_bloginfo( 'version' ) . '; PHP/' . PHP_VERSION,
		);
		$access_key = $this->access_key();
		if ( '' !== $access_key ) {
			$headers['Authorization'] = 'Bearer ' . $access_key;
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'headers'             => $headers,
				'timeout'             => 12,
				'redirection'         => 2,
				'reject_unsafe_urls'  => true,
				'sslverify'           => true,
				'limit_response_size' => self::MAX_RESPONSE_SIZE,
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'baretoc_update_request', $response->get_error_message() );
		}

		$status  = wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true, 32 );
		if ( 200 !== $status || JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) || true !== ( $decoded['success'] ?? false ) || ! is_array( $decoded['data'] ?? null ) ) {
			$message = is_array( $decoded ) ? (string) ( $decoded['message'] ?? '' ) : '';

			return new WP_Error(
				'baretoc_update_response',
				'' !== $message ? $message : __( 'The BareTOC update server returned an invalid response.', 'baretoc' )
			);
		}

		$release    = $decoded['data'];
		$validation = $this->validate_release( $release );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		set_site_transient( $cache_key, $release, self::CACHE_TTL );

		return $release;
	}

	/**
	 * Validates a release identity and its signed download URL.
	 *
	 * @param array $release Release metadata.
	 * @return true|WP_Error
	 */
	private function validate_release( $release ) {
		$version = (string) ( $release['version'] ?? '' );
		if ( ( $release['slug'] ?? '' ) !== $this->slug || 1 !== preg_match( '/^[0-9A-Za-z][0-9A-Za-z.+_-]{0,31}$/', $version ) ) {
			return new WP_Error(
				'baretoc_update_manifest',
				__( 'The BareTOC update manifest identity is invalid.', 'baretoc' )
			);
		}

		$endpoint        = $this->endpoint();
		$download_url    = (string) ( $release['download_url'] ?? '' );
		$api_scheme      = strtolower( (string) wp_parse_url( $endpoint, PHP_URL_SCHEME ) );
		$api_host        = strtolower( (string) wp_parse_url( $endpoint, PHP_URL_HOST ) );
		$api_port        = (int) wp_parse_url( $endpoint, PHP_URL_PORT );
		$download_host   = strtolower( (string) wp_parse_url( $download_url, PHP_URL_HOST ) );
		$download_port   = (int) wp_parse_url( $download_url, PHP_URL_PORT );
		$download_scheme = strtolower( (string) wp_parse_url( $download_url, PHP_URL_SCHEME ) );

		if ( '' === $download_url || ! wp_http_validate_url( $download_url ) || 'https' !== $api_scheme || 'https' !== $download_scheme || '' === $api_host || $download_host !== $api_host || $download_port !== $api_port ) {
			return new WP_Error(
				'baretoc_update_package',
				__( 'The BareTOC update package URL is not trusted.', 'baretoc' )
			);
		}

		$query = array();
		wp_parse_str( (string) wp_parse_url( $download_url, PHP_URL_QUERY ), $query );
		if (
			'download' !== ( $query['action'] ?? '' )
			|| ( $query['slug'] ?? '' ) !== $this->slug
			|| ( $query['version'] ?? '' ) !== $version
			|| 1 !== preg_match( '/^[0-9]+$/', (string) ( $query['expires'] ?? '' ) )
			|| (int) $query['expires'] <= time()
			|| 1 !== preg_match( '/^[a-f0-9]{64}$/', (string) ( $query['checksum'] ?? '' ) )
			|| 1 !== preg_match( '/^[a-f0-9]{64}$/', (string) ( $query['signature'] ?? '' ) )
		) {
			return new WP_Error(
				'baretoc_update_signature',
				__( 'The BareTOC update package signature is invalid or expired.', 'baretoc' )
			);
		}

		return true;
	}

	/**
	 * Converts validated metadata to WordPress update data.
	 *
	 * @param array $release     Release metadata.
	 * @param array $plugin_data Installed plugin headers.
	 * @return array
	 */
	private function to_update_offer( $release, $plugin_data ) {
		return array(
			'id'           => $this->update_uri(),
			'slug'         => $this->slug,
			'plugin'       => $this->plugin_file,
			'version'      => (string) $release['version'],
			'new_version'  => (string) $release['version'],
			'url'          => (string) ( $release['homepage'] ?? ( $plugin_data['PluginURI'] ?? 'https://sitefueler.com/baretoc/' ) ),
			'package'      => (string) $release['download_url'],
			'icons'        => $this->remote_assets( $release['icons'] ?? array() ),
			'banners'      => $this->remote_assets( $release['banners'] ?? array() ),
			'tested'       => (string) ( $release['tested'] ?? '' ),
			'requires_php' => (string) ( $release['requires_php'] ?? '' ),
			'requires'     => (string) ( $release['requires'] ?? '' ),
		);
	}

	/**
	 * Keeps only valid HTTPS icon and banner URLs.
	 *
	 * @param mixed $assets Remote asset map.
	 * @return array
	 */
	private function remote_assets( $assets ) {
		$valid = array();
		if ( ! is_array( $assets ) ) {
			return $valid;
		}

		foreach ( $assets as $size => $url ) {
			$url = is_string( $url ) ? trim( $url ) : '';
			if ( is_string( $size ) && '' !== $url && 'https' === strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) && wp_http_validate_url( $url ) ) {
				$valid[ $size ] = $url;
			}
		}

		return $valid;
	}

	/**
	 * Returns the configured HTTPS metadata endpoint.
	 *
	 * @return string
	 */
	private function endpoint() {
		$configured = defined( 'BARETOC_UPDATE_API_URL' ) ? (string) BARETOC_UPDATE_API_URL : self::DEFAULT_API_URL;
		$endpoint   = trim( (string) apply_filters( 'baretoc_update_api_url', $configured, $this->slug ) );

		if ( 'https' !== strtolower( (string) wp_parse_url( $endpoint, PHP_URL_SCHEME ) ) || ! wp_http_validate_url( $endpoint ) ) {
			return '';
		}

		return esc_url_raw( $endpoint, array( 'https' ) );
	}

	/**
	 * Returns an optional server access key.
	 *
	 * @return string
	 */
	private function access_key() {
		$key = defined( 'BARETOC_UPDATE_ACCESS_KEY' ) ? trim( (string) BARETOC_UPDATE_ACCESS_KEY ) : '';

		return trim( (string) apply_filters( 'baretoc_update_access_key', $key, $this->slug ) );
	}

	/**
	 * Returns the selected release channel.
	 *
	 * @return string
	 */
	private function channel() {
		$channel = strtolower( trim( (string) apply_filters( 'baretoc_update_channel', 'stable', $this->slug ) ) );

		return in_array( $channel, self::CHANNELS, true ) ? $channel : 'stable';
	}

	/**
	 * Builds the network-wide metadata cache key.
	 *
	 * @param string $action Metadata action.
	 * @return string
	 */
	private function cache_key( $action ) {
		$identity = implode( '|', array( $this->slug, $action, $this->channel(), $this->endpoint(), $this->access_key() ) );

		return 'baretoc_update_' . hash( 'sha256', $identity );
	}

	/**
	 * Returns BareTOC's declared Update URI.
	 *
	 * @return string
	 */
	private function update_uri() {
		return 'https://api.sitefueler.com/updater/' . rawurlencode( $this->slug ) . '/';
	}

	/**
	 * Checks whether the current install uses the canonical directory.
	 *
	 * @return bool
	 */
	private function is_canonical_install() {
		return basename( rtrim( dirname( BARETOC_FILE ), '/\\' ) ) === $this->slug;
	}

	/**
	 * Checks whether upgrader context targets BareTOC.
	 *
	 * @param mixed $hook_extra Upgrade context.
	 * @return bool
	 */
	private function is_plugin_upgrade( $hook_extra ) {
		if ( ! is_array( $hook_extra ) || 'plugin' !== ( $hook_extra['type'] ?? '' ) ) {
			return false;
		}

		if ( ( $hook_extra['plugin'] ?? '' ) === $this->plugin_file ) {
			return true;
		}

		return is_array( $hook_extra['plugins'] ?? null ) && in_array( $this->plugin_file, $hook_extra['plugins'], true );
	}
}
