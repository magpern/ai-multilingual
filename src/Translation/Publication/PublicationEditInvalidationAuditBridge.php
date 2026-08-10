<?php
/**
 * Bridges Store invalidate-on-edit into the publication audit stream (ADR-0020).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\Publication;

use AIMultilingual\Translation\Store;

/**
 * Listens to `aiml_publication_invalidated_by_edit` (fired by {@see Store})
 * and re-emits it as a `publication_invalidated_by_edit` event on the
 * `aiml_publication_audit` channel with a safe payload — without changing the
 * existing hook's signature or call sites.
 */
final class PublicationEditInvalidationAuditBridge {

	/**
	 * Audit logger.
	 *
	 * @var PublicationAuditLogger
	 */
	private PublicationAuditLogger $audit;

	/**
	 * Builds the bridge.
	 *
	 * @param PublicationAuditLogger|null $audit Audit logger.
	 */
	public function __construct( ?PublicationAuditLogger $audit = null ) {
		$this->audit = $audit ?? new PublicationAuditLogger();
	}

	/**
	 * Registers the bridging hook.
	 */
	public function register(): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}

		add_action( 'aiml_publication_invalidated_by_edit', array( $this, 'on_invalidated_by_edit' ), 10, 5 );
	}

	/**
	 * Forwards one invalidate-on-edit occurrence to the audit channel.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source object id.
	 * @param int    $language_id Language id.
	 * @param string $segment_key Segment key.
	 * @param string $old_publish Previous publish_status.
	 */
	public function on_invalidated_by_edit(
		string $source_type,
		int $source_id,
		int $language_id,
		string $segment_key,
		string $old_publish
	): void {
		$payload = array(
			'source_type'        => $source_type,
			'source_id'          => $source_id,
			'segment_key'        => $segment_key,
			'language_id'        => $language_id,
			'old_publish_status' => $old_publish,
			'new_publish_status' => Store::PUBLISH_UNPUBLISHED,
			'source_surface'     => 'workspace_save',
		);

		if ( Store::SOURCE_POST === $source_type ) {
			$payload['post_id'] = $source_id;
		}

		$this->audit->log( PublicationAuditEvents::INVALIDATED_BY_EDIT, $payload );
	}
}
