import { Button, Modal, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export interface ConfirmDialogProps {
	title: string;
	message: string;
	confirmLabel?: string;
	cancelLabel?: string;
	isDestructive?: boolean;
	busy?: boolean;
	errorMessage?: string;
	onConfirm: () => void;
	onCancel: () => void;
}

/**
 * Thin shared confirmation Modal (OTL.6).
 *
 * Not a general UI framework — Operations consequential confirms only.
 * Specialized Review/Jobs dialogs remain separate.
 */
export default function ConfirmDialog( {
	title,
	message,
	confirmLabel,
	cancelLabel,
	isDestructive = false,
	busy = false,
	errorMessage = '',
	onConfirm,
	onCancel,
}: ConfirmDialogProps ) {
	return (
		<Modal
			title={ title }
			onRequestClose={ () => {
				if ( ! busy ) {
					onCancel();
				}
			} }
			className="aiml-confirm-dialog"
		>
			<p className="aiml-confirm-dialog__message">{ message }</p>
			{ errorMessage && (
				<Notice status="error" isDismissible={ false }>
					{ errorMessage }
				</Notice>
			) }
			<div className="aiml-confirm-dialog__actions">
				<Button
					variant="tertiary"
					onClick={ onCancel }
					disabled={ busy }
				>
					{ cancelLabel || __( 'Cancel', 'ai-multilingual' ) }
				</Button>
				<Button
					variant="primary"
					isDestructive={ isDestructive }
					onClick={ onConfirm }
					disabled={ busy }
				>
					{ busy
						? __( 'Working…', 'ai-multilingual' )
						: confirmLabel || __( 'Continue', 'ai-multilingual' ) }
				</Button>
			</div>
		</Modal>
	);
}
