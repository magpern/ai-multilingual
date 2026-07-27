<?php
/**
 * Signals that UUID repair must abort without mutating content.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

/**
 * Internal control-flow exception for atomic UUID repair aborts.
 */
final class UuidRepairAbortException extends \RuntimeException {
}
