<?php
/**
 * Spike S5 — Strategy F: persistent UUID attribute identity.
 *
 * Per docs/plans/AI_MULTILINGUAL_PLANNING.md § Strategies:
 *   Key shape: b:<uuid>:content
 *
 * THROWAWAY CODE. Branch spike/s5 only.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Strategy;

final class StrategyF {

	public const NAME = 'F';

	/**
	 * Inject UUIDs then extract eligible segments keyed by UUID.
	 *
	 * @return array{
	 *   segments: array<string, array{uuid: string, block_name: string, text: string}>,
	 *   content: string,
	 *   inject_stats: array<string, mixed>
	 * }
	 */
	public static function prepare( string $content ): array {
		$injected = UuidInjector::inject( $content );
		$stats    = $injected['stats'];
		$stats['regenerated_uuids'] = $injected['regenerated_uuids'];

		return array(
			'segments'     => self::extract_from_content( $injected['content'] ),
			'content'      => $injected['content'],
			'inject_stats' => $stats,
		);
	}

	/**
	 * @return array<string, array{uuid: string, block_name: string, text: string}>
	 */
	public static function extract_from_content( string $content ): array {
		$segments = array();

		foreach ( UuidBlockWalker::walk_eligible( $content ) as $block ) {
			$key = StrategyFContract::segment_key( $block['uuid'] );
			$segments[ $key ] = array(
				'uuid'       => $block['uuid'],
				'block_name' => $block['block_name'],
				'text'       => $block['text'],
			);
		}

		return $segments;
	}

	/**
	 * @return array<string, array{uuid: string, block_name: string, text: string}>
	 */
	public static function extract( string $content ): array {
		return self::prepare( $content )['segments'];
	}

	public static function segment_key( string $uuid ): string {
		return StrategyFContract::segment_key( $uuid );
	}
}
