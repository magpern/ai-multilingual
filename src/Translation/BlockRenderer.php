<?php
/**
 * Strategy F block renderer proof.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation;

use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockRenderLogger;
use AIMultilingual\Block\BlockTreeWalker;
use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Block\UuidValidator;

/**
 * Rebuilds supported Gutenberg blocks from an explicit translation map.
 *
 * Proof-only: no Store lookup, no WordPress render hooks, no frontend output.
 */
final class BlockRenderer {

	/**
	 * Builds the block renderer.
	 *
	 * @param AdapterRegistry   $adapters Adapter lookup.
	 * @param BlockRenderLogger $logger   Structured render logger.
	 */
	public function __construct(
		private AdapterRegistry $adapters,
		private BlockRenderLogger $logger,
	) {
	}

	/**
	 * Applies translations to a parsed block tree.
	 *
	 * @param array<int, array<string, mixed>> $blocks       Parsed block tree (by reference).
	 * @param array<string, string>            $translations Segment key to translated content.
	 */
	public function render( array &$blocks, array $translations ): RenderResult {
		if ( array() === $translations ) {
			return new RenderResult( $blocks, false );
		}

		$events  = array();
		$changed = false;

		( new BlockTreeWalker() )->walk(
			$blocks,
			function ( array &$block ) use ( &$translations, &$events, &$changed ): void {
				$name = (string) ( $block['blockName'] ?? '' );
				if ( '' === $name ) {
					return;
				}

				$adapter = $this->adapters->get( $name );
				if ( null === $adapter ) {
					$inner = $block['innerBlocks'] ?? array();
					if ( ! is_array( $inner ) || array() === $inner ) {
						$this->record_event(
							BlockRenderLogger::EVENT_UNSUPPORTED_BLOCK,
							array( 'block_name' => $name ),
							$events
						);
					}

					return;
				}

				if ( ! $adapter->is_translatable_instance( $block ) ) {
					return;
				}

				$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
				$uuid  = isset( $attrs[ Contract::ATTR_NAME ] )
					? (string) $attrs[ Contract::ATTR_NAME ]
					: '';

				if ( ! UuidValidator::is_valid_non_empty( $uuid ) ) {
					return;
				}

				foreach ( $adapter->get_supported_fields() as $field_id ) {
					$segment_key = SegmentKey::build( $uuid, $field_id );

					if ( ! array_key_exists( $segment_key, $translations ) ) {
						$this->record_event(
							BlockRenderLogger::EVENT_TRANSLATION_MISSING,
							array(
								'block_name'  => $name,
								'field'       => $field_id,
								'segment_key' => $segment_key,
							),
							$events
						);
						continue;
					}

					$translated = (string) $translations[ $segment_key ];
					if ( '' === $translated ) {
						$this->record_event(
							BlockRenderLogger::EVENT_TRANSLATION_MISSING,
							array(
								'block_name'  => $name,
								'field'       => $field_id,
								'segment_key' => $segment_key,
								'reason'      => 'empty_translation',
							),
							$events
						);
						continue;
					}

					$before_html = (string) ( $block['innerHTML'] ?? '' );
					$block       = $adapter->apply_translation( $block, $field_id, $translated );

					if ( (string) ( $block['innerHTML'] ?? '' ) !== $before_html ) {
						$changed = true;
						$this->record_event(
							BlockRenderLogger::EVENT_BLOCK_RENDERED,
							array(
								'block_name'  => $name,
								'field'       => $field_id,
								'segment_key' => $segment_key,
							),
							$events
						);
					}
				}
			}
		);

		return new RenderResult( $blocks, $changed, $events );
	}

	/**
	 * Applies translations to serialized block content.
	 *
	 * @param string                $content      Serialized post content.
	 * @param array<string, string> $translations Segment key to translated content.
	 */
	public function render_content( string $content, array $translations ): RenderResult {
		if ( array() === $translations ) {
			return new RenderResult( array(), false, array(), $content );
		}

		if ( ! function_exists( 'parse_blocks' ) || ! function_exists( 'serialize_blocks' ) || ! function_exists( 'has_blocks' ) ) {
			return new RenderResult( array(), false, array(), $content );
		}

		if ( ! has_blocks( $content ) ) {
			return new RenderResult( array(), false, array(), $content );
		}

		$blocks = parse_blocks( $content );
		$result = $this->render( $blocks, $translations );

		if ( ! $result->changed ) {
			return new RenderResult( $blocks, false, $result->events, $content );
		}

		return new RenderResult( $blocks, true, $result->events, serialize_blocks( $blocks ) );
	}

	/**
	 * Records one structured event in the result and logger.
	 *
	 * @param string                     $event  Event name.
	 * @param array<string, mixed>       $context Event context.
	 * @param list<array<string, mixed>> $events Structured events.
	 */
	private function record_event( string $event, array $context, array &$events ): void {
		$events[] = array(
			'event'   => $event,
			'context' => $context,
		);
		$this->logger->log( $event, $context );
	}
}
