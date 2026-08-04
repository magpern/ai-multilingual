import { Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import type { SegmentRow } from '../types/segment-row';
import SegmentRowView from './SegmentRowView';

interface SegmentTableProps {
	rows: SegmentRow[];
	loading: boolean;
	error: string;
	batchMessage: string;
	filterActive: boolean;
	selectedKeys: Set< string >;
	allVisibleSelected: boolean;
	hasSelectableVisible: boolean;
	onToggleSelect: ( segmentKey: string, checked: boolean ) => void;
	onToggleSelectAll: ( checked: boolean ) => void;
	onDraftChange: ( segmentKey: string, value: string ) => void;
	onSave: ( segmentKey: string ) => void;
	onReload: ( segmentKey: string ) => void;
	onSuggestProfile?: ( segmentKey: string, profile: string ) => void;
	suggestingKey?: string | null;
}

export default function SegmentTable( {
	rows,
	loading,
	error,
	batchMessage,
	filterActive,
	selectedKeys,
	allVisibleSelected,
	hasSelectableVisible,
	onToggleSelect,
	onToggleSelectAll,
	onDraftChange,
	onSave,
	onReload,
	onSuggestProfile,
	suggestingKey = null,
}: SegmentTableProps ) {
	if ( loading ) {
		return <Spinner />;
	}

	if ( error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		);
	}

	if ( rows.length === 0 ) {
		return (
			<Notice status="info" isDismissible={ false }>
				{ filterActive
					? __(
							'No segments match the current filter.',
							'ai-multilingual'
					  )
					: __(
							'Select a post to load its segments.',
							'ai-multilingual'
					  ) }
			</Notice>
		);
	}

	return (
		<>
			{ batchMessage && (
				<Notice status="info" isDismissible={ false }>
					{ batchMessage }
				</Notice>
			) }
			<table className="aiml-workspace-segment-table widefat striped">
				<thead>
					<tr>
						<th scope="col">
							<label className="screen-reader-text" htmlFor="aiml-select-all-visible">
								{ __( 'Select all visible editable segments', 'ai-multilingual' ) }
							</label>
							<input
								id="aiml-select-all-visible"
								type="checkbox"
								checked={ allVisibleSelected }
								disabled={ ! hasSelectableVisible }
								onChange={ ( event ) =>
									onToggleSelectAll( event.target.checked )
								}
							/>
						</th>
						<th scope="col">{ __( 'Order', 'ai-multilingual' ) }</th>
						<th scope="col">{ __( 'Source', 'ai-multilingual' ) }</th>
						<th scope="col">{ __( 'Target', 'ai-multilingual' ) }</th>
						<th scope="col">{ __( 'Status', 'ai-multilingual' ) }</th>
						<th scope="col">{ __( 'Stale', 'ai-multilingual' ) }</th>
						<th scope="col">{ __( 'Actions', 'ai-multilingual' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ rows.map( ( row ) => (
						<SegmentRowView
							key={ row.segmentKey }
							row={ row }
							selected={ selectedKeys.has( row.segmentKey ) }
							suggesting={ suggestingKey === row.segmentKey }
							onToggleSelect={ onToggleSelect }
							onDraftChange={ onDraftChange }
							onSave={ onSave }
							onReload={ onReload }
							onSuggestProfile={ onSuggestProfile }
						/>
					) ) }
				</tbody>
			</table>
		</>
	);
}
