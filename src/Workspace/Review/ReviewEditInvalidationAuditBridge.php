<?php
/**
 * Bridges the existing Store invalidate-on-edit hook into the stable
 * `aiml_review_audit` event stream (ADR-0015 §5.4 / §12).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\Review;

use AIMultilingual\Translation\Store;

/**
 * Listens to `aiml_review_invalidated_by_edit` (fired by {@see Store}
 * since R2) and re-emits it as a `review_invalidated_by_edit` event on the
 * `aiml_review_audit` channel with a safe payload — without changing the
 * existing hook's signature or call sites.
 */
final class ReviewEditInvalidationAuditBridge {

	/**
	 * Audit logger.
	 *
	 * @var ReviewAuditLogger
	 */
	private ReviewAuditLogger $audit;

	/**
	 * Builds the bridge.
	 *
	 * @param ReviewAuditLogger|null $audit Audit logger.
	 */
	public function __construct( ?ReviewAuditLogger $audit = null ) {
		$this->audit = $audit ?? new ReviewAuditLogger();
	}

	/**
	 * Registers the bridging hook.
	 */
	public function register(): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}

		add_action( 'aiml_review_invalidated_by_edit', array( $this, 'on_invalidated_by_edit' ), 10, 5 );
	}

	/**
	 * Forwards one invalidate-on-edit occurrence to the audit channel.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source object id.
	 * @param int    $language_id Language id.
	 * @param string $segment_key Segment key.
	 * @param string $old_review  Previous review_status.
	 */
	public function on_invalidated_by_edit(
		string $source_type,
		int $source_id,
		int $language_id,
		string $segment_key,
		string $old_review
	): void {
		$payload = array(
			'source_type'       => $source_type,
			'source_id'         => $source_id,
			'segment_key'       => $segment_key,
			'language_id'       => $language_id,
			'old_review_status' => $old_review,
			'new_review_status' => Store::REVIEW_NOT_SUBMITTED,
			'source_surface'    => 'workspace_save',
		);

		if ( Store::SOURCE_POST === $source_type ) {
			$payload['post_id'] = $source_id;
		}

		$this->audit->log( ReviewAuditEvents::INVALIDATED_BY_EDIT, $payload );
	}
}
