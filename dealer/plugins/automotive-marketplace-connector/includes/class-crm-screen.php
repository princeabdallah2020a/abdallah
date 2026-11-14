<?php
/**
 * "Marketplace" section inside the dealer CRM (/manager/marketplace/).
 *
 * @package AutomotiveMarketplaceConnector
 */

namespace ACX;

defined( 'ABSPATH' ) || exit;

final class Crm_Screen {

	public static function init() {
		add_filter( 'adc_manager_sections', array( __CLASS__, 'section' ) );
		add_filter( 'adc_locate_template', array( __CLASS__, 'locate' ), 10, 2 );
		add_filter( 'adc_manager_action', array( __CLASS__, 'action' ), 10, 3 );
	}

	public static function section( $sections ) {
		$new = array();
		foreach ( $sections as $key => $section ) {
			if ( 'settings' === $key ) {
				$new['marketplace'] = array( __( 'Marketplace', 'automotive-marketplace-connector' ), 'globe', 'adc_access' );
			}
			$new[ $key ] = $section;
		}
		if ( ! isset( $new['marketplace'] ) ) {
			$new['marketplace'] = array( __( 'Marketplace', 'automotive-marketplace-connector' ), 'globe', 'adc_access' );
		}
		return $new;
	}

	public static function locate( $file, $name ) {
		if ( '' === $file && 0 === strpos( $name, 'manager/marketplace' ) ) {
			$candidate = ACX_DIR . 'templates/' . $name . '.php';
			return is_readable( $candidate ) ? $candidate : '';
		}
		return $file;
	}

	/**
	 * CRM actions of this plugin (nonce already verified by the CRM).
	 *
	 * @return array|\WP_Error|null
	 */
	public static function action( $result, $action, array $in ) {
		if ( 0 !== strpos( $action, 'acx_' ) ) {
			return $result;
		}
		$owner = current_user_can( 'adc_manage_settings' );
		switch ( $action ) {
			case 'acx_connect':
				if ( ! $owner ) {
					break;
				}
				$done = Connection::connect( isset( $in['code'] ) ? (string) $in['code'] : '' );
				if ( is_wp_error( $done ) ) {
					return $done;
				}
				Sync::queue_all();
				Contacts::push();
				return array( 'message' => __( 'Connected. Your vehicles with "Also publish on the AutoBuzy marketplace" ticked are being sent.', 'automotive-marketplace-connector' ) );
			case 'acx_disconnect':
				if ( ! $owner ) {
					break;
				}
				Connection::disconnect();
				return array( 'message' => __( 'Disconnected. Your vehicles stay on the marketplace until the platform removes them; reconnect to update them.', 'automotive-marketplace-connector' ) );
			case 'acx_resync':
				if ( ! $owner ) {
					break;
				}
				Sync::queue_all();
				return array( 'message' => __( 'Full synchronisation started.', 'automotive-marketplace-connector' ) );
			case 'acx_retry':
				if ( ! current_user_can( 'adc_publish_vehicles' ) ) {
					break;
				}
				Sync::retry_errors();
				Sync::run();
				return array( 'message' => __( 'Failed vehicles were sent again.', 'automotive-marketplace-connector' ) );
			case 'acx_check':
				$status = Connection::request( 'GET', '/sync/status' );
				if ( is_wp_error( $status ) ) {
					return $status;
				}
				Connection::save_remote( $status );
				return array( 'message' => __( 'The connection works.', 'automotive-marketplace-connector' ) );
			case 'acx_whatsapp':
				return Contacts::save_own( isset( $in['phone'] ) ? (string) $in['phone'] : '' );
		}
		return new \WP_Error( 'adc_forbidden', __( 'You do not have permission to do this.', 'automotive-marketplace-connector' ) );
	}
}
