import { Button, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import type { LanguageOption } from '../types/view-models';
import { jobStatusFilterOptions } from '../utils/jobs';
import LanguageSelect from './LanguageSelect';

interface JobsFilterBarProps {
	languages: LanguageOption[];
	languageCode: string;
	onLanguageChange: ( code: string ) => void;
	status: string;
	onStatusChange: ( status: string ) => void;
	batchIdFilter: string;
	onBatchIdFilterChange: ( value: string ) => void;
	onRefresh: () => void;
	onCreate: () => void;
	loading: boolean;
	canManage: boolean;
}

export default function JobsFilterBar( {
	languages,
	languageCode,
	onLanguageChange,
	status,
	onStatusChange,
	batchIdFilter,
	onBatchIdFilterChange,
	onRefresh,
	onCreate,
	loading,
	canManage,
}: JobsFilterBarProps ) {
	return (
		<div className="aiml-jobs-filter-bar">
			<SelectControl
				__nextHasNoMarginBottom
				label={ __( 'Status', 'ai-multilingual' ) }
				value={ status }
				options={ jobStatusFilterOptions() }
				onChange={ onStatusChange }
			/>
			<LanguageSelect
				languages={ languages }
				value={ languageCode }
				onChange={ onLanguageChange }
				allowEmpty
				emptyLabel={ __( 'All languages', 'ai-multilingual' ) }
			/>
			<TextControl
				__nextHasNoMarginBottom
				label={ __( 'Batch ID', 'ai-multilingual' ) }
				value={ batchIdFilter }
				onChange={ onBatchIdFilterChange }
			/>
			<div className="aiml-jobs-filter-actions">
				<Button variant="secondary" onClick={ onRefresh } disabled={ loading }>
					{ __( 'Refresh', 'ai-multilingual' ) }
				</Button>
				{ canManage && (
					<Button variant="primary" onClick={ onCreate } disabled={ loading }>
						{ __( 'Create job', 'ai-multilingual' ) }
					</Button>
				) }
			</div>
		</div>
	);
}
