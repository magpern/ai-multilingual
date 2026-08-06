import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import type { JobAction, TranslationJobDetail, TranslationJobSummary } from '../types/jobs';
import type { LanguageOption } from '../types/view-models';
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
	onToggleExpand: ( jobId: number ) => void;
	onAction: ( jobId: number, action: JobAction ) => void;
}

export default function JobRow( {
	job,
	detail,
	expanded,
	languages,
	canCancel,
	canRun,
	busy,
	onToggleExpand,
	onAction,
}: JobRowProps ) {
	const jobError = boundedJobError( job );
	const failedItems =
		detail?.items?.filter( ( item ) => boundedItemError( item ) ) ?? [];

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
					{ canCancel && canPauseJob( job ) && (
						<Button
							variant="secondary"
							onClick={ () => onAction( job.job_id, 'pause' ) }
							disabled={ busy }
						>
							{ __( 'Pause', 'ai-multilingual' ) }
						</Button>
					) }
					{ canCancel && canResumeJob( job ) && (
						<Button
							variant="secondary"
							onClick={ () => onAction( job.job_id, 'resume' ) }
							disabled={ busy }
						>
							{ __( 'Resume', 'ai-multilingual' ) }
						</Button>
					) }
					{ canCancel && canCancelJob( job ) && (
						<Button
							variant="secondary"
							isDestructive
							onClick={ () => onAction( job.job_id, 'cancel' ) }
							disabled={ busy }
						>
							{ __( 'Cancel', 'ai-multilingual' ) }
						</Button>
					) }
					{ canRun && canRetryFailedJob( job ) && (
						<Button
							variant="secondary"
							onClick={ () => onAction( job.job_id, 'retry-failed' ) }
							disabled={ busy }
						>
							{ __( 'Retry failed', 'ai-multilingual' ) }
						</Button>
					) }
					{ canRun && canRunJob( job ) && (
						<Button
							variant="primary"
							onClick={ () => onAction( job.job_id, 'run' ) }
							disabled={ busy }
						>
							{ __( 'Run', 'ai-multilingual' ) }
						</Button>
					) }
				</td>
			</tr>
			{ expanded && (
				<tr className="aiml-jobs-row-detail">
					<td colSpan={ 8 }>
						{ jobError && (
							<div className="aiml-jobs-error">
								<strong>{ __( 'Job error', 'ai-multilingual' ) }</strong>
								<p>
									<code>{ jobError.code }</code> — { jobError.message }
								</p>
							</div>
						) }
						{ failedItems.length > 0 ? (
							<div className="aiml-jobs-item-errors">
								<strong>
									{ __( 'Failed item errors', 'ai-multilingual' ) }
								</strong>
								<ul>
									{ failedItems.map( ( item ) => {
										const itemError = boundedItemError( item );
										if ( ! itemError ) {
											return null;
										}

										return (
											<li key={ item.item_id }>
												<code>{ item.segment_key }</code>
												{ ': ' }
												<code>{ itemError.code }</code> — { itemError.message }
											</li>
										);
									} ) }
								</ul>
							</div>
						) : (
							<p className="aiml-jobs-detail-empty">
								{ detail
									? __(
											'No bounded item errors for this job.',
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
