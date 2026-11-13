<?php
/**
 * Payment provider contract.
 *
 * Built-in providers are manual (the dealer pays by Wave, Orange Money, bank
 * transfer or cash and uploads a receipt; staff verifies it). A gateway with
 * an API can be added with the "amc_payment_providers" filter. Only a provider
 * returning true from supports_recurring() may be presented as automatic renewal.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

interface Payment_Provider {

	public function id();

	public function label();

	public function is_available();

	public function supports_recurring();

	public function requires_proof();

	/**
	 * Payment instructions shown to the dealer.
	 *
	 * @param int    $amount    Amount in minor units.
	 * @param string $currency  ISO code.
	 * @param string $reference Reference to write on the payment.
	 */
	public function instructions( $amount, $currency, $reference );
}
