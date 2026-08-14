<?php
/**
 * Reindex frontier checkpoint record.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Routing;

/**
 * Frontier checkpoint DTO for bounded hierarchy reindex (MSEO.3).
 */
final class FrontierRecord {

	public string $parent_source_type;
	public int $parent_source_id;
	public ?string $checkpoint_json;
	public int $generation;
	public string $status;

	/**
	 * @param string      $parent_source_type Parent source type.
	 * @param int         $parent_source_id   Parent source id.
	 * @param string|null $checkpoint_json    Bounded checkpoint JSON.
	 * @param int         $generation         Coalescing generation counter.
	 * @param string      $status             pending|running|failed|completed.
	 */
	public function __construct(
		string $parent_source_type,
		int $parent_source_id,
		?string $checkpoint_json = null,
		int $generation = 1,
		string $status = 'pending'
	) {
		$this->parent_source_type = $parent_source_type;
		$this->parent_source_id   = $parent_source_id;
		$this->checkpoint_json    = $checkpoint_json;
		$this->generation         = $generation;
		$this->status             = $status;
	}
}
