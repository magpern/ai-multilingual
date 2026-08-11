<?php
/**
 * Stable reason codes for OTL allowed_actions UI admission.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\Operator;

/**
 * Denial / metadata reason codes for AllowedActionsResolver.
 *
 * Not a mutation-policy catalog — UI admission diagnostics only.
 */
final class ActionReasonCodes {

	public const CAPABILITY_DENIED          = 'capability_denied';
	public const STATE_INELIGIBLE           = 'state_ineligible';
	public const DETAIL_ONLY                = 'detail_only';
	public const SOURCE_ACCESS_DENIED       = 'source_access_denied';
	public const PUBLICATION_INELIGIBLE     = 'publication_ineligible';
	public const PUBLICATION_ALREADY_ACTIVE = 'publication_already_active';
	public const NOT_PUBLISHED              = 'not_published';
	public const DEFERRED_MILESTONE         = 'deferred_milestone';
	public const LINK_UNAVAILABLE           = 'link_unavailable';

	/**
	 * Prevents instantiation.
	 */
	private function __construct() {}
}
