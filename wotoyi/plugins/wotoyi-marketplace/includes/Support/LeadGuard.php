<?php
/**
 * Anti-spam for the public lead form (SECURITY-REVIEW M2): honeypot + signed time token.
 *
 * The token is
 *  - signed (keyed MAC), so it cannot be forged;
 *  - time-boxed (3 s .. 2 h): a bot that posts instantly or replays an old token is rejected;
 *  - bound to the requester's IP bucket (IPv6 = /64): a token farmed on one network is useless on a botnet;
 *  - optionally bound to a target (vehicle/service ref passed to GET /leads/token): it only works for that listing;
 *  - SINGLE USE: the first accepted lead consumes it (a marker is stored for the token's remaining lifetime).
 *
 * It is not a CAPTCHA. Plug a challenge provider (Turnstile/hCaptcha) into the `wotoyi_mp_lead_challenge` filter.
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Support;

defined( 'ABSPATH' ) || exit;

final class LeadGuard {

	public const MIN_AGE = 3;    // Seconds a human needs to fill the form.
	public const MAX_AGE = 7200; // Token validity.

	/** Normalised target string, e.g. "vehicle:<ref>"; '' = not bound to a listing. */
	public static function target( string $type, string $ref ): string {
		$ref = strtolower( trim( $ref ) );
		return '' === $ref || ! in_array( $type, array( 'vehicle', 'service' ), true ) ? '' : $type . ':' . $ref;
	}

	private static function mac( string $ts, string $nonce, string $target ): string {
		return substr( Crypto::keyed_hash( 'lead-token', $ts . '|' . $nonce . '|' . Ip::hash( Ip::client() ) . '|' . $target ), 0, 32 );
	}

	public static function issue( string $target = '' ): string {
		$ts    = (string) Clock::now();
		$nonce = bin2hex( random_bytes( 6 ) );
		return $ts . '.' . $nonce . '.' . self::mac( $ts, $nonce, $target );
	}

	private static function used_key( string $token ): string {
		return 'wotoyi_lt_' . substr( hash( 'sha256', $token ), 0, 32 );
	}

	/**
	 * Valid for THIS requester and THIS target, in the age window, and not used yet.
	 * A token issued without a target is accepted for any target; a token issued with a target only for that one.
	 */
	public static function valid( mixed $token, string $target = '' ): bool {
		if ( ! is_string( $token ) || 1 !== preg_match( '/^(\d{10})\.([a-f0-9]{12})\.([a-f0-9]{32})$/', $token, $m ) ) {
			return false;
		}
		$ok = hash_equals( self::mac( $m[1], $m[2], '' ), $m[3] );
		if ( ! $ok && '' !== $target ) {
			$ok = hash_equals( self::mac( $m[1], $m[2], $target ), $m[3] );
		}
		if ( ! $ok ) {
			return false;
		}
		$age = Clock::now() - (int) $m[1];
		if ( $age < self::MIN_AGE || $age > self::MAX_AGE ) {
			return false;
		}
		return false === get_transient( self::used_key( $token ) );
	}

	/** Mark the token as spent. @return bool false when it was already spent. */
	public static function consume( string $token ): bool {
		$key = self::used_key( $token );
		if ( false !== get_transient( $key ) ) {
			return false;
		}
		set_transient( $key, 1, self::MAX_AGE + MINUTE_IN_SECONDS );
		return true;
	}
}
