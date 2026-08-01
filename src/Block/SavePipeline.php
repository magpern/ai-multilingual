<?php
/**
 * Strategy F save-time UUID persistence orchestration.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

use AIMultilingual\Settings;
use AIMultilingual\Translation\Extractor;
use WP_Post;

/**
 * Mutates canonical post content before database write when injection is enabled.
 */
final class SavePipeline {

	/**
	 * Prevents recursive injection when downstream hooks re-enter the save path.
	 *
	 * @var bool
	 */
	private static bool $injecting = false;

	/**
	 * Suppresses injection while explicit migration persists content.
	 *
	 * @var bool
	 */
	private static bool $migration_suspended = false;

	/**
	 * Constructs the save pipeline.
	 *
	 * @param Settings     $settings  Plugin settings.
	 * @param UuidInjector $injector  UUID persistence pipeline.
	 * @param Extractor    $extractor Body storage classifier.
	 */
	public function __construct(
		private Settings $settings,
		private UuidInjector $injector,
		private Extractor $extractor,
	) {
	}

	/**
	 * Registers the pre-insert save hook.
	 */
	public function register(): void {
		add_filter( 'wp_insert_post_data', array( $this, 'filter_insert_post_data' ), 8, 2 );
	}

	/**
	 * Injects UUIDs into post content before WordPress persists the post.
	 *
	 * @param array<string, mixed> $data    Sanitized post data.
	 * @param array<string, mixed> $postarr Raw post data.
	 * @return array<string, mixed>
	 */
	public function filter_insert_post_data( array $data, array $postarr ): array {
		if ( self::$injecting || self::$migration_suspended ) {
			return $data;
		}

		if ( ! $this->should_inject( $data, $postarr ) ) {
			return $data;
		}

		self::$injecting = true;

		try {
			$content = isset( $data['post_content'] ) ? (string) $data['post_content'] : '';
			$post_id = (int) ( $postarr['ID'] ?? 0 );

			if ( $post_id > 0 ) {
				$existing = get_post( $post_id );
				if ( $existing instanceof WP_Post && '' !== $existing->post_content ) {
					$content = $this->merge_uuids_from_previous( $content, (string) $existing->post_content );
				}
			}

			$result  = $this->injector->inject_content( $content );

			if ( ! $result->successful ) {
				return $data;
			}

			$data['post_content'] = $result->content;
		} finally {
			self::$injecting = false;
		}

		return $data;
	}

	/**
	 * Determines whether UUID injection should run for this save.
	 *
	 * @param array<string, mixed> $data    Sanitized post data.
	 * @param array<string, mixed> $postarr Raw post data.
	 */
	public function should_inject( array $data, array $postarr ): bool {
		if ( ! $this->settings->block_uuid_injection_enabled() ) {
			return false;
		}

		if ( ! $this->settings->block_attr_registration_enabled() ) {
			return false;
		}

		if ( $this->is_revision_or_autosave( $data, $postarr ) ) {
			return false;
		}

		$content = isset( $data['post_content'] ) ? (string) $data['post_content'] : '';
		if ( '' === $content || ! function_exists( 'has_blocks' ) || ! has_blocks( $content ) ) {
			return false;
		}

		if ( $this->is_elementor_body( $data, $postarr ) ) {
			return false;
		}

		if ( ! $this->current_user_can_edit( $data, $postarr ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Runs injection with the recursion guard engaged.
	 *
	 * Exposed for tests; production callers use {@see filter_insert_post_data()}.
	 *
	 * @param array<string, mixed> $data    Sanitized post data.
	 * @param array<string, mixed> $postarr Raw post data.
	 * @return array<string, mixed>
	 */
	public function apply_with_guard( array $data, array $postarr ): array {
		return $this->filter_insert_post_data( $data, $postarr );
	}

	/**
	 * Suspends save-time injection while migration persists content explicitly.
	 */
	public static function suspend_for_migration(): void {
		self::$migration_suspended = true;
	}

	/**
	 * Resumes save-time injection after migration completes.
	 */
	public static function resume_after_migration(): void {
		self::$migration_suspended = false;
	}

	/**
	 * Resets the recursion guard between tests.
	 */
	public static function reset_guard_for_tests(): void {
		self::$injecting           = false;
		self::$migration_suspended = false;
	}

	/**
	 * Copies aimlBlockId values from previous saved content when the editor omits them.
	 *
	 * Gutenberg may serialize eligible blocks without aimlBlockId even when registration
	 * is enabled (first save injects server-side only). Reconcile by document order and
	 * block type before UUID injection runs.
	 *
	 * @param string $incoming Incoming post content.
	 * @param string $previous Previously persisted post content.
	 */
	private function merge_uuids_from_previous( string $incoming, string $previous ): string {
		if ( ! function_exists( 'parse_blocks' ) || ! function_exists( 'serialize_blocks' ) || ! function_exists( 'has_blocks' ) ) {
			return $incoming;
		}

		if ( ! has_blocks( $incoming ) || ! has_blocks( $previous ) ) {
			return $incoming;
		}

		$incoming_blocks = parse_blocks( $incoming );
		$registry        = new BlockRegistry();
		$previous_blocks = $this->eligible_blocks_in_order( parse_blocks( $previous ), $registry );
		$changed         = false;
		$index           = 0;

		( new BlockTreeWalker() )->walk(
			$incoming_blocks,
			function ( array &$block ) use ( &$index, $previous_blocks, $registry, &$changed ): void {
				if ( ! $registry->is_eligible( $block ) ) {
					return;
				}

				$previous_block = $previous_blocks[ $index ] ?? null;
				++$index;

				if ( null === $previous_block ) {
					return;
				}

				$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
				$uuid  = isset( $attrs[ Contract::ATTR_NAME ] ) ? (string) $attrs[ Contract::ATTR_NAME ] : '';

				if ( '' !== $uuid ) {
					return;
				}

				$prev_attrs = is_array( $previous_block['attrs'] ?? null ) ? $previous_block['attrs'] : array();
				$prev_uuid  = isset( $prev_attrs[ Contract::ATTR_NAME ] ) ? (string) $prev_attrs[ Contract::ATTR_NAME ] : '';

				if ( ! UuidValidator::is_valid_non_empty( $prev_uuid ) ) {
					return;
				}

				if ( (string) ( $block['blockName'] ?? '' ) !== (string) ( $previous_block['blockName'] ?? '' ) ) {
					return;
				}

				if ( ! is_array( $block['attrs'] ?? null ) ) {
					$block['attrs'] = array();
				}

				$block['attrs'][ Contract::ATTR_NAME ] = $prev_uuid;
				$changed                               = true;
			}
		);

		return $changed ? serialize_blocks( $incoming_blocks ) : $incoming;
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks Parsed block tree.
	 * @param BlockRegistry                  $registry Block eligibility policy.
	 * @return list<array<string, mixed>>
	 */
	private function eligible_blocks_in_order( array $blocks, BlockRegistry $registry ): array {
		$eligible = array();

		( new BlockTreeWalker() )->walk(
			$blocks,
			static function ( array $block ) use ( &$eligible, $registry ): void {
				if ( $registry->is_eligible( $block ) ) {
					$eligible[] = $block;
				}
			}
		);

		return $eligible;
	}

	/**
	 * Returns true when the save targets a revision or autosave.
	 *
	 * @param array<string, mixed> $data    Sanitized post data.
	 * @param array<string, mixed> $postarr Raw post data.
	 */
	private function is_revision_or_autosave( array $data, array $postarr ): bool {
		unset( $data );

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return true;
		}

		$post_id = (int) ( $postarr['ID'] ?? 0 );
		if ( $post_id > 0 ) {
			if ( function_exists( 'wp_is_post_autosave' ) && wp_is_post_autosave( $post_id ) ) {
				return true;
			}

			if ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) ) {
				return true;
			}
		}

		$post_type = (string) ( $postarr['post_type'] ?? '' );
		if ( 'revision' === $post_type ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns true when the post body is stored in Elementor format.
	 *
	 * @param array<string, mixed> $data    Sanitized post data.
	 * @param array<string, mixed> $postarr Raw post data.
	 */
	private function is_elementor_body( array $data, array $postarr ): bool {
		unset( $data );

		$post_id = (int) ( $postarr['ID'] ?? 0 );
		if ( $post_id <= 0 ) {
			return false;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		return Extractor::BODY_ELEMENTOR === $this->extractor->body_status( $post );
	}

	/**
	 * Returns true when the current user may edit or create the post.
	 *
	 * @param array<string, mixed> $data    Sanitized post data.
	 * @param array<string, mixed> $postarr Raw post data.
	 */
	private function current_user_can_edit( array $data, array $postarr ): bool {
		$post_id = (int) ( $postarr['ID'] ?? 0 );

		if ( $post_id > 0 ) {
			return current_user_can( 'edit_post', $post_id );
		}

		$post_type = (string) ( $data['post_type'] ?? $postarr['post_type'] ?? 'post' );
		$object    = get_post_type_object( $post_type );

		if ( null === $object ) {
			return false;
		}

		return current_user_can( $object->cap->create_posts );
	}
}
