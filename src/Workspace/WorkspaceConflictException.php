<?php
/**
 * Optimistic locking conflict for workspace saves.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace;

/**
 * Raised when a client-supplied source_hash no longer matches the canonical source.
 */
final class WorkspaceConflictException extends \RuntimeException {

	/**
	 * Refreshed segment application DTOs for the conflict response.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $segments;

	/**
	 * Builds the collaborator.
	 *
	 * @param array<int, array<string, mixed>> $segments Refreshed segment DTOs.
	 */
	public function __construct( array $segments ) {
		parent::__construct( 'Source hash mismatch.', 409 );
		$this->segments = $segments;
	}

	/**
	 * Returns refreshed segment DTOs for the conflict response.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function segments(): array {
		return $this->segments;
	}
}
