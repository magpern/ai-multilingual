import { Notice, Spinner, Table } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import type { SegmentRow } from '../types/segment-row';
import SegmentRowView from './SegmentRowView';

interface SegmentTableProps {
	rows: SegmentRow[];
	loading: boolean;
	error: string;
	batchMessage: string;
	onDraftChange: ( segmentKey: string, value: string ) => void;
	onSave: ( segmentKey: string ) => void;
	onReload: ( segmentKey: string ) => void;
}

export default function SegmentTable( {
	rows,
	loading,
	error,
	batchMessage,
	onDraftChange,
	onSave,
	onReload,
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
				{ __(
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
			<Table className="aiml-workspace-segment-table">
				<thead>
					<tr>
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
							onDraftChange={ onDraftChange }
							onSave={ onSave }
							onReload={ onReload }
						/>
					) ) }
				</tbody>
			</Table>
		</>
	);
}
