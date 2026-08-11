<?php
/**
 * Serializer for OTL operations list items.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rest\ViewModel;

/**
 * Maps assembled list arrays to ViewModels.
 */
final class OperatorTranslationListItemSerializer {

	/**
	 * Builds a list item ViewModel from an assembled array.
	 *
	 * @param array<string, mixed> $item Assembled list item.
	 */
	public function from_array( array $item ): OperatorTranslationListItemViewModel {
		return new OperatorTranslationListItemViewModel( $item );
	}

	/**
	 * Serializes many assembled list items.
	 *
	 * @param list<array<string, mixed>> $items Assembled items.
	 * @return list<array<string, mixed>>
	 */
	public function many_to_arrays( array $items ): array {
		$out = array();
		foreach ( $items as $item ) {
			$out[] = $this->from_array( $item )->to_array();
		}

		return $out;
	}
}
