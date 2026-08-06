<?php
/**
 * Provider/profile availability checks for background translation jobs.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

use AIMultilingual\Translation\AI\NullAIProvider;
use AIMultilingual\Translation\AI\ProviderRegistry;

/**
 * Thin wrapper over ProviderRegistry for job create/execute validation (plan §12.1).
 */
final class BackgroundTranslationJobProviderValidator {

	/**
	 * Provider registry.
	 *
	 * @var ProviderRegistry
	 */
	private ProviderRegistry $registry;

	/**
	 * Builds the validator.
	 *
	 * @param ProviderRegistry $registry Provider registry.
	 */
	public function __construct( ProviderRegistry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Whether the job's recorded provider contract is currently satisfiable.
	 *
	 * Empty provider_id skips the check (site default at execution).
	 *
	 * @param object $job Job row.
	 */
	public function is_provider_available( object $job ): bool {
		$provider_id = trim( (string) ( $job->provider_id ?? '' ) );
		if ( '' === $provider_id ) {
			return true;
		}

		$provider = $this->registry->get( $provider_id );
		if ( null === $provider ) {
			return false;
		}

		if ( NullAIProvider::ID === $provider->get_id() ) {
			return false;
		}

		return true;
	}
}
