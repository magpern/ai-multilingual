<?php
/**
 * Route history persistence record.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Routing;

/**
 * History write DTO. Hash derived by repository.
 */
final class HistoryRecord {

	public int $language_id;
	public CanonicalPath $historical_path;
	public string $source_type;
	public int $source_id;
	public string $source_subtype;

	/**
	 * @param int           $language_id     Language id.
	 * @param CanonicalPath $historical_path Canonical historical path.
	 * @param string        $source_type     Store source type.
	 * @param int           $source_id       Source object id.
	 * @param string        $source_subtype  Source subtype.
	 */
	public function __construct(
		int $language_id,
		CanonicalPath $historical_path,
		string $source_type,
		int $source_id,
		string $source_subtype = ''
	) {
		$this->language_id     = $language_id;
		$this->historical_path = $historical_path;
		$this->source_type     = $source_type;
		$this->source_id       = $source_id;
		$this->source_subtype  = $source_subtype;
	}
}
