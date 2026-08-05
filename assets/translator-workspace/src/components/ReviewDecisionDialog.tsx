import { Button, Modal, Notice, TextareaControl } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';

import {
	REASON_MAX_LENGTH,
	normalizeRejectionReason,
} from '../utils/review-status';

interface ReviewDecisionDialogProps {
	action: 'approve' | 'reject';
	count: number;
	reason: string;
	onReasonChange: ( value: string ) => void;
	onConfirm: () => void;
	onCancel: () => void;
	busy?: boolean;
	errorMessage?: string;
}

/**
 * Shared confirmation surface for every approve/reject decision — single-row
 * or batch. Reject always requires a trimmed 1–512 char reason (ADR-0015
 * §24); approve never rewrites text and only confirms the transition.
 */
export default function ReviewDecisionDialog( {
	action,
	count,
	reason,
	onReasonChange,
	onConfirm,
	onCancel,
	busy = false,
	errorMessage = '',
}: ReviewDecisionDialogProps ) {
	const isReject = 'reject' === action;
	const { value: trimmedReason, valid } = normalizeRejectionReason( reason );

	const title = isReject
		? sprintf(
				/* translators: %d: segment count */
				_n(
					'Reject %d segment',
					'Reject %d segments',
					count,
					'ai-multilingual'
				),
				count
		  )
		: sprintf(
				/* translators: %d: segment count */
				_n(
					'Approve %d segment',
					'Approve %d segments',
					count,
					'ai-multilingual'
				),
				count
		  );

	return (
		<Modal
			title={ title }
			onRequestClose={ onCancel }
			className="aiml-review-dialog"
		>
			{ isReject ? (
				<TextareaControl
					__nextHasNoMarginBottom
					label={ __( 'Rejection reason (required)', 'ai-multilingual' ) }
					help={ sprintf(
						/* translators: 1: current length, 2: max length */
						__( '%1$d / %2$d characters', 'ai-multilingual' ),
						trimmedReason.length,
						REASON_MAX_LENGTH
					) }
					value={ reason }
					onChange={ onReasonChange }
					rows={ 4 }
					disabled={ busy }
				/>
			) : (
				<p>
					{ __(
						'Approved translations become eligible for translation-memory reuse. This does not publish or change the frontend.',
						'ai-multilingual'
					) }
				</p>
			) }
			{ errorMessage && (
				<Notice status="error" isDismissible={ false }>
					{ errorMessage }
				</Notice>
			) }
			<div className="aiml-review-dialog-actions">
				<Button variant="tertiary" onClick={ onCancel } disabled={ busy }>
					{ __( 'Cancel', 'ai-multilingual' ) }
				</Button>
				<Button
					variant="primary"
					isDestructive={ isReject }
					onClick={ onConfirm }
					disabled={ busy || ( isReject && ! valid ) }
					aria-label={ title }
				>
					{ busy
						? __( 'Working…', 'ai-multilingual' )
						: isReject
							? __( 'Reject', 'ai-multilingual' )
							: __( 'Approve', 'ai-multilingual' ) }
				</Button>
			</div>
		</Modal>
	);
}
