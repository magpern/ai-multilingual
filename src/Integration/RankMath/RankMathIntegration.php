<?php
/**
 * Rank Math SEO Integration API v1 consumer (A.SEOc).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Integration\RankMath;

use AIMultilingual\Integration\CompatibilityStatus;
use AIMultilingual\Integration\Contract;
use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Integration\IntegrationSecurity;
use AIMultilingual\Integration\PluginIntegrationInterface;
use AIMultilingual\Integration\TranslationUnitDescriptor;
use AIMultilingual\Language\LanguageContext;
use AIMultilingual\Seo\LanguageRelationshipService;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;
use WP_Post;
use WP_Term;

/**
 * Rank Math title/description/schema + OpenGraph/Twitter + sitemap cooperation.
 *
 * A.SEOc Supported: SC1–SC6, SC10–SC14. Partially Supported: SC7–SC9.
 * A.SEOd Supported: SD1–SD3, SD5–SD8, SD11. Partially Supported: explicit FB/Twitter text.
 * A.SEOe Supported: SE1–SE9, SE12. Deferred: SE10, SE11.
 * Consumes SB11 unchanged. Official Rank Math filters/actions only.
 */
final class RankMathIntegration implements PluginIntegrationInterface {

	public const ID = 'rankmath';

	public const MIN_VERSION = '1.0.200';

	public const PLUGIN_BASENAME = 'seo-by-rank-math/rank-math.php';

	public const META_TITLE = 'rank_math_title';

	public const META_DESCRIPTION = 'rank_math_description';

	public const META_FACEBOOK_TITLE = 'rank_math_facebook_title';

	public const META_FACEBOOK_DESCRIPTION = 'rank_math_facebook_description';

	public const META_TWITTER_TITLE = 'rank_math_twitter_title';

	public const META_TWITTER_DESCRIPTION = 'rank_math_twitter_description';

	public const META_TWITTER_USE_FACEBOOK = 'rank_math_twitter_use_facebook';

	public const FIELD_TITLE = 'title';

	public const FIELD_DESCRIPTION = 'description';

	public const FIELD_FACEBOOK_TITLE = 'facebook_title';

	public const FIELD_FACEBOOK_DESCRIPTION = 'facebook_description';

	public const FIELD_TWITTER_TITLE = 'twitter_title';

	public const FIELD_TWITTER_DESCRIPTION = 'twitter_description';

	public const OWNER_POST = 'post';

	public const OWNER_TERM = 'term';

	public const HOOK_TITLE = 'rank_math/frontend/title';

	public const HOOK_DESCRIPTION = 'rank_math/frontend/description';

	public const HOOK_REPLACEMENTS = 'rank_math/replacements';

	public const HOOK_SCHEMA_ENTITY = 'rank_math/snippet/rich_snippet_entity';

	public const HOOK_OG_FACEBOOK = 'rank_math/opengraph/facebook';

	public const HOOK_OG_TITLE = 'rank_math/opengraph/facebook/og_title';

	public const HOOK_OG_DESCRIPTION = 'rank_math/opengraph/facebook/og_description';

	public const HOOK_OG_LOCALE = 'rank_math/opengraph/facebook/og_locale';

	public const HOOK_OG_URL = 'rank_math/opengraph/url';

	public const HOOK_TWITTER_TITLE = 'rank_math/opengraph/twitter/twitter_title';

	public const HOOK_TWITTER_DESCRIPTION = 'rank_math/opengraph/twitter/twitter_description';

	/**
	 * Taxonomies admitted for SC5/SC6 explicit Rank Math term fields.
	 *
	 * @var list<string>
	 */
	private const TERM_TAXONOMIES = array( 'category', 'post_tag', 'product_cat', 'product_tag' );

	/**
	 * Builds the Rank Math integration.
	 *
	 * @param PluginIdentity                   $identity      Serializer.
	 * @param Store                            $store         Store.
	 * @param LanguageContext                  $context       Language context.
	 * @param LanguageRelationshipService|null $relationships SB11 (consumed unchanged).
	 * @param bool|null                        $installed     Test override.
	 * @param bool|null                        $active        Test override.
	 * @param string|null                      $version       Test override.
	 * @param bool|null                        $disabled      Test override.
	 * @param bool|null                        $hooks_present Test override.
	 */
	/**
	 * A.SEOe sitemap overlay helper (null until relationships available).
	 *
	 * @var RankMathSitemapOverlay|null
	 */
	private ?RankMathSitemapOverlay $sitemap_overlay = null;

	public function __construct(
		private PluginIdentity $identity,
		private Store $store,
		private LanguageContext $context,
		private ?LanguageRelationshipService $relationships = null,
		private ?bool $installed = null,
		private ?bool $active = null,
		private ?string $version = null,
		private ?bool $disabled = null,
		private ?bool $hooks_present = null,
	) {
		if ( null !== $this->relationships ) {
			$this->sitemap_overlay = new RankMathSitemapOverlay( $this->relationships );
		}
	}

	/**
	 * Production factory.
	 *
	 * @param PluginIdentity                   $identity      Serializer.
	 * @param Store                            $store         Store.
	 * @param LanguageContext                  $context       Language context.
	 * @param LanguageRelationshipService|null $relationships SB11.
	 */
	public static function create_default(
		PluginIdentity $identity,
		Store $store,
		LanguageContext $context,
		?LanguageRelationshipService $relationships = null
	): self {
		return new self( $identity, $store, $context, $relationships );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return self::ID;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_api_version(): string {
		return Contract::API_VERSION;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_compatibility(): CompatibilityStatus {
		if ( ! $this->is_installed() ) {
			return new CompatibilityStatus( Contract::STATE_UNAVAILABLE, 'plugin_missing' );
		}
		if ( $this->is_disabled() ) {
			return new CompatibilityStatus( Contract::STATE_DISABLED, 'integration_disabled' );
		}
		if ( ! $this->is_active() ) {
			return new CompatibilityStatus( Contract::STATE_UNAVAILABLE, 'plugin_inactive' );
		}
		if ( version_compare( $this->resolved_version(), self::MIN_VERSION, '<' ) ) {
			return new CompatibilityStatus( Contract::STATE_UNSUPPORTED_VERSION, 'version_too_low' );
		}
		if ( ! $this->required_hooks_present() ) {
			return new CompatibilityStatus( Contract::STATE_MISSING_REQUIRED_HOOK, 'hooks_missing' );
		}
		return new CompatibilityStatus( Contract::STATE_COMPATIBLE, 'ok' );
	}

	/**
	 * SB11 accessor for consumers/tests — contract unchanged.
	 */
	public function relationships(): ?LanguageRelationshipService {
		return $this->relationships;
	}

	/**
	 * Extract explicit Rank Math SEO fields for a post (and hosted term units).
	 *
	 * @param WP_Post $post Canonical post.
	 * @return list<TranslationUnitDescriptor>
	 */
	public function extract_for_post( WP_Post $post ): array {
		if ( ! $this->get_compatibility()->allows_operation() ) {
			return array();
		}

		$units = array();
		$seen  = array();

		foreach ( $this->extract_post_units( $post ) as $unit ) {
			$this->append_unique( $units, $seen, $unit );
		}

		if ( $this->is_shop_host( $post ) ) {
			foreach ( $this->extract_term_units_for_taxonomies( array( 'product_cat', 'product_tag' ) ) as $unit ) {
				$this->append_unique( $units, $seen, $unit );
			}
		}

		if ( $this->is_posts_page_host( $post ) ) {
			foreach ( $this->extract_term_units_for_taxonomies( array( 'category', 'post_tag' ) ) as $unit ) {
				$this->append_unique( $units, $seen, $unit );
			}
		}

		return $units;
	}

	/**
	 * Register Rank Math frontend/schema/token/OpenGraph/Twitter cooperation hooks.
	 *
	 * @param callable(string): (?string) $resolve Segment key → translated text.
	 */
	public function register_output_hooks( callable $resolve ): void {
		if ( ! $this->get_compatibility()->allows_overlay() ) {
			return;
		}

		add_filter(
			self::HOOK_TITLE,
			function ( $title ) use ( $resolve ) {
				return $this->overlay_frontend_string( $title, self::FIELD_TITLE, $resolve );
			},
			20
		);

		add_filter(
			self::HOOK_DESCRIPTION,
			function ( $description ) use ( $resolve ) {
				return $this->overlay_frontend_string( $description, self::FIELD_DESCRIPTION, $resolve );
			},
			20
		);

		add_filter(
			self::HOOK_REPLACEMENTS,
			function ( $replacements, $args = null ) {
				return $this->filter_replacements( $replacements, $args );
			},
			20,
			2
		);

		add_filter(
			self::HOOK_SCHEMA_ENTITY,
			function ( $entity ) use ( $resolve ) {
				return $this->overlay_schema_entity( $entity, $resolve );
			},
			20
		);

		// A.SEOd — OpenGraph / Twitter (official Rank Math seams only).
		add_filter(
			self::HOOK_OG_TITLE,
			function ( $title ) use ( $resolve ) {
				return $this->overlay_social_text(
					$title,
					$resolve,
					self::FIELD_FACEBOOK_TITLE,
					self::META_FACEBOOK_TITLE,
					self::FIELD_TITLE
				);
			},
			20
		);

		add_filter(
			self::HOOK_OG_DESCRIPTION,
			function ( $description ) use ( $resolve ) {
				return $this->overlay_social_text(
					$description,
					$resolve,
					self::FIELD_FACEBOOK_DESCRIPTION,
					self::META_FACEBOOK_DESCRIPTION,
					self::FIELD_DESCRIPTION
				);
			},
			20
		);

		add_filter(
			self::HOOK_TWITTER_TITLE,
			function ( $title ) use ( $resolve ) {
				return $this->overlay_twitter_text( $title, $resolve, self::FIELD_TITLE, self::FIELD_FACEBOOK_TITLE, self::META_FACEBOOK_TITLE, self::FIELD_TWITTER_TITLE, self::META_TWITTER_TITLE );
			},
			20
		);

		add_filter(
			self::HOOK_TWITTER_DESCRIPTION,
			function ( $description ) use ( $resolve ) {
				return $this->overlay_twitter_text( $description, $resolve, self::FIELD_DESCRIPTION, self::FIELD_FACEBOOK_DESCRIPTION, self::META_FACEBOOK_DESCRIPTION, self::FIELD_TWITTER_DESCRIPTION, self::META_TWITTER_DESCRIPTION );
			},
			20
		);

		// SD3/SD5/SD6 also register via register_public_social_hooks() so they
		// run on the default language (IntegrationFrontendBridge skips overlays there).
		$this->register_public_social_hooks();
	}

	/**
	 * Register document-level social hooks that must run for every public language.
	 *
	 * SD3/SD5/SD6: og:url reinforce, og:locale reinforce, og:locale:alternate.
	 * Safe on the default language (no Store text overlays).
	 */
	public function register_public_social_hooks(): void {
		if ( ! $this->get_compatibility()->allows_overlay() ) {
			return;
		}

		if ( has_filter( self::HOOK_OG_URL, array( $this, 'filter_og_url' ) ) ) {
			return;
		}

		add_filter( self::HOOK_OG_URL, array( $this, 'filter_og_url' ), 20 );
		add_filter( self::HOOK_OG_LOCALE, array( $this, 'filter_og_locale' ), 20 );
		add_action( self::HOOK_OG_FACEBOOK, array( $this, 'action_emit_locale_alternates' ), 2 );
	}

	/**
	 * Register A.SEOe Rank Math sitemap overlays (must run before parse_query).
	 *
	 * Idempotent. Skips when Rank Math overlay is not allowed or SB11 is absent.
	 * Does not register sitemap providers or replace Rank Math ownership.
	 */
	public function register_sitemap_hooks(): void {
		if ( null === $this->sitemap_overlay ) {
			return;
		}
		if ( ! $this->get_compatibility()->allows_overlay() ) {
			return;
		}

		$this->sitemap_overlay->register();
	}

	/**
	 * A.SEOe sitemap overlay accessor (tests / diagnostics — not a public SEO product API).
	 */
	public function sitemap_overlay(): ?RankMathSitemapOverlay {
		return $this->sitemap_overlay;
	}

	/**
	 * Filter callback for rank_math/opengraph/url.
	 *
	 * @param mixed $url Rank Math URL.
	 * @return mixed
	 */
	public function filter_og_url( $url ) {
		return $this->reinforce_og_url( $url );
	}

	/**
	 * Filter callback for rank_math/opengraph/facebook/og_locale.
	 *
	 * @param mixed $locale Rank Math locale.
	 * @return mixed
	 */
	public function filter_og_locale( $locale ) {
		return $this->reinforce_og_locale( $locale );
	}

	/**
	 * Action callback for rank_math/opengraph/facebook locale alternates.
	 *
	 * @param mixed $opengraph Rank Math OpenGraph object.
	 */
	public function action_emit_locale_alternates( $opengraph = null ): void {
		$this->emit_locale_alternates( $opengraph );
	}

	/**
	 * Test helper: mutate simulated plugin state.
	 *
	 * @param bool|null   $installed     Installed.
	 * @param bool|null   $active        Active.
	 * @param string|null $version       Version.
	 * @param bool|null   $disabled      Disabled.
	 * @param bool|null   $hooks_present Hooks present.
	 */
	public function configure(
		?bool $installed = null,
		?bool $active = null,
		?string $version = null,
		?bool $disabled = null,
		?bool $hooks_present = null
	): void {
		if ( null !== $installed ) {
			$this->installed = $installed;
		}
		if ( null !== $active ) {
			$this->active = $active;
		}
		if ( null !== $version ) {
			$this->version = $version;
		}
		if ( null !== $disabled ) {
			$this->disabled = $disabled;
		}
		if ( null !== $hooks_present ) {
			$this->hooks_present = $hooks_present;
		}
	}

	/**
	 * Whether a Rank Math SEO field value is a stable literal (no variables).
	 *
	 * @param string $value Raw meta.
	 */
	public static function is_literal_seo_field( string $value ): bool {
		$value = trim( $value );
		if ( '' === $value ) {
			return false;
		}
		return 1 !== preg_match( '/%[a-z0-9_]+(?:\([^)]*\))?%/i', $value );
	}

	/**
	 * Build a Rank Math PluginIdentity key.
	 *
	 * @param string $owner_type Owner type.
	 * @param string $owner_id   Owner id.
	 * @param string $field      Field.
	 */
	public function build_key( string $owner_type, string $owner_id, string $field ): string {
		return $this->identity->build( self::ID, $owner_type, $owner_id, $field );
	}

	/**
	 * Extract explicit Rank Math SEO units for one post/product/page.
	 *
	 * @param WP_Post $post Post.
	 * @return list<TranslationUnitDescriptor>
	 */
	private function extract_post_units( WP_Post $post ): array {
		$units   = array();
		$post_id = (int) $post->ID;
		if ( $post_id <= 0 ) {
			return $units;
		}

		$title = $this->read_post_meta( $post_id, self::META_TITLE );
		if ( self::is_literal_seo_field( $title ) ) {
			$unit = $this->make_unit(
				self::OWNER_POST,
				(string) $post_id,
				self::FIELD_TITLE,
				$title,
				'Rank Math SEO title',
				(string) $post->post_type
			);
			if ( null !== $unit ) {
				$units[] = $unit;
			}
		}

		$description = $this->read_post_meta( $post_id, self::META_DESCRIPTION );
		if ( self::is_literal_seo_field( $description ) ) {
			$unit = $this->make_unit(
				self::OWNER_POST,
				(string) $post_id,
				self::FIELD_DESCRIPTION,
				$description,
				'Rank Math SEO description',
				(string) $post->post_type
			);
			if ( null !== $unit ) {
				$units[] = $unit;
			}
		}

		foreach (
			array(
				array( self::META_FACEBOOK_TITLE, self::FIELD_FACEBOOK_TITLE, 'Rank Math Facebook title' ),
				array( self::META_FACEBOOK_DESCRIPTION, self::FIELD_FACEBOOK_DESCRIPTION, 'Rank Math Facebook description' ),
				array( self::META_TWITTER_TITLE, self::FIELD_TWITTER_TITLE, 'Rank Math Twitter title' ),
				array( self::META_TWITTER_DESCRIPTION, self::FIELD_TWITTER_DESCRIPTION, 'Rank Math Twitter description' ),
			) as $social
		) {
			$raw = $this->read_post_meta( $post_id, $social[0] );
			if ( ! self::is_literal_seo_field( $raw ) ) {
				continue;
			}
			$unit = $this->make_unit(
				self::OWNER_POST,
				(string) $post_id,
				$social[1],
				$raw,
				$social[2],
				(string) $post->post_type
			);
			if ( null !== $unit ) {
				$units[] = $unit;
			}
		}

		return $units;
	}

	/**
	 * Extract explicit Rank Math SEO units for admitted taxonomies.
	 *
	 * @param array<int, string> $taxonomies Taxonomies.
	 * @return list<TranslationUnitDescriptor>
	 */
	private function extract_term_units_for_taxonomies( array $taxonomies ): array {
		$units = array();
		foreach ( $taxonomies as $taxonomy ) {
			if ( ! in_array( $taxonomy, self::TERM_TAXONOMIES, true ) ) {
				continue;
			}
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'number'     => 0,
				)
			);
			if ( ! is_array( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				if ( ! $term instanceof WP_Term ) {
					continue;
				}
				$term_id = (int) $term->term_id;
				if ( $term_id <= 0 ) {
					continue;
				}

				$title = $this->read_term_meta( $term_id, self::META_TITLE );
				if ( self::is_literal_seo_field( $title ) ) {
					$unit = $this->make_unit(
						self::OWNER_TERM,
						(string) $term_id,
						self::FIELD_TITLE,
						$title,
						'Rank Math term SEO title',
						$taxonomy
					);
					if ( null !== $unit ) {
						$units[] = $unit;
					}
				}

				$description = $this->read_term_meta( $term_id, self::META_DESCRIPTION );
				if ( self::is_literal_seo_field( $description ) ) {
					$unit = $this->make_unit(
						self::OWNER_TERM,
						(string) $term_id,
						self::FIELD_DESCRIPTION,
						$description,
						'Rank Math term SEO description',
						$taxonomy
					);
					if ( null !== $unit ) {
						$units[] = $unit;
					}
				}

				foreach (
					array(
						array( self::META_FACEBOOK_TITLE, self::FIELD_FACEBOOK_TITLE, 'Rank Math term Facebook title' ),
						array( self::META_FACEBOOK_DESCRIPTION, self::FIELD_FACEBOOK_DESCRIPTION, 'Rank Math term Facebook description' ),
						array( self::META_TWITTER_TITLE, self::FIELD_TWITTER_TITLE, 'Rank Math term Twitter title' ),
						array( self::META_TWITTER_DESCRIPTION, self::FIELD_TWITTER_DESCRIPTION, 'Rank Math term Twitter description' ),
					) as $social
				) {
					$raw = $this->read_term_meta( $term_id, $social[0] );
					if ( ! self::is_literal_seo_field( $raw ) ) {
						continue;
					}
					$unit = $this->make_unit(
						self::OWNER_TERM,
						(string) $term_id,
						$social[1],
						$raw,
						$social[2],
						$taxonomy
					);
					if ( null !== $unit ) {
						$units[] = $unit;
					}
				}
			}
		}
		return $units;
	}

	/**
	 * Build one Rank Math translation unit descriptor.
	 *
	 * @param string $owner_type Owner type.
	 * @param string $owner_id   Owner id.
	 * @param string $field      Field.
	 * @param string $source     Source text.
	 * @param string $label      Label.
	 * @param string $context    Parent context.
	 */
	private function make_unit(
		string $owner_type,
		string $owner_id,
		string $field,
		string $source,
		string $label,
		string $context
	): ?TranslationUnitDescriptor {
		$text = IntegrationSecurity::sanitize_plain( $source );
		if ( '' === $text ) {
			return null;
		}
		try {
			$key = $this->build_key( $owner_type, $owner_id, $field );
		} catch ( \InvalidArgumentException $e ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			return null;
		}

		return new TranslationUnitDescriptor(
			$key,
			$text,
			Store::source_hash( $text, Store::FORMAT_PLAIN ),
			Store::FORMAT_PLAIN,
			Contract::OWNERSHIP_RECORD,
			$owner_type,
			$owner_id,
			$field,
			$label,
			self::ID,
			$context
		);
	}

	/**
	 * Append a unit when its segment key has not been seen.
	 *
	 * @param array<int, TranslationUnitDescriptor> $units Units.
	 * @param array<string, true>                   $seen  Seen keys.
	 * @param TranslationUnitDescriptor             $unit  Unit.
	 */
	private function append_unique( array &$units, array &$seen, TranslationUnitDescriptor $unit ): void {
		if ( isset( $seen[ $unit->segment_key ] ) ) {
			return;
		}
		$seen[ $unit->segment_key ] = true;
		$units[]                    = $unit;
	}

	/**
	 * Overlay Rank Math frontend title/description when an explicit Store hit exists.
	 *
	 * @param mixed                       $value   Rank Math resolved string.
	 * @param string                      $field   title|description.
	 * @param callable(string): (?string) $resolve Resolver.
	 * @return mixed
	 */
	private function overlay_frontend_string( $value, string $field, callable $resolve ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}

		$key = $this->current_seo_segment_key( $field );
		if ( null === $key ) {
			return $value;
		}

		$translated = $resolve( $key );
		if ( null === $translated || '' === $translated ) {
			return $value;
		}

		return IntegrationSecurity::sanitize_plain( $translated );
	}

	/**
	 * Overlay OpenGraph text: explicit Facebook field first, else A.SEOc SEO identity.
	 *
	 * @param mixed                       $value          Rank Math tag content.
	 * @param callable(string): (?string) $resolve        Resolver.
	 * @param string                      $social_field   PluginIdentity social field.
	 * @param string                      $social_meta    Rank Math social meta key.
	 * @param string                      $seo_field      title|description fallback.
	 * @return mixed
	 */
	private function overlay_social_text( $value, callable $resolve, string $social_field, string $social_meta, string $seo_field ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}

		$key = $this->current_social_segment_key( $social_field, $social_meta );
		if ( null !== $key ) {
			$translated = $resolve( $key );
			if ( null !== $translated && '' !== $translated ) {
				return IntegrationSecurity::sanitize_plain( $translated );
			}
		}

		return $this->overlay_frontend_string( $value, $seo_field, $resolve );
	}

	/**
	 * Overlay Twitter text respecting Rank Math facebook-reuse default.
	 *
	 * @param mixed                       $value            Tag content.
	 * @param callable(string): (?string) $resolve          Resolver.
	 * @param string                      $seo_field        SEO fallback field.
	 * @param string                      $facebook_field   Facebook identity field.
	 * @param string                      $facebook_meta    Facebook meta key.
	 * @param string                      $twitter_field    Twitter identity field.
	 * @param string                      $twitter_meta     Twitter meta key.
	 * @return mixed
	 */
	private function overlay_twitter_text(
		$value,
		callable $resolve,
		string $seo_field,
		string $facebook_field,
		string $facebook_meta,
		string $twitter_field,
		string $twitter_meta
	) {
		if ( ! is_string( $value ) ) {
			return $value;
		}

		if ( $this->current_twitter_uses_facebook() ) {
			return $this->overlay_social_text( $value, $resolve, $facebook_field, $facebook_meta, $seo_field );
		}

		return $this->overlay_social_text( $value, $resolve, $twitter_field, $twitter_meta, $seo_field );
	}

	/**
	 * Reinforce og:url with SB11 current public absolute URL when available.
	 *
	 * @param mixed $url Rank Math URL.
	 * @return mixed
	 */
	private function reinforce_og_url( $url ) {
		if ( null === $this->relationships ) {
			return $url;
		}
		$current = $this->relationships->current_public();
		if ( null === $current || '' === $current->url ) {
			return $url;
		}
		return $current->url;
	}

	/**
	 * Reinforce og:locale from LanguageContext locale (Facebook underscore form).
	 *
	 * @param mixed $locale Rank Math locale.
	 * @return mixed
	 */
	private function reinforce_og_locale( $locale ) {
		$language = $this->context->current();
		if ( null === $language ) {
			return $locale;
		}
		$candidate = str_replace( '-', '_', (string) ( $language->locale ?? '' ) );
		if ( '' === $candidate ) {
			return $locale;
		}
		return $candidate;
	}

	/**
	 * Emit og:locale:alternate for published SB11 languages except current (SD6/SD11).
	 *
	 * @param mixed $opengraph Rank Math OpenGraph network object.
	 */
	private function emit_locale_alternates( $opengraph ): void {
		if ( null === $this->relationships || ! is_object( $opengraph ) || ! method_exists( $opengraph, 'tag' ) ) {
			return;
		}

		$seen = array();
		foreach ( $this->relationships->for_public_request() as $rel ) {
			if ( $rel->is_current ) {
				continue;
			}
			$fb_locale = str_replace( '-', '_', $rel->hreflang );
			if ( '' === $fb_locale || isset( $seen[ $fb_locale ] ) ) {
				continue;
			}
			$seen[ $fb_locale ] = true;
			$opengraph->tag( 'og:locale:alternate', $fb_locale );
		}
	}

	/**
	 * Whether Twitter should reuse Facebook meta (Rank Math default true when empty).
	 */
	private function current_twitter_uses_facebook(): bool {
		$obj = function_exists( 'get_queried_object' ) ? get_queried_object() : null;
		$raw = '';
		if ( $obj instanceof WP_Post ) {
			$raw = $this->read_post_meta( (int) $obj->ID, self::META_TWITTER_USE_FACEBOOK );
		} elseif ( $obj instanceof WP_Term || ( is_object( $obj ) && isset( $obj->term_id ) ) ) {
			$raw = $this->read_term_meta( (int) $obj->term_id, self::META_TWITTER_USE_FACEBOOK );
		}

		if ( '' === $raw ) {
			return true;
		}

		$normalized = strtolower( trim( $raw ) );
		return ! in_array( $normalized, array( '0', 'off', 'false', 'no' ), true );
	}

	/**
	 * Segment key for an explicit social meta field when literal meta is present.
	 *
	 * @param string $field    PluginIdentity field.
	 * @param string $meta_key Rank Math meta key.
	 */
	private function current_social_segment_key( string $field, string $meta_key ): ?string {
		$obj = function_exists( 'get_queried_object' ) ? get_queried_object() : null;

		if ( $obj instanceof WP_Post ) {
			$raw = $this->read_post_meta( (int) $obj->ID, $meta_key );
			if ( ! self::is_literal_seo_field( $raw ) ) {
				return null;
			}
			try {
				return $this->build_key( self::OWNER_POST, (string) (int) $obj->ID, $field );
			} catch ( \InvalidArgumentException $e ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				return null;
			}
		}

		if ( $obj instanceof WP_Term || ( is_object( $obj ) && isset( $obj->term_id, $obj->taxonomy ) ) ) {
			$term_id  = (int) $obj->term_id;
			$taxonomy = (string) $obj->taxonomy;
			if ( $term_id <= 0 || ! in_array( $taxonomy, self::TERM_TAXONOMIES, true ) ) {
				return null;
			}
			$raw = $this->read_term_meta( $term_id, $meta_key );
			if ( ! self::is_literal_seo_field( $raw ) ) {
				return null;
			}
			try {
				return $this->build_key( self::OWNER_TERM, (string) $term_id, $field );
			} catch ( \InvalidArgumentException $e ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				return null;
			}
		}

		return null;
	}

	/**
	 * SC7/SC8: ensure content tokens inherit existing Store translations.
	 *
	 * @param mixed $replacements Replacement map.
	 * @param mixed $args         Rank Math args object.
	 * @return mixed
	 */
	private function filter_replacements( $replacements, $args ) {
		if ( ! is_array( $replacements ) ) {
			return $replacements;
		}

		$language = $this->context->current();
		if ( null === $language || $this->context->is_default() ) {
			return $replacements;
		}
		$language_id = (int) $language->language_id;

		$post_id = 0;
		if ( is_object( $args ) && isset( $args->ID ) ) {
			$post_id = (int) $args->ID;
		}
		if ( $post_id <= 0 && function_exists( 'get_queried_object_id' ) ) {
			$obj = get_queried_object();
			if ( $obj instanceof WP_Post ) {
				$post_id = (int) $obj->ID;
			}
		}

		if ( $post_id > 0 ) {
			if ( isset( $replacements['%title%'] ) ) {
				$t = $this->store->translated_value( Store::SOURCE_POST, $post_id, $language_id, Extractor::FIELD_TITLE );
				if ( null !== $t && '' !== $t ) {
					$replacements['%title%'] = IntegrationSecurity::sanitize_plain( $t );
				}
			}
			if ( isset( $replacements['%excerpt%'] ) ) {
				$t = $this->store->translated_value( Store::SOURCE_POST, $post_id, $language_id, Extractor::FIELD_EXCERPT );
				if ( null !== $t && '' !== $t ) {
					$replacements['%excerpt%'] = IntegrationSecurity::sanitize_plain( $t );
				}
			}
			if ( isset( $replacements['%wc_shortdesc%'] ) ) {
				$t = $this->store->translated_value( Store::SOURCE_POST, $post_id, $language_id, Extractor::FIELD_EXCERPT );
				if ( null !== $t && '' !== $t ) {
					$replacements['%wc_shortdesc%'] = IntegrationSecurity::sanitize_plain( $t );
				}
			}
		}

		$term = $this->current_term();
		if ( null !== $term ) {
			// Term name/description content overlays are owned by Woo/WP paths when present.
			// Rank Math %term% / %term_description% inherit those Store hits via shop/posts hosts
			// only when an explicit Rank Math SEO field is absent — no second SEO identity here.
			unset( $term );
		}

		return $replacements;
	}

	/**
	 * SC9: overlay schema name/description when they mirror admitted SEO fields.
	 *
	 * @param mixed                       $entity  Schema entity.
	 * @param callable(string): (?string) $resolve Resolver.
	 * @return mixed
	 */
	private function overlay_schema_entity( $entity, callable $resolve ) {
		if ( ! is_array( $entity ) ) {
			return $entity;
		}

		foreach ( array( 'name', 'headline', 'description' ) as $prop ) {
			if ( ! isset( $entity[ $prop ] ) || ! is_string( $entity[ $prop ] ) ) {
				continue;
			}
			$field = ( 'description' === $prop ) ? self::FIELD_DESCRIPTION : self::FIELD_TITLE;
			$key   = $this->current_seo_segment_key( $field );
			if ( null === $key ) {
				continue;
			}
			$translated = $resolve( $key );
			if ( null === $translated || '' === $translated ) {
				continue;
			}
			$entity[ $prop ] = IntegrationSecurity::sanitize_plain( $translated );
		}

		// Machine / URL properties intentionally untouched (price, sku, @id, url, …).
		return $entity;
	}

	/**
	 * Current document SEO segment key for an admitted field.
	 *
	 * @param string $field title|description.
	 */
	private function current_seo_segment_key( string $field ): ?string {
		$obj = function_exists( 'get_queried_object' ) ? get_queried_object() : null;

		if ( $obj instanceof WP_Post ) {
			$meta_key = ( self::FIELD_TITLE === $field ) ? self::META_TITLE : self::META_DESCRIPTION;
			$raw      = $this->read_post_meta( (int) $obj->ID, $meta_key );
			if ( ! self::is_literal_seo_field( $raw ) ) {
				return null;
			}
			try {
				return $this->build_key( self::OWNER_POST, (string) (int) $obj->ID, $field );
			} catch ( \InvalidArgumentException $e ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				return null;
			}
		}

		if ( $obj instanceof WP_Term || ( is_object( $obj ) && isset( $obj->term_id, $obj->taxonomy ) ) ) {
			$term_id  = (int) $obj->term_id;
			$taxonomy = (string) $obj->taxonomy;
			if ( $term_id <= 0 || ! in_array( $taxonomy, self::TERM_TAXONOMIES, true ) ) {
				return null;
			}
			$meta_key = ( self::FIELD_TITLE === $field ) ? self::META_TITLE : self::META_DESCRIPTION;
			$raw      = $this->read_term_meta( $term_id, $meta_key );
			if ( ! self::is_literal_seo_field( $raw ) ) {
				return null;
			}
			try {
				return $this->build_key( self::OWNER_TERM, (string) $term_id, $field );
			} catch ( \InvalidArgumentException $e ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				return null;
			}
		}

		return null;
	}

	/**
	 * Current queried term when on a taxonomy archive.
	 *
	 * @return WP_Term|null
	 */
	private function current_term(): ?WP_Term {
		$obj = function_exists( 'get_queried_object' ) ? get_queried_object() : null;
		return $obj instanceof WP_Term ? $obj : null;
	}

	/**
	 * Whether the post is the WooCommerce shop host page.
	 *
	 * @param WP_Post $post Post.
	 */
	private function is_shop_host( WP_Post $post ): bool {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return false;
		}
		$shop_id = (int) wc_get_page_id( 'shop' );
		return $shop_id > 0 && (int) $post->ID === $shop_id;
	}

	/**
	 * Whether the post is the posts page host for category SEO units.
	 *
	 * @param WP_Post $post Post.
	 */
	private function is_posts_page_host( WP_Post $post ): bool {
		$posts_page = (int) get_option( 'page_for_posts' );
		return $posts_page > 0 && (int) $post->ID === $posts_page;
	}

	/**
	 * Read a Rank Math post meta string.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 */
	private function read_post_meta( int $post_id, string $key ): string {
		if ( $post_id <= 0 || ! function_exists( 'get_post_meta' ) ) {
			return '';
		}
		$value = get_post_meta( $post_id, $key, true );
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Read a Rank Math term meta string.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $key     Meta key.
	 */
	private function read_term_meta( int $term_id, string $key ): string {
		if ( $term_id <= 0 || ! function_exists( 'get_term_meta' ) ) {
			return '';
		}
		$value = get_term_meta( $term_id, $key, true );
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Whether Rank Math plugin files are installed.
	 */
	private function is_installed(): bool {
		if ( null !== $this->installed ) {
			return $this->installed;
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = function_exists( 'get_plugins' ) ? get_plugins() : array();
		return isset( $plugins[ self::PLUGIN_BASENAME ] );
	}

	/**
	 * Whether Rank Math is active.
	 */
	private function is_active(): bool {
		if ( null !== $this->active ) {
			return $this->active;
		}
		return function_exists( 'is_plugin_active' )
			? is_plugin_active( self::PLUGIN_BASENAME )
			: class_exists( '\RankMath\Helper', false );
	}

	/**
	 * Whether the AIML Rank Math integration is disabled.
	 */
	private function is_disabled(): bool {
		if ( null !== $this->disabled ) {
			return $this->disabled;
		}
		/**
		 * Disable the Rank Math AIML integration.
		 *
		 * @since 1.1.0
		 *
		 * @param bool $disabled Whether disabled.
		 */
		return (bool) apply_filters( 'aiml_rankmath_integration_disabled', false );
	}

	/**
	 * Resolved Rank Math plugin version string.
	 */
	private function resolved_version(): string {
		if ( null !== $this->version ) {
			return $this->version;
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = function_exists( 'get_plugins' ) ? get_plugins() : array();
		if ( isset( $plugins[ self::PLUGIN_BASENAME ]['Version'] ) ) {
			return (string) $plugins[ self::PLUGIN_BASENAME ]['Version'];
		}
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return (string) RANK_MATH_VERSION;
		}
		return '0.0.0';
	}

	/**
	 * Whether required Rank Math frontend/schema seams are available.
	 */
	private function required_hooks_present(): bool {
		if ( null !== $this->hooks_present ) {
			return $this->hooks_present;
		}
		// Filters exist in Rank Math source even before runtime registration.
		return class_exists( '\RankMath\Paper\Paper', false )
			|| class_exists( '\RankMath\Helper', false )
			|| $this->is_active();
	}
}
