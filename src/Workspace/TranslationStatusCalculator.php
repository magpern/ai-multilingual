<?php
/**
 * Page-level translation status aggregation.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace;

use AIMultilingual\Translation\Store;
use WP_Post;

/**
 * Derives page-level status from Store summaries and post state.
 */
final class TranslationStatusCalculator {

	/**
	 * Injected dependency.
	 *
	 * @var Store
	 */
	private Store $store;

	/**
	 * Builds the collaborator.
	 *
	 * @param Store $store Segment store.
	 */
	public function __construct( Store $store ) {
		$this->store = $store;
	}

	/**
	 * Summary counts for one post and language from merged segment DTOs.
	 *
	 * @param WP_Post                          $post        Canonical post.
	 * @param int                              $language_id Target language id.
	 * @param array<int, array<string, mixed>> $segments    Assembled segment DTOs.
	 * @return array<string, mixed>
	 */
	public function for_segments( WP_Post $post, int $language_id, array $segments ): array {
		$missing    = 0;
		$stale      = 0;
		$translated = 0;
		$reviewed   = 0;

		foreach ( $segments as $row ) {
			$status = (string) ( $row['status'] ?? Store::STATUS_MISSING );
			if ( Store::STATUS_MISSING === $status ) {
				++$missing;
				continue;
			}

			if ( ! empty( $row['is_stale'] ) ) {
				++$stale;
			}

			if ( Store::STATUS_REVIEWED === $status ) {
				++$reviewed;
			} elseif ( Store::STATUS_MISSING !== $status && Store::STATUS_IGNORED !== $status ) {
				++$translated;
			}
		}

		$total = count( $segments );

		return array(
			'post_id'          => (int) $post->ID,
			'post_title'       => (string) $post->post_title,
			'post_status'      => (string) $post->post_status,
			'post_type'        => (string) $post->post_type,
			'language_id'      => $language_id,
			'total_segments'   => $total,
			'missing_count'    => $missing,
			'stale_count'      => $stale,
			'translated_count' => $translated,
			'reviewed_count'   => $reviewed,
			'overall_state'    => $this->overall_state( $total, $missing, $stale, $reviewed ),
			'is_published'     => 'publish' === $post->post_status,
			'edit_link'        => (string) get_edit_post_link( (int) $post->ID, 'raw' ),
		);
	}

	/**
	 * Summary counts for one post and language.
	 *
	 * @param WP_Post $post        Canonical post.
	 * @param int     $language_id Target language id.
	 * @return array<string, mixed>
	 */
	public function for_post( WP_Post $post, int $language_id ): array {
		$segments = $this->store->load_object( Store::SOURCE_POST, (int) $post->ID, $language_id );

		$missing    = 0;
		$stale      = 0;
		$translated = 0;
		$reviewed   = 0;

		foreach ( $segments as $row ) {
			$status = (string) ( $row->status ?? Store::STATUS_MISSING );
			if ( Store::STATUS_MISSING === $status ) {
				++$missing;
				continue;
			}

			if ( (int) ( $row->is_stale ?? 0 ) ) {
				++$stale;
			}

			if ( Store::STATUS_REVIEWED === $status ) {
				++$reviewed;
			} elseif ( Store::STATUS_MISSING !== $status && Store::STATUS_IGNORED !== $status ) {
				++$translated;
			}
		}

		$total = count( $segments );

		return array(
			'post_id'          => (int) $post->ID,
			'post_title'       => (string) $post->post_title,
			'post_status'      => (string) $post->post_status,
			'post_type'        => (string) $post->post_type,
			'language_id'      => $language_id,
			'total_segments'   => $total,
			'missing_count'    => $missing,
			'stale_count'      => $stale,
			'translated_count' => $translated,
			'reviewed_count'   => $reviewed,
			'overall_state'    => $this->overall_state( $total, $missing, $stale, $reviewed ),
			'is_published'     => 'publish' === $post->post_status,
			'edit_link'        => (string) get_edit_post_link( (int) $post->ID, 'raw' ),
		);
	}

	/**
	 * Lightweight per-language summary for list views.
	 *
	 * @param int $post_id Post id.
	 * @return array<int, array{total: int, stale: int}>
	 */
	public function summaries_for_post( int $post_id ): array {
		return $this->store->summary_for_object( Store::SOURCE_POST, $post_id );
	}

	/**
	 * Builds the collaborator.
	 *
	 * @param int $total     Segment count.
	 * @param int $missing   Missing count.
	 * @param int $stale     Stale count.
	 * @param int $reviewed  Reviewed count.
	 */
	private function overall_state( int $total, int $missing, int $stale, int $reviewed ): string {
		if ( 0 === $total ) {
			return 'not_started';
		}

		if ( $stale > 0 ) {
			return 'stale';
		}

		if ( $missing === $total ) {
			return 'not_started';
		}

		if ( $reviewed === $total && 0 === $missing ) {
			return 'complete';
		}

		return 'in_progress';
	}
}
