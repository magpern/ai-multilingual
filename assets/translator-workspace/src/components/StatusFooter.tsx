import { __, sprintf } from '@wordpress/i18n';

import type { WorkspaceTranslationStatus } from '../types/view-models';
import { overallStateLabel } from '../utils/segment-status';

interface StatusFooterProps {
	status: WorkspaceTranslationStatus;
}

export default function StatusFooter( { status }: StatusFooterProps ) {
	return (
		<div
			className="aiml-workspace-footer"
			role="region"
			aria-label={ __( 'Translation progress summary', 'ai-multilingual' ) }
		>
			<h2 className="aiml-workspace-footer-title">
				{ __( 'Translation progress', 'ai-multilingual' ) }
			</h2>
			<p className="aiml-workspace-footer-overall">
				<strong>{ __( 'Overall state:', 'ai-multilingual' ) }</strong>{ ' ' }
				<span className="aiml-workspace-overall-state">
					{ overallStateLabel( status.overall_state ) }
				</span>
				{ status.is_published && (
					<>
						{ ' · ' }
						<span className="aiml-workspace-published-indicator">
							{ __( 'Published on site', 'ai-multilingual' ) }
						</span>
					</>
				) }
			</p>
			<ul className="aiml-workspace-footer-counts">
				<li>
					{ sprintf(
						/* translators: %d: count */
						__( 'Translated: %d', 'ai-multilingual' ),
						status.translated_count
					) }
				</li>
				<li>
					{ sprintf(
						/* translators: %d: count */
						__( 'Missing: %d', 'ai-multilingual' ),
						status.missing_count
					) }
				</li>
				<li>
					{ sprintf(
						/* translators: %d: count */
						__( 'Stale: %d', 'ai-multilingual' ),
						status.stale_count
					) }
				</li>
				<li>
					{ sprintf(
						/* translators: %d: count */
						__( 'Reviewed: %d', 'ai-multilingual' ),
						status.reviewed_count
					) }
				</li>
				<li>
					{ sprintf(
						/* translators: %d: count */
						__( 'Total segments: %d', 'ai-multilingual' ),
						status.total_segments
					) }
				</li>
			</ul>
		</div>
	);
}
