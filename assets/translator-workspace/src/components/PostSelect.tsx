import { useEffect, useState } from '@wordpress/element';
import { ComboboxControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { fetchPosts } from '../api/workspace-api';
import type { WorkspacePageSummary } from '../types/view-models';

interface PostSelectProps {
	languageCode: string;
	value: number | null;
	onChange: ( postId: number | null, post: WorkspacePageSummary | null ) => void;
}

export default function PostSelect( {
	languageCode,
	value,
	onChange,
}: PostSelectProps ) {
	const [ search, setSearch ] = useState( '' );
	const [ items, setItems ] = useState< WorkspacePageSummary[] >( [] );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		if ( ! languageCode ) {
			setItems( [] );
			onChange( null, null );
			return;
		}

		let cancelled = false;
		setLoading( true );
		setError( '' );

		fetchPosts( languageCode, search )
			.then( ( response ) => {
				if ( cancelled ) {
					return;
				}
				setItems( response.items );
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setError(
						__(
							'Could not load posts for the workspace.',
							'ai-multilingual'
						)
					);
				}
			} )
			.finally( () => {
				if ( ! cancelled ) {
					setLoading( false );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ languageCode, search ] );

	useEffect( () => {
		if ( ! languageCode ) {
			onChange( null, null );
		}
	}, [ languageCode, onChange ] );

	const options = items.map( ( item ) => ( {
		value: String( item.post_id ),
		label: `${ item.post_title } (${ item.post_type })`,
	} ) );

	return (
		<div className="aiml-workspace-post-select">
			<ComboboxControl
				label={ __( 'Post or page', 'ai-multilingual' ) }
				value={ value ? String( value ) : '' }
				options={ ! languageCode || loading ? [] : options }
				onChange={ ( next ) => {
					if ( ! languageCode || loading ) {
						return;
					}
					const postId = next ? Number( next ) : null;
					const post =
						items.find( ( item ) => item.post_id === postId ) ??
						null;
					onChange( postId, post );
				} }
				onFilterValueChange={ setSearch }
				help={ error || undefined }
			/>
			{ loading && <Spinner /> }
		</div>
	);
}
