import { useCallback, useEffect, useState } from '@wordpress/element';
import { Button, Notice, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import {
	fetchOperationDetail,
	fetchOperationsAttentionCounts,
	fetchOperationsList,
} from '../api/workspace-api';
import type {
	LanguageOption,
	OperationsDetailResponse,
	OperationsListItem,
} from '../types/view-models';
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
	item: OperationsListItem,
	id: string
): boolean {
	return ( item.allowed_actions ?? [] ).some(
		( a ) => a.id === id && a.allowed
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
	const [ inspectorId, setInspectorId ] = useState< number | null >( null );
	const [ detail, setDetail ] = useState< OperationsDetailResponse | null >(
		null
	);
	const [ detailLoading, setDetailLoading ] = useState( false );
	const [ detailError, setDetailError ] = useState( '' );

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
	] );

	useEffect( () => {
		syncUrl();
	}, [ syncUrl ] );

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

	useEffect( () => {
		if ( null === inspectorId ) {
			setDetail( null );
			setDetailError( '' );
			return;
		}
		let cancelled = false;
		( async () => {
			setDetailLoading( true );
			setDetailError( '' );
			try {
				const next = await fetchOperationDetail( inspectorId );
				if ( ! cancelled ) {
					setDetail( next );
				}
			} catch ( err ) {
				if ( ! cancelled ) {
					setDetail( null );
					setDetailError(
						err instanceof Error
							? err.message
							: __(
									'Could not open translation details.',
									'ai-multilingual'
							  )
					);
				}
			} finally {
				if ( ! cancelled ) {
					setDetailLoading( false );
				}
			}
		} )();
		return () => {
			cancelled = true;
		};
	}, [ inspectorId ] );

	const totalPages = Math.max( 1, Math.ceil( total / PER_PAGE ) );

	return (
		<div className="aiml-operations-panel">
			<p className="aiml-operations-honesty" role="note">
				{ __(
					'Operational attention uses cheap persisted translation lifecycle axes (stale, review, publication, translation status). It is not a complete risk classifier: TI.5 assessment categories, TI.7 publication eligibility, and Jobs failures are not listed here. Use the inspector for richer evidence. Unpublished may dominate new-language inventories.',
					'ai-multilingual'
				) }
			</p>

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
										onClick={ () =>
											setInspectorId( item.translation_id )
										}
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
										item.links?.source && (
											<a
												href={ item.links.source }
												target="_blank"
												rel="noopener noreferrer"
											>
												{ __( 'Source', 'ai-multilingual' ) }
											</a>
										) }
									{ actionAllowed( item, 'open_frontend' ) &&
										item.links?.frontend && (
											<a
												href={ item.links.frontend }
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
					onClose={ () => setInspectorId( null ) }
					onOpenInTranslate={ onOpenInTranslate }
					onOpenInReview={ onOpenInReview }
					canTranslate={ canTranslate }
					canReview={ canReview }
				/>
			) }
		</div>
	);
}
