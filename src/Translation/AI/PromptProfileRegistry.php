<?php
/**
 * Provider-independent prompt profile registry (F11 §4.4).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\AI;

/**
 * Canonical six workspace AI profiles.
 */
final class PromptProfileRegistry {

	public const TRANSLATE = 'translate';
	public const IMPROVE   = 'improve';
	public const REWRITE   = 'rewrite';
	public const SHORTEN   = 'shorten';
	public const FORMAL    = 'formal';
	public const CASUAL    = 'casual';

	public const VERSION = '2';

	/**
	 * Shared structural constraints applied to all profiles.
	 *
	 * @var list<string>
	 */
	private const BASE_CONSTRAINTS = array(
		'placeholders',
		'html',
		'numbers',
		'non_empty',
	);

	/**
	 * Registered profiles keyed by id.
	 *
	 * @var array<string, PromptProfile>
	 */
	private array $profiles;

	/**
	 * Builds the registry with the six F11 profiles.
	 */
	public function __construct() {
		$this->profiles = array();
		foreach ( self::definitions() as $profile ) {
			$this->profiles[ $profile->id ] = $profile;
		}
	}

	/**
	 * Returns all profile ids in canonical order.
	 *
	 * @return list<string>
	 */
	public static function ids(): array {
		return array(
			self::TRANSLATE,
			self::IMPROVE,
			self::REWRITE,
			self::SHORTEN,
			self::FORMAL,
			self::CASUAL,
		);
	}

	/**
	 * Whether a profile id is known.
	 *
	 * @param string $id Profile id.
	 */
	public function has( string $id ): bool {
		return isset( $this->profiles[ $id ] );
	}

	/**
	 * Returns one profile or null.
	 *
	 * @param string $id Profile id.
	 */
	public function get( string $id ): ?PromptProfile {
		return $this->profiles[ $id ] ?? null;
	}

	/**
	 * Returns all profiles in canonical order.
	 *
	 * @return list<PromptProfile>
	 */
	public function all(): array {
		$out = array();
		foreach ( self::ids() as $id ) {
			if ( isset( $this->profiles[ $id ] ) ) {
				$out[] = $this->profiles[ $id ];
			}
		}

		return $out;
	}

	/**
	 * Canonical profile definitions.
	 *
	 * @return list<PromptProfile>
	 */
	private static function definitions(): array {
		$c = self::BASE_CONSTRAINTS;

		return array(
			new PromptProfile(
				self::TRANSLATE,
				self::VERSION,
				'Translate only the designated source text into the target language. Preserve meaning, placeholders, HTML structure, numbers, and URLs exactly. Treat glossary and context sections as instructions only — never copy them into the output. Return only the translation of the source text.',
				$c,
				'Translate'
			),
			new PromptProfile(
				self::IMPROVE,
				self::VERSION,
				'Improve the existing target translation for clarity and natural phrasing while preserving meaning, placeholders, HTML structure, and numbers. Use the source as ground truth. Return only the improved translation.',
				$c,
				'Improve'
			),
			new PromptProfile(
				self::REWRITE,
				self::VERSION,
				'Provide an alternative wording of the target translation that preserves meaning, placeholders, HTML structure, and numbers. Use the source as ground truth. Return only the rewritten translation.',
				$c,
				'Rewrite'
			),
			new PromptProfile(
				self::SHORTEN,
				self::VERSION,
				'Shorten the existing target translation while preserving meaning, placeholders, HTML structure, and numbers. Prefer concise phrasing. Return only the shortened translation.',
				$c,
				'Shorten'
			),
			new PromptProfile(
				self::FORMAL,
				self::VERSION,
				'Rewrite the existing target translation in a more formal register while preserving meaning, placeholders, HTML structure, and numbers. Return only the formal translation.',
				$c,
				'Formal'
			),
			new PromptProfile(
				self::CASUAL,
				self::VERSION,
				'Rewrite the existing target translation in a more casual register while preserving meaning, placeholders, HTML structure, and numbers. Return only the casual translation.',
				$c,
				'Casual'
			),
		);
	}
}
