<?php
/**
 * Private documents (business registration, IDs, payment receipts).
 *
 * Files are stored outside public URLs (directory denied to web access,
 * random file names) and served only through a permission-checked handler:
 * platform staff, or the owner of the dealership the file belongs to.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Documents {

	const MAX_BYTES = 5242880; // 5 MB.

	public static function init() {
		add_action( 'admin_post_amc_document', array( __CLASS__, 'serve' ) );
	}

	public static function mimes() {
		return array(
			'application/pdf' => 'pdf',
			'image/jpeg'      => 'jpg',
			'image/png'       => 'png',
			'image/webp'      => 'webp',
		);
	}

	public static function types() {
		return array(
			'rccm'          => __( 'Trade register (RCCM)', 'automotive-marketplace-core' ),
			'ninea'         => __( 'Tax ID (NINEA)', 'automotive-marketplace-core' ),
			'id'            => __( 'ID of the manager', 'automotive-marketplace-core' ),
			'payment_proof' => __( 'Payment receipt', 'automotive-marketplace-core' ),
			'other'         => __( 'Other document', 'automotive-marketplace-core' ),
		);
	}

	public static function type_label( $type ) {
		$types = self::types();
		return isset( $types[ $type ] ) ? $types[ $type ] : $type;
	}

	/** True when a file was sent in this field. */
	public static function has_upload( $field ) {
		return isset( $_FILES[ $field ]['error'] ) && UPLOAD_ERR_NO_FILE !== (int) $_FILES[ $field ]['error']; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Validate an uploaded file.
	 *
	 * @param string $field Form field name.
	 * @return true|\WP_Error
	 */
	public static function validate( $field ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
		$file = isset( $_FILES[ $field ] ) ? $_FILES[ $field ] : null;
		if ( ! $file || UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new \WP_Error( 'amc_upload', __( 'The file could not be uploaded. Please try again.', 'automotive-marketplace-core' ) );
		}
		if ( (int) $file['size'] > self::MAX_BYTES ) {
			return new \WP_Error( 'amc_upload', __( 'The file is too large (5 MB maximum).', 'automotive-marketplace-core' ) );
		}
		$mime = self::detect_mime( $file['tmp_name'] );
		if ( ! isset( self::mimes()[ $mime ] ) ) {
			return new \WP_Error( 'amc_upload', __( 'Accepted formats: PDF, JPG, PNG, WEBP.', 'automotive-marketplace-core' ) );
		}
		// phpcs:enable
		return true;
	}

	private static function detect_mime( $path ) {
		if ( function_exists( 'finfo_open' ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			$mime  = finfo_file( $finfo, $path );
			finfo_close( $finfo );
			return (string) $mime;
		}
		$check = wp_check_filetype_and_ext( $path, basename( $path ) );
		return (string) $check['type'];
	}

	/** Private storage root, created with web access denied. */
	private static function root() {
		$upload = wp_upload_dir();
		$root   = trailingslashit( $upload['basedir'] ) . 'amc-private';
		if ( ! is_dir( $root ) ) {
			wp_mkdir_p( $root );
		}
		if ( ! file_exists( $root . '/.htaccess' ) ) {
			// phpcs:disable WordPress.WP.AlternativeFunctions
			file_put_contents( $root . '/.htaccess', "Require all denied\nDeny from all\n" );
			file_put_contents( $root . '/index.php', "<?php // Silence.\n" );
			file_put_contents( $root . '/web.config', '<?xml version="1.0"?><configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>' );
			// phpcs:enable
		}
		return $root;
	}

	/**
	 * Store an uploaded file (call validate() first).
	 *
	 * @param string $field          Form field name.
	 * @param int    $dealer_id      Owner dealership.
	 * @param string $type           Document type key.
	 * @param int    $application_id Related application.
	 * @return int Document id (0 when nothing was uploaded or storage failed).
	 */
	public static function store( $field, $dealer_id, $type, $application_id = 0 ) {
		global $wpdb;
		if ( ! self::has_upload( $field ) || true !== self::validate( $field ) ) {
			return 0;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
		$file = $_FILES[ $field ];
		$mime = self::detect_mime( $file['tmp_name'] );
		$dir  = self::root() . '/' . absint( $dealer_id );
		wp_mkdir_p( $dir );
		$name = bin2hex( random_bytes( 16 ) ) . '.' . self::mimes()[ $mime ];
		if ( ! move_uploaded_file( $file['tmp_name'], $dir . '/' . $name ) ) {
			return 0;
		}
		$original = sanitize_file_name( wp_basename( (string) $file['name'] ) );
		// phpcs:enable
		$wpdb->insert(
			Database::table( 'documents' ),
			array(
				'dealer_id'      => absint( $dealer_id ),
				'application_id' => absint( $application_id ),
				'type'           => isset( self::types()[ $type ] ) ? $type : 'other',
				'original_name'  => mb_substr( $original, 0, 190 ),
				'path'           => 'amc-private/' . absint( $dealer_id ) . '/' . $name,
				'mime'           => $mime,
				'size'           => (int) filesize( $dir . '/' . $name ),
				'uploaded_by'    => get_current_user_id(),
				'created_at'     => current_time( 'mysql', true ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function get( $id ) {
		global $wpdb;
		$table = Database::table( 'documents' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", absint( $id ) ) );
	}

	public static function for_dealer( $dealer_id ) {
		global $wpdb;
		$table = Database::table( 'documents' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE dealer_id = %d ORDER BY id DESC", absint( $dealer_id ) ) );
		return $rows ? $rows : array();
	}

	/** Signed link to view a document. */
	public static function url( $id ) {
		return wp_nonce_url( add_query_arg( array( 'action' => 'amc_document', 'id' => absint( $id ) ), admin_url( 'admin-post.php' ) ), 'amc_document_' . absint( $id ) );
	}

	public static function serve() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( ! $id || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'amc_document_' . $id ) ) {
			wp_die( esc_html__( 'This link has expired.', 'automotive-marketplace-core' ), 403 );
		}
		$doc    = self::get( $id );
		$dealer = Memberships::current_dealer();
		$owner  = $doc && $dealer && (int) $dealer->id === (int) $doc->dealer_id;
		if ( ! $doc || ! ( $owner || Permissions::is_staff() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'automotive-marketplace-core' ), 403 );
		}
		$upload = wp_upload_dir();
		$path   = trailingslashit( $upload['basedir'] ) . $doc->path;
		if ( false !== strpos( $doc->path, '..' ) || ! is_readable( $path ) ) {
			wp_die( esc_html__( 'File not found.', 'automotive-marketplace-core' ), 404 );
		}
		nocache_headers();
		header( 'Content-Type: ' . $doc->mime );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'Content-Disposition: inline; filename="' . sanitize_file_name( $doc->original_name ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( "Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox" );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}
}
