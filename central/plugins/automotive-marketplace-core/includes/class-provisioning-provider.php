<?php
/**
 * Website provisioning provider contract.
 *
 * WordPress cannot buy domains or open hosting accounts by itself. The
 * built-in provider is a manual checklist completed by staff; a hosting
 * company exposing an API can be plugged in with "amc_provisioning_providers"
 * and return true from can_automate().
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

interface Provisioning_Provider {

	public function id();

	public function label();

	/** @return array<string,string> Ordered steps: key => label. */
	public function steps();

	public function can_automate();

	/**
	 * Run the automated steps for a job.
	 *
	 * @param object $job Provisioning job.
	 * @return array|\WP_Error Keys of the steps completed.
	 */
	public function run( $job );
}
