import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

interface OperationsBulkToolbarProps {
	selectedCount: number;
	canTranslate: boolean;
	busy: boolean;
	dirtyBlocks: boolean;
	onPublish: () => void;
	onUnpublish: () => void;
	onEnqueueRetranslate: () => void;
	onClear: () => void;
}

/**
 * Bounded Operations bulk action bar (OTL.5).
 *
 * Publish availability is invitation-only — not TI.7 eligibility.
 */
export default function OperationsBulkToolbar( {
	selectedCount,
	canTranslate,
	busy,
	dirtyBlocks,
	onPublish,
	onUnpublish,
	onEnqueueRetranslate,
	onClear,
}: OperationsBulkToolbarProps ) {
	if ( selectedCount <= 0 ) {
		return null;
	}

	const disabled = busy || dirtyBlocks || ! canTranslate;

	return (
		<div
			className="aiml-operations-bulk-toolbar"
			role="region"
			aria-label={ __( 'Operations bulk actions', 'ai-multilingual' ) }
		>
			<p className="aiml-operations-bulk-toolbar__count" aria-live="polite">
				{ sprintf(
					/* translators: %d: number of selected translations */
					__( '%d selected', 'ai-multilingual' ),
					selectedCount
				) }
			</p>
			{ dirtyBlocks && (
				<p className="aiml-operations-bulk-toolbar__dirty" role="status">
					{ __(
						'The open translation has unsaved changes and is selected. Save, discard, or remove it from selection before running a bulk action.',
						'ai-multilingual'
					) }
				</p>
			) }
			<div className="aiml-operations-bulk-toolbar__actions">
				<Button
					variant="secondary"
					disabled={ disabled }
					onClick={ onPublish }
				>
					{ __( 'Publish selected', 'ai-multilingual' ) }
				</Button>
				<Button
					variant="secondary"
					disabled={ disabled }
					onClick={ onUnpublish }
				>
					{ __( 'Unpublish selected', 'ai-multilingual' ) }
				</Button>
				<Button
					variant="secondary"
					disabled={ disabled }
					onClick={ onEnqueueRetranslate }
				>
					{ __( 'Enqueue retranslate', 'ai-multilingual' ) }
				</Button>
				<Button variant="tertiary" disabled={ busy } onClick={ onClear }>
					{ __( 'Clear selection', 'ai-multilingual' ) }
				</Button>
			</div>
		</div>
	);
}
