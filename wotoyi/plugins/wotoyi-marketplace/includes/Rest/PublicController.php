<?php
/**
 * Public catalogue endpoints.
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Rest;

use Wotoyi\Marketplace\Plugin;
use Wotoyi\Marketplace\Presenter\DealerPresenter;
use Wotoyi\Marketplace\Repository\LeadRepository;
use Wotoyi\Marketplace\Schema\Enums;
use Wotoyi\Marketplace\Search\Filters;
use Wotoyi\Marketplace\Settings;
use Wotoyi\Marketplace\Support\Cache;
use Wotoyi\Marketplace\Support\Crypto;
use Wotoyi\Marketplace\Support\Ip;
use Wotoyi\Marketplace\Support\LeadGuard;
use Wotoyi\Marketplace\Support\RateLimiter;

defined( 'ABSPATH' ) || exit;

final class PublicController {

	private function cached( \WP_REST_Response $r ): \WP_REST_Response {
		$r->header( 'Cache-Control', 'public, max-age=30, s-maxage=60' );
		return $r;
	}

	private function lang( \WP_REST_Request $request ): string {
		$lang = strtolower( (string) $request->get_param( 'lang' ) );
		return in_array( $lang, Enums::LANGS, true ) ? $lang : 'fr';
	}

	public function vehicles( \WP_REST_Request $request ): \WP_REST_Response {
		$res  = Plugin::search()->search( $request->get_query_params() );
		$resp = new \WP_REST_Response(
			array(
				'items'    => $res['items'],
				'total'    => $res['total'],
				'page'     => $res['page'],
				'per_page' => $res['per_page'],
				'pages'    => $res['pages'],
				'sort'     => $res['sort'],
				'filters'  => $res['filters'],
			)
		);
		$resp->header( 'X-WP-Total', (string) $res['total'] );
		$resp->header( 'X-WP-TotalPages', (string) $res['pages'] );
		return $this->cached( $resp );
	}

	public function vehicles_count( \WP_REST_Request $request ): \WP_REST_Response {
		return $this->cached( new \WP_REST_Response( array( 'count' => Plugin::search()->count( $request->get_query_params() ) ) ) );
	}

	public function facets( \WP_REST_Request $request ): \WP_REST_Response {
		return $this->cached( new \WP_REST_Response( Plugin::search()->facets( $request->get_query_params() ) ) );
	}

	/** Vocabularies and parameter names for building the filter UI. */
	public function meta(): \WP_REST_Response {
		return $this->cached(
			new \WP_REST_Response(
				array(
					'filters'   => Filters::parameter_names(),
					'sorts'     => Filters::SORTS,
					'enums'     => array(
						'offer_type'   => Enums::OFFER_TYPE,
						'body_type'    => Enums::BODY_TYPE,
						'fuel'         => Enums::FUEL,
						'transmission' => Enums::TRANSMISSION,
						'drive'        => Enums::DRIVE,
						'condition'    => Enums::CONDITION,
						'customs'      => Enums::CUSTOMS,
						'upholstery'   => Enums::UPHOLSTERY,
						'colour'       => Enums::COLOUR,
						'service_category' => Enums::SERVICE_CATEGORY,
					),
					'equipment' => Enums::equipment(),
					'limits'    => array( 'max_per_page' => Filters::MAX_PER_PAGE, 'max_offset' => Filters::MAX_OFFSET ),
				)
			)
		);
	}

	public function vehicle( \WP_REST_Request $request ) {
		$detail = Plugin::search()->vehicle_detail( (string) $request['ref'], $this->lang( $request ) );
		if ( ! $detail ) {
			return new \WP_Error( 'wotoyi_not_found', 'Vehicle not found.', array( 'status' => 404 ) );
		}
		unset( $detail['internal_id'] );
		return $this->cached( new \WP_REST_Response( $detail ) );
	}

	/** Count a real view (deduplicated per visitor and vehicle for 30 minutes). */
	public function vehicle_view( \WP_REST_Request $request ) {
		$ref = Plugin::search()->published_vehicle_ref( (string) $request['ref'] );
		if ( ! $ref ) {
			return new \WP_Error( 'wotoyi_not_found', 'Vehicle not found.', array( 'status' => 404 ) );
		}
		$key = 'wotoyi_view_' . substr( hash( 'sha256', Ip::hash( Ip::client() ) . '|' . $ref['id'] ), 0, 32 );
		if ( false === get_transient( $key ) ) {
			set_transient( $key, 1, 30 * MINUTE_IN_SECONDS );
			Plugin::vehicles()->increment_views( $ref['id'] );
		}
		return new \WP_REST_Response( array( 'ok' => true ) );
	}

	public function services( \WP_REST_Request $request ): \WP_REST_Response {
		$res  = Plugin::search()->search_services( $request->get_query_params() );
		$resp = new \WP_REST_Response( $res );
		$resp->header( 'X-WP-Total', (string) $res['total'] );
		$resp->header( 'X-WP-TotalPages', (string) $res['pages'] );
		return $this->cached( $resp );
	}

	public function service( \WP_REST_Request $request ) {
		$detail = Plugin::search()->service_detail( (string) $request['ref'], $this->lang( $request ) );
		if ( ! $detail ) {
			return new \WP_Error( 'wotoyi_not_found', 'Service not found.', array( 'status' => 404 ) );
		}
		unset( $detail['internal_id'] );
		return $this->cached( new \WP_REST_Response( $detail ) );
	}

	public function dealers( \WP_REST_Request $request ): \WP_REST_Response {
		$p   = $request->get_query_params();
		$res = Plugin::dealers()->public_list(
			array(
				'q'        => isset( $p['q'] ) && is_string( $p['q'] ) ? mb_substr( sanitize_text_field( $p['q'] ), 0, 60 ) : '',
				'city'     => isset( $p['city'] ) && is_string( $p['city'] ) ? mb_substr( sanitize_text_field( $p['city'] ), 0, 80 ) : '',
				'verified' => isset( $p['verified'] ) && in_array( (string) $p['verified'], array( '1', 'true' ), true ),
				'page'     => isset( $p['page'] ) ? (int) $p['page'] : 1,
				'per_page' => isset( $p['per_page'] ) ? (int) $p['per_page'] : 12,
			)
		);
		$resp = new \WP_REST_Response( array( 'items' => array_map( array( DealerPresenter::class, 'profile' ), $res['items'] ), 'total' => $res['total'] ) );
		$resp->header( 'X-WP-Total', (string) $res['total'] );
		return $this->cached( $resp );
	}

	public function dealer( \WP_REST_Request $request ) {
		$row = Plugin::dealers()->public_by_ref( strtolower( (string) $request['ref'] ) );
		if ( ! $row ) {
			return new \WP_Error( 'wotoyi_not_found', 'Dealer not found.', array( 'status' => 404 ) );
		}
		return $this->cached( new \WP_REST_Response( DealerPresenter::profile( $row ) ) );
	}

	/* ------------------------------ Leads ------------------------------ */

	public function lead_token(): \WP_REST_Response {
		$r = new \WP_REST_Response( array( 'token' => LeadGuard::issue(), 'min_age' => LeadGuard::MIN_AGE ) );
		$r->header( 'Cache-Control', 'no-store' );
		return $r;
	}

	public function lead_create( \WP_REST_Request $request ) {
		$p = $this->body_params( $request );

		// Honeypot: bots fill the hidden field. Answer "ok" so they do not adapt, store nothing.
		if ( ! empty( $p['website'] ) ) {
			return new \WP_REST_Response( array( 'ok' => true ), 201 );
		}
		if ( ! LeadGuard::valid( $p['token'] ?? null ) ) {
			return new \WP_Error( 'wotoyi_challenge_failed', 'Form token missing, too fresh or expired.', array( 'status' => 400 ) );
		}
		$ip_hash = Ip::hash( Ip::client() );
		if ( ! RateLimiter::allow( 'lead:' . $ip_hash, Settings::int( 'lead_rate_limit' ), 600 ) ) {
			return new \WP_Error( 'wotoyi_rate_limited', 'Too many requests.', array( 'status' => 429 ) );
		}

		$consent = in_array( $p['consent'] ?? null, array( true, 1, '1', 'true', 'yes', 'on' ), true );
		if ( ! $consent ) {
			return new \WP_Error( 'wotoyi_consent_required', 'Consent to be contacted is required.', array( 'status' => 422 ) );
		}

		$vehicle = null;
		$service = null;
		if ( ! empty( $p['vehicle'] ) && is_string( $p['vehicle'] ) ) {
			$vehicle = Plugin::search()->published_vehicle_ref( $p['vehicle'] );
		} elseif ( ! empty( $p['service'] ) && is_string( $p['service'] ) ) {
			$service = Plugin::search()->published_service_ref( $p['service'] );
		}
		$target = $vehicle ?? $service;
		if ( ! $target ) {
			return new \WP_Error( 'wotoyi_invalid_target', 'A published vehicle or service is required.', array( 'status' => 422 ) );
		}

		$name  = isset( $p['name'] ) && is_scalar( $p['name'] ) ? mb_substr( trim( sanitize_text_field( (string) $p['name'] ) ), 0, 120 ) : '';
		$phone = isset( $p['phone'] ) && is_scalar( $p['phone'] ) ? preg_replace( '/[^0-9+()\- ]/', '', (string) $p['phone'] ) : '';
		$email = isset( $p['email'] ) && is_scalar( $p['email'] ) ? sanitize_email( (string) $p['email'] ) : '';
		if ( '' !== $phone && strlen( preg_replace( '/\D/', '', $phone ) ) < 6 ) {
			return new \WP_Error( 'wotoyi_invalid_phone', 'Invalid phone number.', array( 'status' => 422 ) );
		}
		if ( '' !== $email && ! is_email( $email ) ) {
			return new \WP_Error( 'wotoyi_invalid_email', 'Invalid email address.', array( 'status' => 422 ) );
		}
		if ( '' === $phone && '' === $email ) {
			return new \WP_Error( 'wotoyi_contact_required', 'A phone number or an email address is required.', array( 'status' => 422 ) );
		}
		$channel = isset( $p['channel'] ) && is_string( $p['channel'] ) && in_array( $p['channel'], LeadRepository::CHANNELS, true ) ? $p['channel'] : 'web';
		$locale  = isset( $p['locale'] ) && is_string( $p['locale'] ) && in_array( $p['locale'], Enums::LANGS, true ) ? $p['locale'] : null;

		$lead = Plugin::leads()->create(
			array(
				'dealer_id'  => $target['dealer_id'],
				'vehicle_id' => $vehicle['id'] ?? null,
				'service_id' => $service['id'] ?? null,
				'user_id'    => is_user_logged_in() ? get_current_user_id() : null,
				'name'       => '' === $name ? null : $name,
				'phone'      => '' === $phone ? null : $phone,
				'email'      => '' === $email ? null : $email,
				'message'    => isset( $p['message'] ) && is_scalar( $p['message'] ) ? mb_substr( sanitize_textarea_field( (string) $p['message'] ), 0, 2000 ) : null,
				'channel'    => $channel,
				'locale'     => $locale,
				'consent'    => true,
				'ip_hash'    => $ip_hash,
			)
		);
		return new \WP_REST_Response( array( 'ok' => true, 'id' => $lead['uuid'] ), 201 );
	}

	/** JSON body if present, otherwise form/query parameters. @return array<string,mixed> */
	private function body_params( \WP_REST_Request $request ): array {
		$json = json_decode( (string) $request->get_body(), true, 8 );
		return is_array( $json ) && ! array_is_list( $json ) ? $json : (array) $request->get_params();
	}
}
