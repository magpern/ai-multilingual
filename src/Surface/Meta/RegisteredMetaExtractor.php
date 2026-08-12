<?php
/**
 * Native registered-meta segment extractor (TSC.2).
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Surface\Meta;

use AIMultilingual\Surface\AdmittedPostTypes;
use AIMultilingual\Surface\AdmittedTaxonomies;
use AIMultilingual\Surface\SurfaceCapability;
use AIMultilingual\Surface\SurfaceRegistry;
use AIMultilingual\Translation\Store;

/**
 * Emits native_m units only for Surface-admitted owners.
 */
final class RegisteredMetaExtractor {

	/**
	 * @param RegisteredMetaRegistry $registry Catalog.
	 * @param RegisteredMetaReader   $reader   Keyed reader.
	 * @param SurfaceRegistry|null   $surfaces Optional surfaces for admission checks.
	 */
	public function __construct(
		private RegisteredMetaRegistry $registry,
		private RegisteredMetaReader $reader,
		private ?SurfaceRegistry $surfaces = null,
	) {
	}

	/**
	 * Extract native_m segments for a post.
	 *
	 * @param int $post_id Post id.
	 * @return array<string, array<string, mixed>>
	 */
	public function extract_for_post( int $post_id ): array {
		if ( $post_id <= 0 || ! function_exists( 'get_post' ) ) {
			return array();
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return array();
		}
		$post_type = (string) $post->post_type;
		if ( ! AdmittedPostTypes::admits( $post_type, AdmittedPostTypes::CONTEXT_WORKSPACE )
			&& ! AdmittedPostTypes::admits( $post_type, AdmittedPostTypes::CONTEXT_FRONTEND_OVERLAY ) ) {
			return array();
		}
		if ( ! $this->surface_admits( Store::SOURCE_POST, $post_id ) ) {
			return array();
		}
		return $this->extract( Store::SOURCE_POST, $post_id, $post_type );
	}

	/**
	 * Extract native_m segments for a term.
	 *
	 * @param int    $term_id  Term id.
	 * @param string $taxonomy Taxonomy.
	 * @return array<string, array<string, mixed>>
	 */
	public function extract_for_term( int $term_id, string $taxonomy ): array {
		if ( $term_id <= 0 || '' === $taxonomy ) {
			return array();
		}
		if ( ! AdmittedTaxonomies::admits( $taxonomy ) ) {
			return array();
		}
		if ( ! $this->surface_admits( Store::SOURCE_TERM, $term_id ) ) {
			return array();
		}
		return $this->extract( Store::SOURCE_TERM, $term_id, $taxonomy );
	}

	/**
	 * @param string $source_type Source type.
	 * @param int    $source_id   Owner id.
	 * @param string $subtype     Subtype.
	 * @return array<string, array<string, mixed>>
	 */
	private function extract( string $source_type, int $source_id, string $subtype ): array {
		$segments = array();
		$order    = 3000;
		foreach ( $this->registry->active_native_definitions( $source_type, $subtype ) as $definition ) {
			$raw = $this->reader->read( $source_type, $source_id, $definition->meta_key );
			if ( '' === trim( $raw ) ) {
				continue;
			}
			$key              = $definition->native_segment_key();
			$segments[ $key ] = array(
				'field_key'     => RegisteredMetaDefinition::FIELD_KEY,
				'segment_key'   => $key,
				'source_text'   => $raw,
				'text_format'   => $definition->text_format,
				'segment_order' => $order++,
				'segment_kind'  => Store::KIND_FIELD,
				'surface'       => 'registered_meta',
				'meta'          => array(
					'namespace'   => $definition->namespace,
					'meta_key'    => $definition->meta_key,
					'field_label' => $definition->label,
				),
			);
		}
		return $segments;
	}

	/**
	 * @param string $source_type Source type.
	 * @param int    $source_id   Owner id.
	 */
	private function surface_admits( string $source_type, int $source_id ): bool {
		if ( null === $this->surfaces ) {
			return true;
		}
		$surface = $this->surfaces->for( $source_type );
		if ( ! $surface instanceof SurfaceCapability ) {
			return false;
		}
		return $surface->exists( $source_id );
	}
}
