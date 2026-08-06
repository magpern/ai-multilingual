import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import type { JobsHealthResponse } from '../types/jobs';

interface JobsHealthBannerProps {
	health: JobsHealthResponse | null;
	loading: boolean;
	error: string;
}

export default function JobsHealthBanner( {
	health,
	loading,
	error,
}: JobsHealthBannerProps ) {
	if ( loading ) {
		return (
			<Notice status="info" isDismissible={ false }>
				{ __( 'Checking background job health…', 'ai-multilingual' ) }
			</Notice>
		);
	}

	if ( error ) {
		return (
			<Notice status="warning" isDismissible={ false }>
				{ error }
			</Notice>
		);
	}

	if ( ! health ) {
		return null;
	}

	const provider = health.provider;
	const asHealthy = Boolean( health.available );
	const providerHealthy = provider ? Boolean( provider.available ) : null;

	return (
		<div className="aiml-jobs-health-banner" role="status">
			<Notice
				status={ asHealthy ? 'success' : 'error' }
				isDismissible={ false }
			>
				<strong>{ __( 'Action Scheduler', 'ai-multilingual' ) }</strong>
				{ ': ' }
				{ health.message }
			</Notice>
			{ provider ? (
				<Notice
					status={ providerHealthy ? 'success' : 'warning' }
					isDismissible={ false }
				>
					<strong>{ __( 'Translation provider', 'ai-multilingual' ) }</strong>
					{ ': ' }
					{ provider.message }
				</Notice>
			) : (
				<Notice status="info" isDismissible={ false }>
					<strong>{ __( 'Translation provider', 'ai-multilingual' ) }</strong>
					{ ': ' }
					{ __(
						'Provider availability is validated when you create a job.',
						'ai-multilingual'
					) }
				</Notice>
			) }
		</div>
	);
}
