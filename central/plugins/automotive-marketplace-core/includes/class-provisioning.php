<?php
/**
 * Dealer website provisioning jobs.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Provisioning {

	/** @return Provisioning_Provider[] */
	public static function providers() {
		$providers = apply_filters( 'amc_provisioning_providers', array( 'manual' => new Manual_Provisioning_Provider() ) );
		return array_filter(
			$providers,
			static function ( $provider ) {
				return $provider instanceof Provisioning_Provider;
			}
		);
	}

	public static function provider( $id ) {
		$providers = self::providers();
		return isset( $providers[ $id ] ) ? $providers[ $id ] : $providers['manual'];
	}

	/**
	 * Open a job for a dealer (once).
	 *
	 * @param int    $dealer_id      Dealer.
	 * @param string $website_option new_domain|existing_domain|later.
	 * @param string $domain         Domain chosen in the application.
	 */
	public static function create_job( $dealer_id, $website_option = 'later', $domain = '' ) {
		global $wpdb;
		$existing = self::for_dealer( $dealer_id );
		if ( $existing && 'cancelled' !== $existing->status ) {
			return (int) $existing->id;
		}
		$provider = self::provider( (string) Settings::get( 'provisioning_provider' ) );
		$steps    = array();
		foreach ( array_keys( $provider->steps() ) as $key ) {
			$steps[ $key ] = array(
				'done' => false,
				'at'   => null,
				'by'   => 0,
			);
		}
		$now = current_time( 'mysql', true );
		$wpdb->insert(
			Database::table( 'provisioning_jobs' ),
			array(
				'dealer_id'      => absint( $dealer_id ),
				'provider'       => $provider->id(),
				'website_option' => sanitize_key( $website_option ),
				'domain'         => sanitize_text_field( $domain ),
				'steps'          => wp_json_encode( $steps ),
				'status'         => 'queued',
				'notes'          => '',
				'created_at'     => $now,
				'updated_at'     => $now,
			)
		);
		$id = (int) $wpdb->insert_id;
		Audit::log( 'provisioning.created', 'provisioning_job', $id );
		return $id;
	}

	public static function hydrate( $row ) {
		if ( ! $row ) {
			return null;
		}
		$steps     = json_decode( (string) $row->steps, true );
		$row->steps = is_array( $steps ) ? $steps : array();
		return $row;
	}

	public static function get( $id ) {
		global $wpdb;
		$j = Database::table( 'provisioning_jobs' );
		$d = Database::table( 'dealers' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return self::hydrate( $wpdb->get_row( $wpdb->prepare( "SELECT j.*, d.business_name FROM $j j LEFT JOIN $d d ON d.id = j.dealer_id WHERE j.id = %d", absint( $id ) ) ) );
	}

	public static function for_dealer( $dealer_id ) {
		global $wpdb;
		$table = Database::table( 'provisioning_jobs' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return self::hydrate( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE dealer_id = %d ORDER BY id DESC LIMIT 1", absint( $dealer_id ) ) ) );
	}

	public static function list( $status = '' ) {
		global $wpdb;
		$j     = Database::table( 'provisioning_jobs' );
		$d     = Database::table( 'dealers' );
		$where = $status ? $wpdb->prepare( 'WHERE j.status = %s', $status ) : '';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT j.*, d.business_name FROM $j j LEFT JOIN $d d ON d.id = j.dealer_id $where ORDER BY CASE j.status WHEN 'in_progress' THEN 0 WHEN 'queued' THEN 1 WHEN 'done' THEN 2 ELSE 3 END, j.id DESC LIMIT 200" );
		return array_map( array( __CLASS__, 'hydrate' ), $rows ? $rows : array() );
	}

	public static function open_count() {
		global $wpdb;
		$table = Database::table( 'provisioning_jobs' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status IN ('queued','in_progress')" );
	}

	/** @return int[] [ done, total ] */
	public static function progress( $job ) {
		$done = 0;
		foreach ( $job->steps as $step ) {
			$done += empty( $step['done'] ) ? 0 : 1;
		}
		return array( $done, count( $job->steps ) );
	}

	public static function set_step( $job_id, $key, $done ) {
		global $wpdb;
		$job = self::get( $job_id );
		if ( ! $job || ! isset( $job->steps[ $key ] ) || 'cancelled' === $job->status ) {
			return new \WP_Error( 'amc_job', __( 'Unknown step.', 'automotive-marketplace-core' ) );
		}
		$job->steps[ $key ] = array(
			'done' => (bool) $done,
			'at'   => $done ? current_time( 'mysql', true ) : null,
			'by'   => $done ? get_current_user_id() : 0,
		);
		list( $count, $total ) = self::progress( $job );
		$status                = $count >= $total ? 'done' : ( $count ? 'in_progress' : 'queued' );
		$wpdb->update(
			Database::table( 'provisioning_jobs' ),
			array(
				'steps'        => wp_json_encode( $job->steps ),
				'status'       => $status,
				'updated_at'   => current_time( 'mysql', true ),
				'completed_at' => 'done' === $status ? current_time( 'mysql', true ) : null,
			),
			array( 'id' => (int) $job_id )
		);
		Audit::log( 'provisioning.step', 'provisioning_job', $job_id, array( 'step' => $key, 'done' => (bool) $done ) );
		if ( 'done' === $status && 'done' !== $job->status ) {
			self::on_complete( $job_id );
		}
		return true;
	}

	public static function save_details( $job_id, $site_url, $admin_url, $notes ) {
		global $wpdb;
		$wpdb->update(
			Database::table( 'provisioning_jobs' ),
			array(
				'site_url'   => esc_url_raw( $site_url, array( 'http', 'https' ) ),
				'admin_url'  => esc_url_raw( $admin_url, array( 'http', 'https' ) ),
				'notes'      => sanitize_textarea_field( $notes ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $job_id ) )
		);
		Audit::log( 'provisioning.updated', 'provisioning_job', $job_id );
	}

	private static function on_complete( $job_id ) {
		global $wpdb;
		$job    = self::get( $job_id );
		$dealer = Memberships::get_dealer( $job->dealer_id );
		if ( $dealer && $job->site_url && ! $dealer->website_url ) {
			$wpdb->update( Database::table( 'dealers' ), array( 'website_url' => $job->site_url ), array( 'id' => (int) $dealer->id ) );
		}
		Notifications::website_ready( $job_id );
		Audit::log( 'provisioning.completed', 'provisioning_job', $job_id );
	}
}
