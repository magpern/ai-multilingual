<?php
/**
 * Selection-scoped Strategy F admission for Site Translate.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\SiteTranslate;

use AIMultilingual\Block\FeatureFlags;
use AIMultilingual\Settings;
use AIMultilingual\Translation\Extractor;
use WP_Error;
use WP_Post;

/**
 * Classifies body surfaces and enforces the frozen Strategy F selection gate.
 */
final class SiteTranslateAdmissionService {

	public const REASON_BODY_BLOCKS_WITHOUT_STRATEGY_F = 'body_blocks_without_strategy_f';

	/**
	 * Source body classifier.
	 *
	 * @var Extractor
	 */
	private Extractor $extractor;

	/**
	 * Plugin settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Builds the admission service.
	 *
	 * @param Extractor $extractor Source extractor.
	 * @param Settings  $settings  Plugin settings.
	 */
	public function __construct( Extractor $extractor, Settings $settings ) {
		$this->extractor = $extractor;
		$this->settings  = $settings;
	}

	/**
	 * Whether Strategy F is fully valid for block-dependent translation work.
	 */
	public function is_strategy_f_fully_valid(): bool {
		$current   = $this->settings->get();
		$effective = FeatureFlags::validate_dependencies( $current );

		if ( FeatureFlags::has_prohibited_combination( $current ) ) {
			return false;
		}

		foreach ( FeatureFlags::PRODUCTION_FLAGS as $flag ) {
			if ( empty( $effective[ $flag ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Body surface for one post.
	 *
	 * @param WP_Post $post Canonical post.
	 */
	public function body_surface( WP_Post $post ): string {
		return $this->extractor->body_status( $post );
	}

	/**
	 * Whether the post requires Strategy F for block body translation.
	 *
	 * @param WP_Post $post Canonical post.
	 */
	public function is_strategy_f_dependent( WP_Post $post ): bool {
		return Extractor::BODY_BLOCKS === $this->body_surface( $post );
	}

	/**
	 * Validates a Site Translate selection for job creation.
	 *
	 * @param list<int> $post_ids Post ids.
	 * @return true|WP_Error
	 */
	public function validate_selection( array $post_ids ) {
		if ( ! $this->is_strategy_f_fully_valid() ) {
			$blocking = array();
			foreach ( $post_ids as $post_id ) {
				$post = get_post( (int) $post_id );
				if ( ! $post instanceof WP_Post ) {
					continue;
				}
				if ( $this->is_strategy_f_dependent( $post ) ) {
					$blocking[] = array(
						'post_id'      => (int) $post->ID,
						'post_title'   => get_the_title( $post ),
						'post_type'    => (string) $post->post_type,
						'body_surface' => Extractor::BODY_BLOCKS,
						'reason'       => self::REASON_BODY_BLOCKS_WITHOUT_STRATEGY_F,
					);
				}
			}

			if ( array() !== $blocking ) {
				return new WP_Error(
					'aiml_site_translate_strategy_f_required',
					__( 'One or more selected objects require Strategy F (Gutenberg block translation) to be fully configured before Site Translate can run.', 'universal-multilingual' ),
					array(
						'status'            => 422,
						'blocking_objects'  => $blocking,
						'strategy_f_valid'  => false,
						'settings_path'     => admin_url( 'admin.php?page=ai-multilingual#aiml-strategy-f' ),
					)
				);
			}
		}

		return true;
	}
}
