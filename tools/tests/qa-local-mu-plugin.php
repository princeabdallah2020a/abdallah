<?php
/**
 * QA ENVIRONMENT ONLY — copy into wp-content/mu-plugins/ of the local test sites.
 * - lets the two local sites call each other over loopback HTTP,
 * - captures outgoing mail in wp-content/qa-mail.log (JSON lines) instead of sending,
 * - mints nonces for the current user: /?qa_nonce=<full action name> (used by the QA runner).
 * NEVER install on staging or production.
 */
add_filter( 'http_request_host_is_external', '__return_false', 99 );
add_filter(
	'http_request_args',
	function ( $a ) {
		$a['reject_unsafe_urls'] = false;
		return $a;
	},
	99
);
add_filter(
	'pre_wp_mail',
	function ( $null, $atts ) {
		file_put_contents( WP_CONTENT_DIR . '/qa-mail.log', wp_json_encode( array( 'to' => $atts['to'], 'subject' => $atts['subject'], 'body' => wp_strip_all_tags( $atts['message'] ) ) ) . "\n", FILE_APPEND );
		return true;
	},
	10,
	2
);
add_action(
	'init',
	function () {
		if ( isset( $_GET['qa_nonce'] ) ) {
			header( 'Content-Type: text/plain' );
			echo wp_create_nonce( sanitize_text_field( wp_unslash( $_GET['qa_nonce'] ) ) );
			exit;
		}
	},
	1
);
/* WhatsApp Graph API stand-in: nothing leaves the machine; every call is recorded in wp-content/qa-graph.log. */
add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) {
		if ( false === strpos( $url, 'graph.facebook.com' ) ) {
			return $pre;
		}
		file_put_contents( WP_CONTENT_DIR . '/qa-graph.log', wp_json_encode( array( 'url' => $url, 'auth' => isset( $args['headers']['Authorization'] ) ? $args['headers']['Authorization'] : '', 'body' => json_decode( (string) ( $args['body'] ?? '' ), true ) ) ) . "\n", FILE_APPEND );
		return array( 'headers' => array(), 'body' => wp_json_encode( array( 'messages' => array( array( 'id' => 'wamid.OUT' . wp_rand() ) ) ) ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
	},
	10,
	3
);
