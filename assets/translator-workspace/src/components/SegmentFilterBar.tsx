import { SelectControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import type { SegmentFilter } from '../utils/segment-status';
import { filterOptions } from '../utils/segment-status';

interface SegmentFilterBarProps {
	value: SegmentFilter;
	visibleCount: number;
	totalCount: number;
	onChange: ( filter: SegmentFilter ) => void;
}

export default function SegmentFilterBar( {
	value,
	visibleCount,
	totalCount,
	onChange,
}: SegmentFilterBarProps ) {
	return (
		<div className="aiml-workspace-filter-bar">
			<SelectControl
				__nextHasNoMarginBottom
				label={ __( 'Filter segments', 'ai-multilingual' ) }
				value={ value }
				options={ filterOptions().map( ( option ) => ( {
					label: option.label,
					value: option.value,
				} ) ) }
				onChange={ ( next ) => onChange( ( next ?? 'all' ) as SegmentFilter ) }
			/>
			<p className="aiml-workspace-filter-summary" aria-live="polite">
				{ sprintf(
					/* translators: 1: visible count, 2: total count */
					__( 'Showing %1$d of %2$d segments', 'ai-multilingual' ),
					visibleCount,
					totalCount
				) }
			</p>
		</div>
	);
}
