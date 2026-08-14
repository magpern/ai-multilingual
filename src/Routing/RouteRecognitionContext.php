<?php
/**
 * Request-local route recognition facts (MSEO.2 B1).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Routing;

/**
 * Immutable value object owned by Router for one PHP request.
 */
final class RouteRecognitionContext {

	public const KIND_NONE = 'NONE';

	public const KIND_CURRENT_LOCALIZED = 'CURRENT_LOCALIZED';

	public const KIND_HISTORICAL_LOCALIZED = 'HISTORICAL_LOCALIZED';

	public const KIND_SOURCE_PATH = 'SOURCE_PATH';

	/**
	 * Recognition kind.
	 *
	 * @var string
	 */
	private string $kind;

	/**
	 * Original prefixed path before substitution.
	 *
	 * @var string|null
	 */
	private ?string $original_prefixed;

	/**
	 * Original unprefixed path after language strip.
	 *
	 * @var string|null
	 */
	private ?string $original_unprefixed;

	/**
	 * Resolved language id.
	 *
	 * @var int|null
	 */
	private ?int $language_id;

	/**
	 * Store source type.
	 *
	 * @var string|null
	 */
	private ?string $source_type;

	/**
	 * Source object id.
	 *
	 * @var int|null
	 */
	private ?int $source_id;

	/**
	 * Active route id when recognized.
	 *
	 * @var int|null
	 */
	private ?int $route_id;

	/**
	 * History row id when recognized.
	 *
	 * @var int|null
	 */
	private ?int $history_id;

	/**
	 * Original query string (without leading ?).
	 *
	 * @var string|null
	 */
	private ?string $query;

	/**
	 * Builds an empty NONE context.
	 */
	public static function none(): self {
		return new self( self::KIND_NONE );
	}

	/**
	 * Creates a recognition context.
	 *
	 * @param string      $kind                 Recognition kind.
	 * @param string|null $original_prefixed    Prefixed request path.
	 * @param string|null $original_unprefixed  Unprefixed path after language strip.
	 * @param int|null    $language_id          Language id.
	 * @param string|null $source_type          Store source type.
	 * @param int|null    $source_id            Source object id.
	 * @param int|null    $route_id             Active route id.
	 * @param int|null    $history_id           History row id.
	 * @param string|null $query                Query string without ?.
	 */
	public function __construct(
		string $kind,
		?string $original_prefixed = null,
		?string $original_unprefixed = null,
		?int $language_id = null,
		?string $source_type = null,
		?int $source_id = null,
		?int $route_id = null,
		?int $history_id = null,
		?string $query = null
	) {
		$this->kind                = $kind;
		$this->original_prefixed   = $original_prefixed;
		$this->original_unprefixed = $original_unprefixed;
		$this->language_id         = $language_id;
		$this->source_type         = $source_type;
		$this->source_id           = $source_id;
		$this->route_id            = $route_id;
		$this->history_id          = $history_id;
		$this->query               = $query;
	}

	/**
	 * Recognition kind.
	 */
	public function kind(): string {
		return $this->kind;
	}

	/**
	 * Original prefixed path.
	 */
	public function original_prefixed(): ?string {
		return $this->original_prefixed;
	}

	/**
	 * Original unprefixed path.
	 */
	public function original_unprefixed(): ?string {
		return $this->original_unprefixed;
	}

	/**
	 * Language id when recognized.
	 */
	public function language_id(): ?int {
		return $this->language_id;
	}

	/**
	 * Source type when recognized.
	 */
	public function source_type(): ?string {
		return $this->source_type;
	}

	/**
	 * Source id when recognized.
	 */
	public function source_id(): ?int {
		return $this->source_id;
	}

	/**
	 * Route id when recognized.
	 */
	public function route_id(): ?int {
		return $this->route_id;
	}

	/**
	 * History id when recognized.
	 */
	public function history_id(): ?int {
		return $this->history_id;
	}

	/**
	 * Original query string without leading ?.
	 */
	public function query(): ?string {
		return $this->query;
	}

	/**
	 * Whether AIML recognized an inbound localized or historical path.
	 */
	public function is_localized_recognition(): bool {
		return self::KIND_CURRENT_LOCALIZED === $this->kind
			|| self::KIND_HISTORICAL_LOCALIZED === $this->kind;
	}
}
