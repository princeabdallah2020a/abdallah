<?php
/**
 * Manual provisioning: a checklist staff completes for each new dealer site.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Manual_Provisioning_Provider implements Provisioning_Provider {

	public function id() {
		return 'manual';
	}

	public function label() {
		return __( 'Manual setup by our team', 'automotive-marketplace-core' );
	}

	public function steps() {
		return array(
			'hosting'     => __( 'Hosting account created', 'automotive-marketplace-core' ),
			'domain'      => __( 'Domain registered or connected, SSL active', 'automotive-marketplace-core' ),
			'wordpress'   => __( 'WordPress installed', 'automotive-marketplace-core' ),
			'packages'    => __( 'Dealer theme, CRM and connector installed', 'automotive-marketplace-core' ),
			'branding'    => __( 'Logo, colours and contact details configured', 'automotive-marketplace-core' ),
			'admin'       => __( 'Dealer administrator account created', 'automotive-marketplace-core' ),
			'credentials' => __( 'Marketplace API credentials issued and connection tested', 'automotive-marketplace-core' ),
			'handover'    => __( 'Access details sent to the dealer', 'automotive-marketplace-core' ),
		);
	}

	public function can_automate() {
		return false;
	}

	public function run( $job ) {
		return new \WP_Error( 'amc_manual', __( 'This provider has no automated steps.', 'automotive-marketplace-core' ) );
	}
}
