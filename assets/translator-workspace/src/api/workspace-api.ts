import apiFetch from '@wordpress/api-fetch';

import type { BatchSaveResult, BatchTranslateResult } from '../types/segment-row';
import type {
	WorkspacePostsResponse,
	WorkspaceSegment,
	WorkspaceSegmentsResponse,
} from '../types/view-models';

export class WorkspaceConflictError extends Error {
	public readonly segments: WorkspaceSegment[];

	public constructor( message: string, segments: WorkspaceSegment[] ) {
		super( message );
		this.name = 'WorkspaceConflictError';
		this.segments = segments;
	}
}

export class WorkspaceRequestError extends Error {
	public readonly userMessage: string;

	public constructor( userMessage: string ) {
		super( userMessage );
		this.name = 'WorkspaceRequestError';
		this.userMessage = userMessage;
	}
}

function namespace(): string {
	return window.aimlTranslatorWorkspace.restNamespace.replace(
		/^\/|\/$/g,
		''
	);
}

function path( route: string ): string {
	return `/${ namespace() }/${ route.replace( /^\//, '' ) }`;
}

function userMessageFromError( error: unknown ): string {
	if ( error instanceof WorkspaceConflictError ) {
		return error.message;
	}

	if ( error instanceof WorkspaceRequestError ) {
		return error.userMessage;
	}

	const candidate = error as {
		message?: string;
		code?: string;
	};

	if ( candidate?.code === 'rest_forbidden' ) {
		return 'You do not have permission to save this translation.';
	}

	if ( candidate?.message ) {
		return 'The translation could not be saved. Please try again.';
	}

	return 'The translation could not be saved. Please try again.';
}

function parseConflict( error: unknown ): WorkspaceConflictError | null {
	const candidate = error as {
		code?: string;
		message?: string;
		data?: {
			status?: number;
			segments?: WorkspaceSegment[];
		};
		segments?: WorkspaceSegment[];
	};

	const segments =
		candidate?.data?.segments ??
		candidate?.segments ??
		[];

	if (
		candidate?.code === 'aiml_source_hash_mismatch' ||
		candidate?.data?.status === 409
	) {
		return new WorkspaceConflictError(
			candidate.message ||
				'The source text changed since this segment was loaded.',
			segments
		);
	}

	return null;
}

export function configureWorkspaceApi(): void {
	apiFetch.use(
		apiFetch.createNonceMiddleware( window.aimlTranslatorWorkspace.nonce )
	);
}

export async function fetchPosts(
	languageCode: string,
	search = '',
	page = 1
): Promise< WorkspacePostsResponse > {
	return apiFetch< WorkspacePostsResponse >( {
		path: path(
			`workspace/posts?language=${ encodeURIComponent(
				languageCode
			) }&search=${ encodeURIComponent(
				search
			) }&page=${ page }&per_page=20`
		),
	} );
}

export async function fetchSegments(
	postId: number,
	languageCode: string
): Promise< WorkspaceSegmentsResponse > {
	return apiFetch< WorkspaceSegmentsResponse >( {
		path: path(
			`workspace/${ postId }/segments?language=${ encodeURIComponent(
				languageCode
			) }`
		),
	} );
}

export async function fetchPreviewUrl(
	postId: number,
	languageCode: string
): Promise< string > {
	const response = await apiFetch< { url: string } >( {
		path: path(
			`workspace/${ postId }/preview-url?language=${ encodeURIComponent(
				languageCode
			) }`
		),
	} );
	return response.url;
}

export async function saveSegment(
	postId: number,
	languageCode: string,
	segmentKey: string,
	translatedText: string,
	sourceHash: string
): Promise< WorkspaceSegment > {
	try {
		return await apiFetch< WorkspaceSegment >( {
			path: path(
				`workspace/${ postId }/segments/${ segmentKey }?language=${ encodeURIComponent(
					languageCode
				) }`
			),
			method: 'POST',
			data: {
				translated_text: translatedText,
				source_hash: sourceHash,
				status: 'manually_edited',
			},
		} );
	} catch ( error ) {
		const conflict = parseConflict( error );
		if ( conflict ) {
			throw conflict;
		}

		throw new WorkspaceRequestError( userMessageFromError( error ) );
	}
}

export async function saveBatch(
	postId: number,
	languageCode: string,
	items: Array< {
		segment_key: string;
		translated_text: string;
		source_hash: string;
		status: string;
	} >
): Promise< BatchSaveResult > {
	try {
		const response = await apiFetch< BatchSaveResult & {
			segments: WorkspaceSegment[];
		} >( {
			path: path(
				`workspace/${ postId }/segments/batch?language=${ encodeURIComponent(
					languageCode
				) }`
			),
			method: 'POST',
			data: {
				segments: items,
			},
		} );

		return {
			status: response.status,
			updated: response.segments,
			errors: response.errors ?? [],
		};
	} catch ( error ) {
		throw new WorkspaceRequestError( userMessageFromError( error ) );
	}
}

export async function translateBatch(
	postId: number,
	languageCode: string,
	segmentKeys: string[]
): Promise< BatchTranslateResult > {
	try {
		const response = await apiFetch< BatchTranslateResult & {
			segments: WorkspaceSegment[];
		} >( {
			path: path(
				`workspace/${ postId }/translate?language=${ encodeURIComponent(
					languageCode
				) }`
			),
			method: 'POST',
			data: {
				segment_keys: segmentKeys,
				mode: 'sync',
			},
		} );

		return {
			status: response.status,
			updated: response.segments ?? [],
			errors: response.errors ?? [],
			job_id: response.job_id ?? null,
		};
	} catch ( error ) {
		throw new WorkspaceRequestError( userMessageFromError( error ) );
	}
}

export { userMessageFromError };
