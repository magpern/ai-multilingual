import { useCallback, useEffect, useState } from '@wordpress/element';
import { Button, Notice, Panel, PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import {
	WorkspaceRequestError,
	clearSlugCandidate,
	fetchSlugRouteView,
	generateSlugCandidate,
	publishSlugRoute,
	saveSlugCandidate,
	type SlugRouteView,
} from '../api/workspace-api';

interface LocalizedSlugPanelProps {
	postId: number;
	languageCode: string;
}

export default function LocalizedSlugPanel( {
	postId,
	languageCode,
}: LocalizedSlugPanelProps ) {
	const [ view, setView ] = useState<SlugRouteView | null>( null );
	const [ draft, setDraft ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ message, setMessage ] = useState( '' );
	const [ error, setError ] = useState( '' );

	const apply = useCallback( ( next: SlugRouteView ) => {
		setView( next );
		setDraft( next.slug_candidate || '' );
		if ( next.collision_adjusted ) {
			setError(
				__(
					'The published path was adjusted for a collision. Review the effective path below.',
					'ai-multilingual'
				)
			);
		}
	}, [] );

	const load = useCallback( async () => {
		if ( ! postId || ! languageCode ) {
			return;
		}
		setBusy( true );
		setError( '' );
		try {
			apply( await fetchSlugRouteView( postId, languageCode ) );
		} catch ( err ) {
			setError(
				err instanceof WorkspaceRequestError
					? err.userMessage
					: __( 'Could not load localized slug state.', 'ai-multilingual' )
			);
		} finally {
			setBusy( false );
		}
	}, [ apply, languageCode, postId ] );

	useEffect( () => {
		void load();
	}, [ load ] );

	const run = async (
		action: () => Promise<SlugRouteView>,
		okMessage: string
	) => {
		setBusy( true );
		setError( '' );
		setMessage( '' );
		try {
			apply( await action() );
			setMessage( okMessage );
		} catch ( err ) {
			setError(
				err instanceof WorkspaceRequestError
					? err.userMessage
					: __( 'The localized slug action failed.', 'ai-multilingual' )
			);
		} finally {
			setBusy( false );
		}
	};

	if ( ! postId || ! languageCode ) {
		return null;
	}

	return (
		<Panel className="aiml-workspace-localized-slug">
			<PanelBody
				title={ __( 'Localized URL slug', 'ai-multilingual' ) }
				initialOpen={ true }
			>
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }
				{ message && ! error && (
					<Notice status="success" isDismissible={ false }>
						{ message }
					</Notice>
				) }
				{ view?.route_publication_blocked_reason && (
					<Notice status="warning" isDismissible={ false }>
						{ __( 'Publication blocked:', 'ai-multilingual' ) }{ ' ' }
						{ view.route_publication_blocked_reason }
					</Notice>
				) }
				<TextControl
					label={ __( 'Slug candidate', 'ai-multilingual' ) }
					value={ draft }
					onChange={ setDraft }
					disabled={ busy || view?.can_edit_slug === false }
				/>
				<p className="description">
					{ __( 'Origin:', 'ai-multilingual' ) }{ ' ' }
					{ view?.slug_origin || '—' }
					{ ' · ' }
					{ __( 'Effective path:', 'ai-multilingual' ) }{ ' ' }
					{ view?.localized_path ||
						view?.active_route_slug ||
						'—' }
					{ ' · ' }
					{ __( 'Sync:', 'ai-multilingual' ) }{ ' ' }
					{ view?.route_sync_state || '—' }
				</p>
				<div className="aiml-workspace-localized-slug-actions">
					<Button
						variant="secondary"
						disabled={ busy || view?.can_generate === false }
						onClick={ () =>
							void run(
								() => generateSlugCandidate( postId, languageCode ),
								__( 'Candidate generated.', 'ai-multilingual' )
							)
						}
					>
						{ __( 'Generate', 'ai-multilingual' ) }
					</Button>
					<Button
						variant="secondary"
						disabled={ busy }
						onClick={ () =>
							void run(
								() =>
									saveSlugCandidate(
										postId,
										languageCode,
										draft
									),
								__( 'Candidate saved.', 'ai-multilingual' )
							)
						}
					>
						{ __( 'Save candidate', 'ai-multilingual' ) }
					</Button>
					<Button
						variant="secondary"
						disabled={ busy }
						onClick={ () =>
							void run(
								() => clearSlugCandidate( postId, languageCode ),
								__( 'Candidate cleared.', 'ai-multilingual' )
							)
						}
					>
						{ __( 'Clear', 'ai-multilingual' ) }
					</Button>
					<Button
						variant="primary"
						disabled={ busy || view?.can_publish_route === false }
						onClick={ () =>
							void run(
								() => publishSlugRoute( postId, languageCode ),
								__( 'Route published.', 'ai-multilingual' )
							)
						}
					>
						{ __( 'Publish route', 'ai-multilingual' ) }
					</Button>
					<Button
						variant="link"
						disabled={ busy }
						onClick={ () => void load() }
					>
						{ __( 'Refresh', 'ai-multilingual' ) }
					</Button>
				</div>
			</PanelBody>
		</Panel>
	);
}
