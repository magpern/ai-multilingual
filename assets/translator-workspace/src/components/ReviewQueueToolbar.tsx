import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

interface ReviewQueueToolbarProps {
	selectedCount: number;
	busy: boolean;
	onApproveSelected: () => void;
	onRejectSelected: () => void;
	onClearSelection: () => void;
}

export default function ReviewQueueToolbar( {
	selectedCount,
	busy,
	onApproveSelected,
	onRejectSelected,
	onClearSelection,
}: ReviewQueueToolbarProps ) {
	if ( 0 === selectedCount ) {
		return null;
	}

	return (
		<div
			className="aiml-review-queue-toolbar"
			role="region"
			aria-label={ __( 'Bulk review actions', 'ai-multilingual' ) }
		>
			<p className="aiml-workspace-bulk-summary">
				{ sprintf(
					/* translators: %d: selected segment count */
					__( '%d pending segment(s) selected', 'ai-multilingual' ),
					selectedCount
				) }
			</p>
			<div className="aiml-workspace-bulk-actions">
				<Button
					variant="primary"
					onClick={ onApproveSelected }
					disabled={ busy }
					aria-label={ __(
						'Approve selected pending segments',
						'ai-multilingual'
					) }
				>
					{ __( 'Approve selected', 'ai-multilingual' ) }
				</Button>
				<Button
					variant="secondary"
					isDestructive
					onClick={ onRejectSelected }
					disabled={ busy }
					aria-label={ __(
						'Reject selected pending segments',
						'ai-multilingual'
					) }
				>
					{ __( 'Reject selected', 'ai-multilingual' ) }
				</Button>
				<Button variant="tertiary" onClick={ onClearSelection } disabled={ busy }>
					{ __( 'Clear selection', 'ai-multilingual' ) }
				</Button>
			</div>
		</div>
	);
}
