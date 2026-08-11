import { Button, Notice, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import type { OperationsDetailResponse } from '../types/view-models';
import { attentionReasonLabel } from '../utils/operations-attention';
import { reviewStatusLabel } from '../utils/review-status';

interface OperationsInspectorProps {
	detail: OperationsDetailResponse | null;
	loading: boolean;
	error: string;
	onClose: () => void;
	onOpenInTranslate: ( postId: number, languageCode: string ) => void;
	onOpenInReview: ( languageCode: string, postId?: number ) => void;
	canTranslate: boolean;
	canReview: boolean;
}

/**
 * Reusable read-only inspection surface (OTL.1 Decision A).
 * OTL.2 may extend this — no review/publish/Jobs mutation controls here.
 */
export default function OperationsInspector( {
	detail,
	loading,
	error,
	onClose,
	onOpenInTranslate,
	onOpenInReview,
	canTranslate,
	canReview,
}: OperationsInspectorProps ) {
	return (
		<section
			className="aiml-operations-inspector"
			aria-label={ __( 'Translation inspector', 'ai-multilingual' ) }
		>
			<div className="aiml-operations-inspector-header">
				<h2 className="aiml-operations-inspector-title">
					{ __( 'Translation details', 'ai-multilingual' ) }
				</h2>
				<Button variant="tertiary" onClick={ onClose }>
					{ __( 'Close', 'ai-multilingual' ) }
				</Button>
			</div>

			{ loading && (
				<p>
					<Spinner />{ ' ' }
					{ __( 'Loading details…', 'ai-multilingual' ) }
				</p>
			) }
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ ! loading && ! error && detail && (
				<>
					<dl className="aiml-operations-inspector-axes">
						<div>
							<dt>{ __( 'Source', 'ai-multilingual' ) }</dt>
							<dd>
								{ detail.source_type } #{ detail.source_id }
								{ detail.source_subtype
									? ` (${ detail.source_subtype })`
									: '' }
							</dd>
						</div>
						<div>
							<dt>{ __( 'Language', 'ai-multilingual' ) }</dt>
							<dd>{ detail.language_code }</dd>
						</div>
						<div>
							<dt>{ __( 'Translation status', 'ai-multilingual' ) }</dt>
							<dd>{ detail.status }</dd>
						</div>
						<div>
							<dt>{ __( 'Review state', 'ai-multilingual' ) }</dt>
							<dd>{ reviewStatusLabel( detail.review_status ) }</dd>
						</div>
						<div>
							<dt>{ __( 'Publication state', 'ai-multilingual' ) }</dt>
							<dd>{ detail.publish_status }</dd>
						</div>
						<div>
							<dt>{ __( 'Stale', 'ai-multilingual' ) }</dt>
							<dd>
								{ detail.is_stale
									? __( 'Yes', 'ai-multilingual' )
									: __( 'No', 'ai-multilingual' ) }
							</dd>
						</div>
						<div>
							<dt>{ __( 'Operational attention', 'ai-multilingual' ) }</dt>
							<dd>
								{ ( detail.attention_reasons ?? [] ).length
									? detail.attention_reasons
											.map( attentionReasonLabel )
											.join( ', ' )
									: __( 'None', 'ai-multilingual' ) }
							</dd>
						</div>
					</dl>

					{ detail.source_text !== undefined && (
						<div className="aiml-operations-inspector-texts">
							<h3>{ __( 'Source text', 'ai-multilingual' ) }</h3>
							<pre className="aiml-operations-inspector-pre">
								{ detail.source_text }
							</pre>
							<h3>{ __( 'Target text', 'ai-multilingual' ) }</h3>
							<pre className="aiml-operations-inspector-pre">
								{ detail.translated_text ?? '' }
							</pre>
						</div>
					) }

					{ detail.assessment && (
						<div className="aiml-operations-inspector-block">
							<h3>{ __( 'Assessment (TI.5)', 'ai-multilingual' ) }</h3>
							<pre className="aiml-operations-inspector-pre">
								{ JSON.stringify( detail.assessment, null, 2 ) }
							</pre>
						</div>
					) }
					{ detail.qa && (
						<div className="aiml-operations-inspector-block">
							<h3>{ __( 'QA (TI.4)', 'ai-multilingual' ) }</h3>
							<pre className="aiml-operations-inspector-pre">
								{ JSON.stringify( detail.qa, null, 2 ) }
							</pre>
						</div>
					) }
					{ detail.publication && (
						<div className="aiml-operations-inspector-block">
							<h3>
								{ __( 'Publication explain (TI.7)', 'ai-multilingual' ) }
							</h3>
							<pre className="aiml-operations-inspector-pre">
								{ JSON.stringify( detail.publication, null, 2 ) }
							</pre>
						</div>
					) }

					<p className="aiml-operations-inspector-note">
						{ __(
							'This inspector is read-only. Review and publication actions stay in their authoritative workflows.',
							'ai-multilingual'
						) }
					</p>

					<div className="aiml-operations-inspector-actions">
						{ canTranslate &&
							'post' === detail.source_type &&
							detail.source_id > 0 && (
								<Button
									variant="secondary"
									onClick={ () =>
										onOpenInTranslate(
											detail.source_id,
											detail.language_code
										)
									}
								>
									{ __( 'Open in Translate', 'ai-multilingual' ) }
								</Button>
							) }
						{ canReview && (
							<Button
								variant="secondary"
								onClick={ () =>
									onOpenInReview(
										detail.language_code,
										'post' === detail.source_type
											? detail.source_id
											: undefined
									)
								}
							>
								{ __( 'Open in Review', 'ai-multilingual' ) }
							</Button>
						) }
						{ detail.links?.edit_link && (
							<Button
								variant="link"
								href={ detail.links.edit_link }
								target="_blank"
								rel="noopener noreferrer"
							>
								{ __( 'Open source', 'ai-multilingual' ) }
							</Button>
						) }
						{ ( detail.links?.frontend_url ||
							detail.links?.source_frontend_url ) && (
							<Button
								variant="link"
								href={
									detail.links.frontend_url ||
									detail.links.source_frontend_url
								}
								target="_blank"
								rel="noopener noreferrer"
							>
								{ __( 'Open frontend', 'ai-multilingual' ) }
							</Button>
						) }
					</div>
				</>
			) }
			{ ! loading && ! error && ! detail && (
				<p>
					{ sprintf(
						/* translators: placeholder keeps string stable */
						__( 'No translation selected.%s', 'ai-multilingual' ),
						''
					) }
				</p>
			) }
		</section>
	);
}
