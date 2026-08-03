import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import type { WorkspaceTranslationStatus } from '../types/view-models';
import { postStatusLabel, postTypeLabel } from '../utils/segment-status';

interface PublishContextProps {
	status: WorkspaceTranslationStatus;
	onPreview: () => void;
	previewDisabled?: boolean;
}

export default function PublishContext( {
	status,
	onPreview,
	previewDisabled = false,
}: PublishContextProps ) {
	return (
		<div
			className="aiml-workspace-publish-context"
			role="region"
			aria-label={ __( 'Post publication context', 'ai-multilingual' ) }
		>
			<h2 className="aiml-workspace-publish-title">
				{ __( 'Post context', 'ai-multilingual' ) }
			</h2>
			<dl className="aiml-workspace-publish-details">
				<div>
					<dt>{ __( 'Title', 'ai-multilingual' ) }</dt>
					<dd>{ status.post_title }</dd>
				</div>
				<div>
					<dt>{ __( 'Content type', 'ai-multilingual' ) }</dt>
					<dd>{ postTypeLabel( status.post_type ) }</dd>
				</div>
				<div>
					<dt>{ __( 'WordPress status', 'ai-multilingual' ) }</dt>
					<dd>{ postStatusLabel( status.post_status ) }</dd>
				</div>
			</dl>
			<div className="aiml-workspace-publish-actions">
				{ status.edit_link && (
					<Button
						variant="secondary"
						href={ status.edit_link }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'Edit in WordPress', 'ai-multilingual' ) }
					</Button>
				) }
				<Button
					variant="secondary"
					onClick={ onPreview }
					disabled={ previewDisabled }
				>
					{ __( 'Preview translation', 'ai-multilingual' ) }
				</Button>
			</div>
		</div>
	);
}
