import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

interface BulkToolbarProps {
	selectedCount: number;
	dirtySelectedCount: number;
	busy: boolean;
	onSaveSelected: () => void;
	onTranslateSelected: () => void;
	onAcceptTmExact?: () => void;
	onRunQa?: () => void;
	onClearSelection: () => void;
}

export default function BulkToolbar( {
	selectedCount,
	dirtySelectedCount,
	busy,
	onSaveSelected,
	onTranslateSelected,
	onAcceptTmExact,
	onRunQa,
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
				{ onAcceptTmExact && (
					<Button
						variant="secondary"
						onClick={ onAcceptTmExact }
						disabled={ busy }
					>
						{ __( 'Accept TM exact', 'ai-multilingual' ) }
					</Button>
				) }
				{ onRunQa && (
					<Button
						variant="secondary"
						onClick={ onRunQa }
						disabled={ busy }
					>
						{ __( 'Run QA', 'ai-multilingual' ) }
					</Button>
				) }
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
