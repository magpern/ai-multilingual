import { __, sprintf } from '@wordpress/i18n';

import type { SegmentQA } from '../types/view-models';

interface QAPanelProps {
	qa: SegmentQA;
}

function severityClass( severity: string ): string {
	switch ( severity ) {
		case 'error':
			return 'aiml-qa-severity--error';
		case 'warning':
			return 'aiml-qa-severity--warning';
		default:
			return 'aiml-qa-severity--info';
	}
}

export default function QAPanel( { qa }: QAPanelProps ) {
	const { issues, summary } = qa;

	return (
		<div className="aiml-qa-panel">
			<h4 className="aiml-panel-title">
				{ __( 'Quality checks', 'ai-multilingual' ) }
			</h4>
			<p className="aiml-qa-summary description">
				{ sprintf(
					/* translators: 1: errors, 2: warnings, 3: info */
					__( 'Errors: %1$d · Warnings: %2$d · Info: %3$d', 'ai-multilingual' ),
					summary.errors,
					summary.warnings,
					summary.info
				) }
			</p>
			{ issues.length === 0 ? (
				<p className="description">
					{ __( 'No QA issues for this segment.', 'ai-multilingual' ) }
				</p>
			) : (
				<ul className="aiml-qa-list">
					{ issues.map( ( issue, index ) => (
						<li
							key={ `${ issue.code }-${ index }` }
							className={ `aiml-qa-item ${ severityClass(
								issue.severity
							) }` }
						>
							<strong className="aiml-qa-code">{ issue.code }</strong>
							<span className="aiml-qa-severity">{ issue.severity }</span>
							<span className="aiml-qa-message">{ issue.message }</span>
						</li>
					) ) }
				</ul>
			) }
		</div>
	);
}
