<?php
/**
 * A.R1 EXPERIMENTAL — Elementor document walker.
 *
 * Research-only. Load explicitly via: wp eval-file <this-file>
 * Do NOT register via Plugin.php.
 *
 * @package AIMultilingual\Research\AR1
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit( "A.R1 research script must run via WP-CLI eval-file.\n" );
}

/**
 * Recursively walk Elementor elements.
 *
 * @param array<int,array<string,mixed>> $elements Elements tree.
 * @param string                         $path     Structural path prefix.
 * @return list<array<string,mixed>>
 */
function ar1_walk_elements( array $elements, string $path = '' ): array {
	$rows = array();
	foreach ( $elements as $i => $el ) {
		if ( ! is_array( $el ) ) {
			continue;
		}
		$id       = isset( $el['id'] ) ? (string) $el['id'] : '';
		$el_type  = isset( $el['elType'] ) ? (string) $el['elType'] : '';
		$widget   = isset( $el['widgetType'] ) ? (string) $el['widgetType'] : '';
		$settings = ( isset( $el['settings'] ) && is_array( $el['settings'] ) ) ? $el['settings'] : array();
		$node_path = $path === '' ? (string) $i : $path . '/' . $i;

		$setting_keys = array_keys( $settings );
		$responsive   = array();
		foreach ( $settings as $k => $v ) {
			if ( is_string( $k ) && ( str_ends_with( $k, '_tablet' ) || str_ends_with( $k, '_mobile' ) || str_ends_with( $k, '_laptop' ) || str_ends_with( $k, '_widescreen' ) ) ) {
				$responsive[] = $k;
			}
		}

		$has_dynamic = false;
		$dynamic_keys = array();
		if ( isset( $settings['__dynamic__'] ) && is_array( $settings['__dynamic__'] ) ) {
			$has_dynamic  = true;
			$dynamic_keys = array_keys( $settings['__dynamic__'] );
		}

		$repeater_keys = array();
		foreach ( $settings as $k => $v ) {
			if ( is_array( $v ) && $v !== array() && ar1_looks_like_repeater( $v ) ) {
				$repeater_keys[] = (string) $k;
			}
		}

		$template_ref = null;
		if ( in_array( $widget, array( 'template', 'global' ), true ) ) {
			$template_ref = $settings['template_id'] ?? ( $settings['global_widget_id'] ?? null );
		}

		$rows[] = array(
			'id'            => $id,
			'elType'        => $el_type,
			'widgetType'    => $widget,
			'path'          => $node_path,
			'setting_keys'  => $setting_keys,
			'responsive'    => $responsive,
			'has_dynamic'   => $has_dynamic,
			'dynamic_keys'  => $dynamic_keys,
			'repeater_keys' => $repeater_keys,
			'template_ref'  => $template_ref,
			'setting_count' => count( $setting_keys ),
		);

		if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
			$rows = array_merge( $rows, ar1_walk_elements( $el['elements'], $node_path ) );
		}
	}
	return $rows;
}

/**
 * @param array<int|string,mixed> $value Candidate setting value.
 */
function ar1_looks_like_repeater( array $value ): bool {
	if ( ! array_is_list( $value ) ) {
		return false;
	}
	foreach ( $value as $item ) {
		if ( ! is_array( $item ) ) {
			return false;
		}
		if ( isset( $item['_id'] ) || isset( $item['id'] ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Extract all element IDs in document order.
 *
 * @param array<int,array<string,mixed>> $elements Tree.
 * @return list<string>
 */
function ar1_collect_ids( array $elements ): array {
	$ids = array();
	foreach ( ar1_walk_elements( $elements ) as $row ) {
		if ( $row['id'] !== '' ) {
			$ids[] = $row['id'];
		}
	}
	return $ids;
}

/**
 * Build structural path map id => path.
 *
 * @param array<int,array<string,mixed>> $elements Tree.
 * @return array<string,string>
 */
function ar1_id_path_map( array $elements ): array {
	$map = array();
	foreach ( ar1_walk_elements( $elements ) as $row ) {
		if ( $row['id'] !== '' ) {
			$map[ $row['id'] ] = $row['path'];
		}
	}
	return $map;
}

/**
 * Decode _elementor_data for a post.
 *
 * @return array{post_id:int,edit_mode:string,template_type:string,raw_bytes:int,elements:array<int,array<string,mixed>>,error:?string}
 */
function ar1_load_document( int $post_id ): array {
	$raw  = (string) get_post_meta( $post_id, '_elementor_data', true );
	$mode = (string) get_post_meta( $post_id, '_elementor_edit_mode', true );
	$type = (string) get_post_meta( $post_id, '_elementor_template_type', true );
	$out  = array(
		'post_id'       => $post_id,
		'edit_mode'     => $mode,
		'template_type' => $type,
		'raw_bytes'     => strlen( $raw ),
		'elements'      => array(),
		'error'         => null,
	);
	if ( $raw === '' ) {
		$out['error'] = 'empty_elementor_data';
		return $out;
	}
	$decoded = json_decode( $raw, true );
	if ( ! is_array( $decoded ) ) {
		// Elementor sometimes stores slash-escaped JSON.
		$decoded = json_decode( wp_unslash( $raw ), true );
	}
	if ( ! is_array( $decoded ) ) {
		$out['error'] = 'json_decode_failed';
		return $out;
	}
	$out['elements'] = $decoded;
	return $out;
}

/**
 * Sanitize settings for fixture export — drop URLs with query tokens, emails.
 *
 * @param mixed $value Value.
 * @return mixed
 */
function ar1_sanitize_value( $value ) {
	if ( is_string( $value ) ) {
		$value = preg_replace( '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', '[redacted-email]', $value ) ?? $value;
		if ( preg_match( '#https?://#i', $value ) && preg_match( '/(token|key|secret|password)=/i', $value ) ) {
			return '[redacted-url]';
		}
		return $value;
	}
	if ( is_array( $value ) ) {
		$out = array();
		foreach ( $value as $k => $v ) {
			$out[ $k ] = ar1_sanitize_value( $v );
		}
		return $out;
	}
	return $value;
}

/**
 * @param array<int,array<string,mixed>> $elements Tree.
 * @return array<int,array<string,mixed>>
 */
function ar1_sanitize_tree( array $elements ): array {
	$out = array();
	foreach ( $elements as $el ) {
		if ( ! is_array( $el ) ) {
			continue;
		}
		$copy = $el;
		if ( isset( $copy['settings'] ) && is_array( $copy['settings'] ) ) {
			$copy['settings'] = ar1_sanitize_value( $copy['settings'] );
		}
		if ( isset( $copy['elements'] ) && is_array( $copy['elements'] ) ) {
			$copy['elements'] = ar1_sanitize_tree( $copy['elements'] );
		}
		$out[] = $copy;
	}
	return $out;
}

/**
 * Compare two ID lists.
 *
 * @param list<string> $before Before.
 * @param list<string> $after  After.
 * @return array<string,mixed>
 */
function ar1_compare_ids( array $before, array $after ): array {
	$b = array_values( array_unique( $before ) );
	$a = array_values( array_unique( $after ) );
	sort( $b );
	sort( $a );
	$retained = array_values( array_intersect( $b, $a ) );
	$lost     = array_values( array_diff( $b, $a ) );
	$new      = array_values( array_diff( $a, $b ) );
	$dup_b    = count( $before ) - count( array_unique( $before ) );
	$dup_a    = count( $after ) - count( array_unique( $after ) );
	return array(
		'before_count'       => count( $before ),
		'after_count'        => count( $after ),
		'unique_before'      => count( $b ),
		'unique_after'       => count( $a ),
		'retained'           => count( $retained ),
		'lost'               => $lost,
		'new'                => $new,
		'duplicate_ids_before' => $dup_b,
		'duplicate_ids_after'  => $dup_a,
		'all_retained'       => ( $lost === array() && count( $retained ) === count( $b ) ),
	);
}
