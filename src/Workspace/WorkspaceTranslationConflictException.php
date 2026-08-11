<?php
/**
 * Optimistic locking conflict for workspace target (translation) saves.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace;

/**
 * Raised when expected_translation_hash no longer matches the persisted target hash.
 */
final class WorkspaceTranslationConflictException extends \RuntimeException {

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
		parent::__construct( 'Translation hash mismatch.', 409 );
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
