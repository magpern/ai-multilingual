<?php
/**
 * Request-scoped binding for chrome CPT admission registry.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Integration;

/**
 * Holds the M5-A IntegrationAdmissionRegistry for Extractor / Workspace / Extension helpers.
 */
final class IntegrationAdmission {

	/**
	 * Bound registry.
	 *
	 * @var IntegrationAdmissionRegistry|null
	 */
	private static ?IntegrationAdmissionRegistry $registry = null;

	/**
	 * Binds the request-scoped admission registry.
	 *
	 * @param IntegrationAdmissionRegistry $registry Registry.
	 */
	public static function bind( IntegrationAdmissionRegistry $registry ): void {
		self::$registry = $registry;
	}

	/**
	 * Returns the bound registry, if any.
	 */
	public static function registry(): ?IntegrationAdmissionRegistry {
		return self::$registry;
	}

	/**
	 * Clears binding (tests).
	 */
	public static function reset_for_tests(): void {
		self::$registry = null;
	}
}
