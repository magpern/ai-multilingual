<?php
/**
 * Request-local invalidation coordinator (mark dirty → coalesce → shutdown flush).
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Surface;

use AIMultilingual\Block\BlockIdentityMigration;
use AIMultilingual\Translation\Store;

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
	 * Whether a flush is currently running (re-entrancy guard).
	 *
	 * @var bool
	 */
	private bool $flushing = false;

	/**
	 * Whether the shutdown hook is registered.
	 *
	 * @var bool
	 */
	private bool $shutdown_registered = false;

	/**
	 * Sync callback count (tests / architecture proofs).
	 *
	 * @var int
	 */
	private int $sync_count = 0;

	/**
	 * Builds the coordinator.
	 *
	 * @param Store           $store    Segment store.
	 * @param SurfaceRegistry $registry Surface registry owning per-type facts and extraction.
	 */
	public function __construct(
		private Store $store,
		private SurfaceRegistry $registry
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
		// Internal/test flush trigger without running the full WP shutdown cascade.
		add_action( 'aiml_flush_surface_invalidations', array( $this, 'flush' ), 10 );
	}

	/**
	 * Sole flush authority: one Store::sync_source per dirty identity.
	 *
	 * Safe to call again after new dirty marks (re-entrancy blocked only while flushing).
	 */
	public function flush(): void {
		if ( $this->flushing ) {
			return;
		}
		$this->flushing = true;

		try {
			if ( BlockIdentityMigration::is_active() ) {
				$this->dirty = array();
				return;
			}

			$pending     = $this->dirty;
			$this->dirty = array();

			foreach ( $pending as $identity ) {
				$this->sync_identity( (string) $identity['source_type'], (int) $identity['source_id'] );
			}
		} finally {
			$this->flushing = false;
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
		$this->dirty      = array();
		$this->flushing   = false;
		$this->sync_count = 0;
	}

	/**
	 * Syncs one identity from final readable state.
	 *
	 * Which extractor answers is the surface's business, not the coordinator's:
	 * a source type nobody registered is a source type this plugin does not
	 * claim, and syncing it would orphan rows it does not understand.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source id.
	 */
	private function sync_identity( string $source_type, int $source_id ): void {
		$surface = $this->registry->for( $source_type );

		if ( null === $surface ) {
			return;
		}

		if ( ! $surface->exists( $source_id ) ) {
			// Object gone: orphan any existing rows with an empty segment set.
			$this->store->sync_source( $source_type, $source_id, '', array() );
			++$this->sync_count;
			return;
		}

		$this->store->sync_source(
			$source_type,
			$source_id,
			$surface->source_subtype( $source_id ),
			$surface->extract_segments( $source_id )
		);
		++$this->sync_count;
	}
}
