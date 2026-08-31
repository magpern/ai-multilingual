export interface SiteTranslateCoverage {
	eligible_total: number;
	missing: number;
	translated: number;
	unpublished: number;
	published: number;
	stale: number;
	blocked_or_unsupported: string[];
	translation_complete: boolean;
	no_extractable_work: boolean;
}

export interface SiteTranslateObjectRow {
	post_id: number;
	post_title: string;
	post_type: string;
	post_status: string;
	modified_gmt: string;
	body_surface: string;
	coverage: SiteTranslateCoverage;
}

export interface SiteTranslateObjectsResponse {
	items: SiteTranslateObjectRow[];
	page: number;
	per_page: number;
	total: number;
	total_pages: number;
	language_id: number;
	strategy_f_valid: boolean;
}

export interface SiteTranslateCreateJobsResponse {
	batch_id: string;
	complete: boolean;
	created_count: number;
	attempted_count: number;
	skipped_count: number;
	chunk_count: number;
	failed: Array<{
		source_id: number;
		code: string;
		message: string;
		status: number;
	}>;
	jobs: Array< Record< string, unknown > >;
}

export interface SiteTranslateRunBatchResponse {
	batch_id: string;
	enqueued_job_ids: number[];
	skipped_job_ids: number[];
}

export interface SiteTranslateRouteOutcome {
	post_id: number;
	post_type: string;
	outcome: string;
	message: string;
	details?: Record< string, unknown >;
}

export interface SiteTranslateRoutesResponse {
	language_id: number;
	total: number;
	success_count: number;
	outcomes: SiteTranslateRouteOutcome[];
}

export interface SiteTranslateAdmissionResponse {
	allowed: boolean;
	strategy_f_valid: boolean;
}
