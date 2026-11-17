<?php
/**
 * Tenant members: which WordPress users may work for a dealer, with which role. Removing a member suspends the
 * row (history is kept). The last active owner can never be demoted or suspended.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Service;

use Wotoyi\DealerPlatform\Data\TenantDb;
use Wotoyi\DealerPlatform\Schema\Entities;
use Wotoyi\DealerPlatform\Support\ApiException;
use Wotoyi\DealerPlatform\Support\Clock;
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

	/**
	 * @param array<string,mixed> $input user_id | email (+ create_user), role, display_name
	 * @return array<string,mixed>
	 */
	public function add( array $input ): array {
		$role = $input['role'] ?? null;
		if ( ! is_string( $role ) || ! in_array( $role, Entities::ROLES, true ) ) {
			throw ApiException::invalid( 'Unknown role.', array( 'fields' => array( 'role' ) ) );
		}
		$user_id = $this->resolve_user( $input );
		$name    = isset( $input['display_name'] ) && is_string( $input['display_name'] ) ? mb_substr( sanitize_text_field( $input['display_name'] ), 0, 120 ) : null;
		$row     = TenantDb::transaction(
			function () use ( $user_id, $role, $name ): array {
				$existing = TenantDb::get( 'members', array( 'user_id' => $user_id ) );
				if ( $existing ) {
					throw ApiException::conflict( 'wotoyi_member_exists', 'This user is already a member.' );
				}
				$row = $this->insert( $user_id, $role, $name );
				AuditLog::record( 'members.add', 'members', (string) $row['uuid'], array( 'role' => $role ) );
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
				AuditLog::record( 'members.update', 'members', $uuid, array( 'fields' => array_keys( $data ) ) + ( isset( $data['role'] ) ? array( 'role' => $data['role'] ) : array() ) );
				return (array) TenantDb::find_uuid( 'members', $uuid );
			}
		);
		return $this->present( $row );
	}

	public function remove( string $uuid ): array {
		return $this->update( $uuid, array( 'status' => 'suspended' ) );
	}

	private function resolve_user( array $input ): int {
		if ( isset( $input['user_id'] ) && is_numeric( $input['user_id'] ) ) {
			$id = (int) $input['user_id'];
			if ( $id > 0 && false !== get_userdata( $id ) && null !== get_userdata( $id ) ) {
				return $id;
			}
			throw ApiException::invalid( 'User not found.', array( 'fields' => array( 'user_id' ) ) );
		}
		$email = isset( $input['email'] ) && is_string( $input['email'] ) ? sanitize_email( $input['email'] ) : '';
		if ( '' === $email || false === is_email( $email ) ) {
			throw ApiException::invalid( 'user_id or a valid email is required.', array( 'fields' => array( 'user_id', 'email' ) ) );
		}
		$user = get_user_by( 'email', $email );
		if ( $user ) {
			return (int) $user->ID;
		}
		if ( empty( $input['create_user'] ) ) {
			throw ApiException::invalid( 'No WordPress user has this email (send create_user=true to create one).', array( 'fields' => array( 'email' ) ) );
		}
		// Plain `subscriber`: no wp-admin capabilities. The user sets a password through the reset flow.
		$id = wp_insert_user( array( 'user_login' => $email, 'user_email' => $email, 'user_pass' => wp_generate_password( 32, true, true ), 'role' => 'subscriber', 'display_name' => (string) ( $input['display_name'] ?? $email ) ) );
		if ( is_wp_error( $id ) ) {
			throw ApiException::invalid( 'Could not create the user.', array( 'fields' => array( 'email' ) ) );
		}
		if ( function_exists( 'wp_new_user_notification' ) ) {
			wp_new_user_notification( (int) $id, null, 'user' );
		}
		return (int) $id;
	}
}
