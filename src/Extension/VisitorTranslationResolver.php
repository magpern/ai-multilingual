<?php
/**
 * Read-only visitor translation resolver (Extension API v1).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Extension;

use AIMultilingual\Block\Contract as BlockContract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Integration\Contract as IntegrationContract;
use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Integration\IntegrationRegistry;
use AIMultilingual\Language\LanguageContext;
use AIMultilingual\Language\Languages;
use AIMultilingual\Surface\AdmittedPostTypes;
use AIMultilingual\Surface\AdmittedTaxonomies;
use AIMultilingual\Surface\Meta\RegisteredMetaDefinition;
use AIMultilingual\Surface\Meta\RegisteredMetaRegistry;
use AIMultilingual\Translation\Store;

/**
 * Resolves eligible visitor translations without exposing Store rows.
 */
final class VisitorTranslationResolver {

	/**
	 * Renderable translation statuses.
	 *
	 * @var list<string>
	 */
	private const RENDERABLE_STATUSES = array(
		Store::STATUS_MACHINE_TRANSLATED,
		Store::STATUS_MANUALLY_EDITED,
		Store::STATUS_REVIEWED,
	);

	/**
	 * Builds the public visitor translation resolver.
	 *
	 * @param Store                  $store                Segment store (internal).
	 * @param Languages              $languages            Language catalog.
	 * @param LanguageContext        $context              Request language state.
	 * @param RegisteredMetaRegistry $meta_registry        Meta catalog.
	 * @param IntegrationRegistry    $integration_registry Integration catalog.
	 * @param PluginIdentity         $identity             p: key parser.
	 * @param ExtensionDiagnostics   $diagnostics          Diagnostics sink.
	 */
	public function __construct(
		private Store $store,
		private Languages $languages,
		private LanguageContext $context,
		private RegisteredMetaRegistry $meta_registry,
		private IntegrationRegistry $integration_registry,
		private PluginIdentity $identity,
		private ExtensionDiagnostics $diagnostics,
	) {
	}

	/**
	 * Resolves one eligible visitor translation or null (fail closed).
	 *
	 * @param SourceSegmentReference $source   Source segment identity.
	 * @param LanguageReference      $language Target language code.
	 */
	public function resolve( SourceSegmentReference $source, LanguageReference $language ): ?ResolvedTranslation {
		if ( ! $this->validate_source_reference( $source ) ) {
			$this->diagnostics->increment( ExtensionDiagnostics::COUNTER_RESOLVER_MISS );
			return null;
		}

		if ( ! Languages::is_valid_code( $language->code ) ) {
			$this->diagnostics->increment( ExtensionDiagnostics::COUNTER_RESOLVER_MISS );
			return null;
		}

		$language_row = $this->languages->find_by_code( $language->code );
		if ( null === $language_row ) {
			$this->diagnostics->increment( ExtensionDiagnostics::COUNTER_RESOLVER_MISS );
			return null;
		}

		if ( $this->context->is_default() || $this->is_default_language( $language_row ) ) {
			$this->diagnostics->increment( ExtensionDiagnostics::COUNTER_RESOLVER_MISS );
			return null;
		}

		if ( ! $this->source_exists_and_admitted( $source ) ) {
			$this->diagnostics->increment( ExtensionDiagnostics::COUNTER_RESOLVER_MISS );
			return null;
		}

		if ( ! $this->segment_family_active( $source ) ) {
			$this->diagnostics->increment( ExtensionDiagnostics::COUNTER_RESOLVER_MISS );
			return null;
		}

		$rows = $this->store->load_object( $source->source_type, $source->source_id, (int) $language_row->language_id );
		$row  = $rows[ $source->segment_key ] ?? null;
		if ( ! is_object( $row ) ) {
			$this->diagnostics->increment( ExtensionDiagnostics::COUNTER_RESOLVER_MISS );
			return null;
		}

		if ( ! empty( $row->is_stale ) ) {
			$this->diagnostics->increment( ExtensionDiagnostics::COUNTER_RESOLVER_MISS );
			return null;
		}

		$status = (string) ( $row->status ?? '' );
		if ( Store::STATUS_IGNORED === $status || ! in_array( $status, self::RENDERABLE_STATUSES, true ) ) {
			$this->diagnostics->increment( ExtensionDiagnostics::COUNTER_RESOLVER_MISS );
			return null;
		}

		$text = (string) ( $row->translated_text ?? '' );
		if ( '' === trim( $text ) ) {
			$this->diagnostics->increment( ExtensionDiagnostics::COUNTER_RESOLVER_MISS );
			return null;
		}

		if ( ! Store::is_publicly_overlay_eligible( $row ) ) {
			$this->diagnostics->increment( ExtensionDiagnostics::COUNTER_RESOLVER_MISS );
			return null;
		}

		$format = (string) ( $row->text_format ?? Store::FORMAT_PLAIN );
		if ( ! in_array( $format, array( Store::FORMAT_PLAIN, Store::FORMAT_HTML ), true ) ) {
			$format = Store::FORMAT_PLAIN;
		}

		$this->diagnostics->increment( ExtensionDiagnostics::COUNTER_RESOLVER_HIT );

		return new ResolvedTranslation(
			$text,
			$format,
			true
		);
	}

	/**
	 * Validates resolver source segment reference shape and prefix.
	 *
	 * @param SourceSegmentReference $source Source reference.
	 */
	private function validate_source_reference( SourceSegmentReference $source ): bool {
		if ( $source->source_id <= 0 ) {
			return false;
		}
		if ( ! in_array( $source->source_type, array( Store::SOURCE_POST, Store::SOURCE_TERM ), true ) ) {
			return false;
		}
		if ( '' === $source->segment_key ) {
			return false;
		}
		foreach ( Contract::RESOLVER_SEGMENT_PREFIXES as $prefix ) {
			if ( str_starts_with( $source->segment_key, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether the language row matches the current default language.
	 *
	 * @param object $language_row Language row.
	 */
	private function is_default_language( object $language_row ): bool {
		$default = $this->context->default();
		if ( null === $default ) {
			return false;
		}
		return (int) ( $default->language_id ?? 0 ) === (int) ( $language_row->language_id ?? 0 );
	}

	/**
	 * Whether the referenced source object exists and is admitted.
	 *
	 * @param SourceSegmentReference $source Source reference.
	 */
	private function source_exists_and_admitted( SourceSegmentReference $source ): bool {
		if ( Store::SOURCE_POST === $source->source_type ) {
			$post = get_post( $source->source_id );
			if ( ! $post instanceof \WP_Post ) {
				return false;
			}
			return AdmittedPostTypes::admits( (string) $post->post_type, AdmittedPostTypes::CONTEXT_FRONTEND_OVERLAY )
				|| AdmittedPostTypes::admits( (string) $post->post_type, AdmittedPostTypes::CONTEXT_WORKSPACE );
		}

		$term = get_term( $source->source_id );
		if ( ! $term instanceof \WP_Term ) {
			return false;
		}
		return AdmittedTaxonomies::admits( (string) $term->taxonomy );
	}

	/**
	 * Whether the segment family is active for overlay resolution.
	 *
	 * @param SourceSegmentReference $source Source reference.
	 */
	private function segment_family_active( SourceSegmentReference $source ): bool {
		$key = $source->segment_key;

		if ( str_starts_with( $key, RegisteredMetaDefinition::SEGMENT_PREFIX . ':' ) ) {
			$parts = explode( ':', $key, 3 );
			if ( count( $parts ) !== 3 ) {
				return false;
			}
			$meta_key = $parts[2];
			$def      = $this->meta_registry->get( $source->source_type, $meta_key );
			return null !== $def && $def->is_active();
		}

		if ( str_starts_with( $key, IntegrationContract::SEGMENT_KEY_PREFIX . ':' ) ) {
			$parsed = $this->identity->parse( $key );
			if ( ! is_array( $parsed ) ) {
				return false;
			}
			$integration_id = (string) ( $parsed['integration_id'] ?? '' );
			$integration    = $this->integration_registry->get( $integration_id );
			if ( null === $integration ) {
				return false;
			}
			return $integration->get_compatibility()->allows_overlay();
		}

		if ( str_starts_with( $key, BlockContract::SEGMENT_KEY_PREFIX . ':' ) ) {
			if ( ! SegmentKey::is_valid_format( $key ) ) {
				return false;
			}
			return Store::SOURCE_POST === $source->source_type;
		}

		return false;
	}
}
