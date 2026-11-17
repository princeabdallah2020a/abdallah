<?php
/**
 * Anti-spam for the public lead form: honeypot + signed time token (a bot that posts instantly or replays
 * an old token is rejected). Not a CAPTCHA: pair it with a challenge provider for hostile traffic.
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Support;

defined( 'ABSPATH' ) || exit;

final class LeadGuard {

	public const MIN_AGE = 3;    // Seconds a human needs to fill the form.
	public const MAX_AGE = 7200; // Token validity.

	public static function issue(): string {
		$ts = (string) Clock::now();
		return $ts . '.' . substr( Crypto::keyed_hash( 'lead-token', $ts ), 0, 32 );
	}

	public static function valid( mixed $token ): bool {
		if ( ! is_string( $token ) || 1 !== preg_match( '/^(\d{10})\.([a-f0-9]{32})$/', $token, $m ) ) {
			return false;
		}
		if ( ! hash_equals( substr( Crypto::keyed_hash( 'lead-token', $m[1] ), 0, 32 ), $m[2] ) ) {
			return false;
		}
		$age = Clock::now() - (int) $m[1];
		return $age >= self::MIN_AGE && $age <= self::MAX_AGE;
	}
}
