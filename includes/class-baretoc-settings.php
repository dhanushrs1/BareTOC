<?php
/**
 * Global settings and per-post controls.
 *
 * @package BareTOC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns all stored BareTOC configuration and its small admin interface.
 */
final class BareTOC_Settings {

	/** Option key. */
	const OPTION_NAME = 'baretoc_settings';

	/** Per-post disable key. */
	const META_DISABLED = '_baretoc_disabled';

	/** Per-post heading override key. */
	const META_LEVELS = '_baretoc_heading_levels';

	/** Settings page slug. */
	const MENU_SLUG = 'baretoc';

	/**
	 * Registers admin and settings hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_post_options' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_style' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( BARETOC_FILE ), array( $this, 'add_settings_link' ) );
	}

	/**
	 * Default plugin configuration.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'title'            => __( 'Table of Contents', 'baretoc' ),
			'title_element'    => 'div',
			'headings'         => array( 2, 3, 4 ),
			'minimum_headings' => 3,
			'list_style'       => 'numbered',
			'clean_numbering'  => true,
			'placement'        => 'shortcode',
			'generate_ids'     => true,
			'smooth_scroll'    => false,
			'css'              => 'none',
		);
	}

	/**
	 * Returns normalized global settings.
	 *
	 * @return array<string,mixed>
	 */
	public function get() {
		$stored = get_option( self::OPTION_NAME, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$values = array_merge( self::defaults(), $stored );

		return $this->normalize( $values );
	}

	/**
	 * Returns settings with a post's optional heading-level override applied.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>
	 */
	public function get_for_post( $post_id ) {
		$settings = $this->get();

		if ( $post_id > 0 && metadata_exists( 'post', $post_id, self::META_LEVELS ) ) {
			$levels = get_post_meta( $post_id, self::META_LEVELS, true );

			if ( is_array( $levels ) ) {
				$settings['headings'] = $this->sanitize_heading_levels( $levels );
			}
		}

		/**
		 * Filters the heading levels used for a given post.
		 *
		 * @param array<int,int> $levels  Heading numbers from 1 to 6.
		 * @param int            $post_id Post ID, or zero outside a post.
		 */
		$levels = apply_filters( 'baretoc_heading_levels', $settings['headings'], $post_id );

		if ( is_array( $levels ) ) {
			$settings['headings'] = $this->sanitize_heading_levels( $levels );
		}

		return $settings;
	}

	/**
	 * Checks the per-post disable switch.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function is_disabled( $post_id ) {
		return $post_id > 0 && '1' === (string) get_post_meta( $post_id, self::META_DISABLED, true );
	}

	/**
	 * Registers the single compact option array.
	 *
	 * @return void
	 */
	public function register_setting() {
		register_setting(
			'baretoc',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Sanitizes settings submitted through options.php.
	 *
	 * @param mixed $input Submitted value.
	 * @return array<string,mixed>
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		// An unchecked checkbox is omitted from the submitted option array.
		$input['generate_ids']    = ! empty( $input['generate_ids'] );
		$input['smooth_scroll']   = ! empty( $input['smooth_scroll'] );
		$input['clean_numbering'] = ! empty( $input['clean_numbering'] );

		return $this->normalize( $input );
	}

	/**
	 * Adds Settings > BareTOC.
	 *
	 * @return void
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'BareTOC Settings', 'baretoc' ),
			__( 'BareTOC', 'baretoc' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Renders the intentionally restrained settings screen.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = $this->get();
		?>
		<div class="wrap baretoc-settings">
			<h1><?php esc_html_e( 'BareTOC', 'baretoc' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Structure from BareTOC. Design from your theme.', 'baretoc' ); ?></p>
			<?php settings_errors( self::OPTION_NAME ); ?>

			<form action="options.php" method="post">
				<?php settings_fields( 'baretoc' ); ?>

				<section class="baretoc-settings__section" aria-labelledby="baretoc-content-settings">
					<h2 id="baretoc-content-settings"><?php esc_html_e( 'Content', 'baretoc' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="baretoc-title"><?php esc_html_e( 'TOC title', 'baretoc' ); ?></label></th>
							<td><input id="baretoc-title" class="regular-text" type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[title]" value="<?php echo esc_attr( $settings['title'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="baretoc-title-element"><?php esc_html_e( 'Title element', 'baretoc' ); ?></label></th>
							<td>
								<select id="baretoc-title-element" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[title_element]">
									<?php foreach ( array( 'div', 'p', 'h2', 'h3' ) as $element ) : ?>
										<option value="<?php echo esc_attr( $element ); ?>" <?php selected( $settings['title_element'], $element ); ?>><?php echo esc_html( strtoupper( $element ) ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Heading levels', 'baretoc' ); ?></th>
							<td><fieldset class="baretoc-levels"><legend class="screen-reader-text"><?php esc_html_e( 'Heading levels', 'baretoc' ); ?></legend><?php $this->render_heading_checkboxes( $settings['headings'], self::OPTION_NAME . '[headings][]' ); ?></fieldset></td>
						</tr>
						<tr>
							<th scope="row"><label for="baretoc-minimum"><?php esc_html_e( 'Minimum headings', 'baretoc' ); ?></label></th>
							<td><input id="baretoc-minimum" class="small-text" min="1" max="50" type="number" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[minimum_headings]" value="<?php echo esc_attr( $settings['minimum_headings'] ); ?>"> <span class="description"><?php esc_html_e( 'The TOC remains hidden below this count.', 'baretoc' ); ?></span></td>
						</tr>
					</table>
				</section>

				<section class="baretoc-settings__section" aria-labelledby="baretoc-list-settings">
					<h2 id="baretoc-list-settings"><?php esc_html_e( 'List', 'baretoc' ); ?></h2>
					<table class="form-table" role="presentation"><tr><th scope="row"><?php esc_html_e( 'List style', 'baretoc' ); ?></th><td><fieldset>
						<?php
						$list_styles = array(
							'numbered' => __( 'Numbered', 'baretoc' ),
							'bullets'  => __( 'Bullets', 'baretoc' ),
							'none'     => __( 'None', 'baretoc' ),
						);
						foreach ( $list_styles as $value => $label ) :
							?>
							<label><input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[list_style]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $settings['list_style'], $value ); ?>> <?php echo esc_html( $label ); ?></label><br>
						<?php endforeach; ?>
					</fieldset></td></tr>
						<tr><th scope="row"><?php esc_html_e( 'Smart numbering cleanup', 'baretoc' ); ?></th><td>
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[clean_numbering]" value="1" <?php checked( $settings['clean_numbering'] ); ?>> <?php esc_html_e( 'Remove leading numbering from TOC labels', 'baretoc' ); ?></label>
							<p class="description"><?php esc_html_e( 'Prevents output such as “2. 2. Installation” without changing the original heading.', 'baretoc' ); ?></p>
						</td></tr>
					</table>
				</section>

				<section class="baretoc-settings__section" aria-labelledby="baretoc-placement-settings">
					<h2 id="baretoc-placement-settings"><?php esc_html_e( 'Placement', 'baretoc' ); ?></h2>
					<table class="form-table" role="presentation"><tr><th scope="row"><?php esc_html_e( 'Insertion', 'baretoc' ); ?></th><td><fieldset>
						<?php
						$placements = array(
							'shortcode'             => __( 'Shortcode only (recommended)', 'baretoc' ),
							'before_content'        => __( 'Before content', 'baretoc' ),
							'after_first_paragraph' => __( 'After first paragraph', 'baretoc' ),
							'before_first_heading'  => __( 'Before first heading', 'baretoc' ),
						);
						foreach ( $placements as $value => $label ) :
							?>
							<label><input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[placement]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $settings['placement'], $value ); ?>> <?php echo esc_html( $label ); ?></label><br>
						<?php endforeach; ?>
					</fieldset></td></tr></table>
				</section>

				<section class="baretoc-settings__section" aria-labelledby="baretoc-output-settings">
					<h2 id="baretoc-output-settings"><?php esc_html_e( 'Output', 'baretoc' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr><th scope="row"><?php esc_html_e( 'Heading IDs', 'baretoc' ); ?></th><td>
							<p><strong><?php esc_html_e( 'Existing IDs are always preserved.', 'baretoc' ); ?></strong></p>
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[generate_ids]" value="1" <?php checked( $settings['generate_ids'] ); ?>> <?php esc_html_e( 'Generate collision-safe IDs when missing', 'baretoc' ); ?></label>
						</td></tr>
						<tr><th scope="row"><?php esc_html_e( 'Smooth scrolling', 'baretoc' ); ?></th><td>
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[smooth_scroll]" value="1" <?php checked( $settings['smooth_scroll'] ); ?>> <?php esc_html_e( 'Enable smooth scrolling for BareTOC links', 'baretoc' ); ?></label>
							<p class="description"><?php esc_html_e( 'Disabled by default. Reduced-motion preferences are always respected.', 'baretoc' ); ?></p>
						</td></tr>
						<tr><th scope="row"><?php esc_html_e( 'Plugin CSS', 'baretoc' ); ?></th><td><fieldset>
							<label><input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[css]" value="none" <?php checked( $settings['css'], 'none' ); ?>> <?php esc_html_e( 'No plugin CSS (recommended)', 'baretoc' ); ?></label><br>
							<label><input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[css]" value="minimal" <?php checked( $settings['css'], 'minimal' ); ?>> <?php esc_html_e( 'Minimal structural CSS', 'baretoc' ); ?></label>
						</fieldset></td></tr>
					</table>
				</section>

				<section class="baretoc-settings__section" aria-labelledby="baretoc-integrations-settings">
					<h2 id="baretoc-integrations-settings"><?php esc_html_e( 'Integrations', 'baretoc' ); ?></h2>
					<p><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <?php esc_html_e( 'Rank Math table-of-contents detection is enabled automatically.', 'baretoc' ); ?></p>
				</section>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Adds a compact editor meta box to public post types with editor support.
	 *
	 * @return void
	 */
	public function add_meta_boxes() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );

		foreach ( $post_types as $post_type ) {
			if ( post_type_supports( $post_type, 'editor' ) ) {
				add_meta_box(
					'baretoc-options',
					__( 'BareTOC', 'baretoc' ),
					array( $this, 'render_meta_box' ),
					$post_type,
					'side',
					'default'
				);
			}
		}
	}

	/**
	 * Renders per-post disable and heading-level controls.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		$disabled      = $this->is_disabled( $post->ID );
		$uses_global   = ! metadata_exists( 'post', $post->ID, self::META_LEVELS );
		$global_levels = $this->get()['headings'];
		$post_levels   = $uses_global ? $global_levels : get_post_meta( $post->ID, self::META_LEVELS, true );
		$post_levels   = is_array( $post_levels ) ? $post_levels : $global_levels;

		wp_nonce_field( 'baretoc_save_post_options', 'baretoc_options_nonce' );
		?>
		<p><label><input type="checkbox" name="baretoc_disabled" value="1" <?php checked( $disabled ); ?>> <strong><?php esc_html_e( 'Disable TOC for this content', 'baretoc' ); ?></strong></label></p>
		<hr>
		<p><label><input type="checkbox" name="baretoc_use_global" value="1" <?php checked( $uses_global ); ?>> <?php esc_html_e( 'Use global heading levels', 'baretoc' ); ?></label></p>
		<fieldset class="baretoc-levels"><legend class="screen-reader-text"><?php esc_html_e( 'Custom heading levels', 'baretoc' ); ?></legend><?php $this->render_heading_checkboxes( $post_levels, 'baretoc_post_headings[]' ); ?></fieldset>
		<p class="description"><?php esc_html_e( 'Custom levels apply when “Use global heading levels” is unchecked.', 'baretoc' ); ?></p>
		<?php
	}

	/**
	 * Saves per-post controls.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_post_options( $post_id ) {
		if ( ! isset( $_POST['baretoc_options_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['baretoc_options_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'baretoc_save_post_options' ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['baretoc_disabled'] ) ) {
			update_post_meta( $post_id, self::META_DISABLED, '1' );
		} else {
			delete_post_meta( $post_id, self::META_DISABLED );
		}

		if ( isset( $_POST['baretoc_use_global'] ) ) {
			delete_post_meta( $post_id, self::META_LEVELS );
			return;
		}

		$levels = isset( $_POST['baretoc_post_headings'] ) && is_array( $_POST['baretoc_post_headings'] )
			? array_map( 'absint', wp_unslash( $_POST['baretoc_post_headings'] ) )
			: self::defaults()['headings'];

		update_post_meta( $post_id, self::META_LEVELS, $this->sanitize_heading_levels( $levels ) );
	}

	/**
	 * Loads a tiny admin-only stylesheet on BareTOC screens.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_style( $hook_suffix ) {
		if ( 'settings_page_' . self::MENU_SLUG !== $hook_suffix && ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		wp_enqueue_style( 'baretoc-admin', BARETOC_URL . 'assets/css/admin.css', array(), BARETOC_VERSION );
	}

	/**
	 * Adds a direct settings link on the Plugins screen.
	 *
	 * @param array<int,string> $links Existing action links.
	 * @return array<int,string>
	 */
	public function add_settings_link( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::MENU_SLUG ) ) . '">' . esc_html__( 'Settings', 'baretoc' ) . '</a>'
		);

		return $links;
	}

	/**
	 * Prints the reusable H1-H6 checkbox group.
	 *
	 * @param array<int,int> $selected Selected heading numbers.
	 * @param string         $name     Input name.
	 * @return void
	 */
	private function render_heading_checkboxes( $selected, $name ) {
		for ( $level = 1; $level <= 6; $level++ ) {
			?>
			<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $level ); ?>" <?php checked( in_array( $level, $selected, true ) ); ?>> H<?php echo esc_html( $level ); ?></label>
			<?php
		}
	}

	/**
	 * Normalizes global or runtime configuration.
	 *
	 * @param array<string,mixed> $values Values to normalize.
	 * @return array<string,mixed>
	 */
	private function normalize( $values ) {
		$defaults = self::defaults();
		$values   = array_merge( $defaults, $values );
		$levels   = isset( $values['headings'] ) && is_array( $values['headings'] ) ? $values['headings'] : $defaults['headings'];

		return array(
			'title'            => sanitize_text_field( (string) $values['title'] ),
			'title_element'    => in_array( $values['title_element'], array( 'div', 'p', 'h2', 'h3' ), true ) ? $values['title_element'] : $defaults['title_element'],
			'headings'         => $this->sanitize_heading_levels( $levels ),
			'minimum_headings' => min( 50, max( 1, absint( $values['minimum_headings'] ) ) ),
			'list_style'       => in_array( $values['list_style'], array( 'numbered', 'bullets', 'none' ), true ) ? $values['list_style'] : $defaults['list_style'],
			'clean_numbering'  => ! empty( $values['clean_numbering'] ),
			'placement'        => in_array( $values['placement'], array( 'shortcode', 'before_content', 'after_first_paragraph', 'before_first_heading' ), true ) ? $values['placement'] : $defaults['placement'],
			'generate_ids'     => ! empty( $values['generate_ids'] ),
			'smooth_scroll'    => ! empty( $values['smooth_scroll'] ),
			'css'              => 'minimal' === $values['css'] ? 'minimal' : 'none',
		);
	}

	/**
	 * Sanitizes and orders a heading-level list.
	 *
	 * @param array<int,mixed> $levels Submitted heading levels.
	 * @return array<int,int>
	 */
	private function sanitize_heading_levels( $levels ) {
		$levels = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $levels ),
					static function ( $level ) {
						return $level >= 1 && $level <= 6;
					}
				)
			)
		);

		if ( empty( $levels ) ) {
			$levels = array( 2, 3, 4 );
		}

		sort( $levels, SORT_NUMERIC );

		return $levels;
	}
}
