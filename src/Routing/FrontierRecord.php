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

	/**
	 * Parent source type.
	 *
	 * @var string
	 */
	public string $parent_source_type;

	/**
	 * Parent source id.
	 *
	 * @var int
	 */
	public int $parent_source_id;

	/**
	 * Bounded checkpoint JSON.
	 *
	 * @var string|null
	 */
	public ?string $checkpoint_json;

	/**
	 * Coalescing generation counter.
	 *
	 * @var int
	 */
	public int $generation;

	/**
	 * Frontier status (pending|running|completed|degraded|failed).
	 *
	 * `degraded` means the DFS finished with unresolved path collisions:
	 * conflicting children retain prior routes, candidates are untouched,
	 * and the frontier must not be marked completed while conflicts remain.
	 *
	 * @var string
	 */
	public string $status;

	/**
	 * Builds a frontier checkpoint record.
	 *
	 * @param string      $parent_source_type Parent source type.
	 * @param int         $parent_source_id   Parent source id.
	 * @param string|null $checkpoint_json    Bounded checkpoint JSON.
	 * @param int         $generation         Coalescing generation counter.
	 * @param string      $status             pending|running|failed|completed|degraded.
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
