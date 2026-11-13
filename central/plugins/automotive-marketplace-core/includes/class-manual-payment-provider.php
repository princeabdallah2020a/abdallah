<?php
/**
 * Manual payment (Wave, Orange Money, bank transfer, cash): the dealer pays
 * outside the website and uploads a receipt that staff verifies.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Manual_Payment_Provider implements Payment_Provider {

	private $id;
	private $label;
	private $setting;

	public function __construct( $id, $label, $setting ) {
		$this->id      = $id;
		$this->label   = $label;
		$this->setting = $setting;
	}

	public function id() {
		return $this->id;
	}

	public function label() {
		return $this->label;
	}

	public function details() {
		return trim( (string) Settings::get( $this->setting ) );
	}

	public function is_available() {
		return '' !== $this->details();
	}

	public function supports_recurring() {
		return false;
	}

	public function requires_proof() {
		return 'cash' !== $this->id;
	}

	public function instructions( $amount, $currency, $reference ) {
		$details = $this->details();
		if ( '' === $details ) {
			$details = __( 'Our team will send you the payment details.', 'automotive-marketplace-core' );
		}
		/* translators: 1: amount, 2: payment details, 3: reference */
		return sprintf( __( 'Pay %1$s — %2$s. Write the reference %3$s with your payment, then send us the receipt below.', 'automotive-marketplace-core' ), amc_format_money( $amount, $currency ), $details, $reference );
	}
}
