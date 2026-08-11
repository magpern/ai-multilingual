export interface LanguageOption {
	language_id: number;
	code: string;
	name: string;
	native_name: string;
	status: string;
}

export interface WorkspacePageSummary {
	post_id: number;
	post_title: string;
	post_type: string;
	post_status: string;
	modified_gmt: string;
	language_id: number;
	total_segments: number;
	stale_count: number;
}

export type ReviewStatus = 'not_submitted' | 'pending' | 'approved' | 'rejected';

export interface ReviewMetadata {
	review_status: ReviewStatus | string;
	submitted_translation_hash: string;
	review_submitted_by: number | null;
	review_submitted_at: string | null;
	reviewed_by: number | null;
	reviewed_at: string | null;
	rejection_reason: string;
	rejected_by: number | null;
	rejected_at: string | null;
}

export interface WorkspaceSegment extends ReviewMetadata {
	segment_key: string;
	field_key: string;
	block_name: string;
	uuid: string;
	segment_order: number;
	source_text: string;
	source_hash: string;
	translation_hash?: string;
	translated_text: string;
	status: string;
	is_stale: boolean;
	text_format: string;
	can_edit: boolean;
	meta: WorkspaceSegmentMeta;
}

export interface ReviewQueueItem extends ReviewMetadata {
	source_type: string;
	post_id: number;
	language_id: number;
	segment_key: string;
	field_key: string;
	source_text: string;
	translated_text: string;
	status: string;
}

export interface ReviewQueueResponse {
	items: ReviewQueueItem[];
	total: number;
	page: number;
	per_page: number;
}

export interface ReviewErrorContext {
	review_status?: string;
	submitted_translation_hash?: string;
	translation_hash?: string;
	review_submitted_by?: number | null;
	review_submitted_at?: string | null;
	reviewed_by?: number | null;
	reviewed_at?: string | null;
	rejection_reason?: string;
	rejected_by?: number | null;
	rejected_at?: string | null;
	expected_review_status?: string;
	[ key: string ]: unknown;
}

export interface NormalizedSuggestion {
	provider_id: string;
	target_text: string;
	confidence: number;
	rank_tier: number;
	metadata: Record<string, unknown>;
}

export interface QAIssue {
	code: string;
	severity: 'error' | 'warning' | 'info' | string;
	message: string;
	details: Record<string, unknown>;
}

export interface QASummary {
	errors: number;
	warnings: number;
	info: number;
}

export interface SegmentQA {
	issues: QAIssue[];
	summary: QASummary;
}

export interface WorkspaceSegmentMeta {
	suggestions?: NormalizedSuggestion[];
	qa?: SegmentQA;
	[key: string]: unknown;
}

export interface WorkspaceSegmentsResponse {
	post_id: number;
	language_id: number;
	segments: WorkspaceSegment[];
	status: WorkspaceTranslationStatus;
}

export interface WorkspaceTranslationStatus {
	post_id: number;
	post_title: string;
	post_status: string;
	post_type: string;
	language_id: number;
	total_segments: number;
	missing_count: number;
	stale_count: number;
	translated_count: number;
	reviewed_count: number;
	overall_state: string;
	is_published: boolean;
	edit_link: string;
}

export interface WorkspacePostsResponse {
	items: WorkspacePageSummary[];
	page: number;
	per_page: number;
	total: number;
	total_pages: number;
}

export interface TranslatorWorkspaceConfig {
	restNamespace: string;
	nonce: string;
	languages: LanguageOption[];
	initialPostId?: number;
	initialLanguageCode?: string;
	canTranslate: boolean;
	canReview: boolean;
	canAccessOperations?: boolean;
	canViewJobs: boolean;
	canManageJobs: boolean;
	canRunJobs: boolean;
	canCancelJobs: boolean;
}

export interface AllowedActionDescriptor {
	id: string;
	allowed: boolean;
	reason_code: string;
}

export interface OperationsListItem {
	translation_id: number;
	source_type: string;
	source_id: number;
	source_subtype: string;
	language_id: number;
	language_code: string;
	segment_key: string;
	field_key: string;
	status: string;
	review_status: string;
	publish_status: string;
	is_stale: boolean;
	attention_reasons: string[];
	source_preview: string;
	target_preview: string;
	updated_at: string;
	created_at: string;
	provider: string;
	model: string;
	error_code: string;
	error_message: string;
	links: Record< string, string >;
	allowed_actions: AllowedActionDescriptor[];
	jobs: null;
}

export interface OperationsListResponse {
	items: OperationsListItem[];
	total: number;
	page: number;
	per_page: number;
}

export interface OperationsPublicationSettings {
	segment_publication_gate_enabled?: boolean;
	auto_publication_mode?: string;
}

export interface OperationsDetailResponse extends OperationsListItem {
	source_text?: string;
	translated_text?: string;
	text_format?: string;
	source_hash?: string;
	translation_hash?: string;
	tm_id?: number | string | null;
	published_at?: string;
	published_by?: number | null;
	qa?: Record< string, unknown >;
	assessment?: Record< string, unknown >;
	publication?: Record< string, unknown >;
	publication_settings?: OperationsPublicationSettings;
}

export interface OperationsAttentionCountsResponse {
	total: number;
	stale: number;
	review_pending: number;
	review_rejected: number;
	unpublished: number;
	translation_failed: number;
}

declare global {
	interface Window {
		aimlTranslatorWorkspace: TranslatorWorkspaceConfig;
	}
}
