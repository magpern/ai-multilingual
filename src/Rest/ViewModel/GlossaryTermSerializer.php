<?php
/**
 * Maps glossary rows to REST ViewModels.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Rest\ViewModel;

/**
 * Glossary term serializer.
 */
final class GlossaryTermSerializer {

	/**
	 * Maps one DB/service row to a ViewModel.
	 *
	 * @param object $row Glossary row.
	 */
	public function from_row( object $row ): GlossaryTermViewModel {
		return new GlossaryTermViewModel(
			(int) $row->glossary_id,
			(int) $row->source_lang_id,
			(int) $row->target_lang_id,
			(string) $row->source_term,
			(string) $row->source_term_normalized,
			(string) $row->target_term,
			(string) ( $row->context ?? '' ),
			(string) ( $row->description ?? '' ),
			(bool) $row->is_active,
			(string) ( $row->created_at ?? '' ),
			(string) ( $row->updated_at ?? '' )
		);
	}

	/**
	 * Maps many rows to arrays.
	 *
	 * @param array<object> $rows Glossary rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function many_to_arrays( array $rows ): array {
		$out = array();
		foreach ( $rows as $row ) {
			$out[] = $this->from_row( $row )->to_array();
		}

		return $out;
	}
}
