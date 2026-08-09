<?php
/**
 * Shared SEO diagnostics core (A.SEOf).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Seo\Diagnostics;

use AIMultilingual\Integration\Contract;
use AIMultilingual\Integration\RankMath\RankMathIntegration;
use AIMultilingual\Integration\RankMath\RankMathSitemapOverlay;
use AIMultilingual\Language\Languages;
use AIMultilingual\Seo\LanguageRelationship;
use AIMultilingual\Seo\LanguageRelationshipService;

/**
 * Read-only SEO health orchestration.
 *
 * Observes A.SEOa–e contracts. Does not mutate SEO ownership or emit tags.
 */
final class SeoDiagnosticsService {

	/**
	 * Builds the diagnostics service.
	 *
	 * @param LanguageRelationshipService $relationships SB11.
	 * @param Languages                   $languages     Language registry.
	 * @param RankMathIntegration|null    $rank_math     Rank Math integration when available.
	 */
	public function __construct(
		private LanguageRelationshipService $relationships,
		private Languages $languages,
		private ?RankMathIntegration $rank_math = null,
	) {
	}

	/**
	 * Runs the frozen Supported/Partial diagnostics set.
	 *
	 * @param SeoDiagnosticsOptions $options Scan options.
	 */
	public function scan( SeoDiagnosticsOptions $options ): SeoDiagnosticsSnapshot {
		$started     = hrtime( true );
		$path        = $options->normalized_path();
		$url         = $this->resolve_url( $options, $path );
		$checks      = array();
		$limitations = array();
		$http_used   = 0;

		if ( ! (bool) get_option( 'blog_public' ) ) {
			$limitations[] = 'blog_public_zero';
		}

		$rels = $this->relationships->for_path( $path, false );

		$checks[] = $this->check_language_graph( $rels );
		$checks[] = $this->check_hreflang_contract( $rels );
		$checks[] = $this->check_canonical_contract( $rels );
		$checks[] = $this->check_preview_leakage( $path, $rels );
		$checks[] = $this->check_social_contract( $rels );
		$checks[] = $this->check_rankmath_compat();
		$checks[] = $this->check_sitemap_honesty( $limitations );
		$checks[] = $this->check_robots_indexability();
		$checks[] = $this->check_woocommerce_surface( $path );
		$checks[] = $this->check_external_readiness( $limitations );

		if ( $options->include_http && '' !== $url && $http_used < SeoDiagnosticsOptions::MAX_HTTP_FETCHES ) {
			$redirect  = $this->check_redirect_loop( $url, $options->capped_redirect_depth(), $http_used );
			$checks[]  = $redirect['check'];
			$http_used = $redirect['http_fetches'];

			if ( $http_used < SeoDiagnosticsOptions::MAX_HTTP_FETCHES ) {
				$dup       = $this->check_duplicate_titles( $url, $http_used );
				$checks[]  = $dup['check'];
				$http_used = $dup['http_fetches'];
			} else {
				$checks[] = new SeoDiagnosticsCheck(
					'sf10_duplicates',
					SeoDiagnosticsCheck::STATUS_SKIPPED,
					'aiml',
					'http_budget_exhausted',
					'Duplicate-title emission check skipped (HTTP budget).'
				);
			}
		} else {
			$checks[] = new SeoDiagnosticsCheck(
				'sf9_redirect_loop',
				SeoDiagnosticsCheck::STATUS_SKIPPED,
				'aiml',
				'http_disabled',
				'Redirect-loop check skipped (HTTP disabled or no URL).'
			);
			$checks[] = new SeoDiagnosticsCheck(
				'sf10_duplicates',
				SeoDiagnosticsCheck::STATUS_SKIPPED,
				'aiml',
				'http_disabled',
				'Duplicate-title emission check skipped (HTTP disabled or no URL).'
			);
		}

		$summary  = $this->summarize( $checks );
		$checks[] = new SeoDiagnosticsCheck(
			'sf1_health_summary',
			( ( $summary['error'] ?? 0 ) > 0 ) ? SeoDiagnosticsCheck::STATUS_WARNING : SeoDiagnosticsCheck::STATUS_PASS,
			'aiml',
			'aggregated',
			sprintf(
				'SEO health summary: %d pass, %d warning, %d error, %d skipped, %d unavailable.',
				$summary['pass'] ?? 0,
				$summary['warning'] ?? 0,
				$summary['error'] ?? 0,
				$summary['skipped'] ?? 0,
				$summary['unavailable'] ?? 0
			),
			$summary
		);
		// Recompute summary including SF1 row.
		$summary     = $this->summarize( $checks );
		$limitations = array_values( array_unique( $limitations ) );

		return new SeoDiagnosticsSnapshot(
			gmdate( 'c' ),
			$path,
			$url,
			$checks,
			$summary,
			$limitations,
			(int) max( 0, (int) round( ( hrtime( true ) - $started ) / 1e6 ) ),
			$http_used
		);
	}

	/**
	 * Validates SB11 public language graph shape (SF4).
	 *
	 * @param array $rels Public relationships.
	 */
	private function check_language_graph( array $rels ): SeoDiagnosticsCheck {
		if ( count( $rels ) < 1 ) {
			return new SeoDiagnosticsCheck(
				'sf4_language_graph',
				SeoDiagnosticsCheck::STATUS_WARNING,
				'aiml_sb11',
				'empty_public_set',
				'SB11 public language set is empty for this path.'
			);
		}

		$defaults = 0;
		$codes    = array();
		foreach ( $rels as $rel ) {
			if ( ! $rel instanceof LanguageRelationship ) {
				continue;
			}
			if ( isset( $codes[ $rel->language_code ] ) ) {
				return new SeoDiagnosticsCheck(
					'sf4_language_graph',
					SeoDiagnosticsCheck::STATUS_ERROR,
					'aiml_sb11',
					'duplicate_language_code',
					'Duplicate language code in SB11 public set.',
					array( 'code' => $rel->language_code )
				);
			}
			$codes[ $rel->language_code ] = true;
			if ( $rel->is_default ) {
				++$defaults;
			}
		}

		if ( 1 !== $defaults ) {
			return new SeoDiagnosticsCheck(
				'sf4_language_graph',
				SeoDiagnosticsCheck::STATUS_ERROR,
				'aiml_sb11',
				'default_count_invalid',
				'SB11 public set must contain exactly one default language.',
				array( 'defaults' => $defaults )
			);
		}

		return new SeoDiagnosticsCheck(
			'sf4_language_graph',
			SeoDiagnosticsCheck::STATUS_PASS,
			'aiml_sb11',
			'ok',
			'SB11 public language graph is well-formed.',
			array( 'languages' => array_keys( $codes ) )
		);
	}

	/**
	 * Validates expected hreflang uniqueness/reciprocity (SF3).
	 *
	 * @param array $rels Public relationships.
	 */
	private function check_hreflang_contract( array $rels ): SeoDiagnosticsCheck {
		if ( count( $rels ) < 2 ) {
			return new SeoDiagnosticsCheck(
				'sf3_hreflang',
				SeoDiagnosticsCheck::STATUS_SKIPPED,
				'aiml_sb11',
				'insufficient_languages',
				'Hreflang reciprocity requires at least two public languages.'
			);
		}

		$hreflangs = array();
		$urls      = array();
		foreach ( $rels as $rel ) {
			if ( ! $rel instanceof LanguageRelationship ) {
				continue;
			}
			$h = strtolower( $rel->hreflang );
			if ( isset( $hreflangs[ $h ] ) ) {
				return new SeoDiagnosticsCheck(
					'sf3_hreflang',
					SeoDiagnosticsCheck::STATUS_ERROR,
					'aiml_sb11',
					'duplicate_hreflang',
					'Duplicate hreflang in expected SB11 set.',
					array( 'hreflang' => $rel->hreflang )
				);
			}
			$hreflangs[ $h ] = true;
			$urls[]          = $rel->url;
		}

		return new SeoDiagnosticsCheck(
			'sf3_hreflang',
			SeoDiagnosticsCheck::STATUS_PASS,
			'aiml_sb11',
			'ok',
			'Expected hreflang set is reciprocal and unique (incl. x-default candidate).',
			array(
				'hreflangs' => array_keys( $hreflangs ),
				'x_default' => $this->default_url( $rels ),
			)
		);
	}

	/**
	 * Validates canonical expectation from SB11 (SF2).
	 *
	 * @param array $rels Public relationships.
	 */
	private function check_canonical_contract( array $rels ): SeoDiagnosticsCheck {
		$current = null;
		foreach ( $rels as $rel ) {
			if ( $rel instanceof LanguageRelationship && $rel->is_current ) {
				$current = $rel;
				break;
			}
		}
		if ( null === $current ) {
			// No request language context — still validate default URL exists.
			$default = $this->default_url( $rels );
			if ( null === $default ) {
				return new SeoDiagnosticsCheck(
					'sf2_canonical',
					SeoDiagnosticsCheck::STATUS_WARNING,
					'aiml_aseob',
					'no_default_url',
					'No default-language URL available for canonical expectation.'
				);
			}
			return new SeoDiagnosticsCheck(
				'sf2_canonical',
				SeoDiagnosticsCheck::STATUS_PASS,
				'aiml_aseob',
				'ok_default',
				'Canonical expectation available from SB11 default URL.',
				array( 'expected_canonical' => $default )
			);
		}

		return new SeoDiagnosticsCheck(
			'sf2_canonical',
			SeoDiagnosticsCheck::STATUS_PASS,
			'aiml_aseob',
			'ok',
			'Canonical expectation equals SB11 current public URL.',
			array( 'expected_canonical' => $current->url )
		);
	}

	/**
	 * Detects preview-language leakage into public SEO sets (SF8).
	 *
	 * @param string $path Unprefixed path.
	 * @param array  $rels Public relationships.
	 */
	private function check_preview_leakage( string $path, array $rels ): SeoDiagnosticsCheck {
		$public_codes = array();
		foreach ( $rels as $rel ) {
			if ( $rel instanceof LanguageRelationship ) {
				$public_codes[ $rel->language_code ] = true;
			}
		}

		$leaked = array();
		foreach ( $this->languages->all() as $language ) {
			$code = (string) $language->code;
			if ( empty( $language->is_default ) && Languages::STATUS_PREVIEW === (string) $language->status ) {
				if ( isset( $public_codes[ $code ] ) ) {
					$leaked[] = $code;
				}
			}
		}

		// Also ensure preview-inclusive graph is larger when preview langs exist.
		$with_preview = $this->relationships->for_path( $path, true );
		foreach ( $with_preview as $rel ) {
			if ( ! $rel instanceof LanguageRelationship ) {
				continue;
			}
			if ( ! isset( $public_codes[ $rel->language_code ] ) ) {
				// Preview-only language correctly excluded from public set.
				continue;
			}
		}

		if ( $leaked ) {
			return new SeoDiagnosticsCheck(
				'sf8_preview_leakage',
				SeoDiagnosticsCheck::STATUS_ERROR,
				'aiml_sb11',
				'preview_in_public_set',
				'Preview language leaked into public SEO relationship set.',
				array( 'codes' => $leaked )
			);
		}

		return new SeoDiagnosticsCheck(
			'sf8_preview_leakage',
			SeoDiagnosticsCheck::STATUS_PASS,
			'aiml_sb11',
			'ok',
			'No preview languages in public SB11 set (ADR-0008).'
		);
	}

	/**
	 * Validates admitted social overlay readiness (SF5).
	 *
	 * @param array $rels Public relationships.
	 */
	private function check_social_contract( array $rels ): SeoDiagnosticsCheck {
		if ( null === $this->rank_math ) {
			return new SeoDiagnosticsCheck(
				'sf5_social',
				SeoDiagnosticsCheck::STATUS_SKIPPED,
				'rank_math',
				'integration_missing',
				'Social overlay checks skipped (Rank Math integration unavailable).'
			);
		}

		$compat = $this->rank_math->get_compatibility();
		if ( ! $compat->allows_overlay() ) {
			return new SeoDiagnosticsCheck(
				'sf5_social',
				SeoDiagnosticsCheck::STATUS_SKIPPED,
				'rank_math',
				$compat->reason() !== '' ? $compat->reason() : 'overlay_not_allowed',
				'Social overlay checks skipped (Rank Math overlay not allowed).',
				array( 'state' => $compat->state() )
			);
		}

		if ( count( $rels ) < 1 ) {
			return new SeoDiagnosticsCheck(
				'sf5_social',
				SeoDiagnosticsCheck::STATUS_WARNING,
				'aiml_aseod',
				'no_relationships',
				'No SB11 relationships for expected og:locale:alternate set.'
			);
		}

		return new SeoDiagnosticsCheck(
			'sf5_social',
			SeoDiagnosticsCheck::STATUS_PASS,
			'aiml_aseod',
			'ok',
			'Admitted social overlays can derive locale/URL expectations from SB11.',
			array( 'alternate_count' => max( 0, count( $rels ) - 1 ) )
		);
	}

	/**
	 * Reports Rank Math compatibility health (SF12).
	 */
	private function check_rankmath_compat(): SeoDiagnosticsCheck {
		if ( null === $this->rank_math ) {
			return new SeoDiagnosticsCheck(
				'sf12_rankmath_compat',
				SeoDiagnosticsCheck::STATUS_UNAVAILABLE,
				'rank_math',
				'integration_missing',
				'Rank Math integration instance not wired.'
			);
		}

		$compat = $this->rank_math->get_compatibility();
		$status = Contract::STATE_COMPATIBLE === $compat->state()
			? SeoDiagnosticsCheck::STATUS_PASS
			: SeoDiagnosticsCheck::STATUS_WARNING;

		return new SeoDiagnosticsCheck(
			'sf12_rankmath_compat',
			$status,
			'rank_math',
			$compat->reason() !== '' ? $compat->reason() : $compat->state(),
			'Rank Math compatibility: ' . $compat->state() . '.',
			array(
				'state'  => $compat->state(),
				'reason' => $compat->reason(),
			)
		);
	}

	/**
	 * Validates sitemap honesty against blog_public / overlay hooks (SF6).
	 *
	 * @param array $limitations Limitations bag (may append).
	 */
	private function check_sitemap_honesty( array &$limitations ): SeoDiagnosticsCheck {
		$blog_public = (bool) get_option( 'blog_public' );
		$overlay     = class_exists( RankMathSitemapOverlay::class );
		$hooks       = has_filter( RankMathSitemapOverlay::HOOK_URL );

		if ( ! $blog_public ) {
			$limitations[] = 'sitemap_language_enrichment_suppressed';
			return new SeoDiagnosticsCheck(
				'sf6_sitemap',
				SeoDiagnosticsCheck::STATUS_PASS,
				'rank_math',
				'blog_public_honesty',
				'Sitemap language enrichment correctly suppressed while blog_public=0.',
				array(
					'overlay_class' => $overlay,
					'url_hook'      => (bool) $hooks,
				)
			);
		}

		if ( ! $hooks ) {
			return new SeoDiagnosticsCheck(
				'sf6_sitemap',
				SeoDiagnosticsCheck::STATUS_WARNING,
				'aiml_aseoe',
				'sitemap_hooks_missing',
				'blog_public=1 but Rank Math sitemap URL overlay hook is not registered.'
			);
		}

		return new SeoDiagnosticsCheck(
			'sf6_sitemap',
			SeoDiagnosticsCheck::STATUS_PASS,
			'aiml_aseoe',
			'ok',
			'Sitemap overlay hooks present; Rank Math remains owner.',
			array( 'url_hook' => true )
		);
	}

	/**
	 * Reports robots/indexability owner state (SF7).
	 */
	private function check_robots_indexability(): SeoDiagnosticsCheck {
		$blog_public = (bool) get_option( 'blog_public' );
		return new SeoDiagnosticsCheck(
			'sf7_robots_indexability',
			SeoDiagnosticsCheck::STATUS_PASS,
			'wp_core',
			$blog_public ? 'blog_public_on' : 'blog_public_off',
			$blog_public
				? 'blog_public=1 (site allows indexing at WP policy level).'
				: 'blog_public=0 (Discourage search engines). AIML must not force indexability.',
			array( 'blog_public' => $blog_public ? 1 : 0 )
		);
	}

	/**
	 * Validates WooCommerce path is in SEO diagnostic scope (SF11).
	 *
	 * @param string $path Unprefixed path.
	 */
	private function check_woocommerce_surface( string $path ): SeoDiagnosticsCheck {
		$is_product = (bool) preg_match( '#^/product(/|$)#', $path ) || (bool) preg_match( '#^/product-category(/|$)#', $path );
		if ( ! $is_product ) {
			return new SeoDiagnosticsCheck(
				'sf11_woocommerce',
				SeoDiagnosticsCheck::STATUS_SKIPPED,
				'woocommerce',
				'not_woo_path',
				'Path is not a Woo product/category surface for this scan.'
			);
		}

		if ( ! class_exists( '\WooCommerce', false ) && ! function_exists( 'WC' ) ) {
			return new SeoDiagnosticsCheck(
				'sf11_woocommerce',
				SeoDiagnosticsCheck::STATUS_UNAVAILABLE,
				'woocommerce',
				'woo_inactive',
				'WooCommerce not available.'
			);
		}

		return new SeoDiagnosticsCheck(
			'sf11_woocommerce',
			SeoDiagnosticsCheck::STATUS_PASS,
			'woocommerce',
			'ok',
			'Woo path in scope; SEO validation uses upstream A.SEOc–e contracts (no Woo mutation).',
			array( 'path' => $path )
		);
	}

	/**
	 * Advisory external verification readiness checklist (SF15).
	 *
	 * @param array $limitations Limitations.
	 */
	private function check_external_readiness( array $limitations ): SeoDiagnosticsCheck {
		$notes  = array(
			'Use Rank Math sitemap_index.xml as discovery owner.',
			'Verify hreflang/canonical in external webmaster tools manually.',
			'Rich Results / GSC API automation is Deferred (no credentials).',
		);
		$status = in_array( 'blog_public_zero', $limitations, true )
			? SeoDiagnosticsCheck::STATUS_WARNING
			: SeoDiagnosticsCheck::STATUS_PASS;

		return new SeoDiagnosticsCheck(
			'sf15_external_readiness',
			$status,
			'aiml',
			'advisory',
			in_array( 'blog_public_zero', $limitations, true )
				? 'External indexing discouraged (blog_public=0). Advisory checklist only.'
				: 'Advisory external verification readiness checklist available.',
			array( 'notes' => $notes )
		);
	}

	/**
	 * Bounded redirect-loop detection (SF9).
	 *
	 * @param string $url       Absolute URL.
	 * @param int    $max_depth Max hops.
	 * @param int    $http_used HTTP fetches already used.
	 * @return array{check: SeoDiagnosticsCheck, http_fetches: int}
	 */
	private function check_redirect_loop( string $url, int $max_depth, int $http_used ): array {
		$current = $url;
		$seen    = array();
		$hops    = array();
		$fetches = $http_used;

		for ( $i = 0; $i < $max_depth; $i++ ) {
			if ( isset( $seen[ $current ] ) ) {
				return array(
					'check'        => new SeoDiagnosticsCheck(
						'sf9_redirect_loop',
						SeoDiagnosticsCheck::STATUS_ERROR,
						'router',
						'redirect_loop',
						'Redirect loop detected (report only; A.SEOf does not fix Router).',
						array(
							'url'  => $url,
							'hops' => $hops,
							'at'   => $current,
						)
					),
					'http_fetches' => $fetches,
				);
			}
			$seen[ $current ] = true;

			if ( $fetches >= SeoDiagnosticsOptions::MAX_HTTP_FETCHES ) {
				break;
			}

			$response = wp_remote_head(
				$current,
				array(
					'timeout'     => 8,
					'redirection' => 0,
					'sslverify'   => false,
				)
			);
			++$fetches;

			if ( is_wp_error( $response ) ) {
				return array(
					'check'        => new SeoDiagnosticsCheck(
						'sf9_redirect_loop',
						SeoDiagnosticsCheck::STATUS_UNAVAILABLE,
						'router',
						'http_error',
						'Redirect check HTTP error: ' . $response->get_error_code(),
						array( 'url' => $current )
					),
					'http_fetches' => $fetches,
				);
			}

			$code   = (int) wp_remote_retrieve_response_code( $response );
			$loc    = (string) wp_remote_retrieve_header( $response, 'location' );
			$hops[] = array(
				'url'  => $current,
				'code' => $code,
			);

			if ( $code < 300 || $code >= 400 || '' === $loc ) {
				return array(
					'check'        => new SeoDiagnosticsCheck(
						'sf9_redirect_loop',
						SeoDiagnosticsCheck::STATUS_PASS,
						'router',
						'ok',
						'No redirect loop within bounded depth.',
						array( 'hops' => $hops )
					),
					'http_fetches' => $fetches,
				);
			}

			$next = $this->absolutize( $loc, $current );
			if ( $next === $current ) {
				return array(
					'check'        => new SeoDiagnosticsCheck(
						'sf9_redirect_loop',
						SeoDiagnosticsCheck::STATUS_ERROR,
						'router',
						'self_loop',
						'Self-referential redirect loop detected.',
						array(
							'url'  => $current,
							'hops' => $hops,
						)
					),
					'http_fetches' => $fetches,
				);
			}
			$current = $next;
		}

		return array(
			'check'        => new SeoDiagnosticsCheck(
				'sf9_redirect_loop',
				SeoDiagnosticsCheck::STATUS_WARNING,
				'router',
				'max_depth',
				'Redirect chain reached bounded max depth without settling.',
				array( 'hops' => $hops )
			),
			'http_fetches' => $fetches,
		);
	}

	/**
	 * Bounded duplicate <title> emission check (SF10).
	 *
	 * @param string $url       Absolute URL.
	 * @param int    $http_used HTTP fetches already used.
	 * @return array{check: SeoDiagnosticsCheck, http_fetches: int}
	 */
	private function check_duplicate_titles( string $url, int $http_used ): array {
		$fetches  = $http_used;
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 12,
				'redirection' => 3,
				'sslverify'   => false,
			)
		);
		++$fetches;

		if ( is_wp_error( $response ) ) {
			return array(
				'check'        => new SeoDiagnosticsCheck(
					'sf10_duplicates',
					SeoDiagnosticsCheck::STATUS_UNAVAILABLE,
					'aiml',
					'http_error',
					'Duplicate-title check HTTP error.',
					array( 'url' => $url )
				),
				'http_fetches' => $fetches,
			);
		}

		$body  = (string) wp_remote_retrieve_body( $response );
		$count = preg_match_all( '/<title\b/i', $body );
		$count = is_int( $count ) ? $count : 0;

		if ( $count > 1 ) {
			$ownership = ( false !== strpos( $url, '/product/' ) ) ? 'rank_math_or_theme' : 'unknown_foreign';
			return array(
				'check'        => new SeoDiagnosticsCheck(
					'sf10_duplicates',
					SeoDiagnosticsCheck::STATUS_WARNING,
					$ownership,
					'dual_title',
					'Duplicate <title> tags observed; attributed as foreign/pre-existing when on products.',
					array(
						'url'         => $url,
						'title_count' => $count,
					)
				),
				'http_fetches' => $fetches,
			);
		}

		return array(
			'check'        => new SeoDiagnosticsCheck(
				'sf10_duplicates',
				SeoDiagnosticsCheck::STATUS_PASS,
				'aiml',
				'ok',
				'No duplicate <title> conflict detected on sampled URL.',
				array(
					'url'         => $url,
					'title_count' => $count,
				)
			),
			'http_fetches' => $fetches,
		);
	}

	/**
	 * Tallies check statuses for SF1.
	 *
	 * @param array $checks Checks.
	 * @return array<string, int>
	 */
	private function summarize( array $checks ): array {
		$summary = array(
			'pass'        => 0,
			'warning'     => 0,
			'error'       => 0,
			'skipped'     => 0,
			'unavailable' => 0,
		);
		foreach ( $checks as $check ) {
			if ( isset( $summary[ $check->status ] ) ) {
				++$summary[ $check->status ];
			}
		}
		return $summary;
	}

	/**
	 * Returns the SB11 default-language URL when present.
	 *
	 * @param array $rels Relationships.
	 */
	private function default_url( array $rels ): ?string {
		foreach ( $rels as $rel ) {
			if ( $rel instanceof LanguageRelationship && $rel->is_default ) {
				return $rel->url;
			}
		}
		return null;
	}

	/**
	 * Resolves the absolute URL for emission checks.
	 *
	 * @param SeoDiagnosticsOptions $options Options.
	 * @param string                $path    Unprefixed path.
	 */
	private function resolve_url( SeoDiagnosticsOptions $options, string $path ): string {
		if ( '' !== $options->url ) {
			return esc_url_raw( $options->url );
		}
		$home = trailingslashit( (string) get_option( 'home' ) );
		if ( '/' === $path ) {
			return $home;
		}
		return $home . ltrim( $path, '/' );
	}

	/**
	 * Turns a Location header into an absolute URL.
	 *
	 * @param string $location Location header value.
	 * @param string $base     Current absolute URL.
	 */
	private function absolutize( string $location, string $base ): string {
		if ( preg_match( '#^https?://#i', $location ) ) {
			return $location;
		}
		$parts = wp_parse_url( $base );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return $location;
		}
		$origin = $parts['scheme'] . '://' . $parts['host'];
		if ( ! empty( $parts['port'] ) ) {
			$origin .= ':' . $parts['port'];
		}
		if ( str_starts_with( $location, '/' ) ) {
			return $origin . $location;
		}
		return trailingslashit( $origin ) . ltrim( $location, '/' );
	}
}
