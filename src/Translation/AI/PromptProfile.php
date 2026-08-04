<?php
/**
 * One provider-independent prompt profile (F11 §4.4).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\AI;

/**
 * Immutable prompt profile definition.
 */
final class PromptProfile {

	/**
	 * Builds a profile.
	 *
	 * @param string             $id                  Profile id.
	 * @param string             $version             Profile version.
	 * @param string             $system_instructions Provider-agnostic instructions.
	 * @param array<int, string> $constraints         Structural constraint ids.
	 * @param string             $label               Human-readable label.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $version,
		public readonly string $system_instructions,
		public readonly array $constraints,
		public readonly string $label = ''
	) {
	}

	/**
	 * Array shape for diagnostics / settings.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'                  => $this->id,
			'version'             => $this->version,
			'system_instructions' => $this->system_instructions,
			'constraints'         => $this->constraints,
			'label'               => $this->label,
		);
	}
}
