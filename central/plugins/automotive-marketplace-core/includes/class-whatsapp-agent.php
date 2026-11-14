<?php
/**
 * Processes one incoming WhatsApp message (runs in WP-Cron, not in the webhook).
 *
 * - Team members of a dealer (number registered in their CRM) talk to the
 *   dealer agent, within their plan's monthly AI quota.
 * - Any other number talks to the public buyer assistant (catalog search),
 *   unless staff disabled it.
 * - Pending sensitive actions are confirmed here, outside the model, by an
 *   explicit yes/no in French, Wolof or English. Confirmations expire after
 *   15 minutes.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Whatsapp_Agent {

	const HISTORY        = 12;    // Messages kept as conversation memory.
	const CONFIRM_WINDOW = 900;   // Seconds.
	const HOURLY_LIMIT   = 40;    // Messages per number and hour.

	public static function process( $message_id ) {
		global $wpdb;
		$table = Database::table( 'wa_messages' );
		// Claim the message atomically: a second cron run cannot process it again.
		$claimed = $wpdb->update( $table, array( 'status' => 'processing' ), array( 'id' => absint( $message_id ), 'status' => 'received' ) );
		if ( ! $claimed || ! Whatsapp::enabled() ) {
			return;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$msg = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", absint( $message_id ) ) );
		if ( ! $msg ) {
			return;
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 180 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		Whatsapp::mark_read( $msg->wamid );

		$reply = self::answer( $msg );
		if ( '' !== $reply ) {
			Whatsapp::send_text( $msg->phone, $reply );
		}
		$wpdb->update( $table, array( 'status' => 'processed' ), array( 'id' => (int) $msg->id ) );
	}

	/** @return string Reply text ('' = stay silent). */
	private static function answer( $msg ) {
		global $wpdb;
		$table = Database::table( 'wa_messages' );

		// Flood protection.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$recent = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE phone = %s AND direction = 'in' AND created_at >= %s", $msg->phone, gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) ) );
		if ( $recent > self::HOURLY_LIMIT ) {
			return $recent === self::HOURLY_LIMIT + 1 ? __( 'You have sent many messages in a short time. Please wait a little before writing again.', 'automotive-marketplace-core' ) : '';
		}

		$text = trim( (string) $msg->body );
		if ( 'audio' === $msg->kind ) {
			if ( ! Speech::enabled() ) {
				return __( 'Voice messages are not supported yet. Please write your message.', 'automotive-marketplace-core' );
			}
			$media = Whatsapp::download_media( $msg->media_id );
			$text  = is_wp_error( $media ) ? $media : Speech::transcribe( $media['bytes'], $media['mime'] );
			if ( is_wp_error( $text ) || '' === $text ) {
				Assistant::log_error( 'Voice note: ' . ( is_wp_error( $text ) ? $text->get_error_message() : 'empty transcription' ) );
				return __( 'I could not understand this voice message. Could you write it, please?', 'automotive-marketplace-core' );
			}
			$wpdb->update( $table, array( 'body' => mb_substr( sanitize_textarea_field( $text ), 0, 4000 ) ), array( 'id' => (int) $msg->id ) );
		} elseif ( 'text' !== $msg->kind ) {
			return __( 'I can read text and voice messages only. Please write your request.', 'automotive-marketplace-core' );
		}
		if ( '' === $text ) {
			return '';
		}

		return self::respond( $msg->phone, $text );
	}

	/**
	 * The assistant's answer to a person identified by phone number, whatever
	 * the channel (WhatsApp text, voice note, phone call through the gateway).
	 *
	 * @return string Reply.
	 */
	public static function respond( $phone, $text ) {
		$conversation = self::conversation( $phone );
		$contact      = Ai_Contacts::find( $phone );
		$dealer       = $contact ? Memberships::get_dealer( $contact->dealer_id ) : null;

		if ( $contact && $dealer ) {
			$reply = self::for_dealer( $contact, $dealer, $conversation, $text );
		} elseif ( Settings::get( 'wa_public_assistant' ) ) {
			$result = Assistant::reply( 'visitor', self::messages( $conversation, $text ) );
			$reply  = $result['text'] . ( ! empty( $result['url'] ) ? "\n\n" . $result['url'] : '' );
			$conversation['pending'] = null;
		} else {
			return __( 'Hello! This number is reserved for AutoBuzy dealers. To find a vehicle, visit our website.', 'automotive-marketplace-core' ) . ' ' . home_url( '/' );
		}

		$conversation['history'][] = array( 'role' => 'user', 'content' => mb_substr( $text, 0, 2000 ) );
		$conversation['history'][] = array( 'role' => 'assistant', 'content' => mb_substr( $reply, 0, 2000 ) );
		self::save( $phone, $contact ? (int) $contact->dealer_id : 0, $conversation );
		return $reply;
	}

	private static function for_dealer( $contact, $dealer, array &$conversation, $text ) {
		if ( 'active' !== $dealer->status ) {
			return __( 'Your AutoBuzy account is not active, so the assistant is paused. Please contact AutoBuzy to renew your membership.', 'automotive-marketplace-core' );
		}
		$sub   = Subscriptions::current_for_dealer( $dealer->id );
		$quota = $sub ? (int) $sub->ai_quota : 0;
		if ( $quota <= 0 ) {
			return __( 'The WhatsApp assistant is not included in your plan. Upgrade your plan in your dealer space to use it.', 'automotive-marketplace-core' );
		}
		if ( Whatsapp::month_count( $dealer->id ) > $quota ) {
			return __( 'You have used all the assistant messages included in your plan this month. The counter resets on the 1st.', 'automotive-marketplace-core' );
		}

		// A pending sensitive action: an explicit yes/no decides, anything else cancels it.
		$pending = $conversation['pending'];
		if ( $pending ) {
			$answer                  = self::yes_no( $text );
			$conversation['pending'] = null;
			$expired                 = time() - (int) $pending['at'] > self::CONFIRM_WINDOW;
			if ( 'yes' === $answer && ! $expired ) {
				$result = Dealer_Agent::call_dealer( $dealer->id, $contact->user_ref, $pending['tool'], (array) $pending['args'], true );
				Audit::log( 'whatsapp.action', 'dealer', $dealer->id, array( 'tool' => $pending['tool'], 'ok' => empty( $result['error'] ) ) );
				return empty( $result['error'] )
					/* translators: %s: summary of the action */
					? sprintf( __( '✅ Done: %s', 'automotive-marketplace-core' ), $result['done'] ?? $pending['summary'] )
					/* translators: %s: error */
					: sprintf( __( '❌ Not done: %s', 'automotive-marketplace-core' ), $result['error'] );
			}
			if ( 'yes' === $answer && $expired ) {
				return __( 'This confirmation has expired (15 minutes). Please ask again.', 'automotive-marketplace-core' );
			}
			if ( 'no' === $answer ) {
				return __( 'Cancelled. Nothing was changed.', 'automotive-marketplace-core' );
			}
		}

		$result                  = Dealer_Agent::reply( $contact, $dealer, self::messages( $conversation, $text ) );
		$conversation['pending'] = $result['pending'];
		return $result['text'];
	}

	/** yes|no|'' — French, Wolof, English. */
	public static function yes_no( $text ) {
		$t = strtolower( remove_accents( trim( preg_replace( '/[^\p{L}\p{N} \']/u', '', (string) $text ) ) ) );
		if ( preg_match( "/^(oui|yes|y|ok|okay|waaw|wao|waw|confirme|confirmer|confirm|d'?accord|vas-?y|go|1)( .*)?$/", $t ) && mb_strlen( $t ) <= 25 ) {
			return 'yes';
		}
		if ( preg_match( "/^(non|no|n|deedet|dedet|deet|annule|annuler|cancel|stop|0)( .*)?$/", $t ) && mb_strlen( $t ) <= 25 ) {
			return 'no';
		}
		return '';
	}

	/* ------------------------------------------------------------------ */
	/* Conversation memory                                                 */
	/* ------------------------------------------------------------------ */

	private static function conversation( $phone ) {
		global $wpdb;
		$table = Database::table( 'wa_conversations' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row     = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE phone = %s", $phone ) );
		$history = $row ? json_decode( (string) $row->history, true ) : array();
		$pending = $row ? json_decode( (string) $row->pending_action, true ) : null;
		// Memory older than 6 hours is dropped: a new day starts a new conversation.
		if ( $row && strtotime( $row->updated_at . ' UTC' ) < time() - 6 * HOUR_IN_SECONDS ) {
			$history = array();
		}
		return array(
			'history' => is_array( $history ) ? $history : array(),
			'pending' => is_array( $pending ) ? $pending : null,
		);
	}

	private static function save( $phone, $dealer_id, array $conversation ) {
		global $wpdb;
		$history = array_slice( $conversation['history'], -self::HISTORY );
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO ' . Database::table( 'wa_conversations' ) . ' (phone, dealer_id, history, pending_action, updated_at) VALUES (%s, %d, %s, %s, %s)
				ON DUPLICATE KEY UPDATE dealer_id = VALUES(dealer_id), history = VALUES(history), pending_action = VALUES(pending_action), updated_at = VALUES(updated_at)',
				$phone,
				$dealer_id,
				wp_json_encode( $history ),
				$conversation['pending'] ? wp_json_encode( $conversation['pending'] ) : '',
				current_time( 'mysql', true )
			)
		);
	}

	/** Claude messages: alternating user/assistant, starting with user. */
	private static function messages( array $conversation, $text ) {
		$out = array();
		foreach ( $conversation['history'] as $turn ) {
			if ( ! isset( $turn['role'], $turn['content'] ) || ! in_array( $turn['role'], array( 'user', 'assistant' ), true ) ) {
				continue;
			}
			if ( ! $out && 'assistant' === $turn['role'] ) {
				continue;
			}
			if ( $out && end( $out )['role'] === $turn['role'] ) {
				continue;
			}
			$out[] = array( 'role' => $turn['role'], 'content' => (string) $turn['content'] );
		}
		if ( $out && 'user' === end( $out )['role'] ) {
			array_pop( $out );
		}
		$out[] = array( 'role' => 'user', 'content' => mb_substr( $text, 0, Assistant::MAX_USER_CHARS * 2 ) );
		return $out;
	}
}
