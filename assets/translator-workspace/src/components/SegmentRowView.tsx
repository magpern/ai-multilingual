import { Button, Notice, TextareaControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import type { SegmentRow } from '../types/segment-row';
import { qaFromSegment, suggestionsFromSegment } from '../utils/meta';
import { canDecideReview, canSubmitForReview } from '../utils/review-status';
import { segmentStatusLabel } from '../utils/segment-status';
import QAPanel from './QAPanel';
import ReviewMetaSummary from './ReviewMetaSummary';
import ReviewStatusBadge from './ReviewStatusBadge';
import SuggestionPanel from './SuggestionPanel';

interface SegmentRowViewProps {
	row: SegmentRow;
	selected: boolean;
	suggesting?: boolean;
	canReview: boolean;
	onToggleSelect: ( segmentKey: string, checked: boolean ) => void;
	onDraftChange: ( segmentKey: string, value: string ) => void;
	onSave: ( segmentKey: string ) => void;
	onReload: ( segmentKey: string ) => void;
	onSuggestProfile?: ( segmentKey: string, profile: string ) => void;
	onSubmitReview: ( segmentKey: string ) => void;
	onRequestReviewDecision: (
		segmentKeys: string[],
		action: 'approve' | 'reject'
	) => void;
}

function rowStateLabel( row: SegmentRow ): string {
	switch ( row.rowState ) {
		case 'dirty':
			return __( 'Unsaved changes', 'ai-multilingual' );
		case 'saving':
			return __( 'Saving…', 'ai-multilingual' );
		case 'saved':
			return __( 'Saved', 'ai-multilingual' );
		case 'error':
			return __( 'Save failed', 'ai-multilingual' );
		case 'conflict':
			return __( 'Source changed', 'ai-multilingual' );
		default:
			return __( 'Up to date', 'ai-multilingual' );
	}
}

export default function SegmentRowView( {
	row,
	selected,
	suggesting = false,
	canReview,
	onToggleSelect,
	onDraftChange,
	onSave,
	onReload,
	onSuggestProfile,
	onSubmitReview,
	onRequestReviewDecision,
}: SegmentRowViewProps ) {
	const { server } = row;
	const suggestions = suggestionsFromSegment( server );
	const qa = qaFromSegment( server );
	const showSubmitReview = canSubmitForReview( server );
	const showReviewDecision = canReview && canDecideReview( server );
	const sourceId = `aiml-source-${ server.segment_key.replace(
		/[^a-z0-9_-]/gi,
		'-'
	) }`;
	const targetId = `aiml-target-${ server.segment_key.replace(
		/[^a-z0-9_-]/gi,
		'-'
	) }`;
	const editable = server.can_edit;
	const isSaving = row.rowState === 'saving';
	const isDirty = row.rowState === 'dirty' || row.rowState === 'conflict';
	const showSave = editable && isDirty && row.rowState !== 'conflict';
	const selectId = `aiml-select-${ server.segment_key.replace(
		/[^a-z0-9_-]/gi,
		'-'
	) }`;

	return (
		<tr
			className={ `aiml-workspace-row aiml-workspace-row--${ row.rowState }${
				server.is_stale ? ' aiml-workspace-row--stale' : ''
			}${ selected ? ' aiml-workspace-row--selected' : '' }` }
		>
			<td>
				{ editable ? (
					<>
						<label htmlFor={ selectId } className="screen-reader-text">
							{ sprintf(
								/* translators: %s: segment key */
								__( 'Select segment %s', 'ai-multilingual' ),
								server.segment_key
							) }
						</label>
						<input
							id={ selectId }
							type="checkbox"
							checked={ selected }
							disabled={ isSaving }
							onChange={ ( event ) =>
								onToggleSelect(
									row.segmentKey,
									event.target.checked
								)
							}
						/>
					</>
				) : (
					<span className="screen-reader-text">
						{ __(
							'Read-only segment cannot be selected',
							'ai-multilingual'
						) }
					</span>
				) }
			</td>
			<td>{ server.segment_order }</td>
			<td>
				<label htmlFor={ sourceId } className="screen-reader-text">
					{ sprintf(
						/* translators: %s: segment key */
						__( 'Source text for %s', 'ai-multilingual' ),
						server.segment_key
					) }
				</label>
				<textarea
					id={ sourceId }
					className="aiml-workspace-source large-text code"
					readOnly
					value={ server.source_text }
					rows={ 4 }
				/>
				<div className="aiml-workspace-meta">{ server.segment_key }</div>
			</td>
			<td>
				{ editable ? (
					<TextareaControl
						__nextHasNoMarginBottom
						id={ targetId }
						label={ sprintf(
							/* translators: %s: segment key */
							__( 'Translation for %s', 'ai-multilingual' ),
							server.segment_key
						) }
						value={ row.draftText }
						onChange={ ( value ) =>
							onDraftChange( row.segmentKey, value )
						}
						disabled={ isSaving }
						rows={ 4 }
					/>
				) : (
					<>
						<span className="screen-reader-text">
							{ sprintf(
								/* translators: %s: segment key */
								__( 'Read-only translation for %s', 'ai-multilingual' ),
								server.segment_key
							) }
						</span>
						<p className="aiml-workspace-readonly">
							{ row.draftText ||
								__( '(empty)', 'ai-multilingual' ) }
						</p>
						<p className="description">
							{ __(
								'This segment cannot be edited in the workspace.',
								'ai-multilingual'
							) }
						</p>
					</>
				) }
				{ row.rowState === 'conflict' && (
					<Notice status="warning" isDismissible={ false }>
						<p>
							{ __(
								'The source text changed after you loaded this segment. Your translation is preserved locally. Reload the server version, review the new source, then save again.',
								'ai-multilingual'
							) }
						</p>
						{ row.conflictServer && (
							<p className="aiml-workspace-conflict-source">
								<strong>
									{ __( 'Current source:', 'ai-multilingual' ) }
								</strong>{ ' ' }
								{ row.conflictServer.source_text }
							</p>
						) }
						<Button variant="secondary" onClick={ () => onReload( row.segmentKey ) }>
							{ __( 'Reload segment from server', 'ai-multilingual' ) }
						</Button>
					</Notice>
				) }
				{ row.rowState === 'error' && row.errorMessage && (
					<Notice status="error" isDismissible={ false }>
						{ row.errorMessage }
					</Notice>
				) }
				<ReviewMetaSummary review={ server } />
				{ editable && (
					<>
						<SuggestionPanel
							suggestions={ suggestions }
							disabled={ isSaving || suggesting }
							suggesting={ suggesting }
							onAccept={ ( text ) =>
								onDraftChange( row.segmentKey, text )
							}
							onRequestProfile={
								onSuggestProfile
									? ( profile ) =>
											onSuggestProfile( row.segmentKey, profile )
									: undefined
							}
						/>
						<QAPanel qa={ qa } />
					</>
				) }
			</td>
			<td>
				<span
					className="aiml-workspace-status-badge"
					aria-label={ sprintf(
						/* translators: %s: workflow status */
						__( 'Workflow status: %s', 'ai-multilingual' ),
						segmentStatusLabel( server.status )
					) }
				>
					{ segmentStatusLabel( server.status ) }
				</span>
				<ReviewStatusBadge status={ server.review_status } />
				<span className="aiml-workspace-row-state" aria-live="polite">
					{ rowStateLabel( row ) }
				</span>
			</td>
			<td>
				{ server.is_stale ? (
					<span className="aiml-workspace-stale-badge">
						{ __( 'Stale — source changed', 'ai-multilingual' ) }
					</span>
				) : (
					<span className="aiml-workspace-fresh-badge">
						{ __( 'Current', 'ai-multilingual' ) }
					</span>
				) }
			</td>
			<td>
				<div className="aiml-workspace-row-actions">
					{ showSave && (
						<Button
							variant="secondary"
							onClick={ () => onSave( row.segmentKey ) }
							disabled={ isSaving }
							aria-label={ sprintf(
								/* translators: %s: segment key */
								__( 'Save translation for %s', 'ai-multilingual' ),
								server.segment_key
							) }
						>
							{ __( 'Save', 'ai-multilingual' ) }
						</Button>
					) }
					{ showSubmitReview && (
						<Button
							variant="secondary"
							onClick={ () => onSubmitReview( row.segmentKey ) }
							disabled={ isSaving }
							aria-label={ sprintf(
								/* translators: %s: segment key */
								__( 'Submit %s for review', 'ai-multilingual' ),
								server.segment_key
							) }
						>
							{ 'rejected' === server.review_status
								? __( 'Resubmit for review', 'ai-multilingual' )
								: __( 'Submit for review', 'ai-multilingual' ) }
						</Button>
					) }
					{ showReviewDecision && (
						<>
							<Button
								variant="primary"
								onClick={ () =>
									onRequestReviewDecision(
										[ row.segmentKey ],
										'approve'
									)
								}
								aria-label={ sprintf(
									/* translators: %s: segment key */
									__( 'Approve translation for %s', 'ai-multilingual' ),
									server.segment_key
								) }
							>
								{ __( 'Approve', 'ai-multilingual' ) }
							</Button>
							<Button
								variant="secondary"
								isDestructive
								onClick={ () =>
									onRequestReviewDecision(
										[ row.segmentKey ],
										'reject'
									)
								}
								aria-label={ sprintf(
									/* translators: %s: segment key */
									__( 'Reject translation for %s', 'ai-multilingual' ),
									server.segment_key
								) }
							>
								{ __( 'Reject', 'ai-multilingual' ) }
							</Button>
						</>
					) }
				</div>
			</td>
		</tr>
	);
}
