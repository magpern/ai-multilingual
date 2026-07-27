<?php
/**
 * Strategy F UUID persistence on parsed block trees.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

/**
 * Assigns, validates, and repairs persistent block UUIDs on eligible leaves.
 *
 * Same-document duplicate repair uses frozen first-wins policy in document order.
 */
final class UuidInjector {

	/**
	 * Builds the injector.
	 *
	 * @param BlockRegistry           $registry     Block eligibility policy.
	 * @param BlockIdentityLogger     $logger       Structured event logger.
	 * @param callable(): string|null $uuid_factory Optional UUID factory for tests.
	 * @throws \InvalidArgumentException When the UUID factory is not callable.
	 */
	public function __construct(
		private BlockRegistry $registry,
		private BlockIdentityLogger $logger,
		private $uuid_factory = null,
	) {
		if ( null !== $this->uuid_factory && ! is_callable( $this->uuid_factory ) ) {
			throw new \InvalidArgumentException( 'UUID factory must be callable.' );
		}
	}

	/**
	 * Injects and repairs UUIDs in serialized block content atomically.
	 *
	 * @param string $content Serialized block post content.
	 */
	public function inject_content( string $content ): InjectResult {
		if ( ! function_exists( 'parse_blocks' ) || ! function_exists( 'serialize_blocks' ) ) {
			return new InjectResult( $content, false );
		}

		if ( ! has_blocks( $content ) ) {
			return new InjectResult( $content, false );
		}

		$blocks = parse_blocks( $content );
		$result = $this->inject_blocks( $blocks );

		if ( ! $result->successful ) {
			return new InjectResult(
				$content,
				false,
				$result->stats,
				$result->duplicates,
				$result->events,
				false,
				$result->failure_reason,
				$result->replacements
			);
		}

		if ( ! $result->changed ) {
			return new InjectResult(
				$content,
				false,
				$result->stats,
				$result->duplicates,
				$result->events,
				true,
				null,
				$result->replacements
			);
		}

		return new InjectResult(
			serialize_blocks( $blocks ),
			true,
			$result->stats,
			$result->duplicates,
			$result->events,
			true,
			null,
			$result->replacements
		);
	}

	/**
	 * Injects and repairs UUIDs in a parsed block tree.
	 *
	 * On failure the original tree is restored unchanged.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed block tree (by reference).
	 */
	public function inject_blocks( array &$blocks ): InjectResult {
		$snapshot = self::deep_copy_blocks( $blocks );

		$stats        = InjectResult::default_stats();
		$events       = array();
		$replacements = array();
		$claimed      = array();
		$changed      = false;

		try {
			( new BlockTreeWalker() )->walk(
				$blocks,
				function ( array &$block ) use ( &$stats, &$events, &$replacements, &$claimed, &$changed ): void {
					if ( ! $this->registry->is_eligible( $block ) ) {
						return;
					}

					if ( ! is_array( $block['attrs'] ?? null ) ) {
						$block['attrs'] = array();
					}

					$outcome = $this->repair_eligible_block( $block, $claimed, $stats, $events, $replacements );

					if ( null === $outcome ) {
						throw new UuidRepairAbortException( 'uuid_claim_exhausted' );
					}

					if ( $outcome['changed'] ) {
						$changed = true;
					}
				}
			);
		} catch ( UuidRepairAbortException $exception ) {
			$blocks = $snapshot;

			$context  = array(
				'failure_reason' => $exception->getMessage(),
			);
			$events[] = array(
				'event'   => BlockIdentityLogger::EVENT_UUID_REPAIR_FAILED,
				'context' => $context,
			);
			$this->logger->log( BlockIdentityLogger::EVENT_UUID_REPAIR_FAILED, $context );

			return new InjectResult(
				'',
				false,
				$stats,
				array(),
				$events,
				false,
				$exception->getMessage(),
				$replacements
			);
		}

		$duplicates = $this->duplicate_map( $blocks );

		if ( $stats['uuid_duplicate_repaired'] > 0 ) {
			$complete = array(
				'repaired_count' => $stats['uuid_duplicate_repaired'],
			);
			$events[] = array(
				'event'   => BlockIdentityLogger::EVENT_UUID_REPAIR_COMPLETE,
				'context' => $complete,
			);
			$this->logger->log( BlockIdentityLogger::EVENT_UUID_REPAIR_COMPLETE, $complete );
		}

		return new InjectResult(
			'',
			$changed,
			$stats,
			$duplicates,
			$events,
			true,
			null,
			$replacements
		);
	}

	/**
	 * Repairs one eligible block using first-wins duplicate policy.
	 *
	 * @param array<string, mixed>       $block        Parsed block (by reference).
	 * @param array<string, bool>        $claimed      UUIDs already claimed in document order.
	 * @param array<string, int>         $stats        Diagnostic counters.
	 * @param list<array<string, mixed>> $events       Structured events.
	 * @param array<string, string>      $replacements Old to new UUID map.
	 * @return array{changed: bool}|null Null when unique UUID generation fails.
	 */
	private function repair_eligible_block(
		array &$block,
		array &$claimed,
		array &$stats,
		array &$events,
		array &$replacements
	): ?array {
		$attr_name = Contract::ATTR_NAME;
		$old_uuid  = isset( $block['attrs'][ $attr_name ] )
			? (string) $block['attrs'][ $attr_name ]
			: '';
		$uuid      = $old_uuid;
		$changed   = false;
		$context   = array(
			'block_name' => (string) $block['blockName'],
		);

		if ( '' === $uuid ) {
			$uuid = $this->claim_uuid( $claimed, $context, $events );
			if ( null === $uuid ) {
				return null;
			}

			++$stats['uuid_created'];
			$changed = true;
			$this->record_event( BlockIdentityLogger::EVENT_UUID_CREATED, $context, $events );
		} elseif ( ! UuidValidator::is_valid_non_empty( $uuid ) ) {
			$uuid = $this->claim_uuid( $claimed, $context, $events );
			if ( null === $uuid ) {
				return null;
			}

			++$stats['uuid_replaced_invalid'];
			$changed = true;
			$this->record_event( BlockIdentityLogger::EVENT_UUID_REPLACED_INVALID, $context, $events );
		} elseif ( isset( $claimed[ $uuid ] ) ) {
			$duplicate_uuid            = $uuid;
			$context['duplicate_uuid'] = $duplicate_uuid;
			++$stats['uuid_duplicate_detected'];
			$this->record_event( BlockIdentityLogger::EVENT_UUID_DUPLICATE_DETECTED, $context, $events );

			$uuid = $this->claim_uuid( $claimed, $context, $events );
			if ( null === $uuid ) {
				return null;
			}

			$replacements[ $duplicate_uuid ] = $uuid;
			++$stats['uuid_duplicate_repaired'];
			$changed = true;

			$repair_context = array(
				'block_name'       => (string) $block['blockName'],
				'duplicate_uuid'   => $duplicate_uuid,
				'replacement_uuid' => $uuid,
			);
			$this->record_event( BlockIdentityLogger::EVENT_UUID_DUPLICATE_REPAIRED, $repair_context, $events );
		} else {
			$claimed[ $uuid ] = true;
			$context['uuid']  = $uuid;
			++$stats['uuid_preserved'];
			$this->record_event( BlockIdentityLogger::EVENT_UUID_PRESERVED, $context, $events );
		}

		if ( $uuid !== $old_uuid ) {
			$block['attrs'][ $attr_name ] = $uuid;
		}

		return array(
			'changed' => $changed,
		);
	}

	/**
	 * Claims a document-unique UUID and logs generation collisions when they occur.
	 *
	 * @param array<string, bool>        $claimed UUIDs already assigned.
	 * @param array<string, mixed>       $context Base log context.
	 * @param list<array<string, mixed>> $events  Structured events.
	 */
	private function claim_uuid( array &$claimed, array $context, array &$events ): ?string {
		$factory = $this->uuid_factory ?? static fn (): string => UuidGenerator::v4();

		for ( $try = 1; $try <= UuidGenerator::MAX_CLAIM_ATTEMPTS; $try++ ) {
			$uuid = $factory();

			if ( ! UuidValidator::is_valid_non_empty( $uuid ) ) {
				continue;
			}

			if ( isset( $claimed[ $uuid ] ) ) {
				$collision = array_merge(
					$context,
					array(
						'retry_count' => $try,
					)
				);
				$this->record_event( BlockIdentityLogger::EVENT_UUID_GENERATION_COLLISION, $collision, $events );
				continue;
			}

			$claimed[ $uuid ] = true;

			return $uuid;
		}

		return null;
	}

	/**
	 * Records one structured event in the result and logger.
	 *
	 * @param string                     $event   Event name.
	 * @param array<string, mixed>       $context Event context.
	 * @param list<array<string, mixed>> $events  Structured events.
	 */
	private function record_event( string $event, array $context, array &$events ): void {
		$events[] = array(
			'event'   => $event,
			'context' => $context,
		);
		$this->logger->log( $event, $context );
	}

	/**
	 * Deep-copies a parsed block tree for atomic rollback.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed block tree.
	 * @return array<int, array<string, mixed>>
	 */
	private static function deep_copy_blocks( array $blocks ): array {
		if ( function_exists( 'wp_json_encode' ) ) {
			$json = wp_json_encode( $blocks );
			if ( is_string( $json ) ) {
				$copy = json_decode( $json, true );
				if ( is_array( $copy ) ) {
					return $copy;
				}
			}
		}

		// Deep-copy fallback for unit tests without WordPress JSON helpers.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- trusted in-memory block arrays only.
		$copy = unserialize( serialize( $blocks ), array( 'allowed_classes' => false ) );

		return $copy;
	}

	/**
	 * Counts remaining duplicate UUIDs among eligible blocks.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed block tree.
	 * @return array<string, int>
	 */
	private function duplicate_map( array $blocks ): array {
		$counts = array();

		( new BlockTreeWalker() )->walk(
			$blocks,
			function ( array &$block ) use ( &$counts ): void {
				if ( ! $this->registry->is_eligible( $block ) ) {
					return;
				}

				$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
				$uuid  = isset( $attrs[ Contract::ATTR_NAME ] )
					? (string) $attrs[ Contract::ATTR_NAME ]
					: '';

				if ( ! UuidValidator::is_valid_non_empty( $uuid ) ) {
					return;
				}

				if ( ! isset( $counts[ $uuid ] ) ) {
					$counts[ $uuid ] = 0;
				}

				++$counts[ $uuid ];
			}
		);

		$duplicates = array();
		foreach ( $counts as $uuid => $count ) {
			if ( $count > 1 ) {
				$duplicates[ $uuid ] = $count;
			}
		}

		return $duplicates;
	}
}
