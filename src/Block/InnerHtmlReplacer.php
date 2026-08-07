<?php
/**
 * Strategy F inner-HTML field replacement helpers.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

/**
 * DOM-aware replacement of translatable inner HTML without touching block attrs.
 */
final class InnerHtmlReplacer {

	/**
	 * Replaces inner content of the first element with the given tag name.
	 *
	 * When $translated begins with a matching element tag, the full fragment is
	 * returned as-is. Otherwise plain text replaces the element's inner content
	 * while preserving the opening tag attributes.
	 *
	 * @param string $html       Original innerHTML.
	 * @param string $tag        Element tag name (for example `p`, `h2`).
	 * @param string $translated Translated field value.
	 */
	public static function replace_tag_content( string $html, string $tag, string $translated ): string {
		$trimmed = trim( $translated );
		if ( self::starts_with_tag( $trimmed, $tag ) ) {
			return $trimmed;
		}

		$document = self::load_fragment( $html );
		if ( null === $document ) {
			return $html;
		}

		$elements = $document->getElementsByTagName( $tag );
		if ( 0 === $elements->length ) {
			return $html;
		}

		$element = $elements->item( 0 );
		if ( ! $element instanceof \DOMElement ) {
			return $html;
		}

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property names.
		while ( $element->firstChild ) {
			$element->removeChild( $element->firstChild );
		}
		// phpcs:enable

		$element->appendChild( $document->createTextNode( $translated ) );

		return self::body_inner_html( $document );
	}

	/**
	 * Replaces anchor link text while preserving href, classes, and wrapper markup.
	 *
	 * When $translated is a full HTML fragment, it replaces the whole innerHTML.
	 * Otherwise only the first anchor's text content is replaced.
	 *
	 * @param string $html       Original innerHTML.
	 * @param string $translated Translated field value.
	 */
	public static function replace_button_label( string $html, string $translated ): string {
		$trimmed = trim( $translated );
		if ( str_contains( $trimmed, '<' ) ) {
			return $trimmed;
		}

		$document = self::load_fragment( $html );
		if ( null === $document ) {
			return $html;
		}

		$anchors = $document->getElementsByTagName( 'a' );
		if ( 0 === $anchors->length ) {
			return $html;
		}

		$anchor = $anchors->item( 0 );
		if ( ! $anchor instanceof \DOMElement ) {
			return $html;
		}

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property names.
		while ( $anchor->firstChild ) {
			$anchor->removeChild( $anchor->firstChild );
		}
		// phpcs:enable

		$anchor->appendChild( $document->createTextNode( $translated ) );

		return self::body_inner_html( $document );
	}

	/**
	 * Returns the inner HTML of the first element with the given tag name.
	 *
	 * @param string $html HTML fragment.
	 * @param string $tag  Element tag name.
	 */
	public static function first_tag_inner_html( string $html, string $tag ): string {
		$document = self::load_fragment( $html );
		if ( null === $document ) {
			return '';
		}

		$elements = $document->getElementsByTagName( $tag );
		if ( 0 === $elements->length ) {
			return '';
		}

		$element = $elements->item( 0 );
		if ( ! $element instanceof \DOMElement ) {
			return '';
		}

		$inner = '';
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property names.
		foreach ( $element->childNodes as $child ) {
			$inner .= $document->saveHTML( $child );
		}
		// phpcs:enable

		return $inner;
	}

	/**
	 * Replaces a tag inside a nested host block without collapsing innerContent.
	 *
	 * Updates string slots in {@see innerContent} and rebuilds {@see innerHTML}
	 * from those string slots (null slots remain child placeholders).
	 *
	 * @param array<string, mixed> $block      Parsed block.
	 * @param string               $tag        Element tag name.
	 * @param string               $translated Translated inner HTML/text.
	 * @return array<string, mixed>
	 */
	public static function replace_tag_in_host_block( array $block, string $tag, string $translated ): array {
		$parts = $block['innerContent'] ?? null;
		if ( is_array( $parts ) && array() !== $parts ) {
			$rebuilt = '';
			foreach ( $parts as $index => $part ) {
				if ( ! is_string( $part ) ) {
					continue;
				}
				if ( false !== stripos( $part, '<' . $tag ) ) {
					$part            = self::replace_tag_content( $part, $tag, $translated );
					$parts[ $index ] = $part;
				}
				$rebuilt .= $part;
			}
			$block['innerContent'] = $parts;
			$block['innerHTML']    = $rebuilt;

			return $block;
		}

		$block['innerHTML'] = self::replace_tag_content(
			(string) ( $block['innerHTML'] ?? '' ),
			$tag,
			$translated
		);

		if ( is_array( $block['innerContent'] ?? null ) && array() !== $block['innerContent'] ) {
			$block['innerContent'] = array( $block['innerHTML'] );
		}

		return $block;
	}

	/**
	 * Returns the visible file-name link label from a file block fragment.
	 *
	 * @param string $html File block innerHTML.
	 */
	public static function file_name_label( string $html ): string {
		$document = self::load_fragment( $html );
		if ( null === $document ) {
			return '';
		}

		$anchors = $document->getElementsByTagName( 'a' );
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property names.
		for ( $i = 0; $i < $anchors->length; $i++ ) {
			$anchor = $anchors->item( $i );
			if ( ! $anchor instanceof \DOMElement ) {
				continue;
			}
			$class = $anchor->getAttribute( 'class' );
			if ( str_contains( $class, 'wp-block-file__button' ) || $anchor->hasAttribute( 'download' ) ) {
				continue;
			}

			return (string) $anchor->textContent;
		}
		// phpcs:enable

		return '';
	}

	/**
	 * Returns the download button label from a file block fragment.
	 *
	 * @param string $html File block innerHTML.
	 */
	public static function file_download_label( string $html ): string {
		$document = self::load_fragment( $html );
		if ( null === $document ) {
			return '';
		}

		$anchors = $document->getElementsByTagName( 'a' );
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property names.
		for ( $i = 0; $i < $anchors->length; $i++ ) {
			$anchor = $anchors->item( $i );
			if ( ! $anchor instanceof \DOMElement ) {
				continue;
			}
			$class = $anchor->getAttribute( 'class' );
			if ( str_contains( $class, 'wp-block-file__button' ) || $anchor->hasAttribute( 'download' ) ) {
				return (string) $anchor->textContent;
			}
		}
		// phpcs:enable

		return '';
	}

	/**
	 * Replaces the non-download file name anchor text.
	 *
	 * @param string $html       File block innerHTML.
	 * @param string $translated Translated label.
	 */
	public static function replace_file_name_label( string $html, string $translated ): string {
		$document = self::load_fragment( $html );
		if ( null === $document ) {
			return $html;
		}

		$anchors = $document->getElementsByTagName( 'a' );
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property names.
		for ( $i = 0; $i < $anchors->length; $i++ ) {
			$anchor = $anchors->item( $i );
			if ( ! $anchor instanceof \DOMElement ) {
				continue;
			}
			$class = $anchor->getAttribute( 'class' );
			if ( str_contains( $class, 'wp-block-file__button' ) || $anchor->hasAttribute( 'download' ) ) {
				continue;
			}
			while ( $anchor->firstChild ) {
				$anchor->removeChild( $anchor->firstChild );
			}
			$anchor->appendChild( $document->createTextNode( $translated ) );

			return self::body_inner_html( $document );
		}
		// phpcs:enable

		return $html;
	}

	/**
	 * Replaces the download button anchor text.
	 *
	 * @param string $html       File block innerHTML.
	 * @param string $translated Translated label.
	 */
	public static function replace_file_download_label( string $html, string $translated ): string {
		$document = self::load_fragment( $html );
		if ( null === $document ) {
			return $html;
		}

		$anchors = $document->getElementsByTagName( 'a' );
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property names.
		for ( $i = 0; $i < $anchors->length; $i++ ) {
			$anchor = $anchors->item( $i );
			if ( ! $anchor instanceof \DOMElement ) {
				continue;
			}
			$class = $anchor->getAttribute( 'class' );
			if ( ! str_contains( $class, 'wp-block-file__button' ) && ! $anchor->hasAttribute( 'download' ) ) {
				continue;
			}
			while ( $anchor->firstChild ) {
				$anchor->removeChild( $anchor->firstChild );
			}
			$anchor->appendChild( $document->createTextNode( $translated ) );

			return self::body_inner_html( $document );
		}
		// phpcs:enable

		return $html;
	}

	/**
	 * Whether a fragment starts with the given HTML tag.
	 *
	 * @param string $html HTML fragment.
	 * @param string $tag  Element tag name.
	 */
	private static function starts_with_tag( string $html, string $tag ): bool {
		return 1 === preg_match( '/^<' . preg_quote( $tag, '/' ) . '[\s>\/]/i', $html );
	}

	/**
	 * Loads an HTML fragment into a DOMDocument.
	 *
	 * @param string $html HTML fragment.
	 */
	private static function load_fragment( string $html ): ?\DOMDocument {
		if ( '' === trim( $html ) ) {
			return null;
		}

		$document = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$loaded   = $document->loadHTML(
			'<?xml encoding="utf-8" ?><body>' . $html . '</body>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			return null;
		}

		return $document;
	}

	/**
	 * Returns the inner HTML of the synthetic body wrapper.
	 *
	 * @param \DOMDocument $document Loaded fragment document.
	 */
	private static function body_inner_html( \DOMDocument $document ): string {
		$body = $document->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $body instanceof \DOMElement ) {
			return '';
		}

		$html = '';
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property names.
		foreach ( $body->childNodes as $child ) {
			$html .= $document->saveHTML( $child );
		}
		// phpcs:enable

		return $html;
	}
}
