import type { AllowedActionDescriptor } from '../types/view-models';

export interface ReviewGateInput {
	dirty: boolean;
	sourceType: string;
	allowedActions: AllowedActionDescriptor[];
	canTranslate: boolean;
	canReview: boolean;
}

export interface ReviewGateResult {
	submit: boolean;
	approve: boolean;
	reject: boolean;
}

function actionAllowed(
	allowedActions: AllowedActionDescriptor[],
	id: string
): boolean {
	return allowedActions.some( ( action ) => action.id === id && action.allowed );
}

/**
 * Review mutation controls are hidden/disabled when the draft is dirty,
 * the source is not post-backed, or AllowedActionsResolver denies the action.
 */
export function canShowReviewActions( input: ReviewGateInput ): ReviewGateResult {
	const postBacked = 'post' === input.sourceType;

	if ( input.dirty || ! postBacked ) {
		return { submit: false, approve: false, reject: false };
	}

	return {
		submit:
			input.canTranslate &&
			actionAllowed( input.allowedActions, 'submit_for_review' ),
		approve:
			input.canReview &&
			actionAllowed( input.allowedActions, 'approve' ),
		reject:
			input.canReview &&
			actionAllowed( input.allowedActions, 'reject' ),
	};
}
