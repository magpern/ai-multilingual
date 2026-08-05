import { Button, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import type { LanguageOption } from '../types/view-models';
import {
	reviewQueueFilterOptions,
	type ReviewQueueFilter,
} from '../utils/review-status';

interface ReviewQueueFilterBarProps {
	languages: LanguageOption[];
	languageCode: string;
	onLanguageChange: ( code: string ) => void;
	reviewStatus: ReviewQueueFilter;
	onReviewStatusChange: ( status: ReviewQueueFilter ) => void;
	postIdFilter: string;
	onPostIdFilterChange: ( value: string ) => void;
	onRefresh: () => void;
	loading: boolean;
}

export default function ReviewQueueFilterBar( {
	languages,
	languageCode,
	onLanguageChange,
	reviewStatus,
	onReviewStatusChange,
	postIdFilter,
	onPostIdFilterChange,
	onRefresh,
	loading,
}: ReviewQueueFilterBarProps ) {
	const languageOptions = [
		{ label: __( 'All languages', 'ai-multilingual' ), value: '' },
		...languages.map( ( language ) => ( {
			label: language.native_name || language.name || language.code,
			value: language.code,
		} ) ),
	];

	return (
		<div
			className="aiml-review-queue-filter-bar"
			role="search"
			aria-label={ __( 'Review queue filters', 'ai-multilingual' ) }
		>
			<SelectControl
				__nextHasNoMarginBottom
				label={ __( 'Review status', 'ai-multilingual' ) }
				value={ reviewStatus }
				options={ reviewQueueFilterOptions() }
				onChange={ ( next ) =>
					onReviewStatusChange( ( next ?? 'pending' ) as ReviewQueueFilter )
				}
			/>
			<SelectControl
				__nextHasNoMarginBottom
				label={ __( 'Language', 'ai-multilingual' ) }
				value={ languageCode }
				options={ languageOptions }
				onChange={ ( next ) => onLanguageChange( next ?? '' ) }
			/>
			<TextControl
				__nextHasNoMarginBottom
				label={ __( 'Post ID', 'ai-multilingual' ) }
				help={ __(
					'Leave blank to view every post.',
					'ai-multilingual'
				) }
				value={ postIdFilter }
				onChange={ onPostIdFilterChange }
				type="number"
				min={ 0 }
			/>
			<Button variant="secondary" onClick={ onRefresh } disabled={ loading }>
				{ loading
					? __( 'Loading…', 'ai-multilingual' )
					: __( 'Refresh', 'ai-multilingual' ) }
			</Button>
		</div>
	);
}
