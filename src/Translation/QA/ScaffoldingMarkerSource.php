<?php
/**
 * Optional capability: bounded scaffolding markers for a translation batch (TI.4).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\QA;

use AIMultilingual\Translation\AI\TranslationBatch;

/**
 * Provider-side marker export — not part of AIProviderInterface.
 */
interface ScaffoldingMarkerSource {

	/**
	 * Bounded framing prefixes actually included for this batch (not full prompt body).
	 *
	 * @param TranslationBatch $batch Provider batch.
	 * @return list<string>
	 */
	public function scaffolding_markers_for_batch( TranslationBatch $batch ): array;
}
