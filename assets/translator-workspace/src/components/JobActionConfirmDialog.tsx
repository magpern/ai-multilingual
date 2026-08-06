import { Button, Modal, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import type { JobAction } from '../types/jobs';
import { jobActionLabel } from '../utils/jobs';

interface JobActionConfirmDialogProps {
	action: JobAction;
	jobId: number;
	message: string;
	onConfirm: () => void;
	onCancel: () => void;
	busy?: boolean;
	errorMessage?: string;
}

export default function JobActionConfirmDialog( {
	action,
	jobId,
	message,
	onConfirm,
	onCancel,
	busy = false,
	errorMessage = '',
}: JobActionConfirmDialogProps ) {
	const title = sprintf(
		/* translators: 1: action label, 2: job id */
		__( '%1$s job #%2$d', 'ai-multilingual' ),
		jobActionLabel( action ),
		jobId
	);

	return (
		<Modal
			title={ title }
			onRequestClose={ onCancel }
			className="aiml-job-action-dialog"
		>
			<p>{ message }</p>
			{ errorMessage && (
				<Notice status="error" isDismissible={ false }>
					{ errorMessage }
				</Notice>
			) }
			<div className="aiml-job-action-dialog-actions">
				<Button variant="tertiary" onClick={ onCancel } disabled={ busy }>
					{ __( 'Cancel', 'ai-multilingual' ) }
				</Button>
				<Button
					variant="primary"
					isDestructive={ 'cancel' === action }
					onClick={ onConfirm }
					disabled={ busy }
				>
					{ busy
						? __( 'Working…', 'ai-multilingual' )
						: jobActionLabel( action ) }
				</Button>
			</div>
		</Modal>
	);
}
