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
