import { __, sprintf } from '@wordpress/i18n';

import { jobStatusLabel } from '../utils/jobs';

interface JobStatusBadgeProps {
	status: string;
	requestedAction?: string;
}

export default function JobStatusBadge( {
	status,
	requestedAction = 'none',
}: JobStatusBadgeProps ) {
	const label =
		'pause' === requestedAction
			? __( 'Pause requested', 'ai-multilingual' )
			: 'cancel' === requestedAction
				? __( 'Cancel requested', 'ai-multilingual' )
				: jobStatusLabel( status );

	return (
		<span
			className={ `aiml-job-badge aiml-job-badge--${ status }` }
			aria-label={ sprintf(
				/* translators: %s: job status */
				__( 'Job status: %s', 'ai-multilingual' ),
				label
			) }
		>
			{ label }
		</span>
	);
}
