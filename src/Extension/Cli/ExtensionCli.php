<?php
/**
 * WP-CLI extension diagnostics (Extension API v1).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Extension\Cli;

use AIMultilingual\Extension\ExtensionDiagnostics;
use AIMultilingual\Extension\ExtensionRecord;
use AIMultilingual\Extension\ExtensionRegistrar;
use WP_CLI;

/**
 * Read-only extension registration diagnostics.
 */
final class ExtensionCli {

	/**
	 * Registers extension diagnostics commands.
	 *
	 * @param ExtensionRegistrar   $registrar   Extension registrar.
	 * @param ExtensionDiagnostics $diagnostics Diagnostics sink.
	 */
	public static function register( ExtensionRegistrar $registrar, ExtensionDiagnostics $diagnostics ): void {
		if ( ! class_exists( WP_CLI::class ) ) {
			return;
		}

		WP_CLI::add_command(
			'aiml extensions list',
			static function () use ( $registrar, $diagnostics ): void {
				self::list_extensions( $registrar, $diagnostics );
			},
			array(
				'shortdesc' => 'Lists registered extensions and bounded safe facts.',
			)
		);

		WP_CLI::add_command(
			'aiml extensions status',
			static function ( array $args ) use ( $registrar, $diagnostics ): void {
				self::extension_status( $registrar, $diagnostics, $args );
			},
			array(
				'shortdesc' => 'Shows status for one extension id.',
			)
		);
	}

	/**
	 * Lists registered extensions as a WP-CLI table.
	 *
	 * @param ExtensionRegistrar   $registrar   Registrar.
	 * @param ExtensionDiagnostics $diagnostics Diagnostics.
	 */
	private static function list_extensions( ExtensionRegistrar $registrar, ExtensionDiagnostics $diagnostics ): void {
		$rows = array();
		foreach ( $registrar->internal_registry()->all() as $record ) {
			$rows[] = self::summary_row( $record );
		}

		if ( array() === $rows ) {
			WP_CLI::log( 'No extensions registered.' );
			return;
		}

		WP_CLI\Utils\format_items(
			'table',
			$rows,
			array( 'extension_id', 'version', 'active', 'meta', 'blocks', 'provider_allowed', 'provider_denied' )
		);

		$failure = $diagnostics->last_failure();
		if ( null !== $failure ) {
			WP_CLI::log( 'Last registration failure: ' . $failure );
		}
	}

	/**
	 * Prints JSON status for one extension id.
	 *
	 * @param ExtensionRegistrar   $registrar   Registrar.
	 * @param ExtensionDiagnostics $diagnostics Diagnostics.
	 * @param array<int, string>   $args        Positional args.
	 */
	private static function extension_status( ExtensionRegistrar $registrar, ExtensionDiagnostics $diagnostics, array $args ): void {
		$extension_id = isset( $args[0] ) ? (string) $args[0] : '';
		if ( '' === $extension_id ) {
			WP_CLI::error( 'Missing extension id.' );
		}

		$record = $registrar->internal_registry()->get( $extension_id );
		if ( null === $record ) {
			WP_CLI::error( 'Extension not found.' );
		}

		$payload = array(
			'extension_id'          => $record->manifest->extension_id,
			'version'               => $record->manifest->version,
			'active'                => $record->active ? 'yes' : 'no',
			'owned_namespaces'      => implode( ',', $record->manifest->owned_namespaces ),
			'meta_count'            => $record->meta_count,
			'block_count'           => $record->block_count,
			'provider_allowed'      => $record->provider_allowed_count,
			'provider_denied'       => $record->provider_denied_count,
			'last_failure'          => $diagnostics->last_failure() ?? '',
			'registration_counters' => $diagnostics->counters(),
		);

		WP_CLI::log( wp_json_encode( $payload, JSON_PRETTY_PRINT ) );
	}

	/**
	 * Builds one summary row for extension list output.
	 *
	 * @param ExtensionRecord $record Extension record.
	 * @return array<string, int|string>
	 */
	private static function summary_row( ExtensionRecord $record ): array {
		return array(
			'extension_id'     => $record->manifest->extension_id,
			'version'          => $record->manifest->version,
			'active'           => $record->active ? 'yes' : 'no',
			'meta'             => $record->meta_count,
			'blocks'           => $record->block_count,
			'provider_allowed' => $record->provider_allowed_count,
			'provider_denied'  => $record->provider_denied_count,
		);
	}
}
