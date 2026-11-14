<?php
/**
 * CRM actions available to the AutoBuzy AI assistant (WhatsApp).
 *
 * POST /wp-json/dealer/v1/ai/execute  { user_ref, tool, args, confirm }
 *
 * Security model — the marketplace is never trusted blindly:
 * - the request must be signed with this website's connection secret;
 * - the action runs as the team member registered with that WhatsApp number
 *   (user_ref), who must still be an active member: their own CRM
 *   permissions apply, exactly as in the CRM screens;
 * - sensitive actions are refused unless confirm=true. Without it, a preview
 *   is returned and the marketplace asks the person to confirm on WhatsApp;
 * - every action is written in the CRM activity log.
 *
 * @package AutomotiveMarketplaceConnector
 */

namespace ACX;

use ADC\Vehicles;
use ADC\Leads;
use ADC\Rentals;
use ADC\Labels;

defined( 'ABSPATH' ) || exit;

final class Ai_Endpoint {

	/** Tools that change important data: confirmation required. */
	const SENSITIVE = array( 'update_vehicle_status', 'update_vehicle_price', 'update_lead_status' );

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			'dealer/v1',
			'/ai/execute',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'execute' ),
				'permission_callback' => array( Connection::class, 'verify' ),
			)
		);
	}

	public static function execute( \WP_REST_Request $request ) {
		$user_id = absint( $request->get_param( 'user_ref' ) );
		$tool    = sanitize_key( (string) $request->get_param( 'tool' ) );
		$args    = (array) $request->get_param( 'args' );
		$confirm = true === $request->get_param( 'confirm' );

		$user = $user_id ? get_userdata( $user_id ) : null;
		// The number must still belong to an active team member.
		if ( ! $user || ! \ADC\Team::is_member( $user_id ) || ! user_can( $user, 'adc_access' ) || '' === (string) get_user_meta( $user_id, Contacts::META, true ) ) {
			return new \WP_Error( 'acx_user', 'This WhatsApp number is no longer linked to an active team member.', array( 'status' => 403 ) );
		}
		wp_set_current_user( $user_id );

		if ( ! method_exists( __CLASS__, 'tool_' . $tool ) ) {
			return new \WP_Error( 'acx_tool', 'Unknown tool.', array( 'status' => 400 ) );
		}
		$sensitive = in_array( $tool, self::SENSITIVE, true );
		$result    = call_user_func( array( __CLASS__, 'tool_' . $tool ), $args, $sensitive && ! $confirm );
		if ( is_wp_error( $result ) ) {
			return rest_ensure_response( array( 'error' => $result->get_error_message() ) );
		}
		if ( $sensitive && $confirm ) {
			\ADC\Activity::log( 'ai_action', 'user', $user_id, 'WhatsApp: ' . ( $result['done'] ?? $tool ) );
		}
		return rest_ensure_response( $result );
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	private static function deny() {
		return new \WP_Error( 'acx_forbidden', __( 'You do not have permission to do this.', 'automotive-marketplace-connector' ) );
	}

	private static function vehicle_row( $v ) {
		return array(
			'vehicle_id'   => (int) $v->id,
			'title'        => $v->title,
			'status'       => $v->status,
			'type'         => $v->listing_type,
			'price'        => adc_money( $v->price_amount, $v->currency ) . ( 'total' !== $v->price_period ? ' / ' . Labels::label( 'price_periods', $v->price_period ) : '' ),
			'price_amount' => (int) $v->price_amount,
			'year'         => (int) $v->year,
			'mileage_km'   => (int) $v->mileage_km,
			'marketplace'  => (bool) $v->publish_to_marketplace,
		);
	}

	private static function preview( $summary ) {
		return array(
			'requires_confirmation' => true,
			'summary'               => $summary,
		);
	}

	private static function local_to_utc( $value ) {
		$ts = strtotime( (string) $value . ' ' . wp_timezone_string() );
		return $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : '';
	}

	/* ------------------------------------------------------------------ */
	/* Read tools                                                          */
	/* ------------------------------------------------------------------ */

	private static function tool_crm_summary() {
		$counts = Vehicles::counts();
		$leads  = Leads::counts_by_status();
		$out    = array(
			'business'          => adc_business()->name,
			'you'               => wp_get_current_user()->display_name,
			'vehicles'          => $counts,
			'leads_by_status'   => $leads,
			'followups_due'     => count( Leads::due_followups() ),
			'rentals_active'    => count( Rentals::search( array( 'view' => 'active' ) ) ),
			'rentals_upcoming'  => count( Rentals::search( array( 'view' => 'upcoming' ) ) ),
			'today'             => wp_date( 'l j F Y' ),
		);
		if ( current_user_can( 'adc_view_reports' ) ) {
			$out['rental_revenue_this_month'] = adc_money( Rentals::revenue_this_month() );
		}
		return $out;
	}

	private static function tool_search_vehicles( array $a ) {
		$args = array( 'limit' => 50 );
		if ( ! empty( $a['query'] ) ) {
			$args['q'] = sanitize_text_field( (string) $a['query'] );
		}
		if ( ! empty( $a['status'] ) ) {
			$args['status'] = sanitize_key( (string) $a['status'] );
		}
		if ( ! empty( $a['listing_type'] ) ) {
			$args['type'] = sanitize_key( (string) $a['listing_type'] );
		}
		$rows = Vehicles::search( $args );
		if ( ! empty( $a['max_price'] ) ) {
			$max  = absint( $a['max_price'] );
			$rows = array_filter( $rows, static fn( $v ) => $v->price_amount && $v->price_amount <= $max );
		}
		$rows = array_slice( array_values( $rows ), 0, min( 15, max( 1, absint( $a['limit'] ?? 10 ) ) ) );
		return array(
			'count'    => count( $rows ),
			'vehicles' => array_map( array( __CLASS__, 'vehicle_row' ), $rows ),
		);
	}

	private static function tool_get_vehicle( array $a ) {
		$v = Vehicles::get( absint( $a['vehicle_id'] ?? 0 ) );
		if ( ! $v ) {
			return new \WP_Error( 'acx', __( 'Vehicle not found.', 'automotive-marketplace-connector' ) );
		}
		$out = self::vehicle_row( $v ) + array(
			'brand'        => $v->brand,
			'model'        => $v->model,
			'fuel'         => Labels::label( 'fuel_types', $v->fuel_type ),
			'transmission' => Labels::label( 'transmissions', $v->transmission ),
			'color'        => $v->color,
			'description'  => mb_substr( (string) $v->description, 0, 600 ),
			'url'          => in_array( $v->status, Vehicles::PUBLIC_STATUSES, true ) ? adc_vehicle_url( $v ) : '',
		);
		if ( 'rent' === $v->listing_type ) {
			$out['next_rentals'] = array_map(
				static fn( $r ) => array(
					'from'     => adc_date( $r->starts_at, true ),
					'to'       => adc_date( $r->ends_at, true ),
					'status'   => $r->status,
				),
				array_slice( Rentals::search( array( 'vehicle_id' => $v->id, 'view' => 'upcoming' ) ), 0, 5 )
			);
		}
		return $out;
	}

	private static function tool_list_leads( array $a ) {
		$args = array( 'limit' => min( 20, max( 1, absint( $a['limit'] ?? 10 ) ) ) );
		if ( ! empty( $a['status'] ) ) {
			$args['status'] = sanitize_key( (string) $a['status'] );
		} else {
			$args['open_only'] = true;
		}
		if ( ! empty( $a['query'] ) ) {
			$args['q'] = sanitize_text_field( (string) $a['query'] );
		}
		$rows = Leads::search( $args );
		return array(
			'count' => count( $rows ),
			'leads' => array_map(
				static fn( $l ) => array(
					'lead_id'   => (int) $l->id,
					'name'      => $l->name,
					'phone'     => $l->phone,
					'status'    => $l->status,
					'source'    => $l->source,
					'vehicle'   => $l->vehicle_title,
					'message'   => mb_substr( (string) $l->message, 0, 200 ),
					'follow_up' => $l->follow_up_at ? adc_date( $l->follow_up_at, true ) : '',
					'created'   => adc_date( $l->created_at, true ),
				),
				$rows
			),
		);
	}

	private static function tool_rentals_overview( array $a ) {
		if ( ! current_user_can( 'adc_manage_rentals' ) && ! current_user_can( 'adc_view_reports' ) ) {
			return self::deny();
		}
		$view = in_array( $a['view'] ?? '', array( 'active', 'upcoming' ), true ) ? $a['view'] : 'upcoming';
		$rows = array_slice( Rentals::search( array( 'view' => $view ) ), 0, 15 );
		return array(
			'view'    => $view,
			'rentals' => array_map(
				static fn( $r ) => array(
					'rental_id' => (int) $r->id,
					'vehicle'   => $r->vehicle_title,
					'customer'  => $r->customer_name,
					'from'      => adc_date( $r->starts_at, true ),
					'to'        => adc_date( $r->ends_at, true ),
					'status'    => $r->status,
					'total'     => adc_money( $r->total_amount ),
				),
				$rows
			),
		);
	}

	private static function tool_check_availability( array $a ) {
		$v     = Vehicles::get( absint( $a['vehicle_id'] ?? 0 ) );
		$start = self::local_to_utc( $a['start'] ?? '' );
		$end   = self::local_to_utc( $a['end'] ?? '' );
		if ( ! $v || ! $start || ! $end || $end <= $start ) {
			return new \WP_Error( 'acx', __( 'Give a vehicle and a valid period (start before end).', 'automotive-marketplace-connector' ) );
		}
		$conflicts = Rentals::conflicts( $v->id, $start, $end );
		return array(
			'vehicle'   => $v->title,
			'available' => ! $conflicts && in_array( $v->status, array( 'published', 'reserved', 'rented' ), true ),
			'status'    => $v->status,
			'conflicts' => array_map( static fn( $r ) => array( 'from' => adc_date( $r->starts_at, true ), 'to' => adc_date( $r->ends_at, true ) ), $conflicts ),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Write tools                                                         */
	/* ------------------------------------------------------------------ */

	private static function tool_create_lead( array $a ) {
		if ( ! current_user_can( 'adc_manage_leads' ) ) {
			return self::deny();
		}
		$id = Leads::save(
			array(
				'name'       => (string) ( $a['name'] ?? '' ),
				'phone'      => (string) ( $a['phone'] ?? '' ),
				'email'      => (string) ( $a['email'] ?? '' ),
				'message'    => (string) ( $a['note'] ?? '' ),
				'vehicle_id' => absint( $a['vehicle_id'] ?? 0 ),
				'interest'   => (string) ( $a['interest'] ?? 'sale' ),
				'source'     => 'whatsapp',
			)
		);
		return is_wp_error( $id ) ? $id : array(
			'lead_id' => $id,
			'done'    => __( 'Lead created.', 'automotive-marketplace-connector' ),
			'url'     => adc_manager_url( 'leads', $id ),
		);
	}

	private static function tool_add_lead_note( array $a ) {
		$lead = Leads::get( absint( $a['lead_id'] ?? 0 ) );
		if ( ! $lead || ! Leads::can_see( $lead ) || ! current_user_can( 'adc_manage_leads' ) ) {
			return self::deny();
		}
		$note = mb_substr( sanitize_textarea_field( (string) ( $a['note'] ?? '' ) ), 0, 2000 );
		if ( '' === $note ) {
			return new \WP_Error( 'acx', __( 'The note is empty.', 'automotive-marketplace-connector' ) );
		}
		Leads::note( $lead->id, 'note', $note );
		return array( 'done' => __( 'Note added.', 'automotive-marketplace-connector' ) );
	}

	private static function tool_update_lead_status( array $a, $preview ) {
		$lead   = Leads::get( absint( $a['lead_id'] ?? 0 ) );
		$status = sanitize_key( (string) ( $a['status'] ?? '' ) );
		if ( ! $lead || ! Leads::can_see( $lead ) || ! current_user_can( 'adc_manage_leads' ) ) {
			return self::deny();
		}
		if ( ! isset( Labels::lead_statuses()[ $status ] ) ) {
			return new \WP_Error( 'acx', __( 'Unknown status.', 'automotive-marketplace-connector' ) );
		}
		/* translators: 1: lead name, 2: new status */
		$summary = sprintf( __( 'Lead %1$s → %2$s', 'automotive-marketplace-connector' ), $lead->name, Labels::label( 'lead_statuses', $status ) );
		if ( $preview ) {
			return self::preview( $summary );
		}
		$done = Leads::set_status( $lead->id, $status, (string) ( $a['reason'] ?? '' ) );
		return is_wp_error( $done ) ? $done : array( 'done' => $summary );
	}

	private static function tool_update_vehicle_status( array $a, $preview ) {
		$v      = Vehicles::get( absint( $a['vehicle_id'] ?? 0 ) );
		$status = sanitize_key( (string) ( $a['status'] ?? '' ) );
		if ( ! $v ) {
			return new \WP_Error( 'acx', __( 'Vehicle not found.', 'automotive-marketplace-connector' ) );
		}
		if ( ! in_array( $status, array( 'published', 'reserved', 'sold', 'rented', 'archived' ), true ) ) {
			return new \WP_Error( 'acx', __( 'Unknown status.', 'automotive-marketplace-connector' ) );
		}
		/* translators: 1: vehicle, 2: new status */
		$summary = sprintf( __( '%1$s → %2$s', 'automotive-marketplace-connector' ), $v->title, Labels::label( 'vehicle_statuses', $status ) );
		if ( $preview ) {
			return self::preview( $summary );
		}
		$done = Vehicles::set_status( $v->id, $status );
		return is_wp_error( $done ) ? $done : array( 'done' => $summary );
	}

	private static function tool_update_vehicle_price( array $a, $preview ) {
		$v      = Vehicles::get( absint( $a['vehicle_id'] ?? 0 ) );
		$amount = absint( $a['price_amount'] ?? 0 );
		if ( ! $v ) {
			return new \WP_Error( 'acx', __( 'Vehicle not found.', 'automotive-marketplace-connector' ) );
		}
		if ( ! current_user_can( 'adc_edit_prices' ) ) {
			return self::deny();
		}
		if ( $amount < 1 ) {
			return new \WP_Error( 'acx', __( 'Please enter a valid price.', 'automotive-marketplace-connector' ) );
		}
		/* translators: 1: vehicle, 2: old price, 3: new price */
		$summary = sprintf( __( '%1$s: price %2$s → %3$s', 'automotive-marketplace-connector' ), $v->title, adc_money( $v->price_amount, $v->currency ), adc_money( $amount, $v->currency ) );
		if ( $preview ) {
			return self::preview( $summary );
		}
		$done = Vehicles::set_price( $v->id, $amount );
		return is_wp_error( $done ) ? $done : array( 'done' => $summary );
	}
}
