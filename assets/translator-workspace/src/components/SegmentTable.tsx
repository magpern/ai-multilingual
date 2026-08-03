import {
	Notice,
	Spinner,
	Table,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import type { WorkspaceSegment } from '../types/view-models';

interface SegmentTableProps {
	segments: WorkspaceSegment[];
	loading: boolean;
	error: string;
}

function statusLabel( status: string ): string {
	switch ( status ) {
		case 'missing':
			return __( 'Missing', 'ai-multilingual' );
		case 'manually_edited':
			return __( 'Edited', 'ai-multilingual' );
		case 'reviewed':
			return __( 'Reviewed', 'ai-multilingual' );
		case 'ignored':
			return __( 'Ignored', 'ai-multilingual' );
		default:
			return status;
	}
}

export default function SegmentTable( {
	segments,
	loading,
	error,
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

	if ( segments.length === 0 ) {
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
		<Table className="aiml-workspace-segment-table">
			<thead>
				<tr>
					<th>{ __( 'Order', 'ai-multilingual' ) }</th>
					<th>{ __( 'Source', 'ai-multilingual' ) }</th>
					<th>{ __( 'Target', 'ai-multilingual' ) }</th>
					<th>{ __( 'Status', 'ai-multilingual' ) }</th>
					<th>{ __( 'Stale', 'ai-multilingual' ) }</th>
				</tr>
			</thead>
			<tbody>
				{ segments.map( ( segment ) => (
					<tr key={ segment.segment_key }>
						<td>{ segment.segment_order }</td>
						<td>
							<div className="aiml-workspace-source">
								{ segment.source_text }
							</div>
							<div className="aiml-workspace-meta">
								{ segment.segment_key }
							</div>
						</td>
						<td>
							{ segment.translated_text ||
								__( '(empty)', 'ai-multilingual' ) }
						</td>
						<td>{ statusLabel( segment.status ) }</td>
						<td>
							{ segment.is_stale
								? __( 'Yes', 'ai-multilingual' )
								: __( 'No', 'ai-multilingual' ) }
						</td>
					</tr>
				) ) }
			</tbody>
		</Table>
	);
}
