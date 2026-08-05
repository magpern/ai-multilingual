import { __, sprintf } from '@wordpress/i18n';

import { reviewStatusLabel } from '../utils/review-status';

interface ReviewStatusBadgeProps {
	status: string;
}

export default function ReviewStatusBadge( { status }: ReviewStatusBadgeProps ) {
	return (
		<span
			className={ `aiml-review-badge aiml-review-badge--${ status }` }
			aria-label={ sprintf(
				/* translators: %s: review status */
				__( 'Review status: %s', 'ai-multilingual' ),
				reviewStatusLabel( status )
			) }
		>
			{ reviewStatusLabel( status ) }
		</span>
	);
}
