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

export interface WorkspaceSegment {
	segment_key: string;
	field_key: string;
	block_name: string;
	uuid: string;
	segment_order: number;
	source_text: string;
	source_hash: string;
	translated_text: string;
	status: string;
	is_stale: boolean;
	text_format: string;
	can_edit: boolean;
	meta: Record<string, unknown>;
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
}

declare global {
	interface Window {
		aimlTranslatorWorkspace: TranslatorWorkspaceConfig;
	}
}
