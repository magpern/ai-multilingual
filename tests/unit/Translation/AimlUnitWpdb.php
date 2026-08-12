<?php
/**
 * In-memory $wpdb stub for unit tests of Store adoption SQL.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Translation;

/**
 * Minimal translations-table harness: identity SELECT, INSERT, UPDATE, TXN.
 */
final class AimlUnitWpdb {

	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	public string $prefix = 'wp_';

	/**
	 * Last error string (WP compatibility).
	 *
	 * @var string
	 */
	public string $last_error = '';

	/**
	 * Rows keyed by translation_id.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $rows = array();

	/**
	 * Next auto-increment id.
	 *
	 * @var int
	 */
	private int $next_id = 1;

	/**
	 * Whether a transaction is open.
	 *
	 * @var bool
	 */
	private bool $in_transaction = false;

	/**
	 * Snapshot of rows at START TRANSACTION.
	 *
	 * @var array<int, array<string, mixed>>|null
	 */
	private ?array $snapshot = null;

	/**
	 * Snapshot of next_id at START TRANSACTION.
	 *
	 * @var int|null
	 */
	private ?int $snapshot_next_id = null;

	/**
	 * Savepoint snapshots keyed by name.
	 *
	 * @var array<string, array{rows: array<int, array<string, mixed>>, next_id: int}>
	 */
	private array $savepoints = array();

	/**
	 * Clears all rows.
	 */
	public function reset(): void {
		$this->rows             = array();
		$this->next_id          = 1;
		$this->in_transaction   = false;
		$this->snapshot         = null;
		$this->snapshot_next_id = null;
		$this->savepoints       = array();
		$this->last_error       = '';
	}

	/**
	 * Seeds one translation row and returns its id.
	 *
	 * @param array<string, mixed> $row Column values.
	 */
	public function seed_row( array $row ): int {
		$id = (int) ( $row['translation_id'] ?? 0 );
		if ( $id <= 0 ) {
			$id = $this->next_id++;
		} else {
			$this->next_id = max( $this->next_id, $id + 1 );
		}

		$row['translation_id'] = $id;
		$this->rows[ $id ]     = $row;

		return $id;
	}

	/**
	 * Returns a row by primary key, or null.
	 *
	 * @param int $translation_id Translation id.
	 */
	public function row( int $translation_id ): ?object {
		$row = $this->rows[ $translation_id ] ?? null;

		return null === $row ? null : (object) $row;
	}

	/**
	 * Every stored row.
	 *
	 * @return list<object>
	 */
	public function all_rows(): array {
		return array_map(
			static fn( array $row ): object => (object) $row,
			array_values( $this->rows )
		);
	}

	/**
	 * Prepares a SQL string with printf-style placeholders.
	 *
	 * @param string $query Query with %s / %d placeholders.
	 * @param mixed  ...$args Placeholder values (or a single array).
	 */
	public function prepare( $query, ...$args ) {
		if ( isset( $args[0] ) && is_array( $args[0] ) && 1 === count( $args ) ) {
			$args = $args[0];
		}

		$i = 0;

		return (string) preg_replace_callback(
			'/%[sdfF]/',
			static function ( array $token ) use ( &$i, $args ): string {
				$value = $args[ $i ] ?? null;
				++$i;

				if ( '%d' === $token[0] ) {
					return (string) (int) $value;
				}

				if ( '%f' === $token[0] || '%F' === $token[0] ) {
					return (string) (float) $value;
				}

				return "'" . str_replace( array( '\\', "'" ), array( '\\\\', "''" ), (string) $value ) . "'";
			},
			(string) $query
		);
	}

	/**
	 * Runs a raw SQL statement (TXN + INSERT).
	 *
	 * @param string $sql SQL.
	 * @return int|true|false
	 */
	public function query( $sql ) {
		$sql = trim( (string) $sql );

		if ( 0 === strcasecmp( $sql, 'START TRANSACTION' ) ) {
			$this->snapshot         = $this->rows;
			$this->snapshot_next_id = $this->next_id;
			$this->in_transaction   = true;
			$this->savepoints       = array();

			return true;
		}

		if ( 0 === strcasecmp( $sql, 'COMMIT' ) ) {
			$this->in_transaction   = false;
			$this->snapshot         = null;
			$this->snapshot_next_id = null;
			$this->savepoints       = array();

			return true;
		}

		if ( 0 === strcasecmp( $sql, 'ROLLBACK' ) ) {
			if ( null !== $this->snapshot ) {
				$this->rows    = $this->snapshot;
				$this->next_id = (int) $this->snapshot_next_id;
			}
			$this->in_transaction   = false;
			$this->snapshot         = null;
			$this->snapshot_next_id = null;
			$this->savepoints       = array();

			return true;
		}

		if ( preg_match( '/^SAVEPOINT\s+`([^`]+)`\s*$/i', $sql, $token ) ) {
			$this->savepoints[ $token[1] ] = array(
				'rows'    => $this->rows,
				'next_id' => $this->next_id,
			);

			return true;
		}

		if ( preg_match( '/^RELEASE\s+SAVEPOINT\s+`([^`]+)`\s*$/i', $sql, $token ) ) {
			unset( $this->savepoints[ $token[1] ] );

			return true;
		}

		if ( preg_match( '/^ROLLBACK\s+TO\s+SAVEPOINT\s+`([^`]+)`\s*$/i', $sql, $token ) ) {
			$point = $this->savepoints[ $token[1] ] ?? null;
			if ( null === $point ) {
				$this->last_error = 'unknown savepoint';

				return false;
			}
			$this->rows    = $point['rows'];
			$this->next_id = $point['next_id'];
			unset( $this->savepoints[ $token[1] ] );

			return true;
		}

		if ( preg_match( '/^INSERT\s+INTO\s+(\S+)\s*\(([^)]+)\)\s*VALUES\s*\((.+)\)\s*$/is', $sql, $token ) ) {
			$columns = array_map( 'trim', explode( ',', $token[2] ) );
			$values  = $this->split_values( $token[3] );

			if ( count( $columns ) !== count( $values ) ) {
				$this->last_error = 'column/value count mismatch';

				return false;
			}

			$row = array();
			foreach ( $columns as $index => $column ) {
				$row[ $column ] = $this->decode_sql_value( $values[ $index ] );
			}

			$this->seed_row( $row );

			return 1;
		}

		$this->last_error = 'unsupported query: ' . $sql;

		return false;
	}

	/**
	 * Fetches one row.
	 *
	 * @param string|null $sql    Prepared SQL.
	 * @param string      $output Unused (OBJECT).
	 * @return object|null
	 */
	public function get_row( $sql = null, $output = 'OBJECT' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		unset( $output );

		$rows = $this->get_results( $sql );

		return $rows[0] ?? null;
	}

	/**
	 * Fetches matching rows.
	 *
	 * @param string|null $sql    Prepared SQL.
	 * @param string      $output Unused (OBJECT).
	 * @return list<object>
	 */
	public function get_results( $sql = null, $output = 'OBJECT' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		unset( $output );

		$sql = (string) $sql;
		$sql = preg_replace( '/\s+FOR UPDATE\s*$/i', '', $sql ) ?? $sql;

		if ( preg_match(
			'/WHERE\s+source_type\s*=\s*\'([^\']*)\'\s+AND\s+source_id\s*=\s*(\d+)\s+AND\s+segment_hash\s*=\s*\'([^\']*)\'\s+AND\s+language_id\s*=\s*(\d+)/i',
			$sql,
			$match
		) ) {
			return $this->filter_rows(
				static function ( array $row ) use ( $match ): bool {
					return (string) ( $row['source_type'] ?? '' ) === $match[1]
						&& (int) ( $row['source_id'] ?? 0 ) === (int) $match[2]
						&& (string) ( $row['segment_hash'] ?? '' ) === $match[3]
						&& (int) ( $row['language_id'] ?? 0 ) === (int) $match[4];
				}
			);
		}

		if ( preg_match(
			'/WHERE\s+source_type\s*=\s*\'([^\']*)\'\s+AND\s+source_id\s*=\s*(\d+)\s+AND\s+language_id\s*=\s*(\d+)/i',
			$sql,
			$match
		) ) {
			$rows = $this->filter_rows(
				static function ( array $row ) use ( $match ): bool {
					return (string) ( $row['source_type'] ?? '' ) === $match[1]
						&& (int) ( $row['source_id'] ?? 0 ) === (int) $match[2]
						&& (int) ( $row['language_id'] ?? 0 ) === (int) $match[3];
				}
			);

			usort(
				$rows,
				static function ( object $a, object $b ): int {
					$order = ( (int) ( $a->segment_order ?? 0 ) ) <=> ( (int) ( $b->segment_order ?? 0 ) );
					if ( 0 !== $order ) {
						return $order;
					}

					return ( (int) $a->translation_id ) <=> ( (int) $b->translation_id );
				}
			);

			return $rows;
		}

		if ( preg_match( '/WHERE\s+translation_id\s*=\s*(\d+)/i', $sql, $match ) ) {
			$row = $this->rows[ (int) $match[1] ] ?? null;

			return null === $row ? array() : array( (object) $row );
		}

		if ( preg_match( '/SHOW TABLES LIKE\s+\'([^\']+)\'/i', $sql, $match ) ) {
			return $match[1] === $this->prefix . 'aiml_translations'
				? array( (object) array( $match[1] ) )
				: array();
		}

		return array();
	}

	/**
	 * Fetches a single variable.
	 *
	 * @param string|null $sql Prepared SQL.
	 * @return mixed
	 */
	public function get_var( $sql = null ) {
		$sql = (string) $sql;

		if ( preg_match( '/@@SESSION\.in_transaction/i', $sql ) ) {
			return $this->in_transaction ? 1 : 0;
		}

		if ( preg_match( '/SHOW TABLES LIKE\s+\'([^\']+)\'/i', $sql, $token ) ) {
			return $token[1] === $this->prefix . 'aiml_translations' ? $token[1] : null;
		}

		$row = $this->get_row( $sql );

		if ( null === $row ) {
			return null;
		}

		$vars = get_object_vars( $row );

		return array_shift( $vars );
	}

	/**
	 * Updates matching rows.
	 *
	 * @param string                       $table        Table name.
	 * @param array<string, mixed>         $data         Columns to set.
	 * @param array<string, mixed>         $where        WHERE map.
	 * @param array<int, string|null>|null $format       Unused.
	 * @param array<int, string>|null      $where_format Unused.
	 * @return int|false
	 */
	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		unset( $table, $format, $where_format );

		$updated = 0;
		foreach ( $this->rows as $id => $row ) {
			if ( ! $this->row_matches( $row, $where ) ) {
				continue;
			}
			$this->rows[ $id ] = array_merge( $row, $data );
			++$updated;
		}

		return $updated;
	}

	/**
	 * Deletes matching rows.
	 *
	 * @param string                  $table        Table name.
	 * @param array<string, mixed>    $where        WHERE map.
	 * @param array<int, string>|null $where_format Unused.
	 * @return int|false
	 */
	public function delete( $table, $where, $where_format = null ) {
		unset( $table, $where_format );

		$deleted = 0;
		foreach ( $this->rows as $id => $row ) {
			if ( ! $this->row_matches( $row, $where ) ) {
				continue;
			}
			unset( $this->rows[ $id ] );
			++$deleted;
		}

		return $deleted;
	}

	/**
	 * Filters stored rows.
	 *
	 * @param array $predicate Predicate callable receiving a row array.
	 * @return list<object>
	 */
	private function filter_rows( $predicate ): array { // phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint -- Callable row filter.
		if ( ! is_callable( $predicate ) ) {
			return array();
		}
		$out = array();
		foreach ( $this->rows as $row ) {
			if ( $predicate( $row ) ) {
				$out[] = (object) $row;
			}
		}

		return $out;
	}

	/**
	 * Whether a row matches a WHERE map.
	 *
	 * @param array<string, mixed> $row   Row.
	 * @param array<string, mixed> $where WHERE.
	 */
	private function row_matches( array $row, array $where ): bool {
		foreach ( $where as $column => $value ) {
			if ( ! array_key_exists( $column, $row ) ) {
				return false;
			}

			// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison,Universal.Operators.StrictComparisons.LooseNotEqual -- Match WP $wpdb->update semantics.
			if ( $row[ $column ] != $value ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Splits a VALUES (...) list respecting quotes.
	 *
	 * @param string $values Raw values clause.
	 * @return list<string>
	 */
	private function split_values( string $values ): array {
		$parts   = array();
		$current = '';
		$in_str  = false;
		$length  = strlen( $values );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $values[ $i ];

			if ( "'" === $char ) {
				$prev = $i > 0 ? $values[ $i - 1 ] : '';
				if ( ! $in_str ) {
					$in_str   = true;
					$current .= $char;
					continue;
				}
				if ( '\\' === $prev ) {
					$current .= $char;
					continue;
				}
				// Doubled quote inside string.
				if ( $i + 1 < $length && "'" === $values[ $i + 1 ] ) {
					$current .= "''";
					++$i;
					continue;
				}
				$in_str   = false;
				$current .= $char;
				continue;
			}

			if ( ! $in_str && ',' === $char ) {
				$parts[] = trim( $current );
				$current = '';
				continue;
			}

			$current .= $char;
		}

		if ( '' !== trim( $current ) || array() !== $parts ) {
			$parts[] = trim( $current );
		}

		return $parts;
	}

	/**
	 * Decodes a SQL literal into a PHP value.
	 *
	 * @param string $value Literal.
	 * @return mixed
	 */
	private function decode_sql_value( string $value ) {
		$value = trim( $value );

		if ( 0 === strcasecmp( $value, 'NULL' ) ) {
			return null;
		}

		if ( preg_match( "/^'(.*)'$/s", $value, $match ) ) {
			return str_replace( array( "''", '\\\\' ), array( "'", '\\' ), $match[1] );
		}

		if ( is_numeric( $value ) ) {
			return str_contains( $value, '.' ) ? (float) $value : (int) $value;
		}

		return $value;
	}
}
