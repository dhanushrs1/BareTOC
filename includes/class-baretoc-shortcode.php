<?php
/**
 * Shortcode, content processing, and optional frontend CSS.
 *
 * @package BareTOC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates shortcode placeholders with one heading-parsing pass.
 */
final class BareTOC_Shortcode {

	/** Placeholder comment pattern. */
	const PLACEHOLDER_PATTERN = '/<!--\s*baretoc-placeholder:([a-zA-Z0-9_-]+)\s*-->/';

	/**
	 * Heading parser.
	 *
	 * @var BareTOC_Parser
	 */
	private $parser;

	/**
	 * Markup renderer.
	 *
	 * @var BareTOC_Renderer
	 */
	private $renderer;

	/**
	 * Settings provider.
	 *
	 * @var BareTOC_Settings
	 */
	private $settings;

	/**
	 * Request-local shortcode attributes, keyed by placeholder token.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private $shortcodes = array();

	/**
	 * Prevents recursive content filtering.
	 *
	 * @var bool
	 */
	private $processing = false;

	/**
	 * Stores dependencies.
	 *
	 * @param BareTOC_Parser   $parser   Heading parser.
	 * @param BareTOC_Renderer $renderer Markup renderer.
	 * @param BareTOC_Settings $settings Settings provider.
	 */
	public function __construct( $parser, $renderer, $settings ) {
		$this->parser   = $parser;
		$this->renderer = $renderer;
		$this->settings = $settings;
	}

	/**
	 * Registers frontend hooks.
	 *
	 * Priority 12 runs after WordPress expands shortcodes at priority 11, so the
	 * final rendered headings can be scanned without JavaScript or a second query.
	 *
	 * @return void
	 */
	public function register() {
		add_shortcode( 'baretoc', array( $this, 'shortcode' ) );
		add_filter( 'the_content', array( $this, 'filter_content' ), 12 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Replaces a shortcode with a request-local placeholder.
	 *
	 * The later content pass can then see headings produced by other shortcodes,
	 * add anchors, and put the TOC exactly where this placeholder appeared.
	 *
	 * @param array<string,mixed>|string $attributes Shortcode attributes.
	 * @return string
	 */
	public function shortcode( $attributes = array() ) {
		$attributes = is_array( $attributes ) ? $attributes : array();

		if ( ! $this->is_filtering_post_content() ) {
			return $this->render_template_shortcode( $attributes );
		}

		$token = wp_unique_id( 'baretoc-' );

		$this->shortcodes[ $token ] = $attributes;

		return '<!--baretoc-placeholder:' . esc_attr( $token ) . '-->';
	}

	/**
	 * Detects whether WordPress is currently expanding the main content.
	 *
	 * Shortcodes in page-builder templates commonly run outside the_content. In
	 * that context there is no later content pass in which to resolve our normal
	 * server-side placeholder, so a small rendered-page fallback is required.
	 *
	 * @return bool
	 */
	private function is_filtering_post_content() {
		return function_exists( 'doing_filter' ) && doing_filter( 'the_content' );
	}

	/**
	 * Outputs a hidden runtime target for a shortcode placed in a site template.
	 *
	 * The target remains hidden until the lightweight template script finds the
	 * configured minimum number of headings. Regular post-content shortcodes do
	 * not use or enqueue this fallback.
	 *
	 * @param array<string,mixed> $attributes Raw shortcode attributes.
	 * @return string
	 */
	private function render_template_shortcode( $attributes ) {
		$post_id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;

		if ( $post_id <= 0 ) {
			$post_id = (int) get_the_ID();
		}

		if ( $this->settings->is_disabled( $post_id ) ) {
			return '';
		}

		$args = $this->apply_shortcode_attributes( $this->settings->get_for_post( $post_id ), $attributes );
		$data = array(
			'ariaLabel'       => __( 'Table of contents', 'baretoc' ),
			'openLabel'       => __( 'Open table of contents', 'baretoc' ),
			'closeLabel'      => __( 'Close table of contents', 'baretoc' ),
			'headings'        => array_values( array_map( 'intval', $args['headings'] ) ),
			'title'           => (string) $args['title'],
			'titleElement'    => (string) $args['title_element'],
			'listStyle'       => (string) $args['list_style'],
			'cleanNumbering'  => ! empty( $args['clean_numbering'] ),
			'minimumHeadings' => max( 1, (int) $args['minimum_headings'] ),
			'generateIds'     => ! empty( $args['generate_ids'] ),
			'container'       => isset( $args['container'] ) ? (string) $args['container'] : '',
			'collapsible'     => ! empty( $args['collapsible'] ),
			'initiallyOpen'   => ! isset( $args['initially_open'] ) || ! empty( $args['initially_open'] ),
		);
		$json = wp_json_encode( $data );

		if ( ! is_string( $json ) ) {
			return '';
		}

		wp_enqueue_script( 'baretoc-template', BARETOC_URL . 'assets/js/template-toc.js', array(), BARETOC_VERSION, true );

		if ( ! empty( $args['smooth_scroll'] ) ) {
			wp_enqueue_script( 'baretoc-smooth-scroll', BARETOC_URL . 'assets/js/smooth-scroll.js', array(), BARETOC_VERSION, true );
		}

		if ( ! empty( $args['collapsible'] ) ) {
			wp_enqueue_script( 'baretoc-toggle', BARETOC_URL . 'assets/js/toggle.js', array(), BARETOC_VERSION, true );
		}

		return '<div class="baretoc-runtime" hidden data-baretoc-config="' . esc_attr( $json ) . '"></div>';
	}

	/**
	 * Adds heading IDs and replaces shortcode placeholders or inserts one auto TOC.
	 *
	 * @param string $content Filtered post content.
	 * @return string
	 */
	public function filter_content( $content ) {
		if ( $this->processing || ! is_string( $content ) ) {
			return $content;
		}

		$this->processing = true;
		$post_id          = (int) get_the_ID();
		$has_shortcode    = (bool) preg_match( self::PLACEHOLDER_PATTERN, $content );

		if ( $this->settings->is_disabled( $post_id ) ) {
			$content          = preg_replace( self::PLACEHOLDER_PATTERN, '', $content );
			$this->processing = false;

			return is_string( $content ) ? $content : '';
		}

		$base_settings = $this->settings->get_for_post( $post_id );
		$auto_insert   = 'shortcode' !== $base_settings['placement'] && $this->should_auto_insert();

		if ( ! $has_shortcode && ! $auto_insert ) {
			$this->processing = false;

			return $content;
		}

		$render_configs  = array();
		$parse_levels    = $auto_insert && ! $has_shortcode ? $base_settings['headings'] : array();
		$rendered_toc    = false;
		$rendered_toggle = false;

		if ( $has_shortcode && preg_match_all( self::PLACEHOLDER_PATTERN, $content, $placeholder_matches ) ) {
			foreach ( $placeholder_matches[1] as $token ) {
				$raw_attributes           = isset( $this->shortcodes[ $token ] ) ? $this->shortcodes[ $token ] : array();
				$render_configs[ $token ] = $this->apply_shortcode_attributes( $base_settings, $raw_attributes );
				$parse_levels             = array_merge( $parse_levels, $render_configs[ $token ]['headings'] );
			}
		}

		$parse_levels = array_values( array_unique( array_map( 'intval', $parse_levels ) ) );
		$parsed       = $this->parser->parse( $content, $base_settings['generate_ids'], $parse_levels );
		$content      = $parsed['content'];

		if ( $has_shortcode ) {
			$content = preg_replace_callback(
				self::PLACEHOLDER_PATTERN,
				function ( $matches ) use ( $render_configs, $parsed, &$rendered_toc, &$rendered_toggle ) {
					$token = $matches[1];

					if ( ! isset( $render_configs[ $token ] ) ) {
						return '';
					}

					$toc = $this->renderer->render( $parsed['headings'], $render_configs[ $token ] );

					if ( '' !== $toc ) {
						$rendered_toc = true;

						if ( ! empty( $render_configs[ $token ]['collapsible'] ) ) {
							$rendered_toggle = true;
						}
					}

					return $toc;
				},
				$content
			);
		} elseif ( $auto_insert ) {
			$toc = $this->renderer->render( $parsed['headings'], $base_settings );

			if ( '' !== $toc ) {
				$content      = $this->insert_automatically( $content, $toc, $base_settings['placement'] );
				$rendered_toc = true;
			}
		}

		if ( $rendered_toc && $base_settings['smooth_scroll'] ) {
			wp_enqueue_script( 'baretoc-smooth-scroll', BARETOC_URL . 'assets/js/smooth-scroll.js', array(), BARETOC_VERSION, true );
		}

		if ( $rendered_toggle ) {
			wp_enqueue_script( 'baretoc-toggle', BARETOC_URL . 'assets/js/toggle.js', array(), BARETOC_VERSION, true );
		}

		$this->processing = false;

		return is_string( $content ) ? $content : '';
	}

	/**
	 * Enqueues only the frontend assets explicitly enabled by the user.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets() {
		if ( is_admin() ) {
			return;
		}

		$settings = $this->settings->get();

		if ( 'minimal' === $settings['css'] ) {
			wp_enqueue_style( 'baretoc', BARETOC_URL . 'assets/css/baretoc.css', array(), BARETOC_VERSION );
		}
	}

	/**
	 * Applies supported shortcode overrides to global/post defaults.
	 *
	 * @param array<string,mixed> $base       Base settings.
	 * @param array<string,mixed> $attributes Raw shortcode attributes.
	 * @return array<string,mixed>
	 */
	private function apply_shortcode_attributes( $base, $attributes ) {
		$base['collapsible']    = false;
		$base['initially_open'] = true;
		$attributes             = shortcode_atts(
			array(
				'headings'      => null,
				'title'         => null,
				'list'          => null,
				'clean_numbers' => null,
				'minimum'       => null,
				'min'           => null,
				'title_element' => null,
				'container'     => null,
				'toggle'        => null,
				'initial'       => null,
			),
			$attributes,
			'baretoc'
		);

		if ( null !== $attributes['headings'] && '' !== $attributes['headings'] ) {
			$levels = preg_split( '/[\s,|]+/', strtolower( (string) $attributes['headings'] ) );
			$levels = array_map(
				static function ( $level ) {
					return (int) str_replace( 'h', '', $level );
				},
				$levels
			);
			$levels = array_values(
				array_unique(
					array_filter(
						$levels,
						static function ( $level ) {
							return $level >= 1 && $level <= 6;
						}
					)
				)
			);

			if ( ! empty( $levels ) ) {
				sort( $levels, SORT_NUMERIC );
				$base['headings'] = $levels;
			}
		}

		if ( null !== $attributes['title'] ) {
			$base['title'] = sanitize_text_field( (string) $attributes['title'] );
		}

		if ( null !== $attributes['list'] ) {
			$list        = strtolower( sanitize_key( (string) $attributes['list'] ) );
			$mapped_list = array_search(
				$list,
				array(
					'numbered' => 'ordered',
					'bullets'  => 'unordered',
				),
				true
			);

			if ( false !== $mapped_list ) {
				$list = $mapped_list;
			}

			if ( in_array( $list, array( 'numbered', 'bullets', 'none' ), true ) ) {
				$base['list_style'] = $list;
			}
		}

		if ( null !== $attributes['clean_numbers'] ) {
			$clean_numbering = filter_var( $attributes['clean_numbers'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

			if ( null !== $clean_numbering ) {
				$base['clean_numbering'] = $clean_numbering;
			}
		}

		$minimum = null !== $attributes['minimum'] ? $attributes['minimum'] : $attributes['min'];

		if ( null !== $minimum && is_numeric( $minimum ) ) {
			$base['minimum_headings'] = min( 50, max( 1, (int) $minimum ) );
		}

		if ( null !== $attributes['title_element'] && in_array( $attributes['title_element'], array( 'div', 'p', 'h2', 'h3' ), true ) ) {
			$base['title_element'] = $attributes['title_element'];
		}

		if ( null !== $attributes['container'] ) {
			$base['container'] = substr( sanitize_text_field( (string) $attributes['container'] ), 0, 200 );
		}

		if ( null !== $attributes['toggle'] ) {
			$collapsible = filter_var( $attributes['toggle'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

			if ( null !== $collapsible ) {
				$base['collapsible'] = $collapsible;
			}
		}

		if ( null !== $attributes['initial'] ) {
			$initial = sanitize_key( (string) $attributes['initial'] );

			if ( in_array( $initial, array( 'open', 'closed' ), true ) ) {
				$base['initially_open'] = 'open' === $initial;
			}
		}

		/**
		 * Filters the final configuration for one shortcode instance.
		 *
		 * @param array<string,mixed> $base       Final settings.
		 * @param array<string,mixed> $attributes Normalized shortcode attributes.
		 */
		$filtered = apply_filters( 'baretoc_shortcode_args', $base, $attributes );

		return is_array( $filtered ) ? $filtered : $base;
	}

	/**
	 * Limits automatic placement to the main singular content loop.
	 *
	 * @return bool
	 */
	private function should_auto_insert() {
		return ! is_admin() && ! is_feed() && is_singular() && in_the_loop() && is_main_query();
	}

	/**
	 * Inserts automatic output at the selected content landmark.
	 *
	 * @param string $content   Parsed content.
	 * @param string $toc       Rendered TOC.
	 * @param string $placement Placement key.
	 * @return string
	 */
	private function insert_automatically( $content, $toc, $placement ) {
		if ( 'after_first_paragraph' === $placement ) {
			$count   = 0;
			$content = preg_replace_callback(
				'/<\/p\s*>/i',
				static function ( $matches ) use ( $toc ) {
					return $matches[0] . $toc;
				},
				$content,
				1,
				$count
			);

			return $count > 0 && is_string( $content ) ? $content : $toc . (string) $content;
		}

		if ( 'before_first_heading' === $placement ) {
			$count   = 0;
			$content = preg_replace_callback(
				'/<h[1-6]\b/i',
				static function ( $matches ) use ( $toc ) {
					return $toc . $matches[0];
				},
				$content,
				1,
				$count
			);

			return $count > 0 && is_string( $content ) ? $content : $toc . (string) $content;
		}

		return $toc . $content;
	}
}
