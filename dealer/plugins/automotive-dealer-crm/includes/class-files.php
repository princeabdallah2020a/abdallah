<?php
/**
 * Private customer documents (IDs, licences, contracts). Stored outside
 * public URLs; served only to logged-in team members with customer access.
 *
 * @package AutomotiveDealerCrm
 */

namespace ADC;

defined( 'ABSPATH' ) || exit;

final class Files {

	const MAX_BYTES = 8388608; // 8 MB.

	public static function init() {
		add_action( 'admin_post_adc_file', array( __CLASS__, 'serve' ) );
	}

	private static function mimes() {
		return array(
			'application/pdf' => 'pdf',
			'image/jpeg'      => 'jpg',
			'image/png'       => 'png',
			'image/webp'      => 'webp',
		);
	}

	private static function root() {
		$upload = wp_upload_dir();
		$root   = trailingslashit( $upload['basedir'] ) . 'adc-private';
		if ( ! is_dir( $root ) ) {
			wp_mkdir_p( $root );
		}
		if ( ! file_exists( $root . '/.htaccess' ) ) {
			// phpcs:disable WordPress.WP.AlternativeFunctions
			file_put_contents( $root . '/.htaccess', "Require all denied\nDeny from all\n" );
			file_put_contents( $root . '/index.php', "<?php // Silence.\n" );
			// phpcs:enable
		}
		return $root;
	}

	/**
	 * Store an uploaded file.
	 *
	 * @param string $field     Form field.
	 * @param array  $relations customer_id, sale_id, rental_id.
	 * @param string $label     Document label.
	 * @return int|\WP_Error|null File id, error, or null when no file was sent.
	 */
	public static function store( $field, array $relations, $label = '' ) {
		global $wpdb;
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
		if ( ! isset( $_FILES[ $field ]['error'] ) || UPLOAD_ERR_NO_FILE === (int) $_FILES[ $field ]['error'] ) {
			return null;
		}
		$file = $_FILES[ $field ];
		// phpcs:enable
		if ( UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new \WP_Error( 'adc_upload', __( 'The file could not be uploaded.', 'automotive-dealer-crm' ) );
		}
		if ( (int) $file['size'] > self::MAX_BYTES ) {
			return new \WP_Error( 'adc_upload', __( 'The file is too large (8 MB maximum).', 'automotive-dealer-crm' ) );
		}
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$mime  = (string) finfo_file( $finfo, $file['tmp_name'] );
		finfo_close( $finfo );
		if ( ! isset( self::mimes()[ $mime ] ) ) {
			return new \WP_Error( 'adc_upload', __( 'Accepted formats: PDF, JPG, PNG, WEBP.', 'automotive-dealer-crm' ) );
		}
		$name = bin2hex( random_bytes( 16 ) ) . '.' . self::mimes()[ $mime ];
		if ( ! move_uploaded_file( $file['tmp_name'], self::root() . '/' . $name ) ) {
			return new \WP_Error( 'adc_upload', __( 'The file could not be uploaded.', 'automotive-dealer-crm' ) );
		}
		$wpdb->insert(
			Database::table( 'files' ),
			array(
				'customer_id'   => isset( $relations['customer_id'] ) ? absint( $relations['customer_id'] ) : 0,
				'sale_id'       => isset( $relations['sale_id'] ) ? absint( $relations['sale_id'] ) : 0,
				'rental_id'     => isset( $relations['rental_id'] ) ? absint( $relations['rental_id'] ) : 0,
				'label'         => mb_substr( sanitize_text_field( $label ), 0, 120 ),
				'original_name' => mb_substr( sanitize_file_name( wp_basename( (string) $file['name'] ) ), 0, 190 ),
				'path'          => 'adc-private/' . $name,
				'mime'          => $mime,
				'size'          => (int) $file['size'],
				'uploaded_by'   => get_current_user_id(),
				'created_at'    => current_time( 'mysql', true ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function for_customer( $customer_id ) {
		global $wpdb;
		$table = Database::table( 'files' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE customer_id = %d ORDER BY id DESC", absint( $customer_id ) ) );
		return $rows ? $rows : array();
	}

	public static function url( $id ) {
		return wp_nonce_url( add_query_arg( array( 'action' => 'adc_file', 'id' => absint( $id ) ), admin_url( 'admin-post.php' ) ), 'adc_file_' . absint( $id ) );
	}

	public static function serve() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( ! $id || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'adc_file_' . $id ) ) {
			wp_die( esc_html__( 'This link has expired.', 'automotive-dealer-crm' ), 403 );
		}
		if ( ! current_user_can( 'adc_manage_customers' ) && ! current_user_can( 'adc_manage_sales' ) && ! current_user_can( 'adc_manage_rentals' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'automotive-dealer-crm' ), 403 );
		}
		global $wpdb;
		$table = Database::table( 'files' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$file   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
		$upload = wp_upload_dir();
		$path   = $file ? trailingslashit( $upload['basedir'] ) . $file->path : '';
		if ( ! $file || false !== strpos( $file->path, '..' ) || ! is_readable( $path ) ) {
			wp_die( esc_html__( 'File not found.', 'automotive-dealer-crm' ), 404 );
		}
		nocache_headers();
		header( 'Content-Type: ' . $file->mime );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'Content-Disposition: inline; filename="' . sanitize_file_name( $file->original_name ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( "Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox" );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}
}
