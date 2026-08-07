<?php
/**
 * Hybrid-D Elementor identity keys.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Elementor;

/**
 * Builds and validates `e:d:<owner_post_id>:<element_id>:<control_key>`.
 */
final class ElementorIdentity {

	/**
	 * Build segment key.
	 *
	 * @param int    $owner_post_id Document post ID.
	 * @param string $element_id    Native Elementor element ID.
	 * @param string $control_key   Control key.
	 */
	public function build( int $owner_post_id, string $element_id, string $control_key ): string {
		$element_id  = trim( $element_id );
		$control_key = trim( $control_key );

		if ( $owner_post_id <= 0 || '' === $element_id || '' === $control_key ) {
			return '';
		}

		if ( ! $this->is_safe_token( $element_id ) || ! $this->is_safe_token( $control_key ) ) {
			return '';
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

	/**
	 * Parse a segment key.
	 *
	 * @param string $segment_key Key.
	 * @return array{owner_post_id:int,element_id:string,control_key:string}|null
	 */
	public function parse( string $segment_key ): ?array {
		$parts = explode( ':', $segment_key );
		if ( 5 !== count( $parts ) ) {
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

		return array(
			'owner_post_id' => $owner,
			'element_id'    => $parts[3],
			'control_key'   => $parts[4],
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
	private function is_safe_token( string $token ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9_-]+$/', $token );
	}
}
