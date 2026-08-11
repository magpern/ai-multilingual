import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { Button, Notice, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import {
	WorkspaceConflictError,
	WorkspaceQABlockedError,
	WorkspaceReviewActionError,
	WorkspaceRequestError,
	WorkspaceReviewConflictError,
	approveReview,
	fetchOperationDetail,
	fetchOperationsAttentionCounts,
	fetchOperationsList,
	publishSegment,
	rejectReview,
	saveSegment,
	submitReview,
	translateBatch,
	unpublishSegment,
} from '../api/workspace-api';
import ReviewDecisionDialog from './ReviewDecisionDialog';
import type {
	LanguageOption,
	OperationsDetailResponse,
	OperationsListItem,
} from '../types/view-models';
import {
	detailConflictMessage,
	detailConflictStatusMessage,
	type DetailConflictKind,
} from '../utils/detail-conflict';
import {
	dirtyLeaveConfirmMessage,
	isDetailDirty,
} from '../utils/detail-dirty';
import {
	ATTENTION_REASON_IDS,
	EMPTY_ATTENTION_COUNTS,
	attentionFilterOptions,
	attentionReasonLabel,
	type AttentionCounts,
	type AttentionPreset,
} from '../utils/operations-attention';
import {
	readOperationsUrlState,
	writeOperationsUrlState,
} from '../utils/operations-url';
import { reviewStatusLabel } from '../utils/review-status';
import OperationsInspector from './OperationsInspector';

interface OperationsPanelProps {
	languages: LanguageOption[];
	canTranslate: boolean;
	canReview: boolean;
	onOpenInTranslate: ( postId: number, languageCode: string ) => void;
	onOpenInReview: ( languageCode: string, postId?: number ) => void;
}

const PER_PAGE = 20;

function actionAllowed(
	item: OperationsListItem | OperationsDetailResponse,
	id: string
): boolean {
	return ( item.allowed_actions ?? [] ).some(
		( action ) => action.id === id && action.allowed
	);
}

export default function OperationsPanel( {
	languages,
	canTranslate,
	canReview,
	onOpenInTranslate,
	onOpenInReview,
}: OperationsPanelProps ) {
	const initial = readOperationsUrlState();
	const defaultLanguage =
		initial.language ||
		( languages[ 0 ] ? String( languages[ 0 ].code ) : '' );

	const [ languageCode, setLanguageCode ] = useState( defaultLanguage );
	const [ attention, setAttention ] = useState< AttentionPreset >(
		initial.attention
	);
	const [ reservedAttentionNotice, setReservedAttentionNotice ] = useState(
		initial.invalidReservedAttention
	);
	const [ status, setStatus ] = useState( initial.status );
	const [ reviewStatus, setReviewStatus ] = useState( initial.reviewStatus );
	const [ publishStatus, setPublishStatus ] = useState(
		initial.publishStatus
	);
	const [ isStale, setIsStale ] = useState( initial.isStale );
	const [ sourceType, setSourceType ] = useState( initial.sourceType );
	const [ sourceId, setSourceId ] = useState( initial.sourceId );
	const [ page, setPage ] = useState( initial.pageNum );
	const [ items, setItems ] = useState< OperationsListItem[] >( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ counts, setCounts ] = useState< AttentionCounts >(
		EMPTY_ATTENTION_COUNTS
	);
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ inspectorId, setInspectorId ] = useState< number | null >(
		initial.translationId
	);
	const [ detail, setDetail ] = useState< OperationsDetailResponse | null >(
		null
	);
	const [ detailLoading, setDetailLoading ] = useState( false );
	const [ detailError, setDetailError ] = useState( '' );
	const [ draftText, setDraftText ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ saveError, setSaveError ] = useState( '' );
	const [ conflictKind, setConflictKind ] =
		useState< DetailConflictKind | null >( null );
	const [ statusMessage, setStatusMessage ] = useState( '' );
	const [ reviewDialogOpen, setReviewDialogOpen ] = useState( false );
	const [ reviewDialogAction, setReviewDialogAction ] = useState<
		'approve' | 'reject'
	>( 'approve' );
	const [ reviewReason, setReviewReason ] = useState( '' );
	const [ reviewBusy, setReviewBusy ] = useState( false );
	const [ reviewDialogError, setReviewDialogError ] = useState( '' );
	const [ lastOperationResult, setLastOperationResult ] = useState<
		string | null
	>( null );

	const dirty = useMemo(
		() => isDetailDirty( draftText, detail?.translated_text ),
		[ draftText, detail?.translated_text ]
	);

	const mutationEligible = Boolean(
		detail &&
			'post' === detail.source_type &&
			actionAllowed( detail, 'edit' )
	);

	const syncUrl = useCallback( () => {
		writeOperationsUrlState( {
			language: languageCode,
			attention,
			pageNum: page,
			status,
			reviewStatus,
			publishStatus,
			isStale,
			sourceType,
			sourceId,
			translationId: inspectorId,
		} );
	}, [
		languageCode,
		attention,
		page,
		status,
		reviewStatus,
		publishStatus,
		isStale,
		sourceType,
		sourceId,
		inspectorId,
	] );

	useEffect( () => {
		syncUrl();
	}, [ syncUrl ] );

	const loadDetail = useCallback( async ( translationId: number ) => {
		setDetailLoading( true );
		setDetailError( '' );
		setSaveError( '' );
		setConflictKind( null );
		try {
			const next = await fetchOperationDetail( translationId );
			setDetail( next );
			setDraftText( next.translated_text ?? '' );
			setStatusMessage( '' );
		} catch ( err ) {
			setDetail( null );
			setDraftText( '' );
			setDetailError(
				err instanceof Error
					? err.message
					: __(
							'Could not open translation details.',
							'ai-multilingual'
					  )
			);
		} finally {
			setDetailLoading( false );
		}
	}, [] );

	const formatPublicationResult = (
		result: Record< string, unknown > | undefined
	): string | null => {
		if ( ! result ) {
			return null;
		}
		const status = String( result.status ?? '' );
		const reasonCodes = Array.isArray( result.reason_codes )
			? ( result.reason_codes as string[] ).join( ', ' )
			: '';
		const errorMessage = String( result.error_message ?? '' );

		switch ( status ) {
			case 'published':
				return __(
					'Publication result: published.',
					'ai-multilingual'
				);
			case 'unpublished':
				return __(
					'Publication result: unpublished.',
					'ai-multilingual'
				);
			case 'noop':
				return __(
					'Publication result: no change (already in that state).',
					'ai-multilingual'
				);
			case 'skipped':
				return reasonCodes
					? sprintf(
							/* translators: %s: reason codes */
							__(
								'Publication result: skipped (%s). Translation itself succeeded.',
								'ai-multilingual'
							),
							reasonCodes
					  )
					: __(
							'Publication result: skipped. Translation itself succeeded.',
							'ai-multilingual'
					  );
			case 'failed':
				return errorMessage
					? sprintf(
							/* translators: %s: error message */
							__(
								'Publication result: failed (%s). Translation itself succeeded.',
								'ai-multilingual'
							),
							errorMessage
					  )
					: __(
							'Publication result: failed. Translation itself succeeded.',
							'ai-multilingual'
					  );
			default:
				return status
					? sprintf(
							/* translators: %s: publication result status */
							__( 'Publication result: %s.', 'ai-multilingual' ),
							status
					  )
					: null;
		}
	};

	useEffect( () => {
		if ( null === inspectorId ) {
			setDetail( null );
			setDetailError( '' );
			setDraftText( '' );
			setSaveError( '' );
			setConflictKind( null );
			setStatusMessage( '' );
			setLastOperationResult( null );
			return;
		}
		loadDetail( inspectorId );
	}, [ inspectorId, loadDetail ] );

	useEffect( () => {
		if ( ! dirty ) {
			return;
		}
		const handler = ( event: BeforeUnloadEvent ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', handler );
		return () => {
			window.removeEventListener( 'beforeunload', handler );
		};
	}, [ dirty ] );

	const requestCloseInspector = useCallback( () => {
		if ( dirty && ! window.confirm( dirtyLeaveConfirmMessage() ) ) {
			return;
		}
		setInspectorId( null );
	}, [ dirty ] );

	const load = useCallback( async () => {
		if ( ! languageCode ) {
			setItems( [] );
			setTotal( 0 );
			setCounts( EMPTY_ATTENTION_COUNTS );
			return;
		}
		setLoading( true );
		setError( '' );
		const parsedSourceId = Number( sourceId.trim() );
		try {
			const [ list, nextCounts ] = await Promise.all( [
				fetchOperationsList( {
					languageCode,
					attention,
					status: status || undefined,
					reviewStatus: reviewStatus || undefined,
					publishStatus: publishStatus || undefined,
					isStale: isStale || undefined,
					sourceType: sourceType || undefined,
					sourceId:
						sourceId.trim() && parsedSourceId > 0
							? parsedSourceId
							: undefined,
					page,
					perPage: PER_PAGE,
				} ),
				fetchOperationsAttentionCounts( languageCode ),
			] );
			setItems( list.items );
			setTotal( list.total );
			setCounts( nextCounts );
		} catch {
			setError(
				__( 'Could not load Operations.', 'ai-multilingual' )
			);
			setItems( [] );
			setTotal( 0 );
		} finally {
			setLoading( false );
		}
	}, [
		languageCode,
		attention,
		status,
		reviewStatus,
		publishStatus,
		isStale,
		sourceType,
		sourceId,
		page,
	] );

	useEffect( () => {
		load();
	}, [ load ] );

	useEffect( () => {
		setPage( 1 );
	}, [
		languageCode,
		attention,
		status,
		reviewStatus,
		publishStatus,
		isStale,
		sourceType,
		sourceId,
	] );

	const handleSave = async () => {
		if ( ! detail || ! mutationEligible || ! dirty ) {
			return;
		}

		setSaving( true );
		setSaveError( '' );
		setConflictKind( null );
		setStatusMessage( __( 'Saving…', 'ai-multilingual' ) );

		try {
			await saveSegment(
				detail.source_id,
				detail.language_code,
				detail.segment_key,
				draftText,
				detail.source_hash ?? '',
				detail.translation_hash ?? ''
			);
			if ( null !== inspectorId ) {
				await loadDetail( inspectorId );
			}
			setStatusMessage(
				__( 'Translation saved.', 'ai-multilingual' )
			);
		} catch ( unknownError ) {
			if ( unknownError instanceof WorkspaceConflictError ) {
				const kind = unknownError.kind;
				setConflictKind( kind );
				setSaveError( detailConflictMessage( kind ) );
				setStatusMessage( detailConflictStatusMessage( kind ) );
				return;
			}

			if ( unknownError instanceof WorkspaceQABlockedError ) {
				const message = sprintf(
					/* translators: %d: error count */
					__(
						'Save blocked by %d quality error(s). Fix QA issues and try again.',
						'ai-multilingual'
					),
					unknownError.qa.summary.errors
				);
				setSaveError( message );
				setStatusMessage( message );
				return;
			}

			const message =
				unknownError instanceof Error
					? unknownError.message
					: __(
							'The translation could not be saved. Please try again.',
							'ai-multilingual'
					  );
			setSaveError( message );
			setStatusMessage( message );
		} finally {
			setSaving( false );
		}
	};

	const handleDiscard = () => {
		setDraftText( detail?.translated_text ?? '' );
		setSaveError( '' );
		setConflictKind( null );
		setStatusMessage( __( 'Changes discarded.', 'ai-multilingual' ) );
	};

	const handleRefreshDetail = () => {
		if ( null !== inspectorId ) {
			if ( dirty && ! window.confirm( dirtyLeaveConfirmMessage() ) ) {
				return;
			}
			loadDetail( inspectorId );
		}
	};

	const runReviewMutation = async (
		action: 'submit' | 'approve' | 'reject',
		reason = ''
	) => {
		if ( ! detail || 'post' !== detail.source_type || dirty ) {
			return;
		}

		setReviewBusy( true );
		setReviewDialogError( '' );
		setStatusMessage(
			__( 'Applying review action…', 'ai-multilingual' )
		);

		try {
			if ( 'submit' === action ) {
				await submitReview(
					detail.source_id,
					detail.language_code,
					detail.segment_key
				);
				setStatusMessage(
					__( 'Submitted for review.', 'ai-multilingual' )
				);
			} else if ( 'approve' === action ) {
				await approveReview(
					detail.source_id,
					detail.language_code,
					detail.segment_key
				);
				setStatusMessage(
					__( 'Translation approved.', 'ai-multilingual' )
				);
			} else {
				await rejectReview(
					detail.source_id,
					detail.language_code,
					detail.segment_key,
					reason
				);
				setStatusMessage(
					__( 'Translation rejected.', 'ai-multilingual' )
				);
			}

			if ( null !== inspectorId ) {
				await loadDetail( inspectorId );
			}
			setReviewDialogOpen( false );
			setReviewReason( '' );
		} catch ( unknownError ) {
			if (
				unknownError instanceof WorkspaceReviewConflictError ||
				unknownError instanceof WorkspaceReviewActionError
			) {
				setReviewDialogError( unknownError.message );
				setStatusMessage( unknownError.message );
				if ( null !== inspectorId ) {
					await loadDetail( inspectorId );
				}
				return;
			}

			const message =
				unknownError instanceof Error
					? unknownError.message
					: __(
							'The review action could not be completed.',
							'ai-multilingual'
					  );
			setReviewDialogError( message );
			setStatusMessage( message );
		} finally {
			setReviewBusy( false );
		}
	};

	const openReviewDialog = ( action: 'approve' | 'reject' ) => {
		setReviewDialogAction( action );
		setReviewReason( '' );
		setReviewDialogError( '' );
		setReviewDialogOpen( true );
	};

	const handlePublish = async () => {
		if ( ! detail || 'post' !== detail.source_type || dirty ) {
			return;
		}

		setSaving( true );
		setLastOperationResult( null );
		setStatusMessage( __( 'Publishing…', 'ai-multilingual' ) );

		try {
			const response = await publishSegment(
				detail.source_id,
				detail.language_code,
				detail.segment_key,
				detail.publish_status
			);
			const publicationNote = formatPublicationResult(
				response.publication_result
			);
			if ( null !== inspectorId ) {
				await loadDetail( inspectorId );
			}
			setLastOperationResult( publicationNote );
			setStatusMessage(
				publicationNote
					? sprintf(
							/* translators: %s: publication result summary */
							__( 'Published. %s', 'ai-multilingual' ),
							publicationNote
					  )
					: __( 'Published.', 'ai-multilingual' )
			);
		} catch ( unknownError ) {
			const message =
				unknownError instanceof WorkspaceRequestError
					? unknownError.userMessage
					: unknownError instanceof Error
					? unknownError.message
					: __(
							'Could not publish this translation.',
							'ai-multilingual'
					  );
			if ( null !== inspectorId ) {
				await loadDetail( inspectorId );
			}
			setStatusMessage( message );
		} finally {
			setSaving( false );
		}
	};

	const handleUnpublish = async () => {
		if ( ! detail || 'post' !== detail.source_type || dirty ) {
			return;
		}

		if (
			! window.confirm(
				__(
					'Unpublish this translation? It will no longer satisfy a gate that requires published status.',
					'ai-multilingual'
				)
			)
		) {
			return;
		}

		setSaving( true );
		setLastOperationResult( null );
		setStatusMessage( __( 'Unpublishing…', 'ai-multilingual' ) );

		try {
			const response = await unpublishSegment(
				detail.source_id,
				detail.language_code,
				detail.segment_key
			);
			const publicationNote = formatPublicationResult(
				response.publication_result
			);
			if ( null !== inspectorId ) {
				await loadDetail( inspectorId );
			}
			setLastOperationResult( publicationNote );
			setStatusMessage(
				publicationNote
					? sprintf(
							/* translators: %s: publication result summary */
							__( 'Unpublished. %s', 'ai-multilingual' ),
							publicationNote
					  )
					: __( 'Unpublished.', 'ai-multilingual' )
			);
		} catch ( unknownError ) {
			const message =
				unknownError instanceof WorkspaceRequestError
					? unknownError.userMessage
					: unknownError instanceof Error
					? unknownError.message
					: __(
							'Could not unpublish this translation.',
							'ai-multilingual'
					  );
			if ( null !== inspectorId ) {
				await loadDetail( inspectorId );
			}
			setStatusMessage( message );
		} finally {
			setSaving( false );
		}
	};

	const handleRetranslate = async () => {
		if ( ! detail || 'post' !== detail.source_type || dirty ) {
			return;
		}

		const mode = String(
			detail.publication_settings?.auto_publication_mode ??
				detail.publication?.mode ??
				'manual'
		);
		const mayAutoPublish =
			'approved_only' === mode || 'controlled_auto' === mode;

		const disclosure = [
			__(
				'Retranslate will replace the current target text with a new AI translation.',
				'ai-multilingual'
			),
			__(
				'Prior review approval will be cleared.',
				'ai-multilingual'
			),
			__(
				'Current publication state will be invalidated by the replacement.',
				'ai-multilingual'
			),
		];
		if ( mayAutoPublish ) {
			disclosure.push(
				__(
					'The new translation MAY be automatically published again if TI.7 allows after persist. Do not assume it stays unpublished.',
					'ai-multilingual'
				)
			);
		}
		disclosure.push(
			__( 'Continue with retranslate?', 'ai-multilingual' )
		);

		if ( ! window.confirm( disclosure.join( '\n\n' ) ) ) {
			return;
		}

		setSaving( true );
		setLastOperationResult( null );
		setStatusMessage( __( 'Retranslating…', 'ai-multilingual' ) );

		try {
			const result = await translateBatch(
				detail.source_id,
				detail.language_code,
				[ detail.segment_key ],
				{
					[ detail.segment_key ]: detail.translation_hash ?? '',
				}
			);

			const hashMismatch = ( result.errors ?? [] ).find(
				( item ) => 'aiml_translation_hash_mismatch' === item.code
			);
			if ( hashMismatch ) {
				if ( null !== inspectorId ) {
					await loadDetail( inspectorId );
				}
				setStatusMessage(
					__(
						'The translation changed while retranslation was running. Reloaded the latest saved target; generated text was not applied.',
						'ai-multilingual'
					)
				);
				return;
			}

			if ( 'failed' === result.status ) {
				if ( null !== inspectorId ) {
					await loadDetail( inspectorId );
				}
				setStatusMessage(
					result.errors[ 0 ]?.message ||
						__(
							'Retranslate failed. The previous target was left unchanged.',
							'ai-multilingual'
						)
				);
				return;
			}

			const publicationNote = formatPublicationResult(
				result.updated[ 0 ]?.publication_result
			);
			if ( null !== inspectorId ) {
				await loadDetail( inspectorId );
			}
			setLastOperationResult( publicationNote );
			setStatusMessage(
				publicationNote
					? sprintf(
							/* translators: %s: publication result summary */
							__(
								'Retranslated. %s',
								'ai-multilingual'
							),
							publicationNote
					  )
					: __( 'Retranslated.', 'ai-multilingual' )
			);
		} catch ( unknownError ) {
			if (
				unknownError instanceof WorkspaceConflictError &&
				'translation' === unknownError.kind
			) {
				if ( null !== inspectorId ) {
					await loadDetail( inspectorId );
				}
				setStatusMessage(
					__(
						'The translation changed while retranslation was running. Reloaded the latest saved target; generated text was not applied.',
						'ai-multilingual'
					)
				);
				return;
			}

			const message =
				unknownError instanceof WorkspaceRequestError
					? unknownError.userMessage
					: unknownError instanceof Error
					? unknownError.message
					: __(
							'Retranslate failed. The previous target was left unchanged.',
							'ai-multilingual'
					  );
			setStatusMessage( message );
		} finally {
			setSaving( false );
		}
	};

	const totalPages = Math.max( 1, Math.ceil( total / PER_PAGE ) );

	return (
		<div className="aiml-operations-panel">
			<p className="aiml-operations-honesty" role="note">
				{ __(
					'Operational attention uses cheap persisted translation lifecycle axes (stale, review, publication, translation status). It is not a complete risk classifier: TI.5 assessment categories, TI.7 publication eligibility, and Jobs failures are not listed here. Use the detail view for richer evidence. Unpublished may dominate new-language inventories.',
					'ai-multilingual'
				) }
			</p>

			{ reservedAttentionNotice && (
				<Notice
					status="warning"
					isDismissible={ true }
					onRemove={ () => setReservedAttentionNotice( false ) }
				>
					{ __(
						'The attention value “needs_review” is reserved for TI.5 assessment and is not an Operations filter. Showing All instead.',
						'ai-multilingual'
					) }
				</Notice>
			) }

			<div
				className="aiml-operations-filters"
				role="search"
				aria-label={ __( 'Operations filters', 'ai-multilingual' ) }
			>
				<label>
					<span>{ __( 'Language', 'ai-multilingual' ) }</span>
					<select
						value={ languageCode }
						onChange={ ( e ) => setLanguageCode( e.target.value ) }
					>
						{ languages.map( ( lang ) => (
							<option key={ lang.code } value={ lang.code }>
								{ lang.name || lang.code }
							</option>
						) ) }
					</select>
				</label>
				<label>
					<span>{ __( 'Attention', 'ai-multilingual' ) }</span>
					<select
						value={ attention }
						onChange={ ( e ) =>
							setAttention( e.target.value as AttentionPreset )
						}
					>
						{ attentionFilterOptions().map( ( opt ) => (
							<option key={ opt.value } value={ opt.value }>
								{ opt.label }
							</option>
						) ) }
					</select>
				</label>
				<label>
					<span>{ __( 'Translation status', 'ai-multilingual' ) }</span>
					<input
						type="text"
						value={ status }
						onChange={ ( e ) => setStatus( e.target.value ) }
						placeholder={ __( 'Any', 'ai-multilingual' ) }
					/>
				</label>
				<label>
					<span>{ __( 'Review state', 'ai-multilingual' ) }</span>
					<input
						type="text"
						value={ reviewStatus }
						onChange={ ( e ) => setReviewStatus( e.target.value ) }
						placeholder={ __( 'Any', 'ai-multilingual' ) }
					/>
				</label>
				<label>
					<span>{ __( 'Publication state', 'ai-multilingual' ) }</span>
					<input
						type="text"
						value={ publishStatus }
						onChange={ ( e ) => setPublishStatus( e.target.value ) }
						placeholder={ __( 'Any', 'ai-multilingual' ) }
					/>
				</label>
				<label>
					<span>{ __( 'Stale', 'ai-multilingual' ) }</span>
					<select
						value={ isStale }
						onChange={ ( e ) => setIsStale( e.target.value ) }
					>
						<option value="">{ __( 'Any', 'ai-multilingual' ) }</option>
						<option value="1">{ __( 'Yes', 'ai-multilingual' ) }</option>
						<option value="0">{ __( 'No', 'ai-multilingual' ) }</option>
					</select>
				</label>
				<label>
					<span>{ __( 'Source type', 'ai-multilingual' ) }</span>
					<input
						type="text"
						value={ sourceType }
						onChange={ ( e ) => setSourceType( e.target.value ) }
						placeholder={ __( 'Any', 'ai-multilingual' ) }
					/>
				</label>
				<label>
					<span>{ __( 'Source ID', 'ai-multilingual' ) }</span>
					<input
						type="text"
						value={ sourceId }
						onChange={ ( e ) => setSourceId( e.target.value ) }
						placeholder={ __( 'Any', 'ai-multilingual' ) }
					/>
				</label>
			</div>

			{ languageCode && (
				<ul
					className="aiml-operations-counts"
					aria-label={ __(
						'Language-wide operational attention counts',
						'ai-multilingual'
					) }
				>
					<li>
						<span>{ __( 'Total', 'ai-multilingual' ) }</span>
						<strong>{ counts.total }</strong>
					</li>
					{ ATTENTION_REASON_IDS.map( ( id ) => (
						<li key={ id }>
							<span>{ attentionReasonLabel( id ) }</span>
							<strong>{ counts[ id ] }</strong>
						</li>
					) ) }
				</ul>
			) }
			<p className="aiml-operations-counts-note">
				{ __(
					'Counts are language-wide and independent (may overlap). They are not narrowed by the axis filters above.',
					'ai-multilingual'
				) }
			</p>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ loading && (
				<p>
					<Spinner />{ ' ' }
					{ __( 'Loading Operations…', 'ai-multilingual' ) }
				</p>
			) }

			<div className="aiml-operations-table-wrap">
				<table className="widefat striped aiml-operations-table">
					<thead>
						<tr>
							<th scope="col">{ __( 'Source', 'ai-multilingual' ) }</th>
							<th scope="col">{ __( 'Source preview', 'ai-multilingual' ) }</th>
							<th scope="col">{ __( 'Target preview', 'ai-multilingual' ) }</th>
							<th scope="col">{ __( 'Language', 'ai-multilingual' ) }</th>
							<th scope="col">{ __( 'Status', 'ai-multilingual' ) }</th>
							<th scope="col">{ __( 'Review', 'ai-multilingual' ) }</th>
							<th scope="col">{ __( 'Publish', 'ai-multilingual' ) }</th>
							<th scope="col">{ __( 'Stale', 'ai-multilingual' ) }</th>
							<th scope="col">{ __( 'Attention', 'ai-multilingual' ) }</th>
							<th scope="col">{ __( 'Updated', 'ai-multilingual' ) }</th>
							<th scope="col">{ __( 'Actions', 'ai-multilingual' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ ! loading && 0 === items.length && (
							<tr>
								<td colSpan={ 11 }>
									{ __(
										'No translations match these filters.',
										'ai-multilingual'
									) }
								</td>
							</tr>
						) }
						{ items.map( ( item ) => (
							<tr key={ item.translation_id }>
								<td>
									{ item.source_type } #{ item.source_id }
								</td>
								<td className="aiml-operations-preview">
									{ item.source_preview }
								</td>
								<td className="aiml-operations-preview">
									{ item.target_preview }
								</td>
								<td>{ item.language_code }</td>
								<td>
									<span className="aiml-operations-axis">
										{ item.status }
									</span>
								</td>
								<td>
									<span className="aiml-operations-axis">
										{ reviewStatusLabel( item.review_status ) }
									</span>
								</td>
								<td>
									<span className="aiml-operations-axis">
										{ item.publish_status }
									</span>
								</td>
								<td>
									{ item.is_stale
										? __( 'Yes', 'ai-multilingual' )
										: __( 'No', 'ai-multilingual' ) }
								</td>
								<td>
									<ul className="aiml-operations-chips">
										{ item.attention_reasons.map( ( reason ) => (
											<li key={ reason }>
												<span className="aiml-operations-chip">
													{ attentionReasonLabel( reason ) }
												</span>
											</li>
										) ) }
									</ul>
								</td>
								<td>{ item.updated_at }</td>
								<td className="aiml-operations-actions">
									<Button
										variant="secondary"
										onClick={ () => {
											if (
												dirty &&
												inspectorId !== item.translation_id &&
												! window.confirm(
													dirtyLeaveConfirmMessage()
												)
											) {
												return;
											}
											setInspectorId( item.translation_id );
										} }
									>
										{ __( 'View detail', 'ai-multilingual' ) }
									</Button>
									{ canTranslate &&
										'post' === item.source_type &&
										item.source_id > 0 && (
											<Button
												variant="link"
												onClick={ () =>
													onOpenInTranslate(
														item.source_id,
														item.language_code
													)
												}
											>
												{ __(
													'Open in Translate',
													'ai-multilingual'
												) }
											</Button>
										) }
									{ canReview && (
										<Button
											variant="link"
											onClick={ () =>
												onOpenInReview(
													item.language_code,
													'post' === item.source_type
														? item.source_id
														: undefined
												)
											}
										>
											{ __(
												'Open in Review',
												'ai-multilingual'
											) }
										</Button>
									) }
									{ actionAllowed( item, 'open_source' ) &&
										item.links?.edit_link && (
											<a
												href={ item.links.edit_link }
												target="_blank"
												rel="noopener noreferrer"
											>
												{ __( 'Source', 'ai-multilingual' ) }
											</a>
										) }
									{ actionAllowed( item, 'open_frontend' ) &&
										( item.links?.frontend_url ||
											item.links?.source_frontend_url ) && (
											<a
												href={
													item.links.frontend_url ||
													item.links.source_frontend_url
												}
												target="_blank"
												rel="noopener noreferrer"
											>
												{ __(
													'Frontend',
													'ai-multilingual'
												) }
											</a>
										) }
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</div>

			<nav
				className="aiml-operations-pagination"
				aria-label={ __( 'Operations pagination', 'ai-multilingual' ) }
			>
				<Button
					variant="secondary"
					disabled={ page <= 1 || loading }
					onClick={ () => setPage( ( p ) => Math.max( 1, p - 1 ) ) }
				>
					{ __( 'Previous', 'ai-multilingual' ) }
				</Button>
				<span aria-live="polite">
					{ sprintf(
						/* translators: 1: current page, 2: total pages, 3: total rows */
						__( 'Page %1$d of %2$d (%3$d total)', 'ai-multilingual' ),
						page,
						totalPages,
						total
					) }
				</span>
				<Button
					variant="secondary"
					disabled={ page >= totalPages || loading }
					onClick={ () =>
						setPage( ( p ) => Math.min( totalPages, p + 1 ) )
					}
				>
					{ __( 'Next', 'ai-multilingual' ) }
				</Button>
			</nav>

			{ null !== inspectorId && (
				<OperationsInspector
					detail={ detail }
					loading={ detailLoading }
					error={ detailError }
					onClose={ requestCloseInspector }
					onOpenInTranslate={ onOpenInTranslate }
					onOpenInReview={ onOpenInReview }
					canTranslate={ canTranslate }
					canReview={ canReview }
					draftText={ draftText }
					onDraftChange={ setDraftText }
					dirty={ dirty }
					saving={ saving }
					saveError={ saveError }
					conflictKind={ conflictKind }
					onSave={ handleSave }
					onDiscard={ handleDiscard }
					onRefreshDetail={ handleRefreshDetail }
					onSubmitReview={ () => runReviewMutation( 'submit' ) }
					onApproveReview={ () => openReviewDialog( 'approve' ) }
					onRejectReview={ () => openReviewDialog( 'reject' ) }
					onPublish={ handlePublish }
					onUnpublish={ handleUnpublish }
					onRetranslate={ handleRetranslate }
					statusMessage={ statusMessage }
					mutationEligible={ mutationEligible }
					lastOperationResult={ lastOperationResult }
				/>
			) }

			{ reviewDialogOpen && (
				<ReviewDecisionDialog
					action={ reviewDialogAction }
					count={ 1 }
					reason={ reviewReason }
					onReasonChange={ setReviewReason }
					onConfirm={ () =>
						runReviewMutation( reviewDialogAction, reviewReason )
					}
					onCancel={ () => {
						if ( ! reviewBusy ) {
							setReviewDialogOpen( false );
							setReviewReason( '' );
							setReviewDialogError( '' );
						}
					} }
					busy={ reviewBusy }
					errorMessage={ reviewDialogError }
				/>
			) }
		</div>
	);
}
