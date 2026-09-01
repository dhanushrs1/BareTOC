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

		$list_style = isset( $args['list_style'] ) ? $args['list_style'] : 'numbered';
		$list_style = in_array( $list_style, array( 'numbered', 'bullets', 'none' ), true ) ? $list_style : 'numbered';
		$list_tag   = 'numbered' === $list_style ? 'ol' : 'ul';
		$tree       = $this->build_tree( $selected );
		$classes    = array( 'baretoc' );

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

		$output = '<nav class="' . esc_attr( implode( ' ', $classes ) ) . '" aria-label="' . esc_attr__( 'Table of contents', 'baretoc' ) . '">';

		if ( '' !== $title ) {
			$output .= '<' . $title_element . ' class="baretoc-title">' . esc_html( $title ) . '</' . $title_element . '>';
		}

		$output .= $this->render_list( $tree, $list_tag, $list_style, true );
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
