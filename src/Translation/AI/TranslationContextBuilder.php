<?php
/**
 * Builds allowlisted TranslationContext for TI.2.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\AI;

use AIMultilingual\Language\Languages;
use AIMultilingual\Translation\Extractor;
use WP_Post;

/**
 * Shared context builder for TranslationService (sync + Jobs).
 */
final class TranslationContextBuilder {

	/**
	 * Field semantic mapper.
	 *
	 * @var FieldSemanticMapper
	 */
	private FieldSemanticMapper $mapper;

	/**
	 * Constructs the builder.
	 *
	 * @param FieldSemanticMapper|null $mapper Optional mapper.
	 */
	public function __construct( ?FieldSemanticMapper $mapper = null ) {
		$this->mapper = $mapper ?? new FieldSemanticMapper();
	}

	/**
	 * Builds context for a WordPress post segment.
	 *
	 * @param WP_Post              $post        Canonical post.
	 * @param array<string, mixed> $segment     Assembled segment.
	 * @param Languages|null       $languages   Optional language registry for display names.
	 * @param string|null          $source_locale Source locale.
	 * @param string|null          $target_locale Target locale.
	 */
	public function build_for_post(
		WP_Post $post,
		array $segment,
		?Languages $languages = null,
		?string $source_locale = null,
		?string $target_locale = null
	): TranslationContext {
		$post_type = (string) $post->post_type;
		$semantic  = $this->mapper->map( $segment, $post_type );
		$title     = $this->cap( (string) $post->post_title, TranslationContext::MAX_OBJECT_TITLE );

		$items     = array();
		$truncated = false;

		$purpose = $this->purpose_for( $semantic );
		if ( '' !== $purpose ) {
			$items[] = new ContextItem( ContextItem::TYPE_CONTENT_PURPOSE, $purpose );
		}

		$field_key = (string) ( $segment['field_key'] ?? '' );
		if (
			in_array(
				$semantic,
				array(
					FieldSemantic::PRODUCT_SHORT_DESCRIPTION,
					FieldSemantic::PRODUCT_LONG_DESCRIPTION,
					FieldSemantic::SEO_TITLE,
					FieldSemantic::SEO_DESCRIPTION,
					FieldSemantic::SEO_SOCIAL_TITLE,
					FieldSemantic::SEO_SOCIAL_DESCRIPTION,
				),
				true
			)
			&& '' !== $title
			&& Extractor::FIELD_TITLE !== $field_key
		) {
			$items[] = new ContextItem( ContextItem::TYPE_SIBLING_TITLE, $title, 'object_title' );
		}

		if ( 'product' === $post_type && function_exists( 'wp_get_post_terms' ) ) {
			$terms = wp_get_post_terms( (int) $post->ID, 'product_cat', array( 'fields' => 'names' ) );
			if ( is_array( $terms ) ) {
				$count = 0;
				foreach ( $terms as $name ) {
					if ( $count >= TranslationContext::MAX_CATEGORIES ) {
						$truncated = true;
						break;
					}
					$name = $this->cap( (string) $name, TranslationContext::MAX_ITEM_VALUE );
					if ( '' === $name ) {
						continue;
					}
					$items[] = new ContextItem( ContextItem::TYPE_CATEGORY, $name );
					++$count;
				}
			}

			if ( function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( (int) $post->ID );
				if ( is_object( $product ) && method_exists( $product, 'get_attributes' ) ) {
					$attrs = $product->get_attributes();
					$count = 0;
					if ( is_array( $attrs ) ) {
						foreach ( $attrs as $attr ) {
							if ( $count >= TranslationContext::MAX_ATTRIBUTE_NAMES ) {
								$truncated = true;
								break;
							}
							$label = '';
							if ( is_object( $attr ) && method_exists( $attr, 'get_name' ) ) {
								$label = (string) $attr->get_name();
							} elseif ( is_string( $attr ) ) {
								$label = $attr;
							}
							$label = $this->cap( $label, TranslationContext::MAX_ITEM_VALUE );
							if ( '' === $label || is_numeric( $label ) ) {
								continue;
							}
							$items[] = new ContextItem( ContextItem::TYPE_ATTRIBUTE_NAME, $label );
							++$count;
						}
					}
				}
			}
		}

		if ( null !== $languages ) {
			foreach ( array( $source_locale, $target_locale ) as $locale ) {
				if ( null === $locale || '' === $locale ) {
					continue;
				}
				$name = $this->language_name_for_locale( $languages, $locale );
				$name = $this->cap( $name, TranslationContext::MAX_ITEM_VALUE );
				if ( '' !== $name ) {
					$items[] = new ContextItem( ContextItem::TYPE_LANGUAGE_NAME, $name, $locale );
				}
			}
		}

		return $this->finalize( $semantic, $post_type, $title, $items, $truncated );
	}

	/**
	 * Builds context from TQ.0 corpus case metadata (real DTO path for harness).
	 *
	 * @param array<string, mixed> $corpus_case Corpus case.
	 */
	public function build_for_corpus_case( array $corpus_case ): TranslationContext {
		$semantic  = $this->mapper->map_corpus( (string) ( $corpus_case['field_semantics'] ?? FieldSemantic::GENERIC ) );
		$title     = $this->cap( (string) ( $corpus_case['object_title'] ?? '' ), TranslationContext::MAX_OBJECT_TITLE );
		$otype     = $this->cap( (string) ( $corpus_case['object_type'] ?? '' ), 64 );
		$items     = array();
		$truncated = false;

		$purpose = $this->purpose_for( $semantic );
		if ( '' !== $purpose ) {
			$items[] = new ContextItem( ContextItem::TYPE_CONTENT_PURPOSE, $purpose );
		}
		if ( '' !== $title && FieldSemantic::PRODUCT_TITLE !== $semantic && FieldSemantic::HEADING !== $semantic ) {
			$items[] = new ContextItem( ContextItem::TYPE_SIBLING_TITLE, $title, 'object_title' );
		}

		$categories = $corpus_case['context_categories'] ?? array();
		if ( is_array( $categories ) ) {
			$count = 0;
			foreach ( $categories as $name ) {
				if ( $count >= TranslationContext::MAX_CATEGORIES ) {
					$truncated = true;
					break;
				}
				$name = $this->cap( (string) $name, TranslationContext::MAX_ITEM_VALUE );
				if ( '' === $name ) {
					continue;
				}
				$items[] = new ContextItem( ContextItem::TYPE_CATEGORY, $name );
				++$count;
			}
		}

		return $this->finalize( $semantic, $otype, $title, $items, $truncated );
	}

	/**
	 * Applies budgets and drop priority, returning a finalized context.
	 *
	 * @param string $semantic     Field semantic.
	 * @param string $object_type  Object type.
	 * @param string $object_title Object title.
	 * @param array  $items        ContextItem list.
	 * @param bool   $truncated    Whether earlier truncation already occurred.
	 */
	private function finalize(
		string $semantic,
		string $object_type,
		string $object_title,
		array $items,
		bool $truncated
	): TranslationContext {
		$kept       = array();
		$char_count = 0;
		// Drop priority: keep content_purpose and sibling_title preferentially; drop attributes then categories.
		$ordered = $this->order_for_budget( $items );

		foreach ( $ordered as $item ) {
			if ( count( $kept ) >= TranslationContext::MAX_ITEMS ) {
				$truncated = true;
				break;
			}
			$piece = $item->type . ':' . $item->value;
			$next  = $char_count + strlen( $piece ) + 1;
			if ( $next > TranslationContext::MAX_TOTAL_CHARS ) {
				$truncated = true;
				break;
			}
			$kept[]     = $item;
			$char_count = $next;
		}

		$types = array();
		foreach ( $kept as $item ) {
			$types[] = $item->type;
		}

		return new TranslationContext(
			FieldSemantic::normalize( $semantic ),
			$this->cap( $object_type, 64 ),
			$object_title,
			$kept,
			array(
				'item_types' => array_values( array_unique( $types ) ),
				'truncated'  => $truncated,
				'char_count' => $char_count,
			)
		);
	}

	/**
	 * Attributes first to drop, then categories, then others retained.
	 *
	 * @param array $items ContextItem list.
	 * @return array Ordered ContextItem list.
	 */
	private function order_for_budget( array $items ): array {
		$priority = array(
			ContextItem::TYPE_CONTENT_PURPOSE => 0,
			ContextItem::TYPE_LANGUAGE_NAME   => 1,
			ContextItem::TYPE_SIBLING_TITLE   => 2,
			ContextItem::TYPE_OBJECT_TITLE    => 2,
			ContextItem::TYPE_CATEGORY        => 3,
			ContextItem::TYPE_ATTRIBUTE_NAME  => 4,
		);
		usort(
			$items,
			static function ( ContextItem $a, ContextItem $b ) use ( $priority ): int {
				$pa = $priority[ $a->type ] ?? 9;
				$pb = $priority[ $b->type ] ?? 9;
				return $pa <=> $pb;
			}
		);
		return $items;
	}

	/**
	 * Maps SEO semantics to content-purpose labels.
	 *
	 * @param string $semantic Field semantic.
	 */
	private function purpose_for( string $semantic ): string {
		return match ( $semantic ) {
			FieldSemantic::SEO_TITLE, FieldSemantic::SEO_DESCRIPTION => 'search_snippet',
			FieldSemantic::SEO_SOCIAL_TITLE, FieldSemantic::SEO_SOCIAL_DESCRIPTION => 'social_snippet',
			default => '',
		};
	}

	/**
	 * Resolves a display name for a locale from the language registry.
	 *
	 * @param Languages $languages Language registry.
	 * @param string    $locale    Locale code.
	 */
	private function language_name_for_locale( Languages $languages, string $locale ): string {
		foreach ( $languages->all() as $row ) {
			if ( (string) ( $row->locale ?? '' ) === $locale ) {
				return (string) ( $row->name ?? '' );
			}
		}

		return '';
	}

	/**
	 * Trims whitespace and hard-caps string length.
	 *
	 * @param string $value Input value.
	 * @param int    $max   Max bytes.
	 */
	private function cap( string $value, int $max ): string {
		$value = trim( preg_replace( '/\s+/u', ' ', $value ) ?? $value );
		if ( strlen( $value ) <= $max ) {
			return $value;
		}
		return rtrim( substr( $value, 0, $max ) );
	}
}
