import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { Button, Notice, Panel, PanelBody } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import {
	WorkspaceConflictError,
	WorkspaceQABlockedError,
	acceptTmSuggestions,
	configureWorkspaceApi,
	fetchPreviewUrl,
	fetchSegments,
	runQaBatch,
	saveBatch,
	saveSegment,
	suggestSegment,
	translateBatch,
} from './api/workspace-api';
import BulkToolbar from './components/BulkToolbar';
import LanguageSelect from './components/LanguageSelect';
import PostSelect from './components/PostSelect';
import PublishContext from './components/PublishContext';
import SegmentFilterBar from './components/SegmentFilterBar';
import SegmentTable from './components/SegmentTable';
import StatusFooter from './components/StatusFooter';
import type { SegmentRow } from './types/segment-row';
import type {
	LanguageOption,
	WorkspacePageSummary,
	WorkspaceTranslationStatus,
} from './types/view-models';
import {
	matchesSegmentFilter,
	type SegmentFilter,
} from './utils/segment-status';
import {
	applyBatchSaveResults,
	applyConflict,
	applyReloadFromServer,
	applySaveSuccess,
	countDirtyRows,
	createRowsFromSegments,
	dirtyRowsInOrder,
	mergeSegmentsIntoRows,
	updateDraftText,
} from './utils/segment-rows';
import { aggregateQaSummary } from './utils/meta';
import {
	allVisibleSelected,
	clearSelection,
	deselectAllVisible,
	selectAllVisible,
	selectedDirtyRows,
	selectedEditableKeys,
	selectableRows,
	toggleSelection,
} from './utils/row-selection';

configureWorkspaceApi();

function readInitialLanguage( languages: LanguageOption[] ): string {
	const configured = window.aimlTranslatorWorkspace.initialLanguageCode;
	if (
		configured &&
		languages.some( ( language ) => language.code === configured )
	) {
		return configured;
	}

	return languages[ 0 ]?.code ?? '';
}

function readInitialPostId(): number | null {
	const configured = window.aimlTranslatorWorkspace.initialPostId ?? 0;
	return configured > 0 ? configured : null;
}

export default function App() {
	const languages: LanguageOption[] =
		window.aimlTranslatorWorkspace.languages ?? [];

	const [ languageCode, setLanguageCode ] = useState( () =>
		readInitialLanguage( languages )
	);
	const [ postId, setPostId ] = useState< number | null >( readInitialPostId );
	const [ postSummary, setPostSummary ] =
		useState< WorkspacePageSummary | null >( null );
	const [ rows, setRows ] = useState< SegmentRow[] >( [] );
	const [ status, setStatus ] =
		useState< WorkspaceTranslationStatus | null >( null );
	const [ loading, setLoading ] = useState( false );
	const [ batchSaving, setBatchSaving ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ previewError, setPreviewError ] = useState( '' );
	const [ batchMessage, setBatchMessage ] = useState( '' );
	const [ segmentFilter, setSegmentFilter ] = useState< SegmentFilter >( 'all' );
	const [ selectedKeys, setSelectedKeys ] = useState< Set< string > >(
		() => new Set()
	);
	const [ suggestingKey, setSuggestingKey ] = useState< string | null >( null );

	const dirtyCount = useMemo( () => countDirtyRows( rows ), [ rows ] );
	const filteredRows = useMemo(
		() =>
			rows.filter( ( row ) =>
				matchesSegmentFilter( row.server, segmentFilter )
			),
		[ rows, segmentFilter ]
	);
	const dirtySelectedCount = useMemo(
		() => selectedDirtyRows( rows, selectedKeys ).length,
		[ rows, selectedKeys ]
	);
	const visibleAllSelected = useMemo(
		() => allVisibleSelected( filteredRows, selectedKeys ),
		[ filteredRows, selectedKeys ]
	);
	const hasSelectableVisible = useMemo(
		() => selectableRows( filteredRows ).length > 0,
		[ filteredRows ]
	);

	const runBatchSave = async (
		dirtyRows: SegmentRow[],
		inFlightMessage: string,
		successMessage: string,
		partialMessage: string
	) => {
		if ( ! postId || ! languageCode || dirtyRows.length === 0 ) {
			return;
		}

		setBatchSaving( true );
		setBatchMessage( inFlightMessage );

		try {
			const result = await saveBatch(
				postId,
				languageCode,
				dirtyRows.map( ( row ) => ( {
					segment_key: row.segmentKey,
					translated_text: row.draftText,
					source_hash: row.server.source_hash,
					status: 'manually_edited',
				} ) )
			);

			setRows( ( current ) =>
				applyBatchSaveResults( current, result, dirtyRows )
			);

			if ( result.status === 'partial' ) {
				setBatchMessage(
					sprintf( partialMessage, result.errors.length )
				);
			} else {
				setBatchMessage( successMessage );
			}
		} catch ( unknownError ) {
			setBatchMessage(
				unknownError instanceof Error
					? unknownError.message
					: __(
							'Batch save failed. Please try again.',
							'ai-multilingual'
					  )
			);
		} finally {
			setBatchSaving( false );
		}
	};

	const loadSegments = useCallback( async () => {
		if ( ! postId || ! languageCode ) {
			setRows( [] );
			setStatus( null );
			return;
		}

		setLoading( true );
		setError( '' );
		setBatchMessage( '' );

		try {
			const response = await fetchSegments( postId, languageCode );
			setRows( createRowsFromSegments( response.segments ) );
			setStatus( response.status );
			setSegmentFilter( 'all' );
			setSelectedKeys( clearSelection() );
		} catch {
			setError(
				__(
					'Could not load workspace segments for this post.',
					'ai-multilingual'
				)
			);
			setRows( [] );
			setStatus( null );
		} finally {
			setLoading( false );
		}
	}, [ languageCode, postId ] );

	useEffect( () => {
		loadSegments();
	}, [ loadSegments ] );

	const handleDraftChange = ( segmentKey: string, value: string ) => {
		setRows( ( current ) => updateDraftText( current, segmentKey, value ) );
		setBatchMessage( '' );
	};

	const handleSaveRow = async ( segmentKey: string ) => {
		if ( ! postId || ! languageCode ) {
			return;
		}

		const row = rows.find( ( candidate ) => candidate.segmentKey === segmentKey );
		if ( ! row || ! row.server.can_edit ) {
			return;
		}

		setRows( ( current ) =>
			current.map( ( candidate ) =>
				candidate.segmentKey === segmentKey
					? { ...candidate, rowState: 'saving', errorMessage: undefined }
					: candidate
			)
		);

		try {
			const saved = await saveSegment(
				postId,
				languageCode,
				segmentKey,
				row.draftText,
				row.server.source_hash
			);
			setRows( ( current ) => applySaveSuccess( current, segmentKey, saved ) );
			setBatchMessage( '' );
		} catch ( unknownError ) {
			if ( unknownError instanceof WorkspaceConflictError ) {
				const refreshed = unknownError.segments.find(
					( segment ) => segment.segment_key === segmentKey
				);
				setRows( ( current ) =>
					applyConflict(
						current,
						segmentKey,
						refreshed,
						row.draftText,
						__(
							'The source text changed since this segment was loaded.',
							'ai-multilingual'
						)
					)
				);
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
				setRows( ( current ) =>
					current.map( ( candidate ) =>
						candidate.segmentKey === segmentKey
							? {
									...candidate,
									rowState: 'error',
									errorMessage: message,
									server: {
										...candidate.server,
										meta: {
											...candidate.server.meta,
											qa: unknownError.qa,
										},
									},
							  }
							: candidate
					)
				);
				return;
			}

			const message =
				unknownError instanceof Error
					? unknownError.message
					: __(
							'The translation could not be saved. Please try again.',
							'ai-multilingual'
					  );

			setRows( ( current ) =>
				current.map( ( candidate ) =>
					candidate.segmentKey === segmentKey
						? {
								...candidate,
								rowState: 'error',
								errorMessage: message,
						  }
						: candidate
				)
			);
		}
	};

	const handleSuggestProfile = async (
		segmentKey: string,
		profile: string
	) => {
		if ( ! postId || ! languageCode ) {
			return;
		}

		setSuggestingKey( segmentKey );
		setBatchMessage( '' );
		try {
			const updated = await suggestSegment(
				postId,
				languageCode,
				segmentKey,
				profile
			);
			setRows( ( current ) =>
				mergeSegmentsIntoRows( current, [ updated ] )
			);
		} catch ( unknownError ) {
			const message =
				unknownError instanceof Error
					? unknownError.message
					: __(
							'Could not request AI suggestions.',
							'ai-multilingual'
					  );
			setBatchMessage( message );
		} finally {
			setSuggestingKey( null );
		}
	};

	const handleReloadRow = ( segmentKey: string ) => {
		const row = rows.find( ( candidate ) => candidate.segmentKey === segmentKey );
		if ( ! row ) {
			return;
		}

		const server = row.conflictServer ?? row.server;
		setRows( ( current ) =>
			applyReloadFromServer( current, segmentKey, server, true )
		);
	};

	const handleSaveAllDirty = async () => {
		await runBatchSave(
			dirtyRowsInOrder(
				rows.filter( ( row ) => row.rowState !== 'conflict' )
			),
			__( 'Saving all changed segments…', 'ai-multilingual' ),
			__( 'All changed segments were saved.', 'ai-multilingual' ),
			/* translators: %d: number of failed segments */
			__(
				'Some segments could not be saved. %d segment(s) still need attention.',
				'ai-multilingual'
			)
		);
	};

	const handleSaveSelected = async () => {
		await runBatchSave(
			dirtyRowsInOrder(
				selectedDirtyRows(
					rows.filter( ( row ) => row.rowState !== 'conflict' ),
					selectedKeys
				)
			),
			__( 'Saving selected segments…', 'ai-multilingual' ),
			__( 'Selected segments were saved.', 'ai-multilingual' ),
			/* translators: %d: number of failed segments */
			__(
				'Some selected segments could not be saved. %d segment(s) still need attention.',
				'ai-multilingual'
			)
		);
	};

	const handleTranslateSelected = async () => {
		if ( ! postId || ! languageCode || selectedKeys.size === 0 ) {
			return;
		}

		const uniqueKeys = selectedEditableKeys( rows, selectedKeys );
		if ( uniqueKeys.length === 0 ) {
			setBatchMessage(
				__(
					'Select at least one editable segment to translate.',
					'ai-multilingual'
				)
			);
			return;
		}

		setBatchSaving( true );
		setBatchMessage(
			__( 'Requesting automatic translation…', 'ai-multilingual' )
		);

		try {
			const result = await translateBatch(
				postId,
				languageCode,
				uniqueKeys
			);

			if ( result.updated.length > 0 ) {
				setRows( ( current ) =>
					mergeSegmentsIntoRows( current, result.updated )
				);
			}

			if ( result.status === 'failed' ) {
				setBatchMessage(
					result.errors[ 0 ]?.message ||
						__(
							'Automatic translation is not configured.',
							'ai-multilingual'
						)
				);
			} else if ( result.status === 'partial' ) {
				setBatchMessage(
					__(
						'Some selected segments could not be translated automatically.',
						'ai-multilingual'
					)
				);
			} else {
				setBatchMessage(
					__( 'Selected segments were translated.', 'ai-multilingual' )
				);
			}
		} catch ( unknownError ) {
			setBatchMessage(
				unknownError instanceof Error
					? unknownError.message
					: __(
							'Automatic translation is not configured.',
							'ai-multilingual'
					  )
			);
		} finally {
			setBatchSaving( false );
		}
	};

	const handleAcceptTmExact = async () => {
		if ( ! postId || ! languageCode || selectedKeys.size === 0 ) {
			return;
		}

		const uniqueKeys = selectedEditableKeys( rows, selectedKeys );
		if ( uniqueKeys.length === 0 ) {
			setBatchMessage(
				__(
					'Select at least one editable segment to accept TM matches.',
					'ai-multilingual'
				)
			);
			return;
		}

		setBatchSaving( true );
		setBatchMessage( __( 'Accepting exact TM suggestions…', 'ai-multilingual' ) );

		try {
			const result = await acceptTmSuggestions(
				postId,
				languageCode,
				uniqueKeys
			);
			if ( result.updated.length > 0 ) {
				setRows( ( current ) =>
					mergeSegmentsIntoRows( current, result.updated )
				);
			}
			setBatchMessage(
				sprintf(
					/* translators: %d: accepted count */
					__( 'Accepted %d exact TM suggestion(s).', 'ai-multilingual' ),
					result.updated.length
				)
			);
		} catch ( unknownError ) {
			setBatchMessage(
				unknownError instanceof Error
					? unknownError.message
					: __( 'Could not accept TM suggestions.', 'ai-multilingual' )
			);
		} finally {
			setBatchSaving( false );
		}
	};

	const handleRunQa = async () => {
		if ( ! postId || ! languageCode || selectedKeys.size === 0 ) {
			return;
		}

		const uniqueKeys = selectedEditableKeys( rows, selectedKeys );
		setBatchSaving( true );
		setBatchMessage( __( 'Running quality checks…', 'ai-multilingual' ) );

		try {
			const result = await runQaBatch( postId, languageCode, uniqueKeys );
			if ( result.segments.length > 0 ) {
				setRows( ( current ) =>
					mergeSegmentsIntoRows( current, result.segments )
				);
			}
			setBatchMessage(
				sprintf(
					/* translators: 1: errors, 2: warnings */
					__(
						'QA complete — errors: %1$d, warnings: %2$d.',
						'ai-multilingual'
					),
					result.summary.errors,
					result.summary.warnings
				)
			);
		} catch ( unknownError ) {
			setBatchMessage(
				unknownError instanceof Error
					? unknownError.message
					: __( 'Could not run QA.', 'ai-multilingual' )
			);
		} finally {
			setBatchSaving( false );
		}
	};

	const handleToggleSelect = ( segmentKey: string, checked: boolean ) => {
		setSelectedKeys( ( current ) =>
			toggleSelection( current, segmentKey, checked )
		);
	};

	const handleToggleSelectAll = ( checked: boolean ) => {
		setSelectedKeys( ( current ) =>
			checked
				? selectAllVisible( current, filteredRows )
				: deselectAllVisible( current, filteredRows )
		);
	};

	const handlePreview = async () => {
		if ( ! postId || ! languageCode ) {
			return;
		}

		setPreviewError( '' );

		try {
			const url = await fetchPreviewUrl( postId, languageCode );
			window.open( url, '_blank', 'noopener,noreferrer' );
		} catch {
			setPreviewError(
				__(
					'Preview URL is unavailable for this post.',
					'ai-multilingual'
				)
			);
		}
	};

	return (
		<div className="aiml-translator-workspace">
			<Panel>
				<PanelBody
					title={ __( 'Workspace', 'ai-multilingual' ) }
					initialOpen={ true }
				>
					<div className="aiml-workspace-toolbar">
						<LanguageSelect
							languages={ languages }
							value={ languageCode }
							onChange={ ( code ) => {
								setLanguageCode( code );
								setPostId( null );
								setPostSummary( null );
								setSegmentFilter( 'all' );
							} }
						/>
						<PostSelect
							languageCode={ languageCode }
							value={ postId }
							onChange={ ( id, summary ) => {
								setPostId( id );
								setPostSummary( summary );
							} }
						/>
						<div className="aiml-workspace-toolbar-actions">
							<Button
								variant="secondary"
								onClick={ loadSegments }
								disabled={ loading || ! postId }
							>
								{ __( 'Refresh', 'ai-multilingual' ) }
							</Button>
							<Button
								variant="secondary"
								onClick={ handleSaveAllDirty }
								disabled={
									batchSaving ||
									loading ||
									! postId ||
									dirtyCount === 0
								}
							>
								{ batchSaving
									? __( 'Saving…', 'ai-multilingual' )
									: sprintf(
											/* translators: %d: dirty segment count */
											__(
												'Save all changed (%d)',
												'ai-multilingual'
											),
											dirtyCount
									  ) }
							</Button>
							<Button
								variant="primary"
								onClick={ handlePreview }
								disabled={ ! postId || ! languageCode }
							>
								{ __( 'Preview', 'ai-multilingual' ) }
							</Button>
						</div>
					</div>
					{ previewError && (
						<Notice status="error" isDismissible={ false }>
							{ previewError }
						</Notice>
					) }
					{ postSummary && (
						<p className="aiml-workspace-summary">
							{ postSummary.post_title } · { postSummary.total_segments }{ ' ' }
							{ __( 'segments', 'ai-multilingual' ) }
							{ postSummary.stale_count > 0 &&
								` · ${ postSummary.stale_count } ${ __(
									'stale',
									'ai-multilingual'
								) }` }
						</p>
					) }
				</PanelBody>
			</Panel>

			{ status && postId && (
				<PublishContext
					status={ status }
					onPreview={ handlePreview }
					previewDisabled={ ! languageCode }
				/>
			) }

			{ rows.length > 0 && (
				<SegmentFilterBar
					value={ segmentFilter }
					visibleCount={ filteredRows.length }
					totalCount={ rows.length }
					onChange={ setSegmentFilter }
				/>
			) }

			<BulkToolbar
				selectedCount={ selectedKeys.size }
				dirtySelectedCount={ dirtySelectedCount }
				busy={ batchSaving }
				onSaveSelected={ handleSaveSelected }
				onTranslateSelected={ handleTranslateSelected }
				onAcceptTmExact={ handleAcceptTmExact }
				onRunQa={ handleRunQa }
				onClearSelection={ () => setSelectedKeys( clearSelection() ) }
			/>

			<SegmentTable
				rows={ filteredRows }
				loading={ loading }
				error={ error }
				batchMessage={ batchMessage }
				filterActive={ segmentFilter !== 'all' }
				selectedKeys={ selectedKeys }
				allVisibleSelected={ visibleAllSelected }
				hasSelectableVisible={ hasSelectableVisible }
				onToggleSelect={ handleToggleSelect }
				onToggleSelectAll={ handleToggleSelectAll }
				onDraftChange={ handleDraftChange }
				onSave={ handleSaveRow }
				onReload={ handleReloadRow }
				onSuggestProfile={ handleSuggestProfile }
				suggestingKey={ suggestingKey }
			/>

			{ status && (
				<StatusFooter
					status={ status }
					qaSummary={ aggregateQaSummary(
						rows.map( ( row ) => row.server )
					) }
				/>
			) }
		</div>
	);
}
