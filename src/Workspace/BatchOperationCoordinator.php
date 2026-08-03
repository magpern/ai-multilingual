<?php
/**
 * Bulk save and translate iteration for the workspace.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace;

use WP_Error;
use WP_Post;

/**
 * Owns bulk iteration, caps, and partial-result aggregation.
 */
final class BatchOperationCoordinator {

	public const BATCH_LIMIT = 50;

	/**
	 * Injected dependency.
	 *
	 * @var WorkspaceService
	 */
	private WorkspaceService $workspace;

	/**
	 * Injected dependency.
	 *
	 * @var TranslationService
	 */
	private TranslationService $translation;

	/**
	 * Builds the collaborator.
	 *
	 * @param WorkspaceService   $workspace   Workspace command facade.
	 * @param TranslationService $translation Auto-translate service.
	 */
	public function __construct( WorkspaceService $workspace, TranslationService $translation ) {
		$this->workspace   = $workspace;
		$this->translation = $translation;
	}

	/**
	 * Saves multiple segments with per-item optimistic locking.
	 *
	 * @param WP_Post                          $post        Canonical post.
	 * @param int                              $language_id Target language id.
	 * @param array<int, array<string, mixed>> $items       Save payloads.
	 * @return array<string, mixed>|WP_Error
	 */
	public function save_batch( WP_Post $post, int $language_id, array $items ) {
		if ( count( $items ) > self::BATCH_LIMIT ) {
			return new WP_Error(
				'aiml_batch_too_large',
				sprintf(
					/* translators: %d: maximum batch size */
					__( 'Batch size exceeds the limit of %d segments.', 'ai-multilingual' ),
					self::BATCH_LIMIT
				),
				array( 'status' => 422 )
			);
		}

		$succeeded = array();
		$failed    = array();

		foreach ( $items as $item ) {
			$key = (string) ( $item['segment_key'] ?? '' );
			if ( '' === $key ) {
				$failed[] = array(
					'segment_key' => '',
					'code'        => 'aiml_invalid_segment',
					'message'     => __( 'Segment key is required.', 'ai-multilingual' ),
				);
				continue;
			}

			try {
				$succeeded[] = $this->workspace->save_segment(
					$post,
					$language_id,
					$key,
					(string) ( $item['translated_text'] ?? '' ),
					(string) ( $item['source_hash'] ?? '' ),
					(string) ( $item['status'] ?? '' )
				);
			} catch ( WorkspaceConflictException $conflict ) {
				$failed[] = array(
					'segment_key' => $key,
					'code'        => 'aiml_source_hash_mismatch',
					'message'     => $conflict->getMessage(),
					'segments'    => $conflict->segments(),
				);
			} catch ( \InvalidArgumentException $exception ) {
				$failed[] = array(
					'segment_key' => $key,
					'code'        => 'aiml_invalid_segment',
					'message'     => $exception->getMessage(),
				);
			}
		}

		return array(
			'status'   => array() === $failed ? 'completed' : 'partial',
			'segments' => $succeeded,
			'errors'   => $failed,
		);
	}

	/**
	 * Translates multiple segments via TranslationService.
	 *
	 * @param WP_Post            $post         Canonical post.
	 * @param int                $language_id  Target language id.
	 * @param array<int, string> $segment_keys Segment keys.
	 * @return array<string, mixed>|WP_Error
	 */
	public function translate_batch( WP_Post $post, int $language_id, array $segment_keys ) {
		if ( count( $segment_keys ) > self::BATCH_LIMIT ) {
			return new WP_Error(
				'aiml_batch_too_large',
				sprintf(
					/* translators: %d: maximum batch size */
					__( 'Batch size exceeds the limit of %d segments.', 'ai-multilingual' ),
					self::BATCH_LIMIT
				),
				array( 'status' => 422 )
			);
		}

		$succeeded = array();
		$failed    = array();

		foreach ( $segment_keys as $segment_key ) {
			$key = (string) $segment_key;
			if ( '' === $key ) {
				$failed[] = array(
					'segment_key' => '',
					'code'        => 'aiml_invalid_segment',
					'message'     => __( 'Segment key is required.', 'ai-multilingual' ),
				);
				continue;
			}

			$result = $this->translation->translate_segment( $post, $language_id, $key );
			if ( $result instanceof WP_Error ) {
				$failed[] = array(
					'segment_key' => $key,
					'code'        => $result->get_error_code(),
					'message'     => $result->get_error_message(),
				);
				continue;
			}

			$succeeded[] = $result;
		}

		if ( array() === $failed ) {
			$status = 'completed';
		} elseif ( array() === $succeeded ) {
			$status = 'failed';
		} else {
			$status = 'partial';
		}

		return array(
			'status'   => $status,
			'job_id'   => null,
			'segments' => $succeeded,
			'errors'   => $failed,
		);
	}
}
