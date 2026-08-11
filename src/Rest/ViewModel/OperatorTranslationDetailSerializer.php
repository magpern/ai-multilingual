<?php
/**
 * Serializer for OTL operations detail.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rest\ViewModel;

/**
 * Maps assembled detail arrays to ViewModels.
 */
final class OperatorTranslationDetailSerializer {

	/**
	 * Builds a detail ViewModel from an assembled array.
	 *
	 * @param array<string, mixed> $item Assembled detail.
	 */
	public function from_array( array $item ): OperatorTranslationDetailViewModel {
		return new OperatorTranslationDetailViewModel( $item );
	}

	/**
	 * Serializes an assembled detail payload.
	 *
	 * @param array<string, mixed> $item Assembled detail.
	 * @return array<string, mixed>
	 */
	public function to_array( array $item ): array {
		return $this->from_array( $item )->to_array();
	}
}
