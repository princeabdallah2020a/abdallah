<?php
/**
 * Tenant members: which WordPress users may work for a dealer, with which role. Removing a member suspends the
 * row (history is kept). The last active owner can never be demoted or suspended.
 *
 * H1: a dealer owner can never attach a WordPress user to the tenant. The owner INVITES an email address; the
 * invitee receives a single-use token by email and must accept while logged in with that same email. The invite
 * reply is identical whether or not the address belongs to a user or is already a member (no enumeration), and
 * invitations are rate limited per tenant and per address. Direct attachment by user id exists only for the
 * platform operator (add_direct, used by the /platform/members route).
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Service;

use Wotoyi\DealerPlatform\Data\Directory;
use Wotoyi\DealerPlatform\Data\Invitations;
use Wotoyi\DealerPlatform\Data\TenantContext;
use Wotoyi\DealerPlatform\Data\TenantDb;
use Wotoyi\DealerPlatform\Schema\Entities;
use Wotoyi\DealerPlatform\Support\ApiException;
use Wotoyi\DealerPlatform\Support\Clock;
use Wotoyi\DealerPlatform\Support\RateLimiter;
use Wotoyi\DealerPlatform\Support\Uuid;

defined( 'ABSPATH' ) || exit;

final class MemberService {

	/** @return array<string,mixed> */
	public function present( array $row ): array {
		return array(
			'id'           => $row['uuid'],
			'user_id'      => (int) $row['user_id'],
			'role'         => $row['role'],
			'display_name' => $row['display_name'],
			'status'       => $row['status'],
			'created_at'   => $row['created_at'],
		);
	}

	/** @return array<string,mixed> */
	public function list(): array {
		$rows = TenantDb::select( 'members', array(), array( 'order' => array( array( 'id', 'asc' ) ), 'limit' => 500 ) );
		return array( 'items' => array_map( array( $this, 'present' ), $rows ) );
	}

	/** Pending invitations of the current tenant (no tokens). @return array<string,mixed> */
	public function invitations(): array {
		$rows = Invitations::list_for_tenant( TenantContext::id() );
		return array(
			'items' => array_map(
				static fn( array $r ): array => array( 'id' => $r['uuid'], 'email' => $r['email'], 'role' => $r['role'], 'display_name' => $r['display_name'], 'expires_at' => $r['expires_at'], 'created_at' => $r['created_at'] ),
				$rows
			),
		);
	}

	public function revoke_invitation( string $uuid ): array {
		if ( 1 !== preg_match( '/^[0-9a-f\-]{36}$/', $uuid ) || ! Invitations::revoke( TenantContext::id(), $uuid ) ) {
			throw ApiException::not_found( 'Invitation' );
		}
		AuditLog::record( 'members.invite_revoked', 'invitations', $uuid );
		return array( 'revoked' => true );
	}

	/**
	 * Invite an email address to join the tenant. ALWAYS answers the same, whether or not the address belongs to a
	 * WordPress user or is already a member.
	 *
	 * @param array<string,mixed> $input email, role, display_name?
	 * @return array<string,mixed>
	 */
	public function invite( array $input ): array {
		$role = $input['role'] ?? null;
		if ( ! is_string( $role ) || ! in_array( $role, Entities::ROLES, true ) ) {
			throw ApiException::invalid( 'Unknown role.', array( 'fields' => array( 'role' ) ) );
		}
		$email = isset( $input['email'] ) && is_string( $input['email'] ) ? strtolower( sanitize_email( $input['email'] ) ) : '';
		if ( '' === $email || false === is_email( $email ) || strlen( $email ) > 190 ) {
			throw ApiException::invalid( 'A valid email is required.', array( 'fields' => array( 'email' ) ) );
		}
		// Abuse limits (M4). The tenant limit answers 429 for every address alike, so it reveals nothing about one address.
		if ( ! RateLimiter::allow( 'invite:t:' . TenantContext::uuid(), 10, 3600 ) ) {
			throw new ApiException( 'wotoyi_rate_limited', 'Too many invitations. Try again later.', 429 );
		}
		$name   = isset( $input['display_name'] ) && is_string( $input['display_name'] ) ? mb_substr( sanitize_text_field( $input['display_name'] ), 0, 120 ) : null;
		$result = array( 'status' => 'invitation_sent', 'message' => 'If this address can be invited, an invitation email has been sent.' );
		// Per address, across all tenants: stops one address from being mail-bombed by several dealers. Silent (same reply).
		if ( ! RateLimiter::allow( 'invite:e:' . hash( 'sha256', $email ), 3, 86400 ) ) {
			AuditLog::record( 'members.invite_throttled', 'members', null, array( 'role' => $role ) );
			return $result;
		}
		$user = get_user_by( 'email', $email );
		if ( $user ) {
			$existing = TenantDb::get( 'members', array( 'user_id' => (int) $user->ID ) );
			if ( $existing && 'active' === $existing['status'] ) {
				AuditLog::record( 'members.invite_skipped', 'members', (string) $existing['uuid'], array( 'role' => $role ) );
				return $result; // Already a member: same answer, no mail.
			}
		}
		$inv = Invitations::create( TenantContext::id(), $email, $role, $name, TenantContext::user_id() );
		AuditLog::record( 'members.invite', 'invitations', $inv['uuid'], array( 'role' => $role, 'expires_at' => $inv['expires_at'] ) );
		$this->send_mail( $email, $role, $inv['token'], $inv['expires_at'] );
		return $result;
	}

	private function send_mail( string $email, string $role, string $token, string $expires ): void {
		$tenant  = TenantContext::tenant();
		$name    = sanitize_text_field( (string) $tenant['name'] );
		$url     = (string) apply_filters( 'wotoyi_dp_invitation_url', home_url( '/pro/accept-invitation' ) . '#token=' . $token, $token ); // Fragment: never sent to servers, logs or referrers.
		$subject = sprintf( 'Invitation to join %s on Wotoyi Pro', $name );
		$message = sprintf( "You have been invited to join %s as %s.\n\nLog in with this email address and open the link to accept (valid until %s UTC):\n%s\n\nIf you did not expect this invitation, ignore this email: nothing happens unless you accept.", $name, $role, $expires, $url );
		wp_mail( $email, $subject, $message );
	}

	/**
	 * The invitee accepts: only the logged-in user whose WordPress email equals the invited address can use the token.
	 * Every failure gives the same answer.
	 *
	 * @return array<string,mixed>
	 */
	public function accept( string $token, int $user_id ): array {
		if ( ! RateLimiter::allow( 'accept:' . $user_id, 10, 3600 ) ) {
			throw new ApiException( 'wotoyi_rate_limited', 'Too many attempts. Try again later.', 429 );
		}
		$invalid = new ApiException( 'wotoyi_invitation_invalid', 'This invitation is invalid, expired or was sent to another address.', 422 );
		$user    = get_userdata( $user_id );
		$inv     = Invitations::find_pending( $token );
		if ( ! $user || ! $inv || strtolower( (string) $user->user_email ) !== strtolower( (string) $inv['email'] ) ) {
			throw $invalid;
		}
		$tenant = Directory::find_by_id( (int) $inv['tenant_id'] );
		if ( ! $tenant || 'active' !== $tenant['status'] ) {
			throw $invalid;
		}
		$member = TenantContext::run(
			$tenant,
			function () use ( $inv, $user_id, $invalid ): array {
				return TenantDb::transaction(
					function () use ( $inv, $user_id, $invalid ): array {
						if ( ! Invitations::consume( (int) $inv['id'], $user_id ) ) {
							throw $invalid; // Lost a race: single use.
						}
						$existing = TenantDb::get( 'members', array( 'user_id' => $user_id ) );
						$from     = $existing ? array( 'role' => $existing['role'], 'status' => $existing['status'] ) : null;
						if ( $existing ) {
							TenantDb::update( 'members', array( 'uuid' => $existing['uuid'] ), array( 'role' => $inv['role'], 'status' => 'active', 'updated_at' => Clock::mysql(), 'version' => array( '@incr' => 1 ) ) );
							$row = (array) TenantDb::find_uuid( 'members', (string) $existing['uuid'] );
						} else {
							$row = $this->insert( $user_id, (string) $inv['role'], $inv['display_name'] );
						}
						AuditLog::record( 'members.accept', 'members', (string) $row['uuid'], array( 'from' => $from, 'to' => array( 'role' => $inv['role'], 'status' => 'active' ), 'invited_by' => (int) $inv['invited_by'] ), $user_id );
						return $row;
					}
				);
			}
		);
		return array( 'member' => $this->present( $member ), 'tenant' => array( 'id' => $tenant['uuid'], 'name' => $tenant['name'] ) );
	}

	/**
	 * Platform operator only (never reachable from a tenant role): attach an existing WordPress user directly.
	 *
	 * @return array<string,mixed>
	 */
	public function add_direct( int $user_id, string $role, ?string $name = null, ?int $actor = null ): array {
		if ( ! in_array( $role, Entities::ROLES, true ) ) {
			throw ApiException::invalid( 'Unknown role.', array( 'fields' => array( 'role' ) ) );
		}
		if ( $user_id <= 0 || ! get_userdata( $user_id ) ) {
			throw ApiException::invalid( 'User not found.', array( 'fields' => array( 'user_id' ) ) );
		}
		$name = null === $name ? null : mb_substr( sanitize_text_field( $name ), 0, 120 );
		$row  = TenantDb::transaction(
			function () use ( $user_id, $role, $name, $actor ): array {
				if ( TenantDb::get( 'members', array( 'user_id' => $user_id ) ) ) {
					throw ApiException::conflict( 'wotoyi_member_exists', 'This user is already a member.' );
				}
				$row = $this->insert( $user_id, $role, $name );
				AuditLog::record( 'members.add', 'members', (string) $row['uuid'], array( 'role' => $role, 'target_user' => $user_id ), $actor, 'platform' );
				return $row;
			}
		);
		return $this->present( $row );
	}

	/** Insert without audit (tenant bootstrap). @return array<string,mixed> */
	public function insert( int $user_id, string $role, ?string $name ): array {
		$uuid = Uuid::v7();
		$now  = Clock::mysql();
		TenantDb::insert( 'members', array( 'uuid' => $uuid, 'version' => 1, 'user_id' => $user_id, 'role' => $role, 'display_name' => $name, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now ) );
		return (array) TenantDb::find_uuid( 'members', $uuid );
	}

	/**
	 * @param array<string,mixed> $input role?, status? (active|suspended), display_name?
	 * @return array<string,mixed>
	 */
	public function update( string $uuid, array $input ): array {
		$row = TenantDb::transaction(
			function () use ( $uuid, $input ): array {
				$member = 1 === preg_match( '/^[0-9a-f\-]{36}$/', $uuid ) ? TenantDb::find_uuid( 'members', $uuid ) : null;
				if ( ! $member ) {
					throw ApiException::not_found( 'Member' );
				}
				$data = array();
				if ( isset( $input['role'] ) ) {
					if ( ! is_string( $input['role'] ) || ! in_array( $input['role'], Entities::ROLES, true ) ) {
						throw ApiException::invalid( 'Unknown role.', array( 'fields' => array( 'role' ) ) );
					}
					$data['role'] = $input['role'];
				}
				if ( isset( $input['status'] ) ) {
					if ( ! in_array( $input['status'], array( 'active', 'suspended' ), true ) ) {
						throw ApiException::invalid( 'Unknown status.', array( 'fields' => array( 'status' ) ) );
					}
					$data['status'] = $input['status'];
				}
				if ( isset( $input['display_name'] ) && is_string( $input['display_name'] ) ) {
					$data['display_name'] = mb_substr( sanitize_text_field( $input['display_name'] ), 0, 120 );
				}
				if ( ! $data ) {
					return $member;
				}
				$loses_owner = 'owner' === $member['role'] && 'active' === $member['status'] && ( ( isset( $data['role'] ) && 'owner' !== $data['role'] ) || ( isset( $data['status'] ) && 'active' !== $data['status'] ) );
				if ( $loses_owner && TenantDb::count( 'members', array( 'role' => 'owner', 'status' => 'active' ) ) <= 1 ) {
					throw ApiException::conflict( 'wotoyi_last_owner', 'A dealer account must keep at least one active owner.' );
				}
				TenantDb::update( 'members', array( 'uuid' => $uuid ), $data + array( 'updated_at' => Clock::mysql(), 'version' => array( '@incr' => 1 ) ) );
				$from = array();
				$to   = array();
				foreach ( array( 'role', 'status' ) as $k ) {
					if ( isset( $data[ $k ] ) ) {
						$from[ $k ] = $member[ $k ];
						$to[ $k ]   = $data[ $k ];
					}
				}
				AuditLog::record( 'members.update', 'members', $uuid, array( 'fields' => array_keys( $data ), 'target_user' => (int) $member['user_id'], 'from' => $from, 'to' => $to ) );
				return (array) TenantDb::find_uuid( 'members', $uuid );
			}
		);
		return $this->present( $row );
	}

	public function remove( string $uuid ): array {
		return $this->update( $uuid, array( 'status' => 'suspended' ) );
	}
}
