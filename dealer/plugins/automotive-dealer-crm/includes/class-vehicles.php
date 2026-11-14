<?php
/**
 * Vehicle inventory — the dealer CRM is the source of truth.
 *
 * Every change fires "adc_vehicle_changed" ( $vehicle_id, $event ) so the
 * Marketplace Connector (Phase 4) can synchronise public data.
 *
 * @package AutomotiveDealerCrm
 */

namespace ADC;

defined( 'ABSPATH' ) || exit;

final class Vehicles {

	const PUBLIC_STATUSES = array( 'published', 'reserved' );

	/* ------------------------------------------------------------------ */
	/* Reads                                                               */
	/* ------------------------------------------------------------------ */

	public static function hydrate( $row ) {
		if ( ! $row ) {
			return null;
		}
		foreach ( array( 'id', 'year', 'mileage_km', 'price_amount', 'purchase_price', 'is_featured', 'publish_to_marketplace', 'thumbnail_id' ) as $key ) {
			$row->$key = (int) $row->$key;
		}
		$features      = json_decode( (string) $row->features, true );
		$row->features = is_array( $features ) ? $features : array();
		return $row;
	}

	public static function get( $id ) {
		global $wpdb;
		$table = Database::table( 'vehicles' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return self::hydrate( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", absint( $id ) ) ) );
	}

	/** Public vehicle by slug (drafts, pending and archived are never public). */
	public static function get_public( $slug ) {
		global $wpdb;
		$table = Database::table( 'vehicles' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE slug = %s AND status IN ('published','reserved','sold','rented')", sanitize_title( $slug ) ) );
		return self::hydrate( $row );
	}

	/** Attachment ids of the gallery, in order. */
	public static function media( $vehicle_id ) {
		global $wpdb;
		$table = Database::table( 'vehicle_media' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT attachment_id FROM $table WHERE vehicle_id = %d ORDER BY position ASC, id ASC", absint( $vehicle_id ) ) ) );
	}

	public static function thumbnail_url( $vehicle, $size = 'medium_large' ) {
		return $vehicle->thumbnail_id ? (string) wp_get_attachment_image_url( $vehicle->thumbnail_id, $size ) : '';
	}

	/**
	 * CRM list.
	 *
	 * @param array $args { status, type, q, limit }.
	 */
	public static function search( array $args = array() ) {
		global $wpdb;
		$table  = Database::table( 'vehicles' );
		$where  = array( '1=1' );
		$params = array();
		if ( ! empty( $args['status'] ) && isset( Labels::vehicle_statuses()[ $args['status'] ] ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		} elseif ( empty( $args['include_archived'] ) ) {
			$where[] = "status <> 'archived'";
		}
		if ( ! empty( $args['type'] ) && isset( Labels::listing_types()[ $args['type'] ] ) ) {
			$where[]  = 'listing_type = %s';
			$params[] = $args['type'];
		}
		if ( ! empty( $args['statuses'] ) ) {
			$in    = array_values( array_intersect( (array) $args['statuses'], array_keys( Labels::vehicle_statuses() ) ) );
			$where[] = $in ? "status IN ('" . implode( "','", $in ) . "')" : '1=0';
		}
		if ( ! empty( $args['q'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['q'] ) . '%';
			$where[]  = '(title LIKE %s OR vin LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}
		$params[] = isset( $args['limit'] ) ? absint( $args['limit'] ) : 200;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE " . implode( ' AND ', $where ) . ' ORDER BY updated_at DESC LIMIT %d', $params ) );
		return array_map( array( __CLASS__, 'hydrate' ), $rows ? $rows : array() );
	}

	public static function counts() {
		global $wpdb;
		$table = Database::table( 'vehicles' );
		$out   = array_fill_keys( array_keys( Labels::vehicle_statuses() ), 0 );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM $table GROUP BY status" ) as $row ) {
			$out[ $row->status ] = (int) $row->n;
		}
		return $out;
	}

	/**
	 * Public inventory search.
	 *
	 * @param array $raw  Untrusted filters (type, brand, body, fuel, transmission, price_max, year_min, q, sort).
	 * @param int   $page Page.
	 * @param int   $per  Per page.
	 */
	public static function search_public( array $raw, $page = 1, $per = 12 ) {
		global $wpdb;
		$table  = Database::table( 'vehicles' );
		$where  = array( "status IN ('published','reserved')" );
		$params = array();
		$f      = self::public_filters( $raw );
		$map    = array(
			'type'         => 'listing_type',
			'body'         => 'body_type',
			'fuel'         => 'fuel_type',
			'transmission' => 'transmission',
		);
		foreach ( $map as $key => $column ) {
			if ( isset( $f[ $key ] ) ) {
				$where[]  = "$column = %s";
				$params[] = $f[ $key ];
			}
		}
		if ( isset( $f['brand'] ) ) {
			$where[]  = 'brand = %s';
			$params[] = $f['brand'];
		}
		if ( isset( $f['price_max'] ) ) {
			$where[]  = 'price_amount <= %d';
			$params[] = $f['price_max'];
		}
		if ( isset( $f['year_min'] ) ) {
			$where[]  = 'year >= %d';
			$params[] = $f['year_min'];
		}
		if ( isset( $f['featured'] ) ) {
			$where[] = 'is_featured = 1';
		}
		if ( isset( $f['q'] ) ) {
			foreach ( array_slice( preg_split( '/\s+/', $f['q'] ), 0, 5 ) as $word ) {
				$where[]  = '(title LIKE %s OR color LIKE %s)';
				$like     = '%' . $wpdb->esc_like( $word ) . '%';
				$params[] = $like;
				$params[] = $like;
			}
		}
		$orders = array(
			'latest'      => 'published_at DESC, id DESC',
			'price_asc'   => 'price_amount ASC, id DESC',
			'price_desc'  => 'price_amount DESC, id DESC',
			'mileage_asc' => 'mileage_km ASC, id DESC',
		);
		$order  = isset( $f['sort'] ) ? $orders[ $f['sort'] ] : 'is_featured DESC, published_at DESC, id DESC';
		$sql    = "FROM $table WHERE " . implode( ' AND ', $where );
		$per    = max( 1, min( 48, (int) $per ) );
		$page   = max( 1, (int) $page );
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( $params ? $wpdb->prepare( "SELECT COUNT(*) $sql", $params ) : "SELECT COUNT(*) $sql" );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * $sql ORDER BY $order LIMIT %d OFFSET %d", array_merge( $params, array( $per, ( $page - 1 ) * $per ) ) ) );
		// phpcs:enable
		return array(
			'items'   => array_map( array( __CLASS__, 'hydrate' ), $rows ? $rows : array() ),
			'total'   => $total,
			'pages'   => (int) ceil( $total / $per ),
			'page'    => $page,
			'filters' => $f,
		);
	}

	public static function public_filters( array $raw ) {
		$raw = wp_unslash( $raw );
		$f   = array();
		$enums = array(
			'type'         => array_keys( Labels::listing_types() ),
			'body'         => array_keys( Labels::body_types() ),
			'fuel'         => array_keys( Labels::fuel_types() ),
			'transmission' => array_keys( Labels::transmissions() ),
			'sort'         => array( 'latest', 'price_asc', 'price_desc', 'mileage_asc' ),
		);
		foreach ( $enums as $key => $allowed ) {
			if ( isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) && in_array( (string) $raw[ $key ], $allowed, true ) ) {
				$f[ $key ] = (string) $raw[ $key ];
			}
		}
		if ( isset( $raw['brand'] ) && is_scalar( $raw['brand'] ) && '' !== $raw['brand'] ) {
			$f['brand'] = mb_substr( sanitize_text_field( (string) $raw['brand'] ), 0, 80 );
		}
		foreach ( array( 'price_max', 'year_min' ) as $key ) {
			if ( isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) && absint( preg_replace( '/\D/', '', (string) $raw[ $key ] ) ) > 0 ) {
				$f[ $key ] = absint( preg_replace( '/\D/', '', (string) $raw[ $key ] ) );
			}
		}
		if ( isset( $raw['q'] ) && is_scalar( $raw['q'] ) && '' !== trim( (string) $raw['q'] ) ) {
			$f['q'] = mb_substr( sanitize_text_field( (string) $raw['q'] ), 0, 60 );
		}
		if ( ! empty( $raw['featured'] ) ) {
			$f['featured'] = 1;
		}
		return $f;
	}

	/** Brands present in the public inventory. */
	public static function public_brands() {
		global $wpdb;
		$table = Database::table( 'vehicles' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_col( "SELECT DISTINCT brand FROM $table WHERE status IN ('published','reserved') ORDER BY brand ASC" );
	}

	/* ------------------------------------------------------------------ */
	/* Writes                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Create or update a vehicle from the CRM form.
	 *
	 * @param array $in Raw input.
	 * @param int   $id Vehicle id (0 = create).
	 * @return int|\WP_Error
	 */
	public static function save( array $in, $id = 0 ) {
		global $wpdb;
		$check = Roles::require_cap( 'adc_manage_vehicles' );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$existing = $id ? self::get( $id ) : null;
		if ( $id && ! $existing ) {
			return new \WP_Error( 'adc_vehicle', __( 'Vehicle not found.', 'automotive-dealer-crm' ) );
		}
		$text  = static function ( $key, $max = 191 ) use ( $in ) {
			return isset( $in[ $key ] ) && is_scalar( $in[ $key ] ) ? mb_substr( sanitize_text_field( (string) $in[ $key ] ), 0, $max ) : '';
		};
		$pick  = static function ( $key, array $allowed, $default ) use ( $in ) {
			return isset( $in[ $key ] ) && in_array( $in[ $key ], $allowed, true ) ? $in[ $key ] : $default;
		};
		$brand = $text( 'brand', 80 );
		$model = $text( 'model', 80 );
		if ( '' === $brand || '' === $model ) {
			return new \WP_Error( 'adc_vehicle', __( 'Brand and model are required.', 'automotive-dealer-crm' ) );
		}
		$year  = absint( $text( 'year', 4 ) );
		$price = absint( preg_replace( '/\D/', '', $text( 'price_amount', 20 ) ) );

		// Price changes on a public vehicle need the price permission.
		if ( $existing && in_array( $existing->status, self::PUBLIC_STATUSES, true ) && $price !== $existing->price_amount && ! current_user_can( 'adc_edit_prices' ) ) {
			return new \WP_Error( 'adc_price', __( 'You are not allowed to change the price of a published vehicle. Ask a manager.', 'automotive-dealer-crm' ) );
		}

		// Requested status, limited by permissions.
		$requested = $pick( 'status', array( 'draft', 'pending_review', 'published' ), $existing ? $existing->status : 'draft' );
		if ( 'published' === $requested && ( ! $existing || 'published' !== $existing->status ) && ! current_user_can( 'adc_publish_vehicles' ) ) {
			$requested = 'pending_review';
		}
		$status = $existing && ! in_array( $existing->status, array( 'draft', 'pending_review', 'published' ), true ) ? $existing->status : $requested;

		$features = array();
		if ( isset( $in['features'] ) ) {
			$features = array_slice( array_values( array_filter( array_map( 'sanitize_text_field', preg_split( '/\r\n|\r|\n|,/', (string) $in['features'] ) ) ) ), 0, 40 );
		}

		$now  = current_time( 'mysql', true );
		$data = array(
			'title'                  => trim( $brand . ' ' . $model . ' ' . ( $year ? $year : '' ) ),
			'brand'                  => $brand,
			'model'                  => $model,
			'year'                   => ( $year >= 1950 && $year <= (int) gmdate( 'Y' ) + 1 ) ? $year : 0,
			'mileage_km'             => absint( preg_replace( '/\D/', '', $text( 'mileage_km', 20 ) ) ),
			'fuel_type'              => $pick( 'fuel_type', array_keys( Labels::fuel_types() ), '' ),
			'transmission'           => $pick( 'transmission', array_keys( Labels::transmissions() ), '' ),
			'body_type'              => $pick( 'body_type', array_keys( Labels::body_types() ), '' ),
			'vehicle_condition'      => $pick( 'vehicle_condition', array_keys( Labels::conditions() ), '' ),
			'color'                  => $text( 'color', 40 ),
			'vin'                    => strtoupper( $text( 'vin', 40 ) ),
			'listing_type'           => $pick( 'listing_type', array_keys( Labels::listing_types() ), 'sale' ),
			'price_amount'           => $price,
			'price_period'           => $pick( 'price_period', array_keys( Labels::price_periods() ), 'total' ),
			'currency'               => Settings::get( 'currency' ),
			'purchase_price'         => current_user_can( 'adc_view_reports' ) || ! $existing ? absint( preg_replace( '/\D/', '', $text( 'purchase_price', 20 ) ) ) : $existing->purchase_price,
			'description'            => isset( $in['description'] ) ? mb_substr( sanitize_textarea_field( (string) $in['description'] ), 0, 8000 ) : '',
			'features'               => wp_json_encode( $features ),
			'status'                 => $status,
			'is_featured'            => empty( $in['is_featured'] ) ? 0 : 1,
			'publish_to_marketplace' => empty( $in['publish_to_marketplace'] ) ? 0 : 1,
			'updated_by'             => get_current_user_id(),
			'updated_at'             => $now,
		);
		if ( 'rent' === $data['listing_type'] && 'total' === $data['price_period'] ) {
			$data['price_period'] = 'day';
		}
		if ( 'sale' === $data['listing_type'] ) {
			$data['price_period'] = 'total';
		}
		if ( 'published' === $status && ( ! $existing || ! $existing->published_at ) ) {
			$data['published_at'] = $now;
		}

		$table = Database::table( 'vehicles' );
		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'id' => $existing->id ) );
			$id    = $existing->id;
			$event = $existing->status !== $status ? 'status' : 'updated';
		} else {
			$data['uid']        = wp_generate_uuid4();
			$data['slug']       = self::unique_slug( $data['title'] );
			$data['created_by'] = get_current_user_id();
			$data['created_at'] = $now;
			$wpdb->insert( $table, $data );
			$id    = (int) $wpdb->insert_id;
			$event = 'created';
		}
		if ( ! $id ) {
			return new \WP_Error( 'adc_db', __( 'The vehicle could not be saved.', 'automotive-dealer-crm' ) );
		}

		$media = self::handle_media( $id, $in );
		if ( is_wp_error( $media ) ) {
			Activity::log( 'vehicle_' . $event, 'vehicle', $id, $data['title'] );
			do_action( 'adc_vehicle_changed', $id, $event );
			// Saved, but some photos were refused.
			return new \WP_Error( 'adc_partial', $media->get_error_message(), array( 'id' => $id ) );
		}

		Activity::log( 'vehicle_' . $event, 'vehicle', $id, $data['title'] );
		do_action( 'adc_vehicle_changed', $id, $event );
		return $id;
	}

	private static function unique_slug( $title ) {
		global $wpdb;
		$table = Database::table( 'vehicles' );
		$base  = substr( sanitize_title( $title ), 0, 170 );
		$base  = $base ? $base : 'vehicle';
		$slug  = $base;
		$i     = 2;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		while ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE slug = %s", $slug ) ) ) {
			$slug = $base . '-' . $i++;
		}
		return $slug;
	}

	/**
	 * New photos (photos[]), removals (remove_media[]) and order (media_order[id]).
	 *
	 * @return true|\WP_Error
	 */
	private static function handle_media( $vehicle_id, array $in ) {
		global $wpdb;
		$table   = Database::table( 'vehicle_media' );
		$current = self::media( $vehicle_id );

		foreach ( isset( $in['remove_media'] ) ? (array) $in['remove_media'] : array() as $attachment_id ) {
			$attachment_id = absint( $attachment_id );
			if ( in_array( $attachment_id, $current, true ) ) {
				$wpdb->delete( $table, array( 'vehicle_id' => $vehicle_id, 'attachment_id' => $attachment_id ) );
				wp_delete_attachment( $attachment_id, true );
			}
		}
		if ( isset( $in['media_order'] ) && is_array( $in['media_order'] ) ) {
			foreach ( $in['media_order'] as $attachment_id => $position ) {
				$wpdb->update( $table, array( 'position' => absint( $position ) ), array( 'vehicle_id' => $vehicle_id, 'attachment_id' => absint( $attachment_id ) ) );
			}
		}

		$error = null;
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
		if ( ! empty( $_FILES['photos']['name'] ) && is_array( $_FILES['photos']['name'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			$files    = $_FILES['photos'];
			$position = count( self::media( $vehicle_id ) );
			$count    = min( 30, count( $files['name'] ) );
			for ( $i = 0; $i < $count; $i++ ) {
				if ( UPLOAD_ERR_NO_FILE === (int) $files['error'][ $i ] ) {
					continue;
				}
				if ( (int) $files['size'][ $i ] > 10485760 || ! in_array( wp_check_filetype( $files['name'][ $i ] )['type'], array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
					$error = new \WP_Error( 'adc_photo', __( 'Some photos were skipped: use JPG, PNG or WEBP images under 10 MB.', 'automotive-dealer-crm' ) );
					continue;
				}
				$single        = array(
					'name'     => $files['name'][ $i ],
					'type'     => $files['type'][ $i ],
					'tmp_name' => $files['tmp_name'][ $i ],
					'error'    => $files['error'][ $i ],
					'size'     => $files['size'][ $i ],
				);
				$attachment_id = media_handle_sideload( $single, 0 );
				if ( is_wp_error( $attachment_id ) ) {
					$error = $attachment_id;
					continue;
				}
				$wpdb->insert(
					$table,
					array(
						'vehicle_id'    => $vehicle_id,
						'attachment_id' => (int) $attachment_id,
						'position'      => $position++,
					)
				);
			}
		}
		// phpcs:enable

		$media = self::media( $vehicle_id );
		$wpdb->update( Database::table( 'vehicles' ), array( 'thumbnail_id' => $media ? $media[0] : 0 ), array( 'id' => $vehicle_id ) );
		return $error ? $error : true;
	}

	/**
	 * Quick status change from the CRM.
	 *
	 * @return true|\WP_Error
	 */
	public static function set_status( $id, $status ) {
		global $wpdb;
		$vehicle = self::get( $id );
		if ( ! $vehicle || ! isset( Labels::vehicle_statuses()[ $status ] ) ) {
			return new \WP_Error( 'adc_vehicle', __( 'Vehicle not found.', 'automotive-dealer-crm' ) );
		}
		$caps = array(
			'draft'          => 'adc_manage_vehicles',
			'pending_review' => 'adc_manage_vehicles',
			'published'      => 'adc_publish_vehicles',
			'archived'       => 'adc_publish_vehicles',
			'reserved'       => 'adc_manage_vehicles',
			'sold'           => 'adc_manage_sales',
			'rented'         => 'adc_manage_rentals',
		);
		if ( ! current_user_can( $caps[ $status ] ) && ! ( 'reserved' === $status && current_user_can( 'adc_manage_sales' ) ) ) {
			return new \WP_Error( 'adc_forbidden', __( 'You do not have permission to do this.', 'automotive-dealer-crm' ) );
		}
		$data = array(
			'status'     => $status,
			'updated_by' => get_current_user_id(),
			'updated_at' => current_time( 'mysql', true ),
		);
		if ( 'published' === $status && ! $vehicle->published_at ) {
			$data['published_at'] = $data['updated_at'];
		}
		if ( 'sold' === $status ) {
			$data['sold_at'] = $data['updated_at'];
		}
		$wpdb->update( Database::table( 'vehicles' ), $data, array( 'id' => $vehicle->id ) );
		Activity::log( 'vehicle_status', 'vehicle', $vehicle->id, $vehicle->title . ' → ' . Labels::label( 'vehicle_statuses', $status ) );
		do_action( 'adc_vehicle_changed', $vehicle->id, 'status' );
		return true;
	}

	/** Internal status change by sales/rentals (permission checked by the caller). */
	/**
	 * Change only the price (used by the AI assistant, after confirmation).
	 *
	 * @return true|\WP_Error
	 */
	public static function set_price( $id, $amount ) {
		global $wpdb;
		$vehicle = self::get( $id );
		if ( ! $vehicle ) {
			return new \WP_Error( 'adc_vehicle', __( 'Vehicle not found.', 'automotive-dealer-crm' ) );
		}
		if ( ! current_user_can( 'adc_edit_prices' ) ) {
			return new \WP_Error( 'adc_forbidden', __( 'You do not have permission to do this.', 'automotive-dealer-crm' ) );
		}
		$amount = absint( $amount );
		if ( $amount < 1 ) {
			return new \WP_Error( 'adc_vehicle', __( 'Please enter a valid price.', 'automotive-dealer-crm' ) );
		}
		$wpdb->update(
			Database::table( 'vehicles' ),
			array(
				'price_amount' => $amount,
				'updated_by'   => get_current_user_id(),
				'updated_at'   => current_time( 'mysql', true ),
			),
			array( 'id' => $vehicle->id )
		);
		Activity::log( 'vehicle_price', 'vehicle', $vehicle->id, $vehicle->title . ' → ' . adc_money( $amount, $vehicle->currency ) );
		do_action( 'adc_vehicle_changed', $vehicle->id, 'updated' );
		return true;
	}

	public static function system_status( $id, $status ) {
		global $wpdb;
		$data = array(
			'status'     => $status,
			'updated_at' => current_time( 'mysql', true ),
		);
		if ( 'sold' === $status ) {
			$data['sold_at'] = $data['updated_at'];
		}
		$wpdb->update( Database::table( 'vehicles' ), $data, array( 'id' => absint( $id ) ) );
		do_action( 'adc_vehicle_changed', absint( $id ), 'status' );
	}

	/** Permanent deletion is limited to drafts and archived vehicles. */
	public static function delete( $id ) {
		global $wpdb;
		$vehicle = self::get( $id );
		if ( ! $vehicle || ! current_user_can( 'adc_publish_vehicles' ) ) {
			return new \WP_Error( 'adc_forbidden', __( 'You do not have permission to do this.', 'automotive-dealer-crm' ) );
		}
		if ( ! in_array( $vehicle->status, array( 'draft', 'archived' ), true ) ) {
			return new \WP_Error( 'adc_vehicle', __( 'Only drafts and archived vehicles can be deleted. Archive it first.', 'automotive-dealer-crm' ) );
		}
		foreach ( self::media( $id ) as $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
		}
		$wpdb->delete( Database::table( 'vehicle_media' ), array( 'vehicle_id' => $vehicle->id ) );
		$wpdb->delete( Database::table( 'vehicles' ), array( 'id' => $vehicle->id ) );
		Activity::log( 'vehicle_deleted', 'vehicle', $vehicle->id, $vehicle->title );
		do_action( 'adc_vehicle_changed', $vehicle->id, 'deleted', $vehicle->uid );
		return true;
	}
}
