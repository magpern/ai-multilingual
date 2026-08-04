import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import type { NormalizedSuggestion } from '../types/view-models';

interface SuggestionPanelProps {
	suggestions: NormalizedSuggestion[];
	disabled?: boolean;
	onAccept: ( text: string ) => void;
	onRequestProfile?: ( profile: string ) => void;
	suggesting?: boolean;
}

function suggestionLabel( suggestion: NormalizedSuggestion ): string {
	if ( suggestion.provider_id === 'tm' ) {
		const match = String( suggestion.metadata?.match_type ?? 'tm' );
		const origin = String( suggestion.metadata?.origin ?? '' );
		return origin
			? sprintf(
					/* translators: 1: match type, 2: origin */
					__( 'TM %1$s (%2$s)', 'ai-multilingual' ),
					match,
					origin
			  )
			: sprintf(
					/* translators: %s: match type */
					__( 'TM %s', 'ai-multilingual' ),
					match
			  );
	}

	const profile = String( suggestion.metadata?.profile ?? 'ai' );
	return sprintf(
		/* translators: %s: prompt profile */
		__( 'AI suggestion (%s)', 'ai-multilingual' ),
		profile
	);
}

function confidenceClass( suggestion: NormalizedSuggestion ): string {
	if ( suggestion.provider_id === 'ai' ) {
		return 'aiml-suggestion-confidence--ai';
	}
	if ( suggestion.confidence >= 100 ) {
		return 'aiml-suggestion-confidence--exact';
	}
	return 'aiml-suggestion-confidence--fuzzy';
}

const AI_PROFILES = [
	'improve',
	'rewrite',
	'shorten',
	'formal',
	'casual',
] as const;

export default function SuggestionPanel( {
	suggestions,
	disabled = false,
	onAccept,
	onRequestProfile,
	suggesting = false,
}: SuggestionPanelProps ) {
	return (
		<div className="aiml-suggestion-panel">
			<h4 className="aiml-panel-title">
				{ __( 'Suggestions', 'ai-multilingual' ) }
			</h4>
			{ onRequestProfile && (
				<div className="aiml-suggestion-profiles">
					{ AI_PROFILES.map( ( profile ) => (
						<Button
							key={ profile }
							variant="tertiary"
							disabled={ disabled || suggesting }
							onClick={ () => onRequestProfile( profile ) }
						>
							{ profile }
						</Button>
					) ) }
				</div>
			) }
			{ suggestions.length === 0 ? (
				<p className="description">
					{ __( 'No suggestions for this segment.', 'ai-multilingual' ) }
				</p>
			) : (
				<ul className="aiml-suggestion-list">
					{ suggestions.map( ( suggestion, index ) => (
						<li
							key={ `${ suggestion.provider_id }-${ index }-${ suggestion.target_text }` }
							className="aiml-suggestion-item"
						>
							<div className="aiml-suggestion-meta">
								<span className="aiml-suggestion-label">
									{ suggestionLabel( suggestion ) }
								</span>
								<span
									className={ `aiml-suggestion-confidence ${ confidenceClass(
										suggestion
									) }` }
								>
									{ suggestion.provider_id === 'ai'
										? __( 'AI', 'ai-multilingual' )
										: Math.round( suggestion.confidence ) }
								</span>
							</div>
							<pre className="aiml-suggestion-text">
								{ suggestion.target_text }
							</pre>
							<Button
								variant="secondary"
								disabled={ disabled }
								onClick={ () => onAccept( suggestion.target_text ) }
							>
								{ __( 'Accept', 'ai-multilingual' ) }
							</Button>
						</li>
					) ) }
				</ul>
			) }
		</div>
	);
}
