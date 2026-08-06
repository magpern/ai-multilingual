import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

import type {
	BatchProgressSummary,
	CreateBulkJobPayload,
	CreateBulkJobResponse,
	CreateSingleJobPayload,
	JobAction,
	JobsHealthResponse,
	JobsListResponse,
	TranslationJobDetail,
	TranslationJobSummary,
} from '../types/jobs';
import { WorkspaceRequestError } from './workspace-api';

function namespace(): string {
	return window.aimlTranslatorWorkspace.restNamespace.replace(
		/^\/|\/$/g,
		''
	);
}

function path( route: string ): string {
	return `/${ namespace() }/${ route.replace( /^\//, '' ) }`;
}

function userMessageFromJobsError( error: unknown ): string {
	const candidate = error as {
		code?: string;
		message?: string;
		data?: { status?: number };
	};

	if ( candidate?.code === 'rest_forbidden' || candidate?.code === 'aiml_forbidden' ) {
		return __(
			'You do not have permission to perform this job action.',
			'ai-multilingual'
		);
	}

	if ( candidate?.code === 'action_scheduler_unavailable' ) {
		return __(
			'Background jobs cannot run because Action Scheduler is unavailable.',
			'ai-multilingual'
		);
	}

	if ( candidate?.code === 'provider_unavailable' ) {
		return __(
			'The configured translation provider is not available.',
			'ai-multilingual'
		);
	}

	if ( candidate?.code === 'idempotency_conflict' ) {
		return __(
			'A job with the same idempotency token already exists with different parameters.',
			'ai-multilingual'
		);
	}

	if ( candidate?.code === 'workload_limit_exceeded' ) {
		return __(
			'This job exceeds the allowed workload limits.',
			'ai-multilingual'
		);
	}

	if ( candidate?.code === 'empty_workload' ) {
		return __(
			'No segments qualify for this job type.',
			'ai-multilingual'
		);
	}

	if ( candidate?.message ) {
		return candidate.message;
	}

	return __(
		'The translation job request could not be completed.',
		'ai-multilingual'
	);
}

export interface JobsListParams {
	status?: string;
	batchId?: string;
	languageId?: number;
	page?: number;
	perPage?: number;
}

export async function fetchJobsHealth(): Promise< JobsHealthResponse > {
	try {
		return await apiFetch< JobsHealthResponse >( {
			path: path( 'jobs/health' ),
		} );
	} catch ( error ) {
		throw new WorkspaceRequestError( userMessageFromJobsError( error ) );
	}
}

export async function fetchJobs(
	params: JobsListParams = {}
): Promise< JobsListResponse > {
	const query = new URLSearchParams();
	if ( params.status ) {
		query.set( 'status', params.status );
	}
	if ( params.batchId ) {
		query.set( 'batch_id', params.batchId );
	}
	if ( params.languageId ) {
		query.set( 'language_id', String( params.languageId ) );
	}
	query.set( 'page', String( params.page ?? 1 ) );
	query.set( 'per_page', String( params.perPage ?? 20 ) );

	try {
		return await apiFetch< JobsListResponse >( {
			path: path( `jobs?${ query.toString() }` ),
		} );
	} catch ( error ) {
		throw new WorkspaceRequestError( userMessageFromJobsError( error ) );
	}
}

export async function fetchJob( jobId: number ): Promise< TranslationJobDetail > {
	try {
		return await apiFetch< TranslationJobDetail >( {
			path: path( `jobs/${ jobId }` ),
		} );
	} catch ( error ) {
		throw new WorkspaceRequestError( userMessageFromJobsError( error ) );
	}
}

export async function fetchBatchProgress(
	batchId: string
): Promise< BatchProgressSummary > {
	try {
		return await apiFetch< BatchProgressSummary >( {
			path: path( `jobs/batch/${ encodeURIComponent( batchId ) }` ),
		} );
	} catch ( error ) {
		throw new WorkspaceRequestError( userMessageFromJobsError( error ) );
	}
}

export async function createJob(
	payload: CreateSingleJobPayload
): Promise< TranslationJobSummary > {
	try {
		return await apiFetch< TranslationJobSummary >( {
			path: path( 'jobs' ),
			method: 'POST',
			data: {
				source_type: 'post',
				prompt_profile: 'default',
				prompt_version: '1',
				...payload,
			},
		} );
	} catch ( error ) {
		throw new WorkspaceRequestError( userMessageFromJobsError( error ) );
	}
}

export async function createBulkJobs(
	payload: CreateBulkJobPayload
): Promise< CreateBulkJobResponse > {
	try {
		return await apiFetch< CreateBulkJobResponse >( {
			path: path( 'jobs' ),
			method: 'POST',
			data: {
				prompt_profile: 'default',
				prompt_version: '1',
				...payload,
			},
		} );
	} catch ( error ) {
		throw new WorkspaceRequestError( userMessageFromJobsError( error ) );
	}
}

export async function mutateJob(
	jobId: number,
	action: JobAction,
	sync = false
): Promise< TranslationJobSummary | { job_id: number; queued: boolean; message: string } > {
	try {
		const data = 'run' === action && sync ? { sync: true } : undefined;

		return await apiFetch( {
			path: path( `jobs/${ jobId }/${ action }` ),
			method: 'POST',
			data,
		} );
	} catch ( error ) {
		throw new WorkspaceRequestError( userMessageFromJobsError( error ) );
	}
}
