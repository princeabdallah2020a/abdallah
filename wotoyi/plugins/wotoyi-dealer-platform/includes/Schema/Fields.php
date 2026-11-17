<?php
/**
 * Generic field-definition engine: SQL column generation, sanitisation/validation, row casting.
 *
 * A definition is: group, type (string|text|enum|int|decimal|bool|date|url|country|currency|json_list|json_map),
 * plus type options (len, values, min, max, precision, scale, item, max_items) and flags
 * (private = never public; managed = set by the marketplace only, ignored if sent by a dealer).
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Schema;

defined( 'ABSPATH' ) || exit;

final class Fields {

	/** SQL column definition (dbDelta-compatible) for one field. */
	public static function column_sql( string $name, array $def ): string {
		switch ( $def['type'] ) {
			case 'string':
				$type = 'VARCHAR(' . (int) ( $def['len'] ?? 80 ) . ')';
				break;
			case 'text':
			case 'json_list':
			case 'json_map':
				$type = 'LONGTEXT';
				break;
			case 'enum':
				$type = 'VARCHAR(24)';
				break;
			case 'int':
				$max  = (int) ( $def['max'] ?? 2147483647 );
				$type = $max <= 255 ? 'TINYINT(3) UNSIGNED' : ( $max <= 65535 ? 'SMALLINT(5) UNSIGNED' : ( $max <= 4294967295 ? 'INT(10) UNSIGNED' : 'BIGINT(20) UNSIGNED' ) );
				if ( isset( $def['min'] ) && $def['min'] < 0 ) {
					$type = str_replace( ' UNSIGNED', '', $type );
				}
				break;
			case 'decimal':
				$type = 'DECIMAL(' . (int) $def['precision'] . ',' . (int) $def['scale'] . ')';
				break;
			case 'bool':
				$type = 'TINYINT(1)';
				break;
			case 'date':
				$type = 'DATE';
				break;
			case 'url':
				$type = 'VARCHAR(500)';
				break;
			case 'country':
				$type = 'CHAR(2)';
				break;
			case 'currency':
				$type = 'CHAR(3)';
				break;
			case 'datetime':
				$type = 'DATETIME';
				break;
			case 'ref':
				$type = 'CHAR(36)';
				break;
			case 'email':
				$type = 'VARCHAR(190)';
				break;
			case 'phone':
				$type = 'VARCHAR(40)';
				break;
			default:
				$type = 'VARCHAR(255)';
		}
		return $name . ' ' . $type . ' DEFAULT NULL';
	}

	/**
	 * Sanitise a payload against definitions. Absent fields become null (snapshot / full-replace semantics).
	 *
	 * @param array<string,array> $defs    Field definitions.
	 * @param array<string,mixed> $payload Raw dealer payload (untrusted).
	 * @param list<string>        $envelope Keys that belong to the envelope, not to the field set.
	 * @return array{clean: array<string,mixed>, warnings: list<string>}
	 */
	public static function sanitize( array $defs, array $payload, array $envelope = array() ): array {
		$clean    = array();
		$warnings = array();

		foreach ( $defs as $name => $def ) {
			if ( ! empty( $def['managed'] ) ) {
				if ( array_key_exists( $name, $payload ) ) {
					$warnings[] = 'managed_field_ignored:' . $name;
				}
				continue; // Never written from dealer payloads.
			}
			if ( ! array_key_exists( $name, $payload ) || null === $payload[ $name ] || '' === $payload[ $name ] ) {
				$clean[ $name ] = null;
				continue;
			}
			$value = self::sanitize_value( $def, $payload[ $name ] );
			if ( null === $value ) {
				$warnings[] = 'invalid_value:' . $name;
			}
			$clean[ $name ] = $value;
		}

		$unknown = array_diff( array_keys( $payload ), array_keys( $defs ), $envelope );
		foreach ( array_slice( array_map( 'strval', $unknown ), 0, 20 ) as $key ) {
			$warnings[] = 'unknown_field:' . preg_replace( '/[^A-Za-z0-9_\-]/', '', $key );
		}

		return array(
			'clean'    => $clean,
			'warnings' => $warnings,
		);
	}

	/**
	 * Partial sanitise (PATCH semantics): only keys present in the payload are returned; null/'' clears the field.
	 * Managed and unknown keys are reported as warnings, never written.
	 *
	 * @return array{clean: array<string,mixed>, warnings: list<string>, invalid: list<string>}
	 */
	public static function sanitize_partial( array $defs, array $payload, array $envelope = array() ): array {
		$clean    = array();
		$warnings = array();
		$invalid  = array();
		foreach ( $defs as $name => $def ) {
			if ( ! array_key_exists( $name, $payload ) ) {
				continue;
			}
			if ( ! empty( $def['managed'] ) || ! empty( $def['readonly'] ) ) {
				$warnings[] = 'readonly_field_ignored:' . $name;
				continue;
			}
			if ( null === $payload[ $name ] || '' === $payload[ $name ] ) {
				$clean[ $name ] = null;
				continue;
			}
			$value = self::sanitize_value( $def, $payload[ $name ] );
			if ( null === $value ) {
				$invalid[] = $name;
				continue;
			}
			$clean[ $name ] = $value;
		}
		foreach ( array_slice( array_map( 'strval', array_diff( array_keys( $payload ), array_keys( $defs ), $envelope ) ), 0, 20 ) as $key ) {
			$warnings[] = 'unknown_field:' . preg_replace( '/[^A-Za-z0-9_\-]/', '', $key );
		}
		return array( 'clean' => $clean, 'warnings' => $warnings, 'invalid' => $invalid );
	}

	/** Sanitise one value; null means invalid. */
	public static function sanitize_value( array $def, mixed $value ): mixed {
		switch ( $def['type'] ) {
			case 'string':
				if ( ! is_scalar( $value ) ) {
					return null;
				}
				$v = trim( sanitize_text_field( (string) $value ) );
				$v = mb_substr( $v, 0, (int) ( $def['len'] ?? 80 ) );
				return '' === $v ? null : $v;

			case 'text':
				if ( ! is_scalar( $value ) ) {
					return null;
				}
				$v = ! empty( $def['plain'] ) ? trim( sanitize_textarea_field( (string) $value ) ) : trim( wp_kses( (string) $value, self::allowed_html() ) );
				$v = mb_substr( $v, 0, 20000 );
				return '' === $v ? null : $v;

			case 'enum':
				if ( ! is_string( $value ) ) {
					return null;
				}
				$v = strtolower( trim( $value ) );
				return in_array( $v, $def['values'], true ) ? $v : null;

			case 'int':
				$n = self::to_number( $value );
				if ( null === $n || ( is_float( $n ) && floor( $n ) !== $n ) ) {
					return null;
				}
				return self::in_range( $n, $def ) ? (int) $n : null;

			case 'decimal':
				$n = self::to_number( $value );
				if ( null === $n || ! self::in_range( $n, $def ) ) {
					return null;
				}
				return round( $n, (int) $def['scale'] );

			case 'bool':
				$b = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
				return null === $b ? null : $b;

			case 'date':
				if ( ! is_string( $value ) || 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
					return null;
				}
				return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) && (int) $m[1] >= 1900 && (int) $m[1] <= 2100 ? $value : null;

			case 'url':
				return self::clean_url( $value );

			case 'country':
				return is_string( $value ) && 1 === preg_match( '/^[A-Za-z]{2}$/', $value ) ? strtoupper( $value ) : null;

			case 'currency':
				return is_string( $value ) && 1 === preg_match( '/^[A-Za-z]{3}$/', $value ) ? strtoupper( $value ) : null;

			case 'datetime':
				if ( ! is_string( $value ) || strlen( $value ) > 40 ) {
					return null;
				}
				$ts = strtotime( $value . ( 1 === preg_match( '/(Z|[+\-]\d{2}:?\d{2})$/', $value ) ? '' : ' UTC' ) );
				return false !== $ts && $ts >= 0 && $ts <= 4102444800 ? gmdate( 'Y-m-d H:i:s', $ts ) : null;

			case 'ref':
				return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $value ) ? $value : null;

			case 'email':
				if ( ! is_string( $value ) || strlen( $value ) > 190 ) {
					return null;
				}
				$e = sanitize_email( trim( $value ) );
				return false !== is_email( $e ) ? strtolower( $e ) : null;

			case 'phone':
				if ( ! is_scalar( $value ) ) {
					return null;
				}
				$p = trim( preg_replace( '/[^0-9+()\-. ]/', '', (string) $value ) ?? '' );
				return strlen( preg_replace( '/\D/', '', $p ) ?? '' ) >= 6 && strlen( $p ) <= 40 ? $p : null;

			case 'json_list':
				if ( ! is_array( $value ) || ! array_is_list( $value ) ) {
					return null;
				}
				$out = array();
				foreach ( array_slice( $value, 0, (int) ( $def['max_items'] ?? 30 ) ) as $item ) {
					$clean = 'url' === ( $def['item'] ?? 'string' )
						? self::clean_url( $item )
						: ( is_scalar( $item ) ? mb_substr( trim( sanitize_text_field( (string) $item ) ), 0, 120 ) : null );
					if ( null !== $clean && '' !== $clean ) {
						$out[] = $clean;
					}
				}
				return array() === $out ? null : $out;

			case 'json_map':
				return self::sanitize_map( $def, $value );
		}
		return null;
	}

	private static function sanitize_map( array $def, mixed $value ): ?array {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$out = array();
		if ( 'i18n' === $def['map'] ) {
			foreach ( Enums::LANGS as $lang ) {
				if ( isset( $value[ $lang ] ) && is_string( $value[ $lang ] ) ) {
					$text = trim( wp_kses( $value[ $lang ], self::allowed_html() ) );
					if ( '' !== $text ) {
						$out[ $lang ] = mb_substr( $text, 0, 20000 );
					}
				}
			}
		} elseif ( 'equipment' === $def['map'] ) {
			foreach ( Enums::equipment() as $cat => $codes ) {
				if ( empty( $value[ $cat ] ) || ! is_array( $value[ $cat ] ) ) {
					continue;
				}
				$ok = array_values( array_unique( array_intersect( array_map( 'strval', $value[ $cat ] ), $codes ) ) );
				if ( $ok ) {
					$out[ $cat ] = $ok;
				}
			}
		}
		return array() === $out ? null : $out;
	}

	/** Allowed HTML in descriptions (no attributes, no scripts). */
	public static function allowed_html(): array {
		return array(
			'br'     => array(),
			'p'      => array(),
			'strong' => array(),
			'em'     => array(),
			'ul'     => array(),
			'ol'     => array(),
			'li'     => array(),
		);
	}

	private static function to_number( mixed $value ): int|float|null {
		if ( is_int( $value ) || is_float( $value ) ) {
			return is_finite( (float) $value ) ? $value + 0 : null;
		}
		if ( is_string( $value ) && is_numeric( trim( $value ) ) ) {
			return trim( $value ) + 0;
		}
		return null;
	}

	private static function in_range( int|float $n, array $def ): bool {
		$min = $def['min'] ?? 0;
		$max = $def['max'] ?? PHP_INT_MAX;
		return $n >= $min && $n <= $max;
	}

	private static function clean_url( mixed $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}
		$url = esc_url_raw( trim( $value ), array( 'http', 'https' ) );
		return '' !== $url && strlen( $url ) <= 500 ? $url : null;
	}

	/** Convert sanitised values to DB-bound scalars (JSON encode, bool to 0/1, decimals as strings). */
	public static function to_db( array $defs, array $clean ): array {
		$row = array();
		foreach ( $defs as $name => $def ) {
			if ( ! array_key_exists( $name, $clean ) ) {
				continue;
			}
			$v = $clean[ $name ];
			if ( null === $v ) {
				$row[ $name ] = null;
			} elseif ( in_array( $def['type'], array( 'json_list', 'json_map' ), true ) ) {
				$row[ $name ] = wp_json_encode( $v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			} elseif ( 'bool' === $def['type'] ) {
				$row[ $name ] = $v ? 1 : 0;
			} elseif ( 'decimal' === $def['type'] ) {
				$row[ $name ] = number_format( (float) $v, (int) $def['scale'], '.', '' );
			} else {
				$row[ $name ] = $v;
			}
		}
		return $row;
	}

	/** Cast a DB row (all strings) back to typed values. */
	public static function cast_row( array $defs, array $row ): array {
		foreach ( $defs as $name => $def ) {
			if ( ! array_key_exists( $name, $row ) ) {
				continue;
			}
			$v = $row[ $name ];
			if ( null === $v ) {
				continue;
			}
			switch ( $def['type'] ) {
				case 'int':
					$row[ $name ] = (int) $v;
					break;
				case 'decimal':
					$row[ $name ] = (float) $v;
					break;
				case 'bool':
					$row[ $name ] = (bool) (int) $v;
					break;
				case 'json_list':
				case 'json_map':
					$row[ $name ] = is_string( $v ) ? ( json_decode( $v, true, 32 ) ?: null ) : $v;
					break;
			}
		}
		return $row;
	}
}
