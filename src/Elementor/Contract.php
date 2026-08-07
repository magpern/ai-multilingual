<?php
/**
 * Elementor Foundation — frozen contracts.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Elementor;

/**
 * Shared constants for A.2 Elementor Foundation.
 */
final class Contract {

	public const SEGMENT_KEY_PREFIX = 'e';

	public const OWNER_SCOPE_DOCUMENT = 'd';

	public const FIELD_KEY = '_elementor';

	public const META_DATA = '_elementor_data';

	public const META_EDIT_MODE = '_elementor_edit_mode';

	/**
	 * Supported Elementor free major.minor family from A.R1 evidence.
	 */
	public const SUPPORTED_MAJOR_MINOR = '4.2';

	/**
	 * Settings flag: extraction enabled.
	 */
	public const FLAG_EXTRACTION = 'elementor_extraction_enabled';

	/**
	 * Settings flag: frontend overlay enabled.
	 */
	public const FLAG_FRONTEND = 'elementor_frontend_rendering_enabled';
}
