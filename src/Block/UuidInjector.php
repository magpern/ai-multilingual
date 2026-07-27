<?php
/**
 * Strategy F UUID persistence on parsed block trees.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

/**
 * Assigns and validates persistent block UUIDs on eligible leaves.
 *
 * Duplicate repair is limited to detection and reporting in F2; full first-wins
 * repair ships in F3.
 */
final class UuidInjector {

	/**
	 * @param BlockRegistry       $registry Block eligibility policy.
	 * @param BlockIdentityLogger $logger   Structured event logger.
	 */
	public function __construct(
		private BlockRegistry $registry,
		private BlockIdentityLogger $logger,
	) {
	}

	/**
	 * Injects UUIDs into serialized block content when needed.
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

		if ( ! $result->changed ) {
			return new InjectResult( $content, false, $result->stats, $result->duplicates, $result->events );
		}

		return new InjectResult(
			serialize_blocks( $blocks ),
			true,
			$result->stats,
			$result->duplicates,
			$result->events
		);
	}

	/**
	 * Injects UUIDs into a parsed block tree.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed block tree (by reference).
	 */
	public function inject_blocks( array &$blocks ): InjectResult {
		$stats      = array(
			'uuid_created'            => 0,
			'uuid_preserved'          => 0,
			'uuid_replaced_invalid'   => 0,
			'uuid_duplicate_detected' => 0,
		);
		$events     = array();
		$seen_valid = array();
		$changed    = false;

		( new BlockTreeWalker() )->walk(
			$blocks,
			function ( array &$block ) use ( &$stats, &$events, &$seen_valid, &$changed ): void {
				if ( ! $this->registry->is_eligible( $block ) ) {
					return;
				}

				if ( ! is_array( $block['attrs'] ?? null ) ) {
					$block['attrs'] = array();
				}

				$attr_name = Contract::ATTR_NAME;
				$old_uuid  = isset( $block['attrs'][ $attr_name ] )
					? (string) $block['attrs'][ $attr_name ]
					: '';
				$uuid      = $old_uuid;
				$event     = null;
				$context   = array(
					'block_name' => (string) $block['blockName'],
				);

				if ( '' === $uuid ) {
					$uuid  = UuidGenerator::v4();
					$event = BlockIdentityLogger::EVENT_UUID_CREATED;
					++$stats['uuid_created'];
					$changed = true;
				} elseif ( ! UuidValidator::is_valid_non_empty( $uuid ) ) {
					$uuid  = UuidGenerator::v4();
					$event = BlockIdentityLogger::EVENT_UUID_REPLACED_INVALID;
					++$stats['uuid_replaced_invalid'];
					$changed = true;
				} elseif ( isset( $seen_valid[ $uuid ] ) ) {
					$event = BlockIdentityLogger::EVENT_UUID_DUPLICATE;
					++$stats['uuid_duplicate_detected'];
					$context['uuid'] = $uuid;
				} else {
					$event = BlockIdentityLogger::EVENT_UUID_PRESERVED;
					++$stats['uuid_preserved'];
					$context['uuid'] = $uuid;
				}

				if ( $uuid !== $old_uuid ) {
					$block['attrs'][ $attr_name ] = $uuid;
				}

				if ( UuidValidator::is_valid_non_empty( $uuid ) ) {
					$seen_valid[ $uuid ] = true;
				}

				if ( null !== $event ) {
					$events[] = array(
						'event'   => $event,
						'context' => $context,
					);
					$this->logger->log( $event, $context );
				}
			}
		);

		$duplicates = $this->duplicate_map( $blocks );

		return new InjectResult( '', $changed, $stats, $duplicates, $events );
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks Parsed block tree.
	 * @return array<string, int>
	 */
	private function duplicate_map( array $blocks ): array {
		$counts = array();

		( new BlockTreeWalker() )->walk(
			$blocks,
			static function ( array &$block ) use ( &$counts ): void {
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
