import type { AllowedActionDescriptor } from '../types/view-models';

export interface PublicationGateInput {
	dirty: boolean;
	sourceType: string;
	allowedActions: Array< { id: string; allowed: boolean } > | AllowedActionDescriptor[];
}

export interface PublicationGateResult {
	publish: boolean;
	unpublish: boolean;
	retranslate: boolean;
}

function actionAllowed(
	allowedActions: Array< { id: string; allowed: boolean } >,
	id: string
): boolean {
	return allowedActions.some( ( action ) => action.id === id && action.allowed );
}

/**
 * Publication / stale-retranslate controls are hidden when the draft is dirty,
 * the source is not post-backed, or AllowedActionsResolver denies the action.
 * Does not evaluate TI.7 eligibility client-side.
 */
export function canShowPublicationActions(
	input: PublicationGateInput
): PublicationGateResult {
	if ( input.dirty || 'post' !== input.sourceType ) {
		return { publish: false, unpublish: false, retranslate: false };
	}

	return {
		publish: actionAllowed( input.allowedActions, 'publish' ),
		unpublish: actionAllowed( input.allowedActions, 'unpublish' ),
		retranslate: actionAllowed( input.allowedActions, 'retranslate_stale' ),
	};
}
