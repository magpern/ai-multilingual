import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { Button, Notice, Panel, PanelBody } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import {
	WorkspaceConflictError,
	configureWorkspaceApi,
	fetchPreviewUrl,
	fetchSegments,
	saveBatch,
	saveSegment,
} from './api/workspace-api';
import LanguageSelect from './components/LanguageSelect';
import PostSelect from './components/PostSelect';
import SegmentTable from './components/SegmentTable';
import type { SegmentRow } from './types/segment-row';
import type {
	LanguageOption,
	WorkspacePageSummary,
	WorkspaceTranslationStatus,
} from './types/view-models';
import {
	applyConflict,
	applyReloadFromServer,
	applySaveSuccess,
	countDirtyRows,
	createRowsFromSegments,
	dirtyRowsInOrder,
	mergeSegmentsIntoRows,
	updateDraftText,
} from './utils/segment-rows';

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

	const dirtyCount = useMemo( () => countDirtyRows( rows ), [ rows ] );

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
		if ( ! postId || ! languageCode || dirtyCount === 0 ) {
			return;
		}

		setBatchSaving( true );
		setBatchMessage(
			__(
				'Saving all changed segments…',
				'ai-multilingual'
			)
		);

		const dirtyRows = dirtyRowsInOrder(
			rows.filter( ( row ) => row.rowState !== 'conflict' )
		);

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

			let nextRows = mergeSegmentsIntoRows( rows, result.updated );

			for ( const item of result.errors ) {
				const preserved = dirtyRows.find(
					( row ) => row.segmentKey === item.segment_key
				);
				if ( ! preserved ) {
					continue;
				}

				if ( item.code === 'aiml_source_hash_mismatch' ) {
					const refreshed = item.segments?.find(
						( segment ) => segment.segment_key === item.segment_key
					);
					nextRows = applyConflict(
						nextRows,
						item.segment_key,
						refreshed,
						preserved.draftText,
						item.message ||
							__(
								'The source text changed since this segment was loaded.',
								'ai-multilingual'
							)
					);
					continue;
				}

				nextRows = nextRows.map( ( row ) =>
					row.segmentKey === item.segment_key
						? {
								...row,
								rowState: 'error',
								errorMessage:
									item.message ||
									__(
										'The translation could not be saved. Please try again.',
										'ai-multilingual'
									),
						  }
						: row
				);
			}

			setRows( nextRows );

			if ( result.status === 'partial' ) {
				setBatchMessage(
					sprintf(
						/* translators: %d: number of failed segments */
						__(
							'Some segments could not be saved. %d segment(s) still need attention.',
							'ai-multilingual'
						),
						result.errors.length
					)
				);
			} else {
				setBatchMessage(
					__( 'All changed segments were saved.', 'ai-multilingual' )
				);
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

			<SegmentTable
				rows={ rows }
				loading={ loading }
				error={ error }
				batchMessage={ batchMessage }
				onDraftChange={ handleDraftChange }
				onSave={ handleSaveRow }
				onReload={ handleReloadRow }
			/>

			{ status && (
				<div className="aiml-workspace-footer">
					<strong>{ __( 'Page status', 'ai-multilingual' ) }</strong>
					{ ': ' }
					{ status.overall_state }
					{ ' · ' }
					{ status.translated_count }
					{ '/' }
					{ status.total_segments }
					{ ' ' }
					{ __( 'translated', 'ai-multilingual' ) }
				</div>
			) }
		</div>
	);
}
