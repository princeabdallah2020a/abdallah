<?php
/**
 * Speech-to-text for WhatsApp voice notes — provider independent.
 *
 * Providers:
 * - none              : voice notes are answered with a request to write instead.
 * - openai_compatible : any service implementing POST {base_url}/audio/transcriptions
 *                       (multipart: file, model) → { "text": "…" }. This contract is
 *                       offered by several vendors and self-hosted Whisper servers.
 *
 * Wolof recognition quality depends entirely on the chosen model: test it with
 * real voice notes before enabling it for dealers (see docs/05-ai-assistant.md).
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Speech {

	const MAX_BYTES = 16777216; // 16 MB: WhatsApp audio limit.

	public static function providers() {
		return array(
			'none'              => __( 'Disabled (ask the person to write)', 'automotive-marketplace-core' ),
			'openai_compatible' => __( 'Transcription API compatible with /audio/transcriptions', 'automotive-marketplace-core' ),
		);
	}

	public static function enabled() {
		return 'none' !== Settings::get( 'stt_provider' ) && '' !== Settings::secret( 'stt_api_key', 'AMC_STT_API_KEY' ) && '' !== (string) Settings::get( 'stt_base_url' );
	}

	/**
	 * Transcribe audio.
	 *
	 * @param string $bytes Audio content.
	 * @param string $mime  MIME type (audio/ogg for WhatsApp voice notes).
	 * @return string|\WP_Error Text.
	 */
	public static function transcribe( $bytes, $mime ) {
		if ( ! self::enabled() ) {
			return new \WP_Error( 'amc_stt_disabled', 'Speech-to-text is not configured.' );
		}
		if ( strlen( $bytes ) > self::MAX_BYTES ) {
			return new \WP_Error( 'amc_stt_size', 'Audio file too large.' );
		}
		$boundary = wp_generate_password( 24, false );
		$ext      = false !== strpos( $mime, 'ogg' ) ? 'ogg' : ( false !== strpos( $mime, 'mpeg' ) ? 'mp3' : ( false !== strpos( $mime, 'mp4' ) ? 'm4a' : 'bin' ) );
		$body     = "--$boundary\r\nContent-Disposition: form-data; name=\"model\"\r\n\r\n" . Settings::get( 'stt_model' ) . "\r\n"
			. "--$boundary\r\nContent-Disposition: form-data; name=\"file\"; filename=\"voice.$ext\"\r\nContent-Type: " . preg_replace( '/[^a-z0-9\/.+-]/', '', strtolower( $mime ) ) . "\r\n\r\n" . $bytes . "\r\n"
			. "--$boundary--\r\n";
		$response = wp_remote_post(
			Settings::get( 'stt_base_url' ) . '/audio/transcriptions',
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . Settings::secret( 'stt_api_key', 'AMC_STT_API_KEY' ),
					'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
				),
				'body'    => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code || ! isset( $data['text'] ) ) {
			return new \WP_Error( 'amc_stt', 'Transcription failed (HTTP ' . $code . ').' );
		}
		return trim( (string) $data['text'] );
	}
}
