import { Button, Notice, Spinner, TextareaControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import type {
	OperationsDetailResponse,
	SegmentQA,
} from '../types/view-models';
import {
	assessmentCategoryLabel,
	assessmentFindingReasons,
	isTi5NeedsReviewCategory,
} from '../utils/assessment-present';
import {
	detailConflictMessage,
	type DetailConflictKind,
} from '../utils/detail-conflict';
import {
	dirtyEditorHint,
	dirtyEvidenceHonestyMessage,
} from '../utils/detail-dirty';
import { canShowPublicationActions } from '../utils/detail-publication-gate';
import { canShowReviewActions } from '../utils/detail-review-gate';
import { attentionReasonLabel } from '../utils/operations-attention';
import { reviewStatusLabel } from '../utils/review-status';
import QAPanel from './QAPanel';

interface OperationsInspectorProps {
	detail: OperationsDetailResponse | null;
	loading: boolean;
	error: string;
	onClose: () => void;
	onOpenInTranslate: ( postId: number, languageCode: string ) => void;
	onOpenInReview: ( languageCode: string, postId?: number ) => void;
	canTranslate: boolean;
	canReview: boolean;
	draftText: string;
	onDraftChange: ( value: string ) => void;
	dirty: boolean;
	saving: boolean;
	saveError: string;
	conflictKind: DetailConflictKind | null;
	onSave: () => void;
	onDiscard: () => void;
	onRefreshDetail: () => void;
	onSubmitReview: () => void;
	onApproveReview: () => void;
	onRejectReview: () => void;
	onPublish: () => void;
	onUnpublish: () => void;
	onRetranslate: () => void;
	statusMessage: string;
	mutationEligible: boolean;
	lastOperationResult?: string | null;
}

function actionAllowed(
	detail: OperationsDetailResponse,
	id: string
): boolean {
	return ( detail.allowed_actions ?? [] ).some(
		( action ) => action.id === id && action.allowed
	);
}

function detailQaToSegmentQa(
	qa: Record< string, unknown > | undefined
): SegmentQA | null {
	if ( ! qa ) {
		return null;
	}

	const rawIssues = Array.isArray( qa.issues ) ? qa.issues : [];
	const summaryRaw =
		qa.summary && 'object' === typeof qa.summary
			? ( qa.summary as Record< string, unknown > )
			: {};

	return {
		issues: rawIssues.map( ( issue ) => {
			const record =
				issue && 'object' === typeof issue
					? ( issue as Record< string, unknown > )
					: {};
			return {
				code: String( record.code ?? '' ),
				severity: String( record.severity ?? 'info' ),
				message: String( record.message ?? '' ),
				details:
					record.details && 'object' === typeof record.details
						? ( record.details as Record< string, unknown > )
						: {},
			};
		} ),
		summary: {
			errors: Number( summaryRaw.errors ?? 0 ),
			warnings: Number( summaryRaw.warnings ?? 0 ),
			info: Number( summaryRaw.info ?? 0 ),
		},
	};
}

function publicationReasonLabel( code: string ): string {
	switch ( code ) {
		case 'eligible':
			return __( 'Eligible for publication', 'ai-multilingual' );
		case 'assessment_blocked':
			return __( 'Assessment blocked', 'ai-multilingual' );
		case 'review_rejected':
			return __( 'Review rejected', 'ai-multilingual' );
		case 'source_not_public':
			return __( 'Source not public', 'ai-multilingual' );
		case 'translation_stale':
			return __( 'Translation stale', 'ai-multilingual' );
		case 'automation_disabled':
			return __( 'Automation disabled', 'ai-multilingual' );
		case 'assessment_needs_review':
			return __( 'Assessment needs review', 'ai-multilingual' );
		case 'assessment_review_recommended':
			return __( 'Assessment review recommended', 'ai-multilingual' );
		case 'evidence_incomplete':
			return __( 'Evidence incomplete', 'ai-multilingual' );
		case 'human_approval_required':
			return __( 'Human approval required', 'ai-multilingual' );
		case 'structurally_clean_required':
			return __( 'Structurally clean required', 'ai-multilingual' );
		case 'provenance_not_allowed':
			return __( 'Provenance not allowed', 'ai-multilingual' );
		case 'publication_already_active':
			return __( 'Already published', 'ai-multilingual' );
		default:
			return code;
	}
}

function publishedByLabel( publishedBy: number | null | undefined ): string {
	if ( null == publishedBy ) {
		return __( 'Unknown', 'ai-multilingual' );
	}
	if ( 0 === publishedBy ) {
		return __( 'System', 'ai-multilingual' );
	}
	return String( publishedBy );
}

function publicationModeLabel( mode: string ): string {
	switch ( mode ) {
		case 'manual':
			return __( 'Manual', 'ai-multilingual' );
		case 'approved_only':
			return __( 'Approved only', 'ai-multilingual' );
		case 'controlled_auto':
			return __( 'Controlled auto', 'ai-multilingual' );
		default:
			return mode || __( 'Unknown', 'ai-multilingual' );
	}
}

/**
 * Unified translation detail workspace (OTL.2) — extends OTL.1 inspector in place.
 */
export default function OperationsInspector( {
	detail,
	loading,
	error,
	onClose,
	onOpenInTranslate,
	onOpenInReview,
	canTranslate,
	canReview,
	draftText,
	onDraftChange,
	dirty,
	saving,
	saveError,
	conflictKind,
	onSave,
	onDiscard,
	onRefreshDetail,
	onSubmitReview,
	onApproveReview,
	onRejectReview,
	onPublish,
	onUnpublish,
	onRetranslate,
	statusMessage,
	mutationEligible,
	lastOperationResult = null,
}: OperationsInspectorProps ) {
	const segmentQa = detail ? detailQaToSegmentQa( detail.qa ) : null;
	const reviewGate = detail
		? canShowReviewActions( {
				dirty,
				sourceType: detail.source_type,
				allowedActions: detail.allowed_actions ?? [],
				canTranslate,
				canReview,
		  } )
		: { submit: false, approve: false, reject: false };
	const publicationGate = detail
		? canShowPublicationActions( {
				dirty,
				sourceType: detail.source_type,
				allowedActions: detail.allowed_actions ?? [],
		  } )
		: { publish: false, unpublish: false, retranslate: false };
	const assessmentCategory = detail?.assessment
		? String( detail.assessment.overall_category ?? '' )
		: '';
	const assessmentReasons = assessmentFindingReasons( detail?.assessment );
	const publicationReasons = Array.isArray( detail?.publication?.reason_codes )
		? ( detail.publication.reason_codes as string[] )
		: [];
	const gateEnabled = Boolean(
		detail?.publication_settings?.segment_publication_gate_enabled
	);
	const autoMode = String(
		detail?.publication_settings?.auto_publication_mode ??
			detail?.publication?.mode ??
			''
	);
	const actionsDisabled = dirty || saving;

	return (
		<section
			className="aiml-operations-inspector"
			aria-label={ __( 'Translation detail', 'ai-multilingual' ) }
		>
			<div className="aiml-operations-inspector-header">
				<h2 className="aiml-operations-inspector-title">
					{ __( 'Translation detail', 'ai-multilingual' ) }
				</h2>
				<Button variant="tertiary" onClick={ onClose }>
					{ __( 'Close', 'ai-multilingual' ) }
				</Button>
			</div>

			<div
				className="aiml-operations-inspector-live"
				role="status"
				aria-live="polite"
				aria-atomic="true"
			>
				{ statusMessage }
			</div>

			{ loading && (
				<p>
					<Spinner />{ ' ' }
					{ __( 'Loading details…', 'ai-multilingual' ) }
				</p>
			) }
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ ! loading && ! error && detail && (
				<>
					<dl className="aiml-operations-inspector-axes">
						<div>
							<dt>{ __( 'Source', 'ai-multilingual' ) }</dt>
							<dd>
								{ detail.source_type } #{ detail.source_id }
								{ detail.source_subtype
									? ` (${ detail.source_subtype })`
									: '' }
							</dd>
						</div>
						<div>
							<dt>{ __( 'Language', 'ai-multilingual' ) }</dt>
							<dd>{ detail.language_code }</dd>
						</div>
						<div>
							<dt>{ __( 'Translation status', 'ai-multilingual' ) }</dt>
							<dd>{ detail.status }</dd>
						</div>
						<div>
							<dt>{ __( 'Review state', 'ai-multilingual' ) }</dt>
							<dd>{ reviewStatusLabel( detail.review_status ) }</dd>
						</div>
						<div>
							<dt>{ __( 'Publication state', 'ai-multilingual' ) }</dt>
							<dd>{ detail.publish_status }</dd>
						</div>
						<div>
							<dt>{ __( 'Stale', 'ai-multilingual' ) }</dt>
							<dd>
								{ detail.is_stale
									? __( 'Yes', 'ai-multilingual' )
									: __( 'No', 'ai-multilingual' ) }
							</dd>
						</div>
						<div>
							<dt>{ __( 'Operational attention', 'ai-multilingual' ) }</dt>
							<dd>
								{ ( detail.attention_reasons ?? [] ).length
									? detail.attention_reasons
											.map( attentionReasonLabel )
											.join( ', ' )
									: __( 'None', 'ai-multilingual' ) }
							</dd>
						</div>
					</dl>

					{ ! mutationEligible && (
						<p
							className="aiml-operations-inspector-honesty"
							role="note"
						>
							{ __(
								'This translation is read-only here. Post-backed segments with edit permission can be changed from this detail view.',
								'ai-multilingual'
							) }
						</p>
					) }

					{ dirty && (
						<p
							className="aiml-operations-inspector-honesty aiml-operations-inspector-honesty--dirty"
							role="note"
						>
							{ dirtyEvidenceHonestyMessage() }
						</p>
					) }

					<div className="aiml-operations-inspector-texts">
						<div className="aiml-operations-inspector-source">
							<h3>{ __( 'Source text', 'ai-multilingual' ) }</h3>
							<pre className="aiml-operations-inspector-pre">
								{ detail.source_text ?? '' }
							</pre>
						</div>
						<div className="aiml-operations-inspector-target">
							<h3>{ __( 'Target text', 'ai-multilingual' ) }</h3>
							{ mutationEligible ? (
								<>
									<TextareaControl
										__nextHasNoMarginBottom
										label={ __(
											'Target translation',
											'ai-multilingual'
										) }
										hideLabelFromVision
										value={ draftText }
										onChange={ onDraftChange }
										rows={ 8 }
										disabled={ saving }
										className="aiml-operations-inspector-textarea"
									/>
									{ dirty && (
										<p className="aiml-operations-inspector-dirty-hint">
											{ dirtyEditorHint() }
										</p>
									) }
								</>
							) : (
								<pre className="aiml-operations-inspector-pre">
									{ detail.translated_text ?? '' }
								</pre>
							) }
						</div>
					</div>

					{ mutationEligible && (
						<div className="aiml-operations-inspector-save">
							{ conflictKind && (
								<Notice status="warning" isDismissible={ false }>
									{ detailConflictMessage( conflictKind ) }
								</Notice>
							) }
							{ saveError && ! conflictKind && (
								<Notice status="error" isDismissible={ false }>
									{ saveError }
								</Notice>
							) }
							<div className="aiml-operations-inspector-save-actions">
								<Button
									variant="primary"
									onClick={ onSave }
									disabled={ saving || ! dirty }
									isBusy={ saving }
								>
									{ saving
										? __( 'Saving…', 'ai-multilingual' )
										: __( 'Save', 'ai-multilingual' ) }
								</Button>
								<Button
									variant="secondary"
									onClick={ onDiscard }
									disabled={ saving || ! dirty }
								>
									{ __( 'Discard changes', 'ai-multilingual' ) }
								</Button>
								<Button
									variant="tertiary"
									onClick={ onRefreshDetail }
									disabled={ saving }
								>
									{ __( 'Refresh', 'ai-multilingual' ) }
								</Button>
							</div>
						</div>
					) }

					{ segmentQa && (
						<div className="aiml-operations-inspector-block">
							<QAPanel qa={ segmentQa } />
						</div>
					) }

					{ detail.assessment && (
						<div className="aiml-operations-inspector-block">
							<h3>{ __( 'Assessment (TI.5)', 'ai-multilingual' ) }</h3>
							{ assessmentCategory && (
								<p
									className={
										isTi5NeedsReviewCategory( assessmentCategory )
											? 'aiml-operations-assessment-category aiml-operations-assessment-category--needs-review'
											: 'aiml-operations-assessment-category'
									}
								>
									<strong>
										{ __( 'Category', 'ai-multilingual' ) }
									</strong>
									{ ': ' }
									{ assessmentCategoryLabel( assessmentCategory ) }
								</p>
							) }
							{ assessmentReasons.length > 0 ? (
								<ul className="aiml-operations-assessment-reasons">
									{ assessmentReasons.map( ( reason, index ) => (
										<li key={ `${ reason }-${ index }` }>
											{ reason }
										</li>
									) ) }
								</ul>
							) : (
								<p className="description">
									{ __(
										'No assessment findings for the saved translation.',
										'ai-multilingual'
									) }
								</p>
							) }
						</div>
					) }

					{ detail.is_stale && (
						<div
							className="aiml-operations-inspector-block aiml-operations-inspector-stale"
							role="alert"
						>
							<Notice status="warning" isDismissible={ false }>
								{ 'published' === detail.publish_status
									? __(
											'This translation is stale because the source changed. It remains published until you edit or retranslate.',
											'ai-multilingual'
									  )
									: __(
											'This translation is stale because the source changed. Edit the target or retranslate before publishing.',
											'ai-multilingual'
									  ) }
							</Notice>
							{ publicationGate.retranslate && (
								<div className="aiml-operations-inspector-stale-actions">
									<Button
										variant="secondary"
										onClick={ onRetranslate }
										disabled={ actionsDisabled }
									>
										{ __( 'Retranslate', 'ai-multilingual' ) }
									</Button>
								</div>
							) }
						</div>
					) }

					{ ( detail.publication || detail.publication_settings ) && (
						<div className="aiml-operations-inspector-block">
							<h3>
								{ __( 'Publication explain (TI.7)', 'ai-multilingual' ) }
							</h3>
							{ detail.publication && (
								<>
									<p>
										<strong>
											{ __( 'Eligible', 'ai-multilingual' ) }
										</strong>
										{ ': ' }
										{ detail.publication.eligible
											? __( 'Yes', 'ai-multilingual' )
											: __( 'No', 'ai-multilingual' ) }
									</p>
									{ publicationReasons.length > 0 && (
										<ul className="aiml-operations-publication-reasons">
											{ publicationReasons.map( ( code ) => (
												<li key={ code }>
													{ publicationReasonLabel( code ) }
												</li>
											) ) }
										</ul>
									) }
								</>
							) }
							<p className="aiml-operations-inspector-note">
								{ __(
									'Approved does not mean published. Publication is a separate step.',
									'ai-multilingual'
								) }
							</p>
							<dl className="aiml-operations-inspector-axes">
								<div>
									<dt>
										{ __(
											'Current publish status',
											'ai-multilingual'
										) }
									</dt>
									<dd>{ detail.publish_status }</dd>
								</div>
								<div>
									<dt>
										{ __( 'Published at', 'ai-multilingual' ) }
									</dt>
									<dd>
										{ detail.published_at
											? detail.published_at
											: __( '—', 'ai-multilingual' ) }
									</dd>
								</div>
								<div>
									<dt>
										{ __( 'Published by', 'ai-multilingual' ) }
									</dt>
									<dd>
										{ publishedByLabel( detail.published_by ) }
									</dd>
								</div>
								{ autoMode && (
									<div>
										<dt>
											{ __(
												'Auto-publication mode',
												'ai-multilingual'
											) }
										</dt>
										<dd>{ publicationModeLabel( autoMode ) }</dd>
									</div>
								) }
								<div>
									<dt>
										{ __(
											'Segment publication gate',
											'ai-multilingual'
										) }
									</dt>
									<dd>
										{ gateEnabled
											? __( 'On', 'ai-multilingual' )
											: __( 'Off', 'ai-multilingual' ) }
									</dd>
								</div>
							</dl>
							{ gateEnabled ? (
								<p className="aiml-operations-inspector-note">
									{ __(
										'Gate ON: published is required by the canonical segment publication gate. Unpublished translations are overlay-ineligible via that gate. Published is necessary but not sufficient for complete frontend rendering (source, route, stale, and integration conditions still apply).',
										'ai-multilingual'
									) }
								</p>
							) : (
								<p className="aiml-operations-inspector-note">
									{ __(
										'Gate OFF: publish_status is not enforced by the segment publication gate. Unpublished translations may still be overlay-eligible via legacy behavior. This does not guarantee every render path displays the translation.',
										'ai-multilingual'
									) }
								</p>
							) }
							{ lastOperationResult && (
								<p
									className="aiml-operations-inspector-note"
									role="status"
								>
									{ lastOperationResult }
								</p>
							) }
						</div>
					) }

					{ ( publicationGate.publish || publicationGate.unpublish ) && (
						<div className="aiml-operations-inspector-block aiml-operations-inspector-publication-actions">
							<h3>
								{ __( 'Publication actions', 'ai-multilingual' ) }
							</h3>
							{ dirty && (
								<p className="aiml-operations-inspector-note">
									{ __(
										'Save or discard your changes before publishing or unpublishing.',
										'ai-multilingual'
									) }
								</p>
							) }
							<div className="aiml-operations-inspector-review-actions">
								{ publicationGate.publish && (
									<Button
										variant="primary"
										onClick={ onPublish }
										disabled={ actionsDisabled }
									>
										{ __( 'Publish', 'ai-multilingual' ) }
									</Button>
								) }
								{ publicationGate.unpublish && (
									<Button
										variant="secondary"
										isDestructive
										onClick={ onUnpublish }
										disabled={ actionsDisabled }
									>
										{ __( 'Unpublish', 'ai-multilingual' ) }
									</Button>
								) }
							</div>
						</div>
					) }

					{ ( reviewGate.submit ||
						reviewGate.approve ||
						reviewGate.reject ) && (
						<div className="aiml-operations-inspector-block aiml-operations-inspector-review">
							<h3>{ __( 'Review actions', 'ai-multilingual' ) }</h3>
							{ dirty && (
								<p className="aiml-operations-inspector-note">
									{ __(
										'Save your changes before submitting or deciding review.',
										'ai-multilingual'
									) }
								</p>
							) }
							<div className="aiml-operations-inspector-review-actions">
								{ reviewGate.submit && (
									<Button
										variant="secondary"
										onClick={ onSubmitReview }
										disabled={ dirty || saving }
									>
										{ __( 'Submit for review', 'ai-multilingual' ) }
									</Button>
								) }
								{ reviewGate.approve && (
									<Button
										variant="primary"
										onClick={ onApproveReview }
										disabled={ dirty || saving }
									>
										{ __( 'Approve', 'ai-multilingual' ) }
									</Button>
								) }
								{ reviewGate.reject && (
									<Button
										variant="secondary"
										isDestructive
										onClick={ onRejectReview }
										disabled={ dirty || saving }
									>
										{ __( 'Reject', 'ai-multilingual' ) }
									</Button>
								) }
							</div>
						</div>
					) }

					<div className="aiml-operations-inspector-block aiml-operations-inspector-provenance">
						<h3>{ __( 'Provenance', 'ai-multilingual' ) }</h3>
						<dl className="aiml-operations-inspector-provenance-list">
							{ detail.provider && (
								<div>
									<dt>{ __( 'Provider', 'ai-multilingual' ) }</dt>
									<dd>{ detail.provider }</dd>
								</div>
							) }
							{ detail.model && (
								<div>
									<dt>{ __( 'Model', 'ai-multilingual' ) }</dt>
									<dd>{ detail.model }</dd>
								</div>
							) }
							{ null != detail.tm_id && '' !== String( detail.tm_id ) && (
								<div>
									<dt>{ __( 'TM entry', 'ai-multilingual' ) }</dt>
									<dd>{ String( detail.tm_id ) }</dd>
								</div>
							) }
						</dl>
					</div>

					<div className="aiml-operations-inspector-actions">
						{ canTranslate &&
							'post' === detail.source_type &&
							detail.source_id > 0 && (
								<Button
									variant="secondary"
									onClick={ () =>
										onOpenInTranslate(
											detail.source_id,
											detail.language_code
										)
									}
								>
									{ __( 'Open in Translate', 'ai-multilingual' ) }
								</Button>
							) }
						{ canReview && (
							<Button
								variant="secondary"
								onClick={ () =>
									onOpenInReview(
										detail.language_code,
										'post' === detail.source_type
											? detail.source_id
											: undefined
									)
								}
							>
								{ __( 'Open in Review', 'ai-multilingual' ) }
							</Button>
						) }
						{ actionAllowed( detail, 'open_source' ) &&
							detail.links?.edit_link && (
								<Button
									variant="link"
									href={ detail.links.edit_link }
									target="_blank"
									rel="noopener noreferrer"
								>
									{ __( 'Open source', 'ai-multilingual' ) }
								</Button>
							) }
						{ actionAllowed( detail, 'open_frontend' ) &&
							( detail.links?.frontend_url ||
								detail.links?.source_frontend_url ) && (
								<Button
									variant="link"
									href={
										detail.links.frontend_url ||
										detail.links.source_frontend_url
									}
									target="_blank"
									rel="noopener noreferrer"
								>
									{ __(
										'Open frontend (publication state + gate eligibility — not verified render proof)',
										'ai-multilingual'
									) }
								</Button>
							) }
					</div>
				</>
			) }
			{ ! loading && ! error && ! detail && (
				<p>
					{ sprintf(
						/* translators: placeholder keeps string stable */
						__( 'No translation selected.%s', 'ai-multilingual' ),
						''
					) }
				</p>
			) }
		</section>
	);
}
