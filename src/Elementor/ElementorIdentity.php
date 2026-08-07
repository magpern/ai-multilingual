<?php
/**
 * Hybrid-D Elementor identity keys.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Elementor;

/**
 * Builds and validates A.2 and additive A.3 nested keys.
 *
 * A.2: `e:d:<owner_post_id>:<element_id>:<control_key>`
 * A.3: `e:d:<owner_post_id>:<element_id>:<control_key>:<nested_item_id>`
 */
final class ElementorIdentity {

	/**
	 * Build a non-nested (A.2) segment key.
	 *
	 * @param int    $owner_post_id Document post ID.
	 * @param string $element_id    Native Elementor element ID.
	 * @param string $control_key   Control key.
	 */
	public function build( int $owner_post_id, string $element_id, string $control_key ): string {
		return $this->build_key( $owner_post_id, $element_id, $control_key, null );
	}

	/**
	 * Build a nested (A.3) segment key using a repeater `_id`.
	 *
	 * @param int    $owner_post_id  Document post ID.
	 * @param string $element_id     Native Elementor element ID.
	 * @param string $control_key    Nested field / control key.
	 * @param string $nested_item_id Repeater item `_id`.
	 */
	public function build_nested( int $owner_post_id, string $element_id, string $control_key, string $nested_item_id ): string {
		$nested_item_id = trim( $nested_item_id );
		if ( '' === $nested_item_id ) {
			return '';
		}

		return $this->build_key( $owner_post_id, $element_id, $control_key, $nested_item_id );
	}

	/**
	 * Parse a segment key (A.2 five-segment or A.3 six-segment).
	 *
	 * @param string $segment_key Key.
	 * @return array{owner_post_id:int,element_id:string,control_key:string,nested_item_id:?string}|null
	 */
	public function parse( string $segment_key ): ?array {
		$parts = explode( ':', $segment_key );
		$count = count( $parts );
		if ( 5 !== $count && 6 !== $count ) {
			return null;
		}

		if ( Contract::SEGMENT_KEY_PREFIX !== $parts[0] || Contract::OWNER_SCOPE_DOCUMENT !== $parts[1] ) {
			return null;
		}

		$owner = (int) $parts[2];
		if ( $owner <= 0 || (string) $owner !== $parts[2] ) {
			return null;
		}

		if ( ! $this->is_safe_token( $parts[3] ) || ! $this->is_safe_token( $parts[4] ) ) {
			return null;
		}

		$nested = null;
		if ( 6 === $count ) {
			if ( ! $this->is_safe_token( $parts[5] ) ) {
				return null;
			}
			$nested = $parts[5];
		}

		return array(
			'owner_post_id'   => $owner,
			'element_id'      => $parts[3],
			'control_key'     => $parts[4],
			'nested_item_id'  => $nested,
		);
	}

	/**
	 * Whether key uses Elementor grammar.
	 *
	 * @param string $segment_key Key.
	 */
	public function is_elementor_key( string $segment_key ): bool {
		return 0 === strpos( $segment_key, Contract::SEGMENT_KEY_PREFIX . ':' );
	}

	/**
	 * Whether a key token is safe for the Hybrid-D grammar.
	 *
	 * @param string $token Candidate token.
	 */
	public function is_safe_token( string $token ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9_-]+$/', $token );
	}

	/**
	 * Shared builder.
	 *
	 * @param int         $owner_post_id  Owner.
	 * @param string      $element_id     Element ID.
	 * @param string      $control_key    Control key.
	 * @param string|null $nested_item_id Nested `_id` or null.
	 */
	private function build_key( int $owner_post_id, string $element_id, string $control_key, ?string $nested_item_id ): string {
		$element_id  = trim( $element_id );
		$control_key = trim( $control_key );

		if ( $owner_post_id <= 0 || '' === $element_id || '' === $control_key ) {
			return '';
		}

		if ( ! $this->is_safe_token( $element_id ) || ! $this->is_safe_token( $control_key ) ) {
			return '';
		}

		if ( null !== $nested_item_id ) {
			$nested_item_id = trim( $nested_item_id );
			if ( '' === $nested_item_id || ! $this->is_safe_token( $nested_item_id ) ) {
				return '';
			}

			return sprintf(
				'%s:%s:%d:%s:%s:%s',
				Contract::SEGMENT_KEY_PREFIX,
				Contract::OWNER_SCOPE_DOCUMENT,
				$owner_post_id,
				$element_id,
				$control_key,
				$nested_item_id
			);
		}

		return sprintf(
			'%s:%s:%d:%s:%s',
			Contract::SEGMENT_KEY_PREFIX,
			Contract::OWNER_SCOPE_DOCUMENT,
			$owner_post_id,
			$element_id,
			$control_key
		);
	}
}
