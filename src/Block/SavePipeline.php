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
		if ( self::$injecting ) {
			return $data;
		}

		if ( ! $this->should_inject( $data, $postarr ) ) {
			return $data;
		}

		self::$injecting = true;

		try {
			$content = isset( $data['post_content'] ) ? (string) $data['post_content'] : '';
			$result  = $this->injector->inject_content( $content );

			if ( ! $result->successful ) {
				return $data;
			}

			if ( $result->changed ) {
				$data['post_content'] = $result->content;
			}
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
	 * Resets the recursion guard between tests.
	 */
	public static function reset_guard_for_tests(): void {
		self::$injecting = false;
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
