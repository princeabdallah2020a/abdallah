<?php
/**
 * Public vehicle card.
 *
 * @var array $args { vehicle: object, eager?: bool }
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

$v     = $args['vehicle'];
$url   = adc_vehicle_url( $v );
$thumb = $v->thumbnail_id ? wp_get_attachment_image( $v->thumbnail_id, 'medium_large', false, array( 'alt' => '', 'loading' => empty( $args['eager'] ) ? 'lazy' : 'eager', 'sizes' => '(max-width: 640px) 100vw, 400px' ) ) : '';
?>
<article class="adc-card">
	<a class="adc-card__media" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true">
		<?php echo $thumb ? $thumb : '<span class="adc-card__placeholder">' . adc_icon( 'car', 40 ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<span class="adc-card__badges">
			<?php if ( 'rent' === $v->listing_type ) : ?><span class="amds-badge amds-badge--info"><?php esc_html_e( 'For rent', 'automotive-dealer-crm' ); ?></span><?php endif; ?>
			<?php if ( 'reserved' === $v->status ) : ?><span class="amds-badge amds-badge--warning"><?php esc_html_e( 'Reserved', 'automotive-dealer-crm' ); ?></span><?php endif; ?>
			<?php if ( $v->is_featured ) : ?><span class="amds-badge amds-badge--accent"><?php esc_html_e( 'Featured', 'automotive-dealer-crm' ); ?></span><?php endif; ?>
		</span>
	</a>
	<div class="adc-card__body">
		<h3 class="adc-card__title"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $v->title ); ?></a></h3>
		<p class="adc-card__price"><?php echo esc_html( adc_vehicle_price( $v ) ); ?></p>
		<ul class="adc-card__specs">
			<?php if ( $v->year ) : ?><li><?php echo adc_icon( 'calendar', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $v->year ); ?></li><?php endif; ?>
			<?php if ( $v->mileage_km ) : ?><li><?php echo adc_icon( 'gauge', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( adc_km( $v->mileage_km ) ); ?></li><?php endif; ?>
			<?php if ( $v->fuel_type ) : ?><li><?php echo adc_icon( 'fuel', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( adc_label( 'fuel_types', $v->fuel_type ) ); ?></li><?php endif; ?>
			<?php if ( $v->transmission ) : ?><li><?php echo adc_icon( 'gearbox', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( adc_label( 'transmissions', $v->transmission ) ); ?></li><?php endif; ?>
		</ul>
	</div>
</article>
