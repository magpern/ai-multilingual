<?php
/**
 * Route persistence record.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Routing;

/**
 * Route write DTO. Hashes are derived by the repository — never supplied.
 */
final class RouteRecord {

	public int $language_id;
	public string $source_type;
	public int $source_id;
	public string $source_subtype;
	public CanonicalPath $source_path;
	public CanonicalPath $localized_path;
	public string $localized_slug;
	public string $route_namespace;
	public string $slug_origin;
	public string $route_status;
	public ?string $activated_at;

	/**
	 * @param int           $language_id     Target language.
	 * @param string        $source_type     Store source type.
	 * @param int           $source_id       Source object id.
	 * @param string        $source_subtype  Source subtype.
	 * @param CanonicalPath $source_path     Canonical source path.
	 * @param CanonicalPath $localized_path  Canonical localized path.
	 * @param string        $localized_slug  Leaf slug segment.
	 * @param string        $route_namespace Route namespace.
	 * @param string        $slug_origin     generated|manual|''.
	 * @param string        $route_status    inactive|active.
	 * @param string|null   $activated_at    Activation timestamp or null.
	 */
	public function __construct(
		int $language_id,
		string $source_type,
		int $source_id,
		string $source_subtype,
		CanonicalPath $source_path,
		CanonicalPath $localized_path,
		string $localized_slug = '',
		string $route_namespace = '',
		string $slug_origin = 'generated',
		string $route_status = 'inactive',
		?string $activated_at = null
	) {
		$this->language_id     = $language_id;
		$this->source_type     = $source_type;
		$this->source_id       = $source_id;
		$this->source_subtype  = $source_subtype;
		$this->source_path     = $source_path;
		$this->localized_path  = $localized_path;
		$this->localized_slug  = $localized_slug;
		$this->route_namespace = $route_namespace;
		$this->slug_origin     = $slug_origin;
		$this->route_status    = $route_status;
		$this->activated_at    = $activated_at;
	}
}
