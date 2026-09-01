<?php
/**
 * Heading parser.
 *
 * @package BareTOC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Finds usable headings and adds collision-safe IDs when needed.
 */
final class BareTOC_Parser {

	/**
	 * Parses headings in an HTML fragment.
	 *
	 * Existing attributes are preserved byte-for-byte. Headings carrying a
	 * `baretoc-ignore` or `no-toc` class are deliberately skipped.
	 *
	 * @param string         $content      Post content after shortcodes have run.
	 * @param bool           $generate_ids Whether missing IDs may be generated.
	 * @param array<int,int> $levels       Heading levels to parse, or an empty array for all.
	 * @return array{content:string,headings:array<int,array{level:int,id:string,title:string}>}
	 */
	public function parse( $content, $generate_ids = true, $levels = array() ) {
		$used_ids = $this->collect_ids( $content );
		$headings = array();
		$levels   = array_map( 'intval', $levels );

		$parsed_content = preg_replace_callback(
			'/((?:<h)([1-6])\b([^>]*)>)(.*?)(<\/h\2\s*>)/is',
			function ( $matches ) use ( &$used_ids, &$headings, $generate_ids, $levels ) {
				$attributes = $matches[3];
				$class      = $this->get_attribute( $attributes, 'class' );
				$level      = (int) $matches[2];

				if ( ! empty( $levels ) && ! in_array( $level, $levels, true ) ) {
					return $matches[0];
				}

				if ( $class['found'] && $this->has_ignored_class( $class['value'] ) ) {
					return $matches[0];
				}

				$title = $this->plain_text( $matches[4] );

				if ( '' === $title ) {
					return $matches[0];
				}

				$id_attribute = $this->get_attribute( $attributes, 'id' );
				$id           = $id_attribute['value'];
				$opening_tag  = $matches[1];

				if ( $id_attribute['found'] && '' === $id ) {
					// An explicit empty ID is left alone instead of rewriting author markup.
					return $matches[0];
				}

				if ( ! $id_attribute['found'] ) {
					if ( ! $generate_ids ) {
						return $matches[0];
					}

					$id = sanitize_title( $title );

					if ( '' === $id ) {
						$id = 'section';
					}

					/**
					 * Filters a generated heading ID before collision handling.
					 *
					 * @param string $id    Generated ID.
					 * @param string $title Plain-text heading title.
					 * @param int    $level Heading level from 1 to 6.
					 */
					$id = (string) apply_filters( 'baretoc_heading_id', $id, $title, $level );
					$id = sanitize_title( $id );

					if ( '' === $id ) {
						$id = 'section';
					}

					$id              = $this->unique_id( $id, $used_ids );
					$opening_tag     = substr( $opening_tag, 0, -1 ) . ' id="' . esc_attr( $id ) . '">';
					$used_ids[ $id ] = true;
				}

				/**
				 * Filters the plain-text label used for a TOC item.
				 *
				 * @param string $title Heading title.
				 * @param string $id    Heading ID.
				 * @param int    $level Heading level from 1 to 6.
				 */
				$title = (string) apply_filters( 'baretoc_heading_title', $title, $id, $level );

				$headings[] = array(
					'level' => $level,
					'id'    => $id,
					'title' => $title,
				);

				return $opening_tag . $matches[4] . $matches[5];
			},
			$content
		);

		if ( null === $parsed_content ) {
			$parsed_content = $content;
			$headings       = array();
		}

		/**
		 * Filters all parsed TOC items before level selection and rendering.
		 *
		 * @param array<int,array{level:int,id:string,title:string}> $headings Parsed headings.
		 * @param string                                              $content  Parsed content.
		 */
		$filtered_headings = apply_filters( 'baretoc_items', $headings, $parsed_content );

		if ( is_array( $filtered_headings ) ) {
			$headings = $filtered_headings;
		}

		return array(
			'content'  => $parsed_content,
			'headings' => $headings,
		);
	}

	/**
	 * Collects all existing IDs so generated anchors never collide with them.
	 *
	 * @param string $content HTML content.
	 * @return array<string,bool>
	 */
	private function collect_ids( $content ) {
		$used_ids = array();
		$pattern  = '/(?:^|\s)id\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+))/i';

		if ( preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$value = $this->matched_attribute_value( $match );

				if ( '' !== $value ) {
					$used_ids[ $value ] = true;
				}
			}
		}

		return $used_ids;
	}

	/**
	 * Reads an HTML attribute without changing the surrounding markup.
	 *
	 * @param string $attributes Raw opening-tag attributes.
	 * @param string $name       Attribute name.
	 * @return array{found:bool,value:string}
	 */
	private function get_attribute( $attributes, $name ) {
		$pattern = '/(?:^|\s)' . preg_quote( $name, '/' ) . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+))/i';

		if ( ! preg_match( $pattern, $attributes, $match ) ) {
			return array(
				'found' => false,
				'value' => '',
			);
		}

		return array(
			'found' => true,
			'value' => $this->matched_attribute_value( $match ),
		);
	}

	/**
	 * Returns whichever quoted or unquoted attribute capture matched.
	 *
	 * @param array<int,string> $attribute_match Regular-expression match.
	 * @return string
	 */
	private function matched_attribute_value( $attribute_match ) {
		for ( $index = 1; $index <= 3; $index++ ) {
			if ( isset( $attribute_match[ $index ] ) && '' !== $attribute_match[ $index ] ) {
				return html_entity_decode( $attribute_match[ $index ], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			}
		}

		return '';
	}

	/**
	 * Determines whether a heading opts out of the TOC.
	 *
	 * @param string $classes Space-separated classes.
	 * @return bool
	 */
	private function has_ignored_class( $classes ) {
		$class_names = preg_split( '/\s+/', strtolower( trim( $classes ) ) );

		return in_array( 'baretoc-ignore', $class_names, true ) || in_array( 'no-toc', $class_names, true );
	}

	/**
	 * Produces a readable label from heading HTML.
	 *
	 * @param string $html Heading inner HTML.
	 * @return string
	 */
	private function plain_text( $html ) {
		$text = wp_strip_all_tags( $html, true );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', $text );

		return trim( (string) $text );
	}

	/**
	 * Makes a generated ID unique in the complete content fragment.
	 *
	 * @param string             $base     Initial ID.
	 * @param array<string,bool> $used_ids IDs already present or generated.
	 * @return string
	 */
	private function unique_id( $base, $used_ids ) {
		$candidate = $base;
		$suffix    = 2;

		while ( isset( $used_ids[ $candidate ] ) ) {
			$candidate = $base . '-' . $suffix;
			++$suffix;
		}

		return $candidate;
	}
}
