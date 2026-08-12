<?php
/**
 * Rank Math SEO meta definition module — single source of truth for six keys.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Surface\Meta;

use AIMultilingual\Integration\RankMath\RankMathIntegration;
use AIMultilingual\Translation\Store;

/**
 * Owns Rank Math textual SEO meta admission / invalidation / provider facts.
 * RankMathIntegration retains p: identity, literal gate, overlays, sitemap.
 */
final class RankMathMetaDefinitions {

	public const NAMESPACE = 'rankmath';

	/**
	 * Exact six admitted SEO text meta keys (sole literal source of truth).
	 *
	 * @var list<string>
	 */
	public const SEO_META_KEYS = array(
		RankMathIntegration::META_TITLE,
		RankMathIntegration::META_DESCRIPTION,
		RankMathIntegration::META_FACEBOOK_TITLE,
		RankMathIntegration::META_FACEBOOK_DESCRIPTION,
		RankMathIntegration::META_TWITTER_TITLE,
		RankMathIntegration::META_TWITTER_DESCRIPTION,
	);

	/**
	 * Exact six admitted SEO text meta keys (sole literal source of truth).
	 *
	 * @return list<string>
	 */
	public static function seo_meta_keys(): array {
		return self::SEO_META_KEYS;
	}

	/**
	 * Meta key → Rank Math field token for PluginIdentity.
	 *
	 * @return array<string, string>
	 */
	public static function field_tokens_by_meta_key(): array {
		return array(
			RankMathIntegration::META_TITLE                => RankMathIntegration::FIELD_TITLE,
			RankMathIntegration::META_DESCRIPTION          => RankMathIntegration::FIELD_DESCRIPTION,
			RankMathIntegration::META_FACEBOOK_TITLE       => RankMathIntegration::FIELD_FACEBOOK_TITLE,
			RankMathIntegration::META_FACEBOOK_DESCRIPTION => RankMathIntegration::FIELD_FACEBOOK_DESCRIPTION,
			RankMathIntegration::META_TWITTER_TITLE        => RankMathIntegration::FIELD_TWITTER_TITLE,
			RankMathIntegration::META_TWITTER_DESCRIPTION  => RankMathIntegration::FIELD_TWITTER_DESCRIPTION,
		);
	}

	/**
	 * Register Rank Math definitions for post and term into the catalog.
	 *
	 * @param RegisteredMetaRegistry $registry Catalog.
	 */
	public static function register_into( RegisteredMetaRegistry $registry ): void {
		$activation = static fn (): bool => defined( 'RANK_MATH_VERSION' ) || class_exists( '\\RankMath', false );

		$labels = array(
			RankMathIntegration::META_TITLE                => 'Rank Math title',
			RankMathIntegration::META_DESCRIPTION          => 'Rank Math description',
			RankMathIntegration::META_FACEBOOK_TITLE       => 'Rank Math Facebook title',
			RankMathIntegration::META_FACEBOOK_DESCRIPTION => 'Rank Math Facebook description',
			RankMathIntegration::META_TWITTER_TITLE        => 'Rank Math Twitter title',
			RankMathIntegration::META_TWITTER_DESCRIPTION  => 'Rank Math Twitter description',
		);

		foreach ( self::field_tokens_by_meta_key() as $meta_key => $field_token ) {
			foreach ( array( Store::SOURCE_POST, Store::SOURCE_TERM ) as $source_type ) {
				$registry->register(
					new RegisteredMetaDefinition(
						namespace: self::NAMESPACE,
						source_type: $source_type,
						meta_key: $meta_key,
						segment_key_mode: RegisteredMetaDefinition::MODE_EXTERNAL_P,
						label: $labels[ $meta_key ] ?? $meta_key,
						admitted_subtypes: null,
						extract_store_capable: true,
						provider_allowed: true,
						overlay_capable: true,
						overlay_resolver_ownership: RegisteredMetaDefinition::OVERLAY_INTEGRATION_PREFIX . self::NAMESPACE,
						activation: $activation,
						external_field_token: $field_token,
					)
				);
			}
		}
	}
}
