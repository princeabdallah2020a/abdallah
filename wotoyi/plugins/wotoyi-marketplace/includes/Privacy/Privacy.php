<?php
/**
 * Buyer personal-data rights (SECURITY-REVIEW H2): WordPress privacy exporter + eraser, account-deletion cleanup
 * and the suggested privacy-policy text. Personal data held by the marketplace: leads (name, phone, e-mail,
 * message, consent proof), favourites and saved searches.
 *
 * What the marketplace CANNOT do: a lead that was already delivered to a dealer also exists in that dealer's own
 * system; the eraser tells the data subject so (the dealer is a separate controller for its copy).
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Privacy;

use Wotoyi\Marketplace\Plugin;
use Wotoyi\Marketplace\Settings;

defined( 'ABSPATH' ) || exit;

final class Privacy {

	public const PAGE_SIZE = 50;

	public static function register_hooks(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( self::class, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( self::class, 'register_eraser' ) );
		add_action( 'delete_user', array( self::class, 'on_delete_user' ) );
		add_action( 'admin_init', array( self::class, 'add_policy_content' ) );
	}

	/** @param array<string,mixed> $exporters @return array<string,mixed> */
	public static function register_exporter( array $exporters ): array {
		$exporters['wotoyi-marketplace'] = array(
			'exporter_friendly_name' => __( 'Wotoyi Marketplace (leads, favourites, saved searches)', 'wotoyi-marketplace' ),
			'callback'               => array( self::class, 'export' ),
		);
		return $exporters;
	}

	/** @param array<string,mixed> $erasers @return array<string,mixed> */
	public static function register_eraser( array $erasers ): array {
		$erasers['wotoyi-marketplace'] = array(
			'eraser_friendly_name' => __( 'Wotoyi Marketplace (leads, favourites, saved searches)', 'wotoyi-marketplace' ),
			'callback'             => array( self::class, 'erase' ),
		);
		return $erasers;
	}

	private static function user_id_for( string $email ): int {
		if ( ! function_exists( 'get_user_by' ) ) {
			return 0;
		}
		$user = get_user_by( 'email', $email );
		return $user && isset( $user->ID ) ? (int) $user->ID : 0;
	}

	/**
	 * Exporter callback.
	 *
	 * @return array{data:list<array<string,mixed>>,done:bool}
	 */
	public static function export( string $email_address, int $page = 1 ): array {
		$page    = max( 1, $page );
		$user_id = self::user_id_for( $email_address );
		$leads   = Plugin::leads()->find_for_subject( $email_address, $user_id, self::PAGE_SIZE, ( $page - 1 ) * self::PAGE_SIZE );
		$data    = array();

		foreach ( $leads as $l ) {
			$fields = array();
			foreach ( array( 'name', 'phone', 'email', 'message', 'channel', 'locale', 'consent_at', 'consent_version', 'consent_purpose', 'delivery_status', 'delivered_at', 'created_at' ) as $k ) {
				if ( null !== $l[ $k ] && '' !== $l[ $k ] ) {
					$fields[] = array( 'name' => $k, 'value' => (string) $l[ $k ] );
				}
			}
			$data[] = array(
				'group_id'          => 'wotoyi-leads',
				'group_label'       => __( 'Contact requests sent to dealers', 'wotoyi-marketplace' ),
				'group_description' => __( 'Messages you sent to a dealer through the marketplace.', 'wotoyi-marketplace' ),
				'item_id'           => 'wotoyi-lead-' . $l['uuid'],
				'data'              => $fields,
			);
		}

		if ( 1 === $page && $user_id > 0 ) {
			foreach ( Plugin::favourites()->export_for_user( $user_id ) as $f ) {
				$data[] = array(
					'group_id'    => 'wotoyi-favourites',
					'group_label' => __( 'Favourites', 'wotoyi-marketplace' ),
					'item_id'     => 'wotoyi-fav-' . $f['item_type'] . '-' . $f['item_id'],
					'data'        => array(
						array( 'name' => 'type', 'value' => (string) $f['item_type'] ),
						array( 'name' => 'listing_id', 'value' => (string) $f['item_id'] ),
						array( 'name' => 'created_at', 'value' => (string) $f['created_at'] ),
					),
				);
			}
			foreach ( Plugin::saved_searches()->list_for_user( $user_id ) as $s ) {
				$data[] = array(
					'group_id'    => 'wotoyi-saved-searches',
					'group_label' => __( 'Saved searches', 'wotoyi-marketplace' ),
					'item_id'     => 'wotoyi-search-' . $s['id'],
					'data'        => array(
						array( 'name' => 'name', 'value' => (string) $s['name'] ),
						array( 'name' => 'filters', 'value' => (string) wp_json_encode( $s['filters'] ) ),
						array( 'name' => 'notify', 'value' => $s['notify'] ? 'yes' : 'no' ),
						array( 'name' => 'created_at', 'value' => (string) $s['created_at'] ),
					),
				);
			}
		}

		return array( 'data' => $data, 'done' => count( $leads ) < self::PAGE_SIZE );
	}

	/**
	 * Eraser callback.
	 *
	 * @return array{items_removed:bool,items_retained:bool,messages:list<string>,done:bool}
	 */
	public static function erase( string $email_address, int $page = 1 ): array {
		$user_id  = self::user_id_for( $email_address );
		$messages = array();
		$removed  = 0;
		$res      = Plugin::leads()->erase_subject( $email_address, $user_id, self::PAGE_SIZE );
		$removed += $res['deleted'] + $res['anonymized'];

		if ( $res['anonymized'] > 0 ) {
			$messages[] = __( 'Contact requests already delivered to dealers were erased from the marketplace. A dealer keeps its own copy: ask the dealer directly to erase it.', 'wotoyi-marketplace' );
		}
		$fav = 0;
		$ss  = 0;
		if ( $user_id > 0 && ! $res['more'] ) { // Account-bound data once the leads are done.
			$fav      = Plugin::favourites()->delete_all_for_user( $user_id );
			$ss       = Plugin::saved_searches()->delete_all_for_user( $user_id );
			$removed += $fav + $ss;
		}
		if ( $removed > 0 ) {
			Plugin::audit()->record( 'system', 0, 'privacy.erase', null, array( 'leads_deleted' => $res['deleted'], 'leads_anonymized' => $res['anonymized'], 'items_removed' => $fav + $ss ) );
		}
		return array( 'items_removed' => $removed > 0, 'items_retained' => false, 'messages' => $messages, 'done' => ! $res['more'] );
	}

	/** `delete_user`: drop the account-bound data and unlink the user from their leads. */
	public static function on_delete_user( $user_id ): void {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return;
		}
		$fav = Plugin::favourites()->delete_all_for_user( $user_id );
		$ss  = Plugin::saved_searches()->delete_all_for_user( $user_id );
		$ld  = Plugin::leads()->detach_user( $user_id );
		if ( $fav + $ss + $ld > 0 ) {
			Plugin::audit()->record( 'system', 0, 'privacy.user_deleted', null, array( 'items_removed' => $fav + $ss, 'leads_anonymized' => $ld ) );
		}
	}

	/** Suggested text for Settings > Privacy > Policy Guide. */
	public static function add_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$d = Settings::int( 'lead_retention_delivered_days' );
		$u = Settings::int( 'lead_retention_undelivered_days' );
		$text = '<h3>' . esc_html__( 'Contacting a dealer', 'wotoyi-marketplace' ) . '</h3><p>'
			. esc_html(
				sprintf(
					/* translators: 1: days, 2: days */
					__( 'When you contact a dealer, we store your name, phone number and/or e-mail, your message and proof of your consent, and transmit them to that dealer, who then contacts you. After delivery to the dealer we erase this data from the marketplace within %1$d days; a request the dealer has not collected within %2$d days is deleted. The dealer keeps its own copy under its own responsibility. Favourites and saved searches are kept until you delete them or your account.', 'wotoyi-marketplace' ),
					$d,
					$u
				)
			) . '</p>';
		wp_add_privacy_policy_content( 'Wotoyi Marketplace', $text );
	}
}
