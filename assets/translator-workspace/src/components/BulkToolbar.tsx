import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

interface BulkToolbarProps {
	selectedCount: number;
	dirtySelectedCount: number;
	busy: boolean;
	onSaveSelected: () => void;
	onTranslateSelected: () => void;
	onClearSelection: () => void;
}

export default function BulkToolbar( {
	selectedCount,
	dirtySelectedCount,
	busy,
	onSaveSelected,
	onTranslateSelected,
	onClearSelection,
}: BulkToolbarProps ) {
	if ( selectedCount === 0 ) {
		return null;
	}

	return (
		<div
			className="aiml-workspace-bulk-toolbar"
			role="region"
			aria-label={ __( 'Bulk segment actions', 'ai-multilingual' ) }
		>
			<p className="aiml-workspace-bulk-summary">
				{ sprintf(
					/* translators: %d: selected segment count */
					__( '%d segment(s) selected', 'ai-multilingual' ),
					selectedCount
				) }
			</p>
			<div className="aiml-workspace-bulk-actions">
				<Button
					variant="secondary"
					onClick={ onSaveSelected }
					disabled={ busy || dirtySelectedCount === 0 }
					aria-label={ __( 'Save selected changed segments', 'ai-multilingual' ) }
				>
					{ busy
						? __( 'Saving…', 'ai-multilingual' )
						: sprintf(
								/* translators: %d: dirty selected count */
								__( 'Save selected (%d)', 'ai-multilingual' ),
								dirtySelectedCount
						  ) }
				</Button>
				<Button
					variant="secondary"
					onClick={ onTranslateSelected }
					disabled={ busy }
					aria-label={ __(
						'Translate selected segments automatically',
						'ai-multilingual'
					) }
				>
					{ __( 'Translate selected', 'ai-multilingual' ) }
				</Button>
				<Button
					variant="tertiary"
					onClick={ onClearSelection }
					disabled={ busy }
				>
					{ __( 'Clear selection', 'ai-multilingual' ) }
				</Button>
			</div>
		</div>
	);
}
