<?php
/**
 * Surface-neutral fail-closed structural attribute preservation.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\Safety;

/**
 * Ensures translated HTML fragments do not mutate protected wrapper attributes.
 *
 * Compares href, class, id, target, rel, and data-* on every element in document
 * order. Any mismatch or element-count drift rejects the translation fragment.
 */
final class StructuralAttributeGuard {

	/**
	 * Attribute names compared for structural equivalence.
	 *
	 * @var list<string>
	 */
	private const PROTECTED_ATTRS = array( 'href', 'class', 'id', 'target', 'rel' );

	/**
	 * Whether structural attributes are unchanged between two HTML fragments.
	 *
	 * @param string $source_html    Source HTML before apply.
	 * @param string $candidate_html HTML after apply/sanitize.
	 */
	public static function preserves_structure( string $source_html, string $candidate_html ): bool {
		if ( $source_html === $candidate_html ) {
			return true;
		}

		return self::fingerprint( $source_html ) === self::fingerprint( $candidate_html );
	}

	/**
	 * Builds an ordered structural fingerprint for an HTML fragment.
	 *
	 * @param string $html HTML fragment.
	 * @return list<array{tag: string, attrs: array<string, string>}>
	 */
	private static function fingerprint( string $html ): array {
		if ( '' === trim( $html ) ) {
			return array();
		}

		$document = self::load_fragment( $html );
		if ( null === $document ) {
			return array();
		}

		$body = $document->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $body instanceof \DOMElement ) {
			return array();
		}

		$fingerprint = array();
		self::collect_fingerprint( $body, $fingerprint );

		return $fingerprint;
	}

	/**
	 * Depth-first collection of protected attributes per element.
	 *
	 * @param \DOMNode                                               $node        Current node.
	 * @param list<array{tag: string, attrs: array<string, string>}> $fingerprint Output fingerprint.
	 */
	private static function collect_fingerprint( \DOMNode $node, array &$fingerprint ): void {
		if ( $node instanceof \DOMElement ) {
			$attrs = self::protected_attributes( $node );
			if ( array() !== $attrs ) {
				// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property names.
				$fingerprint[] = array(
					'tag'   => strtolower( $node->tagName ),
					'attrs' => $attrs,
				);
				// phpcs:enable
			}
		}

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property names.
		if ( $node->hasChildNodes() ) {
			foreach ( $node->childNodes as $child ) {
				self::collect_fingerprint( $child, $fingerprint );
			}
		}
		// phpcs:enable
	}

	/**
	 * Returns normalized protected attributes for one element.
	 *
	 * @param \DOMElement $element DOM element.
	 * @return array<string, string>
	 */
	private static function protected_attributes( \DOMElement $element ): array {
		$attrs = array();

		foreach ( self::PROTECTED_ATTRS as $name ) {
			if ( ! $element->hasAttribute( $name ) ) {
				continue;
			}

			$value = trim( (string) $element->getAttribute( $name ) );
			if ( '' !== $value ) {
				$attrs[ $name ] = $value;
			}
		}

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property names.
		if ( $element->hasAttributes() ) {
			foreach ( $element->attributes as $attribute ) {
				if ( ! $attribute instanceof \DOMAttr ) {
					continue;
				}

				$name = (string) $attribute->name;
				if ( ! str_starts_with( $name, 'data-' ) ) {
					continue;
				}

				$attrs[ $name ] = (string) $attribute->value;
			}
		}
		// phpcs:enable

		if ( array() === $attrs ) {
			return array();
		}

		ksort( $attrs );

		return $attrs;
	}

	/**
	 * Loads an HTML fragment into a DOMDocument.
	 *
	 * @param string $html HTML fragment.
	 */
	private static function load_fragment( string $html ): ?\DOMDocument {
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
}
