import { useCallback, useEffect, useState } from '@wordpress/element';
import { Button, Notice, Panel, PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import {
	configureWorkspaceApi,
	fetchPreviewUrl,
	fetchSegments,
} from './api/workspace-api';
import LanguageSelect from './components/LanguageSelect';
import PostSelect from './components/PostSelect';
import SegmentTable from './components/SegmentTable';
import type {
	LanguageOption,
	WorkspacePageSummary,
	WorkspaceSegment,
	WorkspaceTranslationStatus,
} from './types/view-models';

configureWorkspaceApi();

export default function App() {
	const languages: LanguageOption[] =
		window.aimlTranslatorWorkspace.languages ?? [];

	const [ languageCode, setLanguageCode ] = useState(
		languages[ 0 ]?.code ?? ''
	);
	const [ postId, setPostId ] = useState< number | null >( null );
	const [ postSummary, setPostSummary ] =
		useState< WorkspacePageSummary | null >( null );
	const [ segments, setSegments ] = useState< WorkspaceSegment[] >( [] );
	const [ status, setStatus ] =
		useState< WorkspaceTranslationStatus | null >( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ previewError, setPreviewError ] = useState( '' );

	const loadSegments = useCallback( async () => {
		if ( ! postId || ! languageCode ) {
			setSegments( [] );
			setStatus( null );
			return;
		}

		setLoading( true );
		setError( '' );

		try {
			const response = await fetchSegments( postId, languageCode );
			setSegments( response.segments );
			setStatus( response.status );
		} catch {
			setError(
				__(
					'Could not load workspace segments for this post.',
					'ai-multilingual'
				)
			);
			setSegments( [] );
			setStatus( null );
		} finally {
			setLoading( false );
		}
	}, [ languageCode, postId ] );

	useEffect( () => {
		loadSegments();
	}, [ loadSegments ] );

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
							<Button variant="secondary" onClick={ loadSegments } disabled={ loading || ! postId }>
								{ __( 'Refresh', 'ai-multilingual' ) }
							</Button>
							<Button variant="primary" onClick={ handlePreview } disabled={ ! postId || ! languageCode }>
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
				segments={ segments }
				loading={ loading }
				error={ error }
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
