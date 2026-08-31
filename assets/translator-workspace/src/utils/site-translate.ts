import { __, sprintf } from '@wordpress/i18n';

import type { SiteTranslateCoverage } from '../types/site-translate';
import { JOB_BOUNDS } from './jobs';

export type SiteTranslateCoverageFilter =
	| 'all'
	| 'needs_translation'
	| 'translated_unpublished'
	| 'translation_complete'
	| 'no_extractable_work';

export function coverageFilterOptions(): Array< {
	value: SiteTranslateCoverageFilter;
	label: string;
} > {
	return [
		{ value: 'all', label: __( 'All objects', 'ai-multilingual' ) },
		{
			value: 'needs_translation',
			label: __( 'Needs translation', 'ai-multilingual' ),
		},
		{
			value: 'translated_unpublished',
			label: __( 'Translated, unpublished', 'ai-multilingual' ),
		},
		{
			value: 'translation_complete',
			label: __( 'Translation complete', 'ai-multilingual' ),
		},
		{
			value: 'no_extractable_work',
			label: __( 'No extractable work', 'ai-multilingual' ),
		},
	];
}

export function matchesCoverageFilter(
	coverage: SiteTranslateCoverage,
	filter: SiteTranslateCoverageFilter
): boolean {
	if ( 'all' === filter ) {
		return true;
	}

	const missing = coverage.missing ?? 0;
	const stale = coverage.stale ?? 0;
	const unpublished = coverage.unpublished ?? 0;
	const noWork = Boolean( coverage.no_extractable_work );

	switch ( filter ) {
		case 'needs_translation':
			return ! noWork && ( missing > 0 || stale > 0 );
		case 'translated_unpublished':
			return ! noWork && unpublished > 0 && 0 === missing && 0 === stale;
		case 'translation_complete':
			return Boolean( coverage.translation_complete );
		case 'no_extractable_work':
			return noWork;
		default:
			return true;
	}
}

export function coverageSummaryLabel( coverage: SiteTranslateCoverage ): string {
	if ( coverage.no_extractable_work ) {
		return __( 'No extractable work', 'ai-multilingual' );
	}

	if ( coverage.missing > 0 || coverage.stale > 0 ) {
		return sprintf(
			/* translators: 1: missing count, 2: stale count */
			__( 'Missing %1$d · Stale %2$d', 'ai-multilingual' ),
			coverage.missing,
			coverage.stale
		);
	}

	if ( coverage.unpublished > 0 ) {
		return sprintf(
			/* translators: %d: unpublished segment count */
			__( 'Unpublished %d', 'ai-multilingual' ),
			coverage.unpublished
		);
	}

	return __( 'Translation complete', 'ai-multilingual' );
}

export function blockedReasonLabel( reason: string ): string {
	switch ( reason ) {
		case 'body_blocks_without_strategy_f':
			return __(
				'Gutenberg blocks require Strategy F',
				'ai-multilingual'
			);
		case 'body_elementor':
			return __( 'Elementor body (partial coverage)', 'ai-multilingual' );
		case 'zero_eligible':
			return __( 'No eligible segments', 'ai-multilingual' );
		default:
			return reason;
	}
}

export function routeOutcomeLabel( outcome: string ): string {
	switch ( outcome ) {
		case 'eligible_success':
			return __( 'Route published', 'ai-multilingual' );
		case 'missing_translated_title':
			return __( 'Missing translated title', 'ai-multilingual' );
		case 'title_not_published':
			return __( 'Title not published', 'ai-multilingual' );
		case 'title_stale':
			return __( 'Published title is stale', 'ai-multilingual' );
		case 'manual_slug_locked':
			return __( 'Manual slug locked', 'ai-multilingual' );
		case 'collision':
			return __( 'Slug collision', 'ai-multilingual' );
		case 'language_not_published':
			return __( 'Language not published', 'ai-multilingual' );
		case 'not_admitted':
			return __( 'Not admitted for routing', 'ai-multilingual' );
		case 'publication_ineligible':
			return __( 'Publication ineligible', 'ai-multilingual' );
		default:
			return outcome;
	}
}

export function chunkCountForSelection( count: number ): number {
	if ( count <= 0 ) {
		return 0;
	}
	return Math.ceil( count / JOB_BOUNDS.maxPostsPerBulk );
}

export function siteTranslateChunkMessage( selectedCount: number ): string {
	const chunks = chunkCountForSelection( selectedCount );
	if ( chunks <= 1 ) {
		return sprintf(
			/* translators: %d: selected object count */
			__(
				'Creates one background job for up to %1$d objects (selected: %2$d).',
				'ai-multilingual'
			),
			JOB_BOUNDS.maxPostsPerBulk,
			selectedCount
		);
	}

	return sprintf(
		/* translators: 1: chunk count, 2: selected count, 3: max per chunk */
		__(
			'Splits selection into %1$d jobs (%2$d objects, max %3$d per job).',
			'ai-multilingual'
		),
		chunks,
		selectedCount,
		JOB_BOUNDS.maxPostsPerBulk
	);
}
