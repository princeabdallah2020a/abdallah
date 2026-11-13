<?php
/**
 * Dealer space — public profile.
 *
 * @var array $args { dealer: object }
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

$dealer = $args['dealer'];
AMC\Portal::page_head(
	__( 'Public profile', 'automotive-marketplace-core' ),
	__( 'Shown on the marketplace once your account is active.', 'automotive-marketplace-core' ),
	'active' === $dealer->status ? '<a class="amds-btn amds-btn--secondary" href="' . esc_url( amc_dealer_url( $dealer->slug ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'See my public profile', 'automotive-marketplace-core' ) . '</a>' : ''
);
$input = static function ( $name, $label, $value, $type = 'text' ) {
	echo '<div class="amds-field"><label class="amds-label" for="amc-p-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label><input class="amds-input" type="' . esc_attr( $type ) . '" id="amc-p-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"></div>';
};
?>
<form class="amc-panel amds-form" method="post" enctype="multipart/form-data">
	<?php AMC\Portal::action_fields( 'profile' ); ?>
	<div class="amc-logo-field">
		<span class="amc-logo-field__preview">
			<?php if ( $dealer->logo_url ) : ?>
				<img src="<?php echo esc_url( $dealer->logo_url ); ?>" alt="" width="80" height="80">
			<?php else : ?>
				<?php echo esc_html( amc_initials( $dealer->business_name ) ); ?>
			<?php endif; ?>
		</span>
		<div class="amds-field">
			<label class="amds-label" for="amc-p-logo"><?php esc_html_e( 'Logo (square, JPG/PNG/WEBP, 2 MB max)', 'automotive-marketplace-core' ); ?></label>
			<input class="amds-input" type="file" id="amc-p-logo" name="logo" accept="image/jpeg,image/png,image/webp">
		</div>
	</div>
	<div class="amds-field">
		<label class="amds-label"><?php esc_html_e( 'Trading name', 'automotive-marketplace-core' ); ?></label>
		<p class="amc-readonly"><?php echo esc_html( $dealer->business_name ); ?> <small><?php esc_html_e( 'To change it, contact support.', 'automotive-marketplace-core' ); ?></small></p>
	</div>
	<div class="amds-field">
		<label class="amds-label" for="amc-p-description"><?php esc_html_e( 'Description', 'automotive-marketplace-core' ); ?></label>
		<textarea class="amds-textarea" id="amc-p-description" name="description" rows="5" maxlength="2000"><?php echo esc_textarea( $dealer->description ); ?></textarea>
	</div>
	<div class="amds-form__row">
		<?php
		$input( 'phone', __( 'Phone', 'automotive-marketplace-core' ), $dealer->phone, 'tel' );
		$input( 'whatsapp', __( 'WhatsApp', 'automotive-marketplace-core' ), $dealer->whatsapp, 'tel' );
		?>
	</div>
	<div class="amds-form__row">
		<?php
		$input( 'address', __( 'Address', 'automotive-marketplace-core' ), $dealer->address );
		$input( 'city', __( 'City', 'automotive-marketplace-core' ), $dealer->city );
		?>
	</div>
	<?php $input( 'website_url', __( 'Website', 'automotive-marketplace-core' ), $dealer->website_url, 'url' ); ?>
	<div><button class="amds-btn amds-btn--primary" type="submit"><?php esc_html_e( 'Save', 'automotive-marketplace-core' ); ?></button></div>
</form>
