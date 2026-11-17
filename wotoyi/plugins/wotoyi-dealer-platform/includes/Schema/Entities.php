<?php
/**
 * Entity registry: one definition per tenant table (fields, filters, search, sort). Used by the generic service,
 * the schema generator and the REST layer, so a column cannot exist without validation (and vice versa).
 * Field type options: see Schema\Fields. Extra flags: readonly (never writable through the API), finance
 * (hidden without the finance.read capability), required (create only).
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Schema;

defined( 'ABSPATH' ) || exit;

final class Entities {

	public const ROLES        = array( 'owner', 'manager', 'sales', 'mechanic', 'accountant' );
	public const LEAD_STAGES  = array( 'new', 'contacted', 'qualified', 'proposal', 'won', 'lost' );
	public const LEAD_SOURCES = array( 'marketplace', 'dealer_site', 'whatsapp', 'phone', 'walk_in', 'ai', 'referral', 'other' );
	public const DEAL_STATUS  = array( 'draft', 'confirmed', 'completed', 'cancelled' );
	public const PAYMENT      = array( 'cash', 'transfer', 'mobile_money', 'financing', 'trade_in', 'cheque', 'other' );
	public const RENTAL_STATUS = array( 'reserved', 'active', 'returned', 'cancelled' );
	public const TASK_STATUS  = array( 'open', 'done', 'cancelled' );
	public const RELATED      = array( 'customer', 'lead', 'vehicle', 'service', 'deal', 'rental' );
	public const ACTIVITY     = array( 'note', 'call', 'whatsapp', 'email', 'visit', 'stage_change', 'system', 'sync' );

	/** @var array<string,array>|null */
	private static ?array $all = null;

	/** @return array<string,array> */
	public static function all(): array {
		if ( null !== self::$all ) {
			return self::$all;
		}
		$str   = static fn( int $len = 120, array $o = array() ): array => array( 'type' => 'string', 'len' => $len ) + $o;
		$enum  = static fn( array $values, array $o = array() ): array => array( 'type' => 'enum', 'values' => $values ) + $o;
		$int   = static fn( int $min = 0, int $max = 9999999999999, array $o = array() ): array => array( 'type' => 'int', 'min' => $min, 'max' => $max ) + $o;
		$ref   = static fn( string $entity, array $o = array() ): array => array( 'type' => 'ref', 'entity' => $entity ) + $o;
		$plain = array( 'type' => 'text', 'plain' => true );

		self::$all = array(
			'customers' => array(
				'caps'        => 'customers',
				'soft_delete' => true,
				'fields'      => array(
					'name'        => $str( 120, array( 'required' => true ) ),
					'phone'       => array( 'type' => 'phone' ),
					'whatsapp'    => array( 'type' => 'phone' ),
					'email'       => array( 'type' => 'email' ),
					'city'        => $str( 80 ),
					'country'     => array( 'type' => 'country' ),
					'address'     => $str( 255 ),
					'source'      => $enum( array_merge( self::LEAD_SOURCES, array( 'website' ) ) ),
					'tags'        => array( 'type' => 'json_list', 'item' => 'string', 'max_items' => 20 ),
					'notes'       => $plain,
					'consent'     => array( 'type' => 'bool' ),
					'assigned_to' => $ref( 'members' ),
				),
				'filters'     => array( 'source', 'assigned_to', 'city', 'country' ),
				'search'      => array( 'name', 'phone', 'whatsapp', 'email' ),
				'sortable'    => array( 'created_at', 'updated_at', 'name' ),
				'keys'        => array( 'KEY tenant_phone (tenant_id,phone)', 'KEY tenant_email (tenant_id,email)', 'KEY tenant_name (tenant_id,name)' ),
			),
			'leads' => array(
				'caps'        => 'leads',
				'soft_delete' => true,
				'fields'      => array(
					'customer_uuid'      => $ref( 'customers' ),
					'source'             => $enum( self::LEAD_SOURCES ),
					'external_id'        => $str( 64, array( 'readonly' => true ) ),
					'vehicle_uuid'       => $ref( 'vehicles' ),
					'service_uuid'       => $ref( 'services' ),
					'name'               => $str( 120 ),
					'phone'              => array( 'type' => 'phone' ),
					'email'              => array( 'type' => 'email' ),
					'message'            => $plain,
					'channel'            => $str( 16 ),
					'locale'             => $str( 8 ),
					'stage'              => $enum( self::LEAD_STAGES ),
					'assigned_to'        => $ref( 'members' ),
					'value'              => $int(),
					'currency'           => array( 'type' => 'currency' ),
					'lost_reason'        => $str( 255 ),
					'next_follow_up_at'  => array( 'type' => 'datetime' ),
					'consent'            => array( 'type' => 'bool' ),
				),
				'filters'     => array( 'stage', 'source', 'assigned_to', 'vehicle_uuid', 'customer_uuid' ),
				'search'      => array( 'name', 'phone', 'email', 'message' ),
				'sortable'    => array( 'created_at', 'updated_at', 'next_follow_up_at', 'value' ),
				'keys'        => array( 'UNIQUE KEY tenant_external (tenant_id,external_id)', 'KEY tenant_stage (tenant_id,stage)', 'KEY tenant_followup (tenant_id,next_follow_up_at)' ),
			),
			'deals' => array(
				'caps'        => 'deals',
				'soft_delete' => false,
				'fields'      => array(
					'vehicle_uuid'   => $ref( 'vehicles', array( 'required' => true ) ),
					'customer_uuid'  => $ref( 'customers' ),
					'lead_uuid'      => $ref( 'leads' ),
					'amount'         => $int( 0, 9999999999999, array( 'required' => true ) ),
					'currency'       => array( 'type' => 'currency' ),
					'discount'       => $int(),
					'trade_in_value' => $int(),
					'payment_method' => $enum( self::PAYMENT ),
					'status'         => $enum( self::DEAL_STATUS ),
					'sold_by'        => $ref( 'members' ),
					'sold_at'        => array( 'type' => 'datetime', 'readonly' => true ),
					'reference'      => $str( 40 ),
					'notes'          => $plain,
				),
				'filters'     => array( 'status', 'vehicle_uuid', 'customer_uuid', 'sold_by' ),
				'search'      => array( 'reference', 'notes' ),
				'sortable'    => array( 'created_at', 'sold_at', 'amount' ),
				'keys'        => array( 'KEY tenant_status (tenant_id,status)', 'KEY tenant_sold (tenant_id,sold_at)', 'KEY tenant_vehicle (tenant_id,vehicle_uuid)' ),
			),
			'rentals' => array(
				'caps'        => 'rentals',
				'soft_delete' => false,
				'fields'      => array(
					'vehicle_uuid'    => $ref( 'vehicles', array( 'required' => true ) ),
					'customer_uuid'   => $ref( 'customers', array( 'required' => true ) ),
					'start_date'      => array( 'type' => 'date', 'required' => true ),
					'end_date'        => array( 'type' => 'date', 'required' => true ),
					'daily_rate'      => $int(),
					'total'           => $int(),
					'deposit'         => $int(),
					'currency'        => array( 'type' => 'currency' ),
					'status'          => $enum( self::RENTAL_STATUS ),
					'pickup_location' => $str( 160 ),
					'notes'           => $plain,
				),
				'filters'     => array( 'status', 'vehicle_uuid', 'customer_uuid' ),
				'search'      => array( 'pickup_location', 'notes' ),
				'sortable'    => array( 'start_date', 'end_date', 'created_at' ),
				'keys'        => array( 'KEY tenant_vehicle_dates (tenant_id,vehicle_uuid,start_date,end_date)', 'KEY tenant_status (tenant_id,status)' ),
			),
			'employees' => array(
				'caps'        => 'employees',
				'soft_delete' => true,
				'fields'      => array(
					'name'        => $str( 120, array( 'required' => true ) ),
					'job_title'   => $str( 80 ),
					'phone'       => array( 'type' => 'phone' ),
					'email'       => array( 'type' => 'email' ),
					'hire_date'   => array( 'type' => 'date' ),
					'status'      => $enum( array( 'active', 'inactive' ) ),
					'member_uuid' => $ref( 'members' ),
					'notes'       => $plain,
				),
				'filters'     => array( 'status', 'job_title' ),
				'search'      => array( 'name', 'job_title', 'phone', 'email' ),
				'sortable'    => array( 'name', 'created_at', 'hire_date' ),
				'keys'        => array( 'KEY tenant_status (tenant_id,status)' ),
			),
			'tasks' => array(
				'caps'        => 'tasks',
				'soft_delete' => false,
				'fields'      => array(
					'title'        => $str( 160, array( 'required' => true ) ),
					'description'  => $plain,
					'due_at'       => array( 'type' => 'datetime' ),
					'status'       => $enum( self::TASK_STATUS ),
					'priority'     => $enum( array( 'low', 'normal', 'high' ) ),
					'assigned_to'  => $ref( 'members' ),
					'related_type' => $enum( self::RELATED ),
					'related_uuid' => array( 'type' => 'ref' ),
					'completed_at' => array( 'type' => 'datetime', 'readonly' => true ),
				),
				'filters'     => array( 'status', 'priority', 'assigned_to', 'related_type', 'related_uuid' ),
				'search'      => array( 'title', 'description' ),
				'sortable'    => array( 'due_at', 'created_at', 'priority' ),
				'keys'        => array( 'KEY tenant_status_due (tenant_id,status,due_at)', 'KEY tenant_related (tenant_id,related_uuid)' ),
			),
			'activities' => array(
				'caps'        => 'activities',
				'soft_delete' => false,
				'fields'      => array(
					'entity_type' => $enum( self::RELATED, array( 'required' => true ) ),
					'entity_uuid' => array( 'type' => 'ref', 'required' => true ),
					'type'        => $enum( self::ACTIVITY ),
					'body'        => array( 'type' => 'text', 'plain' => true ),
					'author_id'   => $int( 0, 4294967295, array( 'readonly' => true ) ),
				),
				'filters'     => array( 'entity_type', 'entity_uuid', 'type' ),
				'search'      => array( 'body' ),
				'sortable'    => array( 'created_at' ),
				'keys'        => array( 'KEY tenant_entity (tenant_id,entity_uuid,created_at)' ),
			),
		);
		return self::$all;
	}

	/** @return array<string,mixed> */
	public static function get( string $name ): array {
		$all = self::all();
		if ( ! isset( $all[ $name ] ) ) {
			throw new \InvalidArgumentException( 'unknown_entity:' . $name );
		}
		return $all[ $name ];
	}

	/** Entities that other entities may reference, with the table that proves existence. */
	public static function ref_table( string $entity ): string {
		return $entity;
	}
}
