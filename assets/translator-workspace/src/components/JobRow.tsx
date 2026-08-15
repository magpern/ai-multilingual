import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import type { JobAction, TranslationJobDetail, TranslationJobSummary } from '../types/jobs';
import type { LanguageOption } from '../types/view-models';
import {
	attentionJobItems,
	jobItemNextActionHint,
	jobItemStatusLabel,
} from '../utils/job-item-literacy';
import {
	boundedItemError,
	boundedJobError,
	canCancelJob,
	canPauseJob,
	canResumeJob,
	canRetryFailedJob,
	canRunJob,
	formatJobProgress,
	jobTypeLabel,
	languageLabelForId,
} from '../utils/jobs';
import JobStatusBadge from './JobStatusBadge';

interface JobRowProps {
	job: TranslationJobSummary;
	detail: TranslationJobDetail | null;
	expanded: boolean;
	languages: LanguageOption[];
	canCancel: boolean;
	canRun: boolean;
	busy: boolean;
	focusedItemId?: number | null;
	onToggleExpand: ( jobId: number ) => void;
	onAction: ( jobId: number, action: JobAction ) => void;
	onOpenOperations?: ( args: {
		sourceType: string;
		sourceId: number;
		languageId: number;
	} ) => void;
}

export default function JobRow( {
	job,
	detail,
	expanded,
	languages,
	canCancel,
	canRun,
	busy,
	focusedItemId = null,
	onToggleExpand,
	onAction,
	onOpenOperations,
}: JobRowProps ) {
	const jobError = boundedJobError( job );
	const attentionItems = attentionJobItems( detail?.items );
	const showRun = canRun && canRunJob( job );
	const showRetry = canRun && canRetryFailedJob( job );
	const showPause = canCancel && canPauseJob( job );
	const showResume = canCancel && canResumeJob( job );
	const showCancel = canCancel && canCancelJob( job );

	return (
		<>
			<tr className="aiml-jobs-row">
				<td>{ job.job_id }</td>
				<td>{ jobTypeLabel( job.job_type ) }</td>
				<td>
					<JobStatusBadge
						status={ job.status }
						requestedAction={ job.requested_action }
					/>
				</td>
				<td>{ languageLabelForId( languages, job.language_id ) }</td>
				<td>{ sprintf( __( 'Post #%d', 'ai-multilingual' ), job.source_id ) }</td>
				<td>{ formatJobProgress( job ) }</td>
				<td>{ job.batch_id || '—' }</td>
				<td className="aiml-jobs-row-actions">
					<Button
						variant="link"
						onClick={ () => onToggleExpand( job.job_id ) }
						disabled={ busy }
					>
						{ expanded
							? __( 'Hide details', 'ai-multilingual' )
							: __( 'Details', 'ai-multilingual' ) }
					</Button>
					{ showPause && (
						<Button
							variant="secondary"
							onClick={ () => onAction( job.job_id, 'pause' ) }
							disabled={ busy }
						>
							{ __( 'Pause', 'ai-multilingual' ) }
						</Button>
					) }
					{ showResume && (
						<Button
							variant="secondary"
							onClick={ () => onAction( job.job_id, 'resume' ) }
							disabled={ busy }
						>
							{ __( 'Resume', 'ai-multilingual' ) }
						</Button>
					) }
					{ showCancel && (
						<Button
							variant="secondary"
							isDestructive
							onClick={ () => onAction( job.job_id, 'cancel' ) }
							disabled={ busy }
						>
							{ __( 'Cancel', 'ai-multilingual' ) }
						</Button>
					) }
					{ showRetry && (
						<Button
							variant="secondary"
							onClick={ () => onAction( job.job_id, 'retry-failed' ) }
							disabled={ busy }
						>
							{ __( 'Retry failed', 'ai-multilingual' ) }
						</Button>
					) }
					{ showRun && (
						<Button
							variant="primary"
							onClick={ () => onAction( job.job_id, 'run' ) }
							disabled={ busy }
						>
							{ __( 'Run now', 'ai-multilingual' ) }
						</Button>
					) }
				</td>
			</tr>
			{ expanded && (
				<tr className="aiml-jobs-row-detail">
					<td colSpan={ 8 }>
						{ 'queued' === job.status && showRun && (
							<p className="aiml-jobs-run-cta" role="status">
								{ __(
									'This job is waiting. Use Run now to start processing (administrators only).',
									'ai-multilingual'
								) }
							</p>
						) }
						{ 'queued' === job.status && ! canRun && (
							<p className="aiml-jobs-run-cta" role="status">
								{ __(
									'This job is waiting for an administrator to run it.',
									'ai-multilingual'
								) }
							</p>
						) }
						{ onOpenOperations && (
							<p>
								<Button
									variant="link"
									onClick={ () =>
										onOpenOperations( {
											sourceType: job.source_type,
											sourceId: job.source_id,
											languageId: job.language_id,
										} )
									}
									disabled={ busy }
								>
									{ __(
										'Open source in Operations',
										'ai-multilingual'
									) }
								</Button>
							</p>
						) }
						{ jobError && (
							<div className="aiml-jobs-error">
								<strong>{ __( 'Job error', 'ai-multilingual' ) }</strong>
								<p>
									<code>{ jobError.code }</code> — { jobError.message }
								</p>
							</div>
						) }
						{ attentionItems.length > 0 ? (
							<div className="aiml-jobs-item-errors">
								<strong>
									{ __(
										'Items needing attention',
										'ai-multilingual'
									) }
								</strong>
								<ul>
									{ attentionItems.map( ( item ) => {
										const itemError = boundedItemError( item );
										const hint = jobItemNextActionHint( item.status );
										const focused =
											null !== focusedItemId &&
											focusedItemId === item.item_id;

										return (
											<li
												key={ item.item_id }
												className={
													focused
														? 'aiml-jobs-item--focused'
														: undefined
												}
											>
												<code>{ item.segment_key }</code>
												{ ': ' }
												<strong>
													{ jobItemStatusLabel( item.status ) }
												</strong>
												{ itemError
													? ` — ${ itemError.message }`
													: '' }
												{ hint ? (
													<p className="aiml-jobs-item-hint">
														{ hint }
													</p>
												) : null }
											</li>
										);
									} ) }
								</ul>
							</div>
						) : (
							<p className="aiml-jobs-detail-empty">
								{ detail
									? __(
											'No failed, conflict, or source-moved items for this job.',
											'ai-multilingual'
									  )
									: __(
											'Loading job details…',
											'ai-multilingual'
									  ) }
							</p>
						) }
					</td>
				</tr>
			) }
		</>
	);
}
