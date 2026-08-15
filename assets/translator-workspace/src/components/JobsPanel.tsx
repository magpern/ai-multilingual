import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { Notice, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import {
	fetchBatchProgress,
	fetchJob,
	fetchJobs,
	fetchJobsHealth,
	mutateJob,
} from '../api/jobs-api';
import type {
	BatchProgressSummary,
	JobAction,
	JobStatus,
	JobsHealthResponse,
	TranslationJobDetail,
	TranslationJobSummary,
} from '../types/jobs';
import type { LanguageOption } from '../types/view-models';
import {
	formatBatchProgress,
	groupJobsByBatch,
	jobActionConfirmMessage,
} from '../utils/jobs';
import CreateJobDialog from './CreateJobDialog';
import JobActionConfirmDialog from './JobActionConfirmDialog';
import JobRow from './JobRow';
import JobsFilterBar from './JobsFilterBar';
import JobsHealthBanner from './JobsHealthBanner';

interface JobsPanelProps {
	languages: LanguageOption[];
	canManage: boolean;
	canCancel: boolean;
	canRun: boolean;
	focusedJobId?: number | null;
	focusedItemId?: number | null;
	onOpenOperations?: ( args: {
		sourceType: string;
		sourceId: number;
		languageId: number;
	} ) => void;
}

interface PendingAction {
	jobId: number;
	action: JobAction;
}

const PER_PAGE = 20;

const ACTIVE_POLL_STATUSES = new Set( [
	'queued',
	'running',
	'retry_wait',
] );

export default function JobsPanel( {
	languages,
	canManage,
	canCancel,
	canRun,
	focusedJobId = null,
	focusedItemId = null,
	onOpenOperations,
}: JobsPanelProps ) {
	const [ statusFilter, setStatusFilter ] = useState< JobStatus | 'all' >(
		'all'
	);
	const [ languageCode, setLanguageCode ] = useState( '' );
	const [ batchIdFilter, setBatchIdFilter ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ jobs, setJobs ] = useState< TranslationJobSummary[] >( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ message, setMessage ] = useState( '' );
	const [ health, setHealth ] = useState< JobsHealthResponse | null >( null );
	const [ healthLoading, setHealthLoading ] = useState( false );
	const [ healthError, setHealthError ] = useState( '' );
	const [ batchSummary, setBatchSummary ] =
		useState< BatchProgressSummary | null >( null );
	const [ expandedJobId, setExpandedJobId ] = useState< number | null >( null );
	const [ jobDetails, setJobDetails ] = useState<
		Record< number, TranslationJobDetail >
	>( {} );
	const [ createOpen, setCreateOpen ] = useState( false );
	const [ pendingAction, setPendingAction ] = useState< PendingAction | null >(
		null
	);
	const [ actionBusy, setActionBusy ] = useState( false );
	const [ actionError, setActionError ] = useState( '' );

	const languageId = useMemo( () => {
		if ( ! languageCode ) {
			return undefined;
		}
		return (
			languages.find( ( language ) => language.code === languageCode )
				?.language_id ?? undefined
		);
	}, [ languageCode, languages ] );

	const loadHealth = useCallback( async () => {
		setHealthLoading( true );
		setHealthError( '' );
		try {
			setHealth( await fetchJobsHealth() );
		} catch ( unknownError ) {
			setHealth( null );
			setHealthError(
				unknownError instanceof Error
					? unknownError.message
					: __(
							'Could not load background job health.',
							'ai-multilingual'
					  )
			);
		} finally {
			setHealthLoading( false );
		}
	}, [] );

	const loadJobs = useCallback( async () => {
		setLoading( true );
		setError( '' );

		const trimmedBatch = batchIdFilter.trim();

		try {
			const response = await fetchJobs( {
				status: 'all' === statusFilter ? undefined : statusFilter,
				batchId: trimmedBatch || undefined,
				languageId,
				page,
				perPage: PER_PAGE,
			} );
			setJobs( response.items );
			setTotal( response.total );

			if ( trimmedBatch ) {
				setBatchSummary( await fetchBatchProgress( trimmedBatch ) );
			} else {
				setBatchSummary( null );
			}
		} catch {
			setJobs( [] );
			setTotal( 0 );
			setBatchSummary( null );
			setError( __( 'Could not load translation jobs.', 'ai-multilingual' ) );
		} finally {
			setLoading( false );
		}
	}, [ batchIdFilter, languageId, page, statusFilter ] );

	useEffect( () => {
		loadHealth();
	}, [ loadHealth ] );

	useEffect( () => {
		loadJobs();
	}, [ loadJobs ] );

	useEffect( () => {
		const hasActive = jobs.some( ( job ) =>
			ACTIVE_POLL_STATUSES.has( job.status )
		);
		if ( ! hasActive ) {
			return;
		}

		const timer = window.setInterval( () => {
			loadJobs();
			loadHealth();
		}, 8000 );

		return () => {
			window.clearInterval( timer );
		};
	}, [ jobs, loadJobs, loadHealth ] );

	useEffect( () => {
		if ( null === focusedJobId || focusedJobId <= 0 ) {
			return;
		}
		setExpandedJobId( focusedJobId );
	}, [ focusedJobId, focusedItemId ] );

	useEffect( () => {
		setPage( 1 );
	}, [ statusFilter, languageCode, batchIdFilter ] );

	useEffect( () => {
		if ( null === expandedJobId ) {
			return;
		}

		if ( jobDetails[ expandedJobId ] ) {
			return;
		}

		let cancelled = false;

		fetchJob( expandedJobId )
			.then( ( detail ) => {
				if ( ! cancelled ) {
					setJobDetails( ( current ) => ( {
						...current,
						[ expandedJobId ]: detail,
					} ) );
				}
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setMessage(
						__(
							'Could not load job details for the selected row.',
							'ai-multilingual'
						)
					);
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ expandedJobId, jobDetails ] );

	const totalPages = Math.max( 1, Math.ceil( total / PER_PAGE ) );
	const groupedJobs = useMemo( () => groupJobsByBatch( jobs ), [ jobs ] );

	const confirmAction = async () => {
		if ( ! pendingAction ) {
			return;
		}

		setActionBusy( true );
		setActionError( '' );

		try {
			await mutateJob( pendingAction.jobId, pendingAction.action );
			setPendingAction( null );
			setMessage(
				sprintf(
					/* translators: %d: job id */
					__( 'Job #%d updated.', 'ai-multilingual' ),
					pendingAction.jobId
				)
			);
			loadJobs();
			loadHealth();
		} catch ( unknownError ) {
			setActionError(
				unknownError instanceof Error
					? unknownError.message
					: __(
							'The job action could not be completed.',
							'ai-multilingual'
					  )
			);
		} finally {
			setActionBusy( false );
		}
	};

	return (
		<div
			className="aiml-jobs-panel"
			role="region"
			aria-label={ __( 'Translation jobs', 'ai-multilingual' ) }
		>
			<JobsHealthBanner
				health={ health }
				loading={ healthLoading }
				error={ healthError }
			/>

			<JobsFilterBar
				languages={ languages }
				languageCode={ languageCode }
				onLanguageChange={ setLanguageCode }
				status={ statusFilter }
				onStatusChange={ setStatusFilter }
				batchIdFilter={ batchIdFilter }
				onBatchIdFilterChange={ setBatchIdFilter }
				onRefresh={ () => {
					loadHealth();
					loadJobs();
				} }
				onCreate={ () => setCreateOpen( true ) }
				loading={ loading }
				canManage={ canManage }
			/>

			{ batchSummary && (
				<Notice status="info" isDismissible={ false }>
					<strong>{ __( 'Batch summary', 'ai-multilingual' ) }</strong>
					{ ': ' }
					{ formatBatchProgress( batchSummary ) }
				</Notice>
			) }

			{ message && (
				<Notice
					status="info"
					isDismissible={ true }
					onRemove={ () => setMessage( '' ) }
				>
					{ message }
				</Notice>
			) }

			{ loading && <Spinner /> }

			{ ! loading && error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ ! loading && ! error && 0 === jobs.length && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'No translation jobs match the current filters.',
						'ai-multilingual'
					) }
				</Notice>
			) }

			{ ! loading && ! error && jobs.length > 0 && (
				<>
					{ Array.from( groupedJobs.entries() ).map( ( [ batchId, batchJobs ] ) => (
						<section
							key={ batchId ?? 'standalone' }
							className="aiml-jobs-batch-group"
						>
							{ batchId && (
								<h2 className="aiml-jobs-batch-heading">
									{ sprintf(
										/* translators: 1: batch id, 2: job count */
										__( 'Batch %1$s · %2$d jobs', 'ai-multilingual' ),
										batchId,
										batchJobs.length
									) }
								</h2>
							) }
							<table className="aiml-jobs-table widefat striped">
								<thead>
									<tr>
										<th scope="col">{ __( 'Job', 'ai-multilingual' ) }</th>
										<th scope="col">{ __( 'Type', 'ai-multilingual' ) }</th>
										<th scope="col">{ __( 'Status', 'ai-multilingual' ) }</th>
										<th scope="col">{ __( 'Language', 'ai-multilingual' ) }</th>
										<th scope="col">{ __( 'Source', 'ai-multilingual' ) }</th>
										<th scope="col">{ __( 'Progress', 'ai-multilingual' ) }</th>
										<th scope="col">{ __( 'Batch', 'ai-multilingual' ) }</th>
										<th scope="col">{ __( 'Actions', 'ai-multilingual' ) }</th>
									</tr>
								</thead>
								<tbody>
									{ batchJobs.map( ( job ) => (
										<JobRow
											key={ job.job_id }
											job={ job }
											detail={ jobDetails[ job.job_id ] ?? null }
											expanded={ expandedJobId === job.job_id }
											languages={ languages }
											canCancel={ canCancel }
											canRun={ canRun }
											busy={ actionBusy }
											focusedItemId={
												expandedJobId === job.job_id
													? focusedItemId
													: null
											}
											onToggleExpand={ ( jobId ) =>
												setExpandedJobId( ( current ) =>
													current === jobId ? null : jobId
												)
											}
											onAction={ ( jobId, action ) =>
												setPendingAction( { jobId, action } )
											}
											onOpenOperations={ onOpenOperations }
										/>
									) ) }
								</tbody>
							</table>
						</section>
					) ) }

					<div
						className="aiml-jobs-pagination"
						role="navigation"
						aria-label={ __( 'Jobs pagination', 'ai-multilingual' ) }
					>
						<button
							type="button"
							className="button"
							onClick={ () =>
								setPage( ( current ) => Math.max( 1, current - 1 ) )
							}
							disabled={ page <= 1 }
						>
							{ __( 'Previous', 'ai-multilingual' ) }
						</button>
						<span aria-live="polite">
							{ sprintf(
								/* translators: 1: current page, 2: total pages */
								__( 'Page %1$d of %2$d', 'ai-multilingual' ),
								page,
								totalPages
							) }
						</span>
						<button
							type="button"
							className="button"
							onClick={ () =>
								setPage( ( current ) =>
									Math.min( totalPages, current + 1 )
								)
							}
							disabled={ page >= totalPages }
						>
							{ __( 'Next', 'ai-multilingual' ) }
						</button>
					</div>
				</>
			) }

			{ createOpen && (
				<CreateJobDialog
					languages={ languages }
					open={ createOpen }
					onClose={ () => setCreateOpen( false ) }
					onCreated={ () => {
						setMessage(
							canRun
								? __(
										'Translation job created and waiting. Expand the job and use Run now to start processing.',
										'ai-multilingual'
								  )
								: __(
										'Translation job created and waiting. An administrator must use Run now to start processing.',
										'ai-multilingual'
								  )
						);
						loadJobs();
						loadHealth();
					} }
				/>
			) }

			{ pendingAction && (
				<JobActionConfirmDialog
					action={ pendingAction.action }
					jobId={ pendingAction.jobId }
					message={ jobActionConfirmMessage( pendingAction.action ) }
					onConfirm={ confirmAction }
					onCancel={ () => {
						setPendingAction( null );
						setActionError( '' );
					} }
					busy={ actionBusy }
					errorMessage={ actionError }
				/>
			) }
		</div>
	);
}
