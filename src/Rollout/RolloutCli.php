<?php
/**
 * WP-CLI rollout operator commands.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout;

use AIMultilingual\Rollout\Metrics\RolloutDiagnosticsService;
use AIMultilingual\Rollout\Metrics\RolloutHotMetricsStore;
use WP_CLI;

/**
 * Shared operator CLI for rollout control.
 */
final class RolloutCli {

	/**
	 * Registers rollout commands.
	 */
	public static function register(): void {
		if ( ! class_exists( WP_CLI::class ) ) {
			return;
		}

		WP_CLI::add_command(
			'aiml rollout status',
			array( self::class, 'status' ),
			array(
				'shortdesc' => 'Shows limited rollout status and hot metrics.',
			)
		);

		WP_CLI::add_command(
			'aiml rollout config export',
			array( self::class, 'export_config' ),
			array(
				'shortdesc' => 'Exports sanitized rollout configuration.',
			)
		);

		WP_CLI::add_command(
			'aiml rollout emergency-stop',
			array( self::class, 'emergency_stop' ),
			array(
				'shortdesc' => 'Emergency rollout stop (disables rollout render and cache).',
				'synopsis'  => array(
					array(
						'type'     => 'assoc',
						'name'     => 'user',
						'optional' => false,
					),
					array(
						'type'     => 'flag',
						'name'     => 'yes',
						'optional' => true,
					),
				),
			)
		);

		WP_CLI::add_command(
			'aiml rollout promote',
			array( self::class, 'promote' ),
			array(
				'shortdesc' => 'Promotes rollout stage explicitly.',
				'synopsis'  => array(
					array(
						'type'     => 'positional',
						'name'     => 'stage',
					),
					array(
						'type'     => 'assoc',
						'name'     => 'user',
						'optional' => false,
					),
					array(
						'type'     => 'flag',
						'name'     => 'yes',
						'optional' => true,
					),
				),
			)
		);
	}

	/**
	 * @param array<int, string>    $args Positional args.
	 * @param array<string, mixed>  $assoc Assoc args.
	 */
	public static function status( array $args, array $assoc ): void {
		unset( $args, $assoc );

		$user_id = self::require_view_cap();

		$service = new RolloutDiagnosticsService(
			new RolloutConfigurationRepository(),
			RolloutHotMetricsStore::load()
		);

		WP_CLI::log( wp_json_encode( $service->status_summary(), JSON_PRETTY_PRINT ) );
		unset( $user_id );
	}

	/**
	 * @param array<int, string>    $args Positional args.
	 * @param array<string, mixed>  $assoc Assoc args.
	 */
	public static function export_config( array $args, array $assoc ): void {
		unset( $args, $assoc );
		self::require_view_cap();

		$repo = new RolloutConfigurationRepository();
		WP_CLI::log( wp_json_encode( $repo->export(), JSON_PRETTY_PRINT ) );
	}

	/**
	 * @param array<int, string>    $args Positional args.
	 * @param array<string, mixed>  $assoc Assoc args.
	 */
	public static function emergency_stop( array $args, array $assoc ): void {
		unset( $args );
		$user_id = self::require_user( $assoc, RolloutCapabilities::EMERGENCY_ROLLBACK );
		self::require_confirm( $assoc );

		$result = ( new RolloutEmergencyService(
			new RolloutConfigurationRepository(),
			new RolloutAuditLogger()
		) )->stop( $user_id, 'cli' );

		if ( ! $result->valid ) {
			WP_CLI::error( implode( ',', $result->errors ) );
		}

		WP_CLI::success( 'Emergency stop applied.' );
	}

	/**
	 * @param array<int, string>    $args Positional args.
	 * @param array<string, mixed>  $assoc Assoc args.
	 */
	public static function promote( array $args, array $assoc ): void {
		$stage   = isset( $args[0] ) ? (int) $args[0] : -1;
		$user_id = self::require_user( $assoc, RolloutCapabilities::PROMOTE_ROLLOUT );
		self::require_confirm( $assoc );

		$result = ( new RolloutPromotionService(
			new RolloutConfigurationRepository(),
			new RolloutAuditLogger()
		) )->promote( $stage, $user_id, 'cli' );

		if ( ! $result->valid ) {
			WP_CLI::error( implode( ',', $result->errors ) );
		}

		WP_CLI::success( 'Stage promoted to ' . $stage );
	}

	/**
	 * @param array<string, mixed> $assoc CLI assoc args.
	 */
	private static function require_user( array $assoc, string $cap ): int {
		$user_id = isset( $assoc['user'] ) ? (int) $assoc['user'] : 0;
		if ( $user_id <= 0 || ! RolloutAccess::user_can( $user_id, $cap ) ) {
			WP_CLI::error( 'Missing or unauthorized --user.' );
		}

		return $user_id;
	}

	private static function require_view_cap(): int {
		$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		if ( $user_id <= 0 || ! RolloutAccess::user_can( $user_id, RolloutCapabilities::VIEW_ROLLOUT ) ) {
			WP_CLI::error( 'Current user lacks aiml_view_rollout.' );
		}

		return $user_id;
	}

	/**
	 * @param array<string, mixed> $assoc CLI assoc args.
	 */
	private static function require_confirm( array $assoc ): void {
		if ( empty( $assoc['yes'] ) ) {
			WP_CLI::error( 'Pass --yes to confirm mutation.' );
		}
	}
}
