import apiFetch from '@wordpress/api-fetch';

import type {
	WorkspacePostsResponse,
	WorkspaceSegmentsResponse,
} from '../types/view-models';

function namespace(): string {
	return window.aimlTranslatorWorkspace.restNamespace.replace(/^\/|\/$/g, '');
}

function path(route: string): string {
	return `/${namespace()}/${route.replace(/^\//, '')}`;
}

export function configureWorkspaceApi(): void {
	apiFetch.use(apiFetch.createNonceMiddleware(window.aimlTranslatorWorkspace.nonce));
}

export async function fetchPosts(
	languageCode: string,
	search = '',
	page = 1
): Promise<WorkspacePostsResponse> {
	return apiFetch<WorkspacePostsResponse>({
		path: path(
			`workspace/posts?language=${encodeURIComponent(languageCode)}&search=${encodeURIComponent(search)}&page=${page}&per_page=20`
		),
	});
}

export async function fetchSegments(
	postId: number,
	languageCode: string
): Promise<WorkspaceSegmentsResponse> {
	return apiFetch<WorkspaceSegmentsResponse>({
		path: path(
			`workspace/${postId}/segments?language=${encodeURIComponent(languageCode)}`
		),
	});
}

export async function fetchPreviewUrl(
	postId: number,
	languageCode: string
): Promise<string> {
	const response = await apiFetch<{ url: string }>({
		path: path(
			`workspace/${postId}/preview-url?language=${encodeURIComponent(languageCode)}`
		),
	});
	return response.url;
}
