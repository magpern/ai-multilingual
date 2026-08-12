import { useCallback, useEffect, useState } from '@wordpress/element';
import { Button, Notice, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import {
	approveReview,
	batchReview,
	fetchReviewQueue,
	rejectReview,
} from '../api/workspace-api';
import type { LanguageOption, ReviewQueueItem } from '../types/view-models';
import {
	allQueueVisibleSelected,
	clearQueueSelection,
	deselectAllQueueVisible,
	groupSelectedByPostLanguage,
	isQueueItemSelectable,
	languageCodeForId,
	queueItemKey,
	selectAllQueueVisible,
	selectableQueueItems,
	selectedQueueItems,
	toggleQueueSelection,
} from '../utils/review-queue';
import type { ReviewQueueFilter } from '../utils/review-status';
import ReviewDecisionDialog from './ReviewDecisionDialog';
import ReviewQueueFilterBar from './ReviewQueueFilterBar';
import ReviewQueueRow from './ReviewQueueRow';
import ReviewQueueToolbar from './ReviewQueueToolbar';

interface ReviewQueuePanelProps {
	languages: LanguageOption[];
	canTranslate: boolean;
	onOpenInEditor: ( postId: number, languageCode: string ) => void;
	onOpenInOperations?: ( translationId: number, languageCode: string ) => void;
	initialLanguageCode?: string;
	initialPostId?: string;
}

interface ReviewDialogState {
	action: 'approve' | 'reject';
	targets: ReviewQueueItem[];
	reason: string;
	busy: boolean;
	error: string;
}

const PER_PAGE = 20;

export default function ReviewQueuePanel( {
	languages,
	canTranslate,
	onOpenInEditor,
	onOpenInOperations,
	initialLanguageCode = '',
	initialPostId = '',
}: ReviewQueuePanelProps ) {
	const [ reviewStatus, setReviewStatus ] = useState< ReviewQueueFilter >(
		'pending'
	);
	const [ languageCode, setLanguageCode ] = useState( initialLanguageCode );
	const [ postIdFilter, setPostIdFilter ] = useState( initialPostId );
	const [ page, setPage ] = useState( 1 );
	const [ items, setItems ] = useState< ReviewQueueItem[] >( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ message, setMessage ] = useState( '' );
	const [ selectedKeys, setSelectedKeys ] = useState< Set< string > >(
		() => new Set()
	);
	const [ dialog, setDialog ] = useState< ReviewDialogState | null >( null );

	const load = useCallback( async () => {
		setLoading( true );
		setError( '' );

		const parsedPostId = Number( postIdFilter.trim() );
		const postId =
			postIdFilter.trim() && parsedPostId > 0 ? parsedPostId : undefined;

		try {
			const response = await fetchReviewQueue( {
				postId,
				languageCode: languageCode || undefined,
				reviewStatus,
				page,
				perPage: PER_PAGE,
			} );
			setItems( response.items );
			setTotal( response.total );
		} catch {
			setError(
				__( 'Could not load the review queue.', 'ai-multilingual' )
			);
			setItems( [] );
			setTotal( 0 );
		} finally {
			setLoading( false );
		}
	}, [ postIdFilter, languageCode, reviewStatus, page ] );

	useEffect( () => {
		load();
	}, [ load ] );

	useEffect( () => {
		setPage( 1 );
		setSelectedKeys( clearQueueSelection() );
	}, [ reviewStatus, languageCode, postIdFilter ] );

	const totalPages = Math.max( 1, Math.ceil( total / PER_PAGE ) );
	const selectableVisible = selectableQueueItems( items );

	const openDialog = (
		targets: ReviewQueueItem[],
		action: 'approve' | 'reject'
	) => {
		if ( 0 === targets.length ) {
			return;
		}
		setDialog( { action, targets, reason: '', busy: false, error: '' } );
	};

	const closeDialog = () => setDialog( null );

	const confirmDialog = async () => {
		if ( ! dialog ) {
			return;
		}

		setDialog( ( current ) =>
			current ? { ...current, busy: true, error: '' } : current
		);

		if ( 1 === dialog.targets.length ) {
			const item = dialog.targets[ 0 ];
			const code = languageCodeForId( languages, item.language_id );

			try {
				if ( ! code ) {
					throw new Error(
						__( 'Unknown language for this item.', 'ai-multilingual' )
					);
				}

				if ( 'approve' === dialog.action ) {
					await approveReview(
						item.post_id,
						code,
						item.segment_key,
						undefined,
						item.submitted_translation_hash
					);
				} else {
					await rejectReview(
						item.post_id,
						code,
						item.segment_key,
						dialog.reason,
						undefined,
						item.submitted_translation_hash
					);
				}

				setDialog( null );
				setSelectedKeys( ( current ) =>
					toggleQueueSelection( current, queueItemKey( item ), false )
				);
				setMessage(
					'approve' === dialog.action
						? __( 'Segment approved.', 'ai-multilingual' )
						: __( 'Segment rejected.', 'ai-multilingual' )
				);
				load();
			} catch ( unknownError ) {
				const errorMessage =
					unknownError instanceof Error
						? unknownError.message
						: __(
								'The review action could not be completed.',
								'ai-multilingual'
						  );
				setDialog( ( current ) =>
					current
						? { ...current, busy: false, error: errorMessage }
						: current
				);
			}
			return;
		}

		const groups = groupSelectedByPostLanguage( dialog.targets );
		let successCount = 0;
		let failureCount = 0;

		for ( const group of groups ) {
			const code = languageCodeForId( languages, group.languageId );
			if ( ! code ) {
				failureCount += group.items.length;
				continue;
			}

			try {
				const result = await batchReview(
					group.postId,
					code,
					dialog.action,
					group.items.map( ( item ) => ( {
						segment_key: item.segment_key,
						submitted_translation_hash: item.submitted_translation_hash,
					} ) ),
					dialog.reason
				);
				successCount += result.updated.length;
				failureCount += result.errors.length;
			} catch {
				failureCount += group.items.length;
			}
		}

		setDialog( null );
		setSelectedKeys( clearQueueSelection() );
		setMessage(
			0 === failureCount
				? sprintf(
						/* translators: %d: succeeded count */
						'approve' === dialog.action
							? __( '%d segment(s) approved.', 'ai-multilingual' )
							: __( '%d segment(s) rejected.', 'ai-multilingual' ),
						successCount
				  )
				: sprintf(
						/* translators: 1: succeeded count, 2: failed count */
						__( '%1$d succeeded, %2$d failed.', 'ai-multilingual' ),
						successCount,
						failureCount
				  )
		);
		load();
	};

	return (
		<div
			className="aiml-review-queue-panel"
			role="region"
			aria-label={ __( 'Review queue', 'ai-multilingual' ) }
		>
			<ReviewQueueFilterBar
				languages={ languages }
				languageCode={ languageCode }
				onLanguageChange={ setLanguageCode }
				reviewStatus={ reviewStatus }
				onReviewStatusChange={ setReviewStatus }
				postIdFilter={ postIdFilter }
				onPostIdFilterChange={ setPostIdFilter }
				onRefresh={ load }
				loading={ loading }
			/>

			{ message && (
				<Notice
					status="info"
					isDismissible={ true }
					onRemove={ () => setMessage( '' ) }
				>
					{ message }
				</Notice>
			) }

			<ReviewQueueToolbar
				selectedCount={ selectedKeys.size }
				busy={ loading || null !== dialog }
				onApproveSelected={ () =>
					openDialog(
						selectedQueueItems( items, selectedKeys ),
						'approve'
					)
				}
				onRejectSelected={ () =>
					openDialog(
						selectedQueueItems( items, selectedKeys ),
						'reject'
					)
				}
				onClearSelection={ () =>
					setSelectedKeys( clearQueueSelection() )
				}
			/>

			{ loading && <Spinner /> }

			{ ! loading && error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ ! loading && ! error && 0 === items.length && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'No segments match the current review queue filters.',
						'ai-multilingual'
					) }
				</Notice>
			) }

			{ ! loading && ! error && items.length > 0 && (
				<table className="aiml-review-queue-table widefat striped">
					<thead>
						<tr>
							<th scope="col">
								<label
									className="screen-reader-text"
									htmlFor="aiml-queue-select-all"
								>
									{ __(
										'Select all visible pending segments',
										'ai-multilingual'
									) }
								</label>
								<input
									id="aiml-queue-select-all"
									type="checkbox"
									checked={ allQueueVisibleSelected(
										items,
										selectedKeys
									) }
									disabled={ 0 === selectableVisible.length }
									onChange={ ( event ) =>
										setSelectedKeys( ( current ) =>
											event.target.checked
												? selectAllQueueVisible(
														current,
														items
												  )
												: deselectAllQueueVisible(
														current,
														items
												  )
										)
									}
								/>
							</th>
							<th scope="col">{ __( 'Post', 'ai-multilingual' ) }</th>
							<th scope="col">{ __( 'Language', 'ai-multilingual' ) }</th>
							<th scope="col">{ __( 'Source', 'ai-multilingual' ) }</th>
							<th scope="col">{ __( 'Translation', 'ai-multilingual' ) }</th>
							<th scope="col">{ __( 'Status', 'ai-multilingual' ) }</th>
							<th scope="col">{ __( 'Actions', 'ai-multilingual' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ items.map( ( item ) => (
							<ReviewQueueRow
								key={ queueItemKey( item ) }
								item={ item }
								languages={ languages }
								selected={ selectedKeys.has( queueItemKey( item ) ) }
								selectable={ isQueueItemSelectable( item ) }
								canTranslate={ canTranslate }
								onToggleSelect={ ( key, checked ) =>
									setSelectedKeys( ( current ) =>
										toggleQueueSelection( current, key, checked )
									)
								}
								onApprove={ ( target ) =>
									openDialog( [ target ], 'approve' )
								}
								onReject={ ( target ) =>
									openDialog( [ target ], 'reject' )
								}
								onOpenInEditor={ onOpenInEditor }
								onOpenInOperations={ onOpenInOperations }
							/>
						) ) }
					</tbody>
				</table>
			) }

			{ ! loading && ! error && items.length > 0 && (
				<div
					className="aiml-review-queue-pagination"
					role="navigation"
					aria-label={ __( 'Review queue pagination', 'ai-multilingual' ) }
				>
					<Button
						variant="secondary"
						onClick={ () => setPage( ( current ) => Math.max( 1, current - 1 ) ) }
						disabled={ page <= 1 }
					>
						{ __( 'Previous', 'ai-multilingual' ) }
					</Button>
					<span aria-live="polite">
						{ sprintf(
							/* translators: 1: current page, 2: total pages */
							__( 'Page %1$d of %2$d', 'ai-multilingual' ),
							page,
							totalPages
						) }
					</span>
					<Button
						variant="secondary"
						onClick={ () =>
							setPage( ( current ) => Math.min( totalPages, current + 1 ) )
						}
						disabled={ page >= totalPages }
					>
						{ __( 'Next', 'ai-multilingual' ) }
					</Button>
				</div>
			) }

			{ dialog && (
				<ReviewDecisionDialog
					action={ dialog.action }
					count={ dialog.targets.length }
					reason={ dialog.reason }
					onReasonChange={ ( value ) =>
						setDialog( ( current ) =>
							current ? { ...current, reason: value } : current
						)
					}
					onConfirm={ confirmDialog }
					onCancel={ closeDialog }
					busy={ dialog.busy }
					errorMessage={ dialog.error }
				/>
			) }
		</div>
	);
}
