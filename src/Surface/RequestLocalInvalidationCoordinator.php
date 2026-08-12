<?php
/**
 * Request-local invalidation coordinator (mark dirty → coalesce → shutdown flush).
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Surface;

use AIMultilingual\Block\BlockIdentityMigration;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;
use WP_Post;

/**
 * Mutation hooks mark identities dirty only. Shutdown is the sole flush authority.
 *
 * No provider calls. No durable queue. Final readable source/meta must be synced.
 */
final class RequestLocalInvalidationCoordinator {

	/**
	 * Dirty identities keyed by "source_type:source_id".
	 *
	 * @var array<string, array{source_type: string, source_id: int}>
	 */
	private array $dirty = array();

	/**
	 * Whether shutdown flush already ran for this request.
	 */
	private bool $flushed = false;

	/**
	 * Whether the shutdown hook is registered.
	 */
	private bool $shutdown_registered = false;

	/**
	 * Sync callback count (tests / architecture proofs).
	 */
	private int $sync_count = 0;

	/**
	 * @param Store     $store     Segment store.
	 * @param Extractor $extractor Source extractor.
	 */
	public function __construct(
		private Store $store,
		private Extractor $extractor
	) {
	}

	/**
	 * Marks a source identity dirty for coalesced final-state sync.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source id.
	 */
	public function mark_dirty( string $source_type, int $source_id ): void {
		if ( '' === $source_type || $source_id <= 0 ) {
			return;
		}

		$key                 = $source_type . ':' . $source_id;
		$this->dirty[ $key ] = array(
			'source_type' => $source_type,
			'source_id'   => $source_id,
		);

		$this->ensure_shutdown_hook();
	}

	/**
	 * Clears a pending dirty mark (e.g. autosave/revision for that object).
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source id.
	 */
	public function clear_dirty( string $source_type, int $source_id ): void {
		unset( $this->dirty[ $source_type . ':' . $source_id ] );
	}

	/**
	 * Registers the shutdown flush hook once.
	 */
	public function ensure_shutdown_hook(): void {
		if ( $this->shutdown_registered ) {
			return;
		}
		$this->shutdown_registered = true;
		add_action( 'shutdown', array( $this, 'flush' ), 20 );
	}

	/**
	 * Sole flush authority: one Store::sync_source per dirty identity.
	 */
	public function flush(): void {
		if ( $this->flushed ) {
			return;
		}
		$this->flushed = true;

		if ( BlockIdentityMigration::is_active() ) {
			$this->dirty = array();
			return;
		}

		$pending     = $this->dirty;
		$this->dirty = array();

		foreach ( $pending as $identity ) {
			$this->sync_identity( (string) $identity['source_type'], (int) $identity['source_id'] );
		}
	}

	/**
	 * Test helper: pending dirty count.
	 */
	public function dirty_count(): int {
		return count( $this->dirty );
	}

	/**
	 * Test helper: sync invocations this request.
	 */
	public function sync_count(): int {
		return $this->sync_count;
	}

	/**
	 * Test helper: reset request-local state.
	 */
	public function reset_for_tests(): void {
		$this->dirty   = array();
		$this->flushed = false;
		$this->sync_count = 0;
	}

	/**
	 * Syncs one identity from final readable state.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source id.
	 */
	private function sync_identity( string $source_type, int $source_id ): void {
		if ( Store::SOURCE_POST !== $source_type ) {
			return;
		}

		$post = get_post( $source_id );
		if ( ! $post instanceof WP_Post ) {
			// Object gone: orphan any existing rows with empty segment set.
			$this->store->sync_source( Store::SOURCE_POST, $source_id, '', array() );
			++$this->sync_count;
			return;
		}

		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}

		$segments = $this->extractor->extract( $post );
		$this->store->sync_source(
			Store::SOURCE_POST,
			$source_id,
			(string) $post->post_type,
			$segments
		);
		++$this->sync_count;
	}
}
