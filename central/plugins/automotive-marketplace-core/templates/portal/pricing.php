<?php
/**
 * Pricing page (/pricing/). All prices come from the plans table.
 *
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

$plans     = AMC\Plans::all( true );
$durations = AMC\Plans::durations();
$providers = AMC\Payments::providers();

get_header();
?>
<main id="main" class="amc-main amc-pricing">
	<section class="amc-pricing__hero">
		<div class="amds-container">
			<p class="amc-eyebrow"><?php esc_html_e( 'For dealerships and rental agencies', 'automotive-marketplace-core' ); ?></p>
			<h1 class="amc-pricing__title"><?php esc_html_e( 'One subscription. Your website, your CRM and the marketplace.', 'automotive-marketplace-core' ); ?></h1>
			<p class="amc-pricing__lead"><?php esc_html_e( 'Every plan includes an independent website on your own domain, a management tool built for car sales and rentals, and automatic publication of your vehicles on the marketplace.', 'automotive-marketplace-core' ); ?></p>
			<div class="amc-segment amc-segment--hero" role="radiogroup" aria-label="<?php esc_attr_e( 'Commitment', 'automotive-marketplace-core' ); ?>" data-amc-duration-switch>
				<?php foreach ( $durations as $m => $discount ) : ?>
					<label>
						<input type="radio" name="pricing_months" value="<?php echo esc_attr( $m ); ?>" <?php checked( 1, $m ); ?>>
						<span>
							<?php
							/* translators: %d: number of months */
							echo esc_html( sprintf( _n( '%d month', '%d months', $m, 'automotive-marketplace-core' ), $m ) );
							?>
							<?php if ( $discount > 0 ) : ?><em>−<?php echo esc_html( rtrim( rtrim( number_format( $discount, 1, ',', '' ), '0' ), ',' ) ); ?>%</em><?php endif; ?>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<div class="amds-container">
		<div class="amc-plans">
			<?php foreach ( $plans as $plan ) : ?>
				<?php
				$prices = array();
				foreach ( array_keys( $durations ) as $m ) {
					$p            = AMC\Plans::price( $plan, $m );
					$prices[ $m ] = array(
						'monthly' => amc_format_money( $p['monthly'], $plan->currency ),
						/* translators: 1: total amount, 2: number of months */
						'billed'  => 1 === $m ? __( 'Billed monthly', 'automotive-marketplace-core' ) : sprintf( _n( '%1$s for %2$d month', '%1$s for %2$d months', $m, 'automotive-marketplace-core' ), amc_format_money( $p['total'], $plan->currency ), $m ),
						/* translators: %s: amount saved */
						'saving'  => $p['saving'] ? sprintf( __( 'You save %s', 'automotive-marketplace-core' ), amc_format_money( $p['saving'], $plan->currency ) ) : '',
						'url'     => add_query_arg( array( 'plan' => $plan->code, 'months' => $m ), AMC\Portal::url( 'register' ) ),
					);
				}
				?>
				<article class="amc-plan<?php echo $plan->is_highlighted ? ' amc-plan--highlight' : ''; ?>" data-amc-plan-prices="<?php echo esc_attr( wp_json_encode( $prices ) ); ?>">
					<?php if ( $plan->is_highlighted ) : ?>
						<span class="amc-plan__flag"><?php esc_html_e( 'Most chosen', 'automotive-marketplace-core' ); ?></span>
					<?php endif; ?>
					<h2 class="amc-plan__name"><?php echo esc_html( $plan->name ); ?></h2>
					<?php if ( $plan->tagline ) : ?><p class="amc-plan__tagline"><?php echo esc_html( $plan->tagline ); ?></p><?php endif; ?>
					<p class="amc-plan__price"><span data-amc-price-monthly><?php echo esc_html( $prices[1]['monthly'] ); ?></span><small><?php esc_html_e( '/ month', 'automotive-marketplace-core' ); ?></small></p>
					<p class="amc-plan__billed" data-amc-price-billed><?php echo esc_html( $prices[1]['billed'] ); ?></p>
					<p class="amc-plan__saving" data-amc-price-saving><?php echo esc_html( $prices[1]['saving'] ); ?></p>
					<ul class="amc-plan__limits">
						<li><strong><?php echo esc_html( number_format_i18n( $plan->vehicle_limit ) ); ?></strong> <?php esc_html_e( 'vehicles', 'automotive-marketplace-core' ); ?></li>
						<li><strong><?php echo esc_html( number_format_i18n( $plan->team_members ) ); ?></strong> <?php echo esc_html( _n( 'user', 'users', $plan->team_members, 'automotive-marketplace-core' ) ); ?></li>
						<li><strong><?php echo esc_html( number_format_i18n( $plan->ai_quota ) ); ?></strong> <?php esc_html_e( 'AI requests / month', 'automotive-marketplace-core' ); ?></li>
					</ul>
					<ul class="amc-plan__features">
						<?php foreach ( $plan->features as $feature ) : ?>
							<li><?php echo amc_icon( 'check', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $feature ); ?></li>
						<?php endforeach; ?>
					</ul>
					<a class="amds-btn amds-btn--<?php echo $plan->is_highlighted ? 'accent' : 'primary'; ?> amds-btn--lg amds-btn--block" data-amc-price-url href="<?php echo esc_url( $prices[1]['url'] ); ?>">
						<?php
						/* translators: %s: plan name */
						echo esc_html( sprintf( __( 'Choose %s', 'automotive-marketplace-core' ), $plan->name ) );
						?>
					</a>
				</article>
			<?php endforeach; ?>
		</div>

		<?php if ( $plans ) : ?>
			<section class="amc-block amc-compare" aria-labelledby="amc-compare-title">
				<h2 id="amc-compare-title"><?php esc_html_e( 'Compare the plans', 'automotive-marketplace-core' ); ?></h2>
				<div class="amc-table-wrap">
					<table class="amds-table">
						<thead><tr><th scope="col"></th><?php foreach ( $plans as $plan ) : ?><th scope="col"><?php echo esc_html( $plan->name ); ?></th><?php endforeach; ?></tr></thead>
						<tbody>
							<?php
							$yes  = amc_icon( 'check', 18 );
							$rows = array(
								__( 'Price per month', 'automotive-marketplace-core' ) => static function ( $p ) {
									return esc_html( amc_format_money( $p->price_amount, $p->currency ) );
								},
								__( 'Independent website on your domain', 'automotive-marketplace-core' ) => static function () use ( $yes ) {
									return $yes;
								},
								__( 'Dealer CRM', 'automotive-marketplace-core' ) => static function () use ( $yes ) {
									return $yes;
								},
								__( 'Listings on the marketplace', 'automotive-marketplace-core' ) => static function () use ( $yes ) {
									return $yes;
								},
								__( 'Vehicles', 'automotive-marketplace-core' ) => static function ( $p ) {
									return esc_html( number_format_i18n( $p->vehicle_limit ) );
								},
								__( 'Users', 'automotive-marketplace-core' ) => static function ( $p ) {
									return esc_html( number_format_i18n( $p->team_members ) );
								},
								__( 'AI requests per month', 'automotive-marketplace-core' ) => static function ( $p ) {
									return esc_html( number_format_i18n( $p->ai_quota ) );
								},
								__( 'Premium visibility', 'automotive-marketplace-core' ) => static function ( $p ) use ( $yes ) {
									return $p->visibility_boost ? $yes : '—';
								},
							);
							foreach ( $rows as $label => $cell ) :
								?>
								<tr><th scope="row"><?php echo esc_html( $label ); ?></th><?php foreach ( $plans as $plan ) : ?><td><?php echo $cell( $plan ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td><?php endforeach; ?></tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</section>
		<?php endif; ?>

		<section class="amc-block amc-pricing__faq" aria-labelledby="amc-faq-title">
			<h2 id="amc-faq-title"><?php esc_html_e( 'Questions', 'automotive-marketplace-core' ); ?></h2>
			<details><summary><?php esc_html_e( 'How do I pay?', 'automotive-marketplace-core' ); ?></summary>
				<p>
					<?php
					/* translators: %s: list of payment methods */
					echo esc_html( sprintf( __( 'By %s. You send the payment, then upload the receipt in your dealer space; our team confirms it.', 'automotive-marketplace-core' ), implode( ', ', array_map( static function ( $p ) { return $p->label(); }, $providers ) ) ) );
					?>
				</p>
			</details>
			<details><summary><?php esc_html_e( 'Is my website really mine?', 'automotive-marketplace-core' ); ?></summary>
				<p><?php esc_html_e( 'Yes. It runs on its own hosting and domain, with its own database and backups. The marketplace only receives the vehicles you choose to publish.', 'automotive-marketplace-core' ); ?></p></details>
			<details><summary><?php esc_html_e( 'How long does the setup take?', 'automotive-marketplace-core' ); ?></summary>
				<p><?php esc_html_e( 'After approval and payment, our team sets up your website and CRM, usually within a few working days. You follow every step from your dealer space.', 'automotive-marketplace-core' ); ?></p></details>
			<details><summary><?php esc_html_e( 'Can I change plan?', 'automotive-marketplace-core' ); ?></summary>
				<p><?php esc_html_e( 'Yes, at each renewal, from your dealer space. A renewal starts at the end of your current period.', 'automotive-marketplace-core' ); ?></p></details>
		</section>

		<section class="amc-pricing__cta">
			<h2><?php esc_html_e( 'Ready to grow your dealership?', 'automotive-marketplace-core' ); ?></h2>
			<p><?php esc_html_e( 'The application takes about five minutes.', 'automotive-marketplace-core' ); ?></p>
			<a class="amds-btn amds-btn--accent amds-btn--lg" href="<?php echo esc_url( AMC\Portal::url( 'register' ) ); ?>"><?php esc_html_e( 'Start my application', 'automotive-marketplace-core' ); ?></a>
		</section>
	</div>
</main>
<?php
get_footer();
