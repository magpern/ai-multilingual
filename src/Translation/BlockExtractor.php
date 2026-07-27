<?php
/**
 * Strategy F block extraction.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation;

use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockExtractionLogger;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\BlockTreeWalker;
use AIMultilingual\Block\Contract;
use AIMultilingual\Block\ExtractedSegment;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Block\SourceNormalizer;
use AIMultilingual\Block\UuidValidator;
use WP_Post;

/**
 * Extracts stable block segments from parsed Gutenberg content.
 */
final class BlockExtractor {

	/**
	 * Builds the block extractor.
	 *
	 * @param AdapterRegistry       $adapters Adapter lookup.
	 * @param BlockRegistry         $registry Block eligibility policy.
	 * @param BlockExtractionLogger $logger   Structured extraction logger.
	 */
	public function __construct(
		private AdapterRegistry $adapters,
		private BlockRegistry $registry,
		private BlockExtractionLogger $logger,
	) {
	}

	/**
	 * Extracts block segments from a canonical post.
	 *
	 * @param WP_Post $post Canonical post.
	 * @return array<string, array<string, mixed>> Segments keyed by segment key.
	 */
	public function extract_post( WP_Post $post ): array {
		return $this->extract_content( (string) $post->post_content );
	}

	/**
	 * Extracts block segments from serialized block content.
	 *
	 * @param string $content Serialized post content.
	 * @return array<string, array<string, mixed>> Segments keyed by segment key.
	 */
	public function extract_content( string $content ): array {
		if ( ! function_exists( 'parse_blocks' ) || ! function_exists( 'has_blocks' ) || ! has_blocks( $content ) ) {
			return array();
		}

		return $this->extract_blocks( parse_blocks( $content ) );
	}

	/**
	 * Extracts block segments from a parsed block tree.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed block tree.
	 * @return array<string, array<string, mixed>> Segments keyed by segment key.
	 */
	public function extract_blocks( array $blocks ): array {
		$segments = array();
		$order    = 0;

		( new BlockTreeWalker() )->walk(
			$blocks,
			function ( array $block ) use ( &$segments, &$order ): void {
				$name = (string) ( $block['blockName'] ?? '' );

				if ( '' === $name || $this->registry->is_dynamic( $name ) ) {
					return;
				}

				$adapter = $this->adapters->get( $name );
				if ( null === $adapter ) {
					if ( $this->registry->is_supported( $name ) ) {
						$this->logger->log(
							BlockExtractionLogger::EVENT_ADAPTER_MISSING,
							array(
								'block_name' => $name,
							)
						);
					}

					return;
				}

				if ( ! $adapter->is_translatable_instance( $block ) ) {
					return;
				}

				$validation = $adapter->validate_block_structure( $block );
				if ( ! $validation->valid ) {
					return;
				}

				$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
				$uuid  = isset( $attrs[ Contract::ATTR_NAME ] )
					? (string) $attrs[ Contract::ATTR_NAME ]
					: '';

				if ( ! UuidValidator::is_valid_non_empty( $uuid ) ) {
					$this->logger->log(
						BlockExtractionLogger::EVENT_FIELD_SKIPPED,
						array(
							'block_name' => $name,
							'reason'     => 'missing_uuid',
						)
					);

					return;
				}

				foreach ( $adapter->extract_fields( $block ) as $field ) {
					if ( ! in_array( $field->field_id, $adapter->get_supported_fields(), true ) ) {
						$this->logger->log(
							BlockExtractionLogger::EVENT_FIELD_SKIPPED,
							array(
								'block_name' => $name,
								'field'      => $field->field_id,
								'reason'     => 'unsupported_field',
							)
						);
						continue;
					}

					if ( '' === trim( $field->source_text ) ) {
						$this->logger->log(
							BlockExtractionLogger::EVENT_FIELD_SKIPPED,
							array(
								'block_name' => $name,
								'field'      => $field->field_id,
								'reason'     => 'empty_source',
							)
						);
						continue;
					}

					$segment_key = SegmentKey::build( $uuid, $field->field_id );
					if ( isset( $segments[ $segment_key ] ) ) {
						$this->logger->log(
							BlockExtractionLogger::EVENT_FIELD_SKIPPED,
							array(
								'block_name'     => $name,
								'field'          => $field->field_id,
								'duplicate_uuid' => $uuid,
								'reason'         => 'duplicate_segment_key',
							)
						);
						continue;
					}

					$normalized = SourceNormalizer::normalize( $field->source_text, $field->text_format );
					$hash       = SourceNormalizer::source_hash( $field->source_text, $field->text_format );

					$segment = new ExtractedSegment(
						$segment_key,
						$field->field_id,
						$name,
						$uuid,
						$field->source_text,
						$normalized,
						$hash,
						$field->text_format,
						$order,
						array(
							'block_name' => $name,
						)
					);

					$segments[ $segment_key ] = $segment->to_sync_segment();
					++$order;

					$this->logger->log(
						BlockExtractionLogger::EVENT_BLOCK_EXTRACTED,
						array(
							'block_name'  => $name,
							'field'       => $field->field_id,
							'segment_key' => $segment_key,
						)
					);
				}
			}
		);

		return $segments;
	}
}
