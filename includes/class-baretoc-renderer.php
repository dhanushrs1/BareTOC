<?php
/**
 * Semantic table-of-contents renderer.
 *
 * @package BareTOC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts parsed headings into small, theme-friendly navigation markup.
 */
final class BareTOC_Renderer {

	/**
	 * Renders a table of contents.
	 *
	 * @param array<int,array{level:int,id:string,title:string}> $headings Parsed headings.
	 * @param array<string,mixed>                                $args     Render settings.
	 * @return string
	 */
	public function render( $headings, $args ) {
		$levels = isset( $args['headings'] ) && is_array( $args['headings'] )
			? array_map( 'intval', $args['headings'] )
			: array( 2, 3, 4 );

		$selected = array_values(
			array_filter(
				$headings,
				static function ( $heading ) use ( $levels ) {
					return is_array( $heading )
						&& isset( $heading['level'], $heading['id'], $heading['title'] )
						&& in_array( (int) $heading['level'], $levels, true );
				}
			)
		);

		$minimum = isset( $args['minimum_headings'] ) ? max( 1, (int) $args['minimum_headings'] ) : 3;

		if ( count( $selected ) < $minimum ) {
			return '';
		}

		foreach ( $selected as &$heading ) {
			$original_title = (string) $heading['title'];
			$clean_title    = ! empty( $args['clean_numbering'] )
				? $this->remove_leading_numbering( $original_title )
				: $original_title;

			/**
			 * Filters an item's display label after smart numbering cleanup.
			 *
			 * @param string $clean_title    Clean TOC label.
			 * @param string $original_title Original heading label.
			 * @param string $id             Heading ID.
			 * @param int    $level          Heading level from 1 to 6.
			 */
			$heading['title'] = (string) apply_filters(
				'baretoc_item_title',
				$clean_title,
				$original_title,
				(string) $heading['id'],
				(int) $heading['level']
			);
		}
		unset( $heading );

		$list_style    = isset( $args['list_style'] ) ? $args['list_style'] : 'numbered';
		$list_style    = in_array( $list_style, array( 'numbered', 'bullets', 'none' ), true ) ? $list_style : 'numbered';
		$list_tag      = 'numbered' === $list_style ? 'ol' : 'ul';
		$tree          = $this->build_tree( $selected );
		$classes       = array( 'baretoc' );
		$collapsible   = ! empty( $args['collapsible'] );
		$smooth_toggle = $collapsible && ! empty( $args['smooth_toggle'] );

		if ( $collapsible ) {
			$classes[] = 'baretoc--collapsible';
		}

		if ( $smooth_toggle ) {
			$classes[] = 'baretoc--smooth-toggle';
		}

		/**
		 * Filters classes applied to the TOC nav element.
		 *
		 * @param array<int,string> $classes Nav classes.
		 * @param array<string,mixed> $args  Render settings.
		 */
		$classes = apply_filters( 'baretoc_classes', $classes, $args );
		$classes = is_array( $classes ) ? array_filter( array_map( 'sanitize_html_class', $classes ) ) : array( 'baretoc' );

		$title = isset( $args['title'] ) ? (string) $args['title'] : '';

		/**
		 * Filters the visible TOC title.
		 *
		 * @param string              $title Visible title.
		 * @param array<string,mixed> $args  Render settings.
		 */
		$title         = (string) apply_filters( 'baretoc_title', $title, $args );
		$title_element = isset( $args['title_element'] ) ? $args['title_element'] : 'div';
		$title_element = in_array( $title_element, array( 'div', 'p', 'h2', 'h3' ), true ) ? $title_element : 'div';

		$output            = '<nav class="' . esc_attr( implode( ' ', $classes ) ) . '" aria-label="' . esc_attr__( 'Table of contents', 'baretoc' ) . '">';
		$list              = $this->render_list( $tree, $list_tag, $list_style, true );
		$header_attributes = 'class="baretoc-header"';

		if ( $collapsible ) {
			$content_id        = wp_unique_id( 'baretoc-content-' );
			$initial_state     = ! isset( $args['initially_open'] ) || ! empty( $args['initially_open'] ) ? 'open' : 'closed';
			$header_attributes = 'class="baretoc-header baretoc-toggle" role="button" tabindex="0" aria-expanded="true" aria-controls="' . esc_attr( $content_id ) . '" aria-label="' . esc_attr__( 'Close table of contents', 'baretoc' ) . '" data-baretoc-initial="' . esc_attr( $initial_state ) . '" data-baretoc-smooth="' . ( $smooth_toggle ? 'yes' : 'no' ) . '" data-open-label="' . esc_attr__( 'Open table of contents', 'baretoc' ) . '" data-close-label="' . esc_attr__( 'Close table of contents', 'baretoc' ) . '"';
		}

		$output .= '<div ' . $header_attributes . '>';

		if ( '' !== $title ) {
			$output .= '<' . $title_element . ' class="baretoc-title">' . esc_html( $title ) . '</' . $title_element . '>';
		}

		if ( $collapsible ) {
			$output .= $this->render_toggle_icon();
		}

		$output .= '</div>';

		if ( $collapsible ) {
			$output .= '<div id="' . esc_attr( $content_id ) . '" class="baretoc-content">' . $list . '</div>';
		} else {
			$output .= $list;
		}

		$output .= $this->render_schema( $selected, $title );
		$output .= '</nav>';

		/**
		 * Filters the complete BareTOC HTML output.
		 *
		 * @param string                                               $output   Rendered HTML.
		 * @param array<int,array{level:int,id:string,title:string}>    $selected Selected items.
		 * @param array<string,mixed>                                  $args     Render settings.
		 */
		return (string) apply_filters( 'baretoc_output', $output, $selected, $args );
	}

	/**
	 * Returns one decorative disclosure icon for the interactive header.
	 *
	 * CSS hides the vertical line while expanded, changing the plus into a minus
	 * without duplicating icon markup.
	 *
	 * @return string
	 */
	private function render_toggle_icon() {
		return '<svg class="baretoc-toggle-icon" aria-hidden="true" focusable="false" width="20" height="20" viewBox="0 0 256 256"><rect width="256" height="256" fill="none"></rect><rect x="40" y="40" width="176" height="176" rx="8" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></rect><line x1="88" y1="128" x2="168" y2="128" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></line><line class="baretoc-toggle-icon__vertical" x1="128" y1="88" x2="128" y2="168" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></line></svg>';
	}

	/**
	 * Renders a machine-readable ItemList for search engines and other consumers.
	 *
	 * @param array<int,array{level:int,id:string,title:string}> $headings Selected headings.
	 * @param string                                             $title    Visible TOC title.
	 * @return string
	 */
	private function render_schema( $headings, $title ) {
		$base_url  = '';
		$permalink = function_exists( 'get_permalink' ) ? get_permalink() : '';

		if ( is_string( $permalink ) && '' !== $permalink ) {
			$base_url = (string) preg_replace( '/#.*$/', '', $permalink );
		}

		$items = array();

		foreach ( $headings as $position => $heading ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $position + 1,
				'name'     => (string) $heading['title'],
				'url'      => $base_url . '#' . rawurlencode( (string) $heading['id'] ),
			);
		}

		$schema = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'ItemList',
			'name'            => '' !== $title ? $title : __( 'Table of contents', 'baretoc' ),
			'itemListOrder'   => 'https://schema.org/ItemListOrderAscending',
			'numberOfItems'   => count( $items ),
			'itemListElement' => $items,
		);

		/**
		 * Filters the Schema.org ItemList generated for the TOC.
		 *
		 * Return a non-array value to omit the JSON-LD block.
		 *
		 * @param array<string,mixed>                              $schema   ItemList data.
		 * @param array<int,array{level:int,id:string,title:string}> $headings Selected headings.
		 * @param string                                           $title    Visible TOC title.
		 */
		$schema = apply_filters( 'baretoc_schema', $schema, $headings, $title );

		if ( ! is_array( $schema ) ) {
			return '';
		}

		$json = wp_json_encode( $schema );

		return is_string( $json ) && '' !== $json
			? '<script class="baretoc-schema" type="application/ld+json">' . $json . '</script>'
			: '';
	}

	/**
	 * Creates a hierarchy based on the document's heading levels.
	 *
	 * Skipped levels remain valid: an H4 following an H2 becomes that H2's child
	 * without adding empty wrapper items.
	 *
	 * @param array<int,array{level:int,id:string,title:string}> $headings Selected headings.
	 * @return array<int,object>
	 */
	private function build_tree( $headings ) {
		$roots = array();
		$stack = array();

		foreach ( $headings as $heading ) {
			$node = (object) array(
				'level'    => (int) $heading['level'],
				'id'       => (string) $heading['id'],
				'title'    => (string) $heading['title'],
				'children' => array(),
			);

			while ( ! empty( $stack ) ) {
				$parent = end( $stack );

				if ( $node->level > $parent->level ) {
					break;
				}

				array_pop( $stack );
			}

			if ( empty( $stack ) ) {
				$roots[] = $node;
			} else {
				$parent             = end( $stack );
				$parent->children[] = $node;
			}

			$stack[] = $node;
		}

		return $roots;
	}

	/**
	 * Recursively renders a branch of the heading tree.
	 *
	 * @param array<int,object> $nodes      Heading nodes.
	 * @param string            $list_tag   ol or ul.
	 * @param string            $list_style numbered, bullets, or none.
	 * @param bool              $is_root    Whether this is the outer list.
	 * @return string
	 */
	private function render_list( $nodes, $list_tag, $list_style, $is_root = false ) {
		$classes   = $is_root ? array( 'baretoc-list' ) : array( 'baretoc-sublist' );
		$classes[] = 'baretoc-list--' . $list_style;
		$output    = '<' . $list_tag . ' class="' . esc_attr( implode( ' ', $classes ) ) . '">';

		foreach ( $nodes as $node ) {
			$output .= '<li class="baretoc-item baretoc-level-' . (int) $node->level . '">';
			$output .= '<a class="baretoc-link" href="#' . esc_attr( $node->id ) . '">' . esc_html( $node->title ) . '</a>';

			if ( ! empty( $node->children ) ) {
				$output .= $this->render_list( $node->children, $list_tag, $list_style, false );
			}

			$output .= '</li>';
		}

		$output .= '</' . $list_tag . '>';

		return $output;
	}

	/**
	 * Removes a conservative leading section marker from a TOC label.
	 *
	 * Supported examples include `1.`, `1)`, `(1)`, `1.2`, `IV.`, and `A.`.
	 * Four-digit values such as years are deliberately left intact.
	 *
	 * @param string $title Original heading title.
	 * @return string
	 */
	private function remove_leading_numbering( $title ) {
		$pattern = '/^\s*(?:
			\(\s*(?:\d{1,3}|[A-Za-z]|[IVXLCDM]+)\s*\)
			|\d{1,3}(?:\.\d+)+(?:[.):])?
			|\d{1,3}(?:[.):]|\s*[-–—:])
			|(?:[IVXLCDM]+|[A-Za-z])(?:[.):]|\s*[-–—:])
		)(?:\s*[-–—:]\s*|\s+)/ux';
		$clean   = preg_replace( $pattern, '', $title, 1 );
		$clean   = is_string( $clean ) ? trim( $clean ) : $title;

		return '' !== $clean ? $clean : $title;
	}
}
