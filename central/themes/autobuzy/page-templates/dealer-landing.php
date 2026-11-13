<?php
/**
 * Template Name: Become a dealer
 *
 * Phase 1: presentation + membership request form. Online registration,
 * plan selection and payment are delivered in Phase 2.
 *
 * @package AutoBuzy
 */

defined( 'ABSPATH' ) || exit;

$has = autobuzy_has_marketplace();

get_header();
?>
<main id="main">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<section class="am-hero am-hero--compact">
			<div class="amds-container am-hero__inner">
				<p class="am-eyebrow am-eyebrow--on-dark"><?php esc_html_e( 'For dealerships and rental agencies', 'autobuzy' ); ?></p>
				<h1 class="am-hero__title"><?php the_title(); ?></h1>
				<?php if ( has_excerpt() ) : ?>
					<p class="am-hero__lead"><?php echo esc_html( get_the_excerpt() ); ?></p>
				<?php endif; ?>
				<div class="am-hero__actions">
					<a class="amds-btn amds-btn--accent amds-btn--lg" href="<?php echo esc_url( $has ? amc_portal_url( 'register' ) : '#amc-inquiry' ); ?>"><?php esc_html_e( 'Apply online', 'autobuzy' ); ?></a>
					<?php if ( $has ) : ?>
						<a class="amds-btn amds-btn--outline-on-dark amds-btn--lg" href="<?php echo esc_url( amc_portal_url( 'pricing' ) ); ?>"><?php esc_html_e( 'See plans and prices', 'autobuzy' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</section>

		<section class="am-section">
			<div class="amds-container">
				<?php autobuzy_section_head( __( 'What your membership includes', 'autobuzy' ) ); ?>
				<div class="am-features">
					<?php
					$items = array(
						array( 'globe', __( 'Independent website', 'autobuzy' ), __( 'A professional website on your own domain and hosting, with your logo, colors and vehicles.', 'autobuzy' ) ),
						array( 'chart', __( 'Dealer CRM', 'autobuzy' ), __( 'Manage stock, leads, customers, sales and rentals from one dashboard.', 'autobuzy' ) ),
						array( 'store', __( 'Marketplace listings', 'autobuzy' ), __( 'Your published vehicles appear on the marketplace and stay in sync with your stock.', 'autobuzy' ) ),
						array( 'users', __( 'Team access', 'autobuzy' ), __( 'Invite your sales and rental agents with the right permissions.', 'autobuzy' ) ),
					);
					foreach ( $items as $item ) :
						?>
						<div class="am-feature">
							<span class="am-feature__icon"><?php echo autobuzy_icon( $item[0], 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<h3 class="am-feature__title"><?php echo esc_html( $item[1] ); ?></h3>
							<p><?php echo esc_html( $item[2] ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>

		<section class="am-section am-section--subtle">
			<div class="amds-container">
				<?php autobuzy_section_head( __( 'How to join', 'autobuzy' ) ); ?>
				<ol class="am-steps">
					<?php
					$steps = array(
						array( __( 'Apply online', 'autobuzy' ), __( 'Choose your plan and send your application with your business documents.', 'autobuzy' ) ),
						array( __( 'Verification', 'autobuzy' ), __( 'Our team checks your business information and helps you choose a plan.', 'autobuzy' ) ),
						array( __( 'Setup', 'autobuzy' ), __( 'We set up your website and your CRM and connect them to the marketplace.', 'autobuzy' ) ),
						array( __( 'Publish', 'autobuzy' ), __( 'You add your vehicles: they go live on your website and on the marketplace.', 'autobuzy' ) ),
					);
					foreach ( $steps as $step ) :
						?>
						<li class="am-step"><h3 class="am-step__title"><?php echo esc_html( $step[0] ); ?></h3><p><?php echo esc_html( $step[1] ); ?></p></li>
					<?php endforeach; ?>
				</ol>
			</div>
		</section>

		<section class="am-section">
			<div class="amds-container am-split">
				<div>
					<h2 class="am-section__title"><?php esc_html_e( 'A question before you start?', 'autobuzy' ); ?></h2>
					<div class="am-prose"><?php the_content(); ?></div>
				</div>
				<div class="amds-surface">
					<?php
					if ( $has ) {
						AMC\Inquiries::render_form( 'dealer_interest' );
					}
					?>
				</div>
			</div>
		</section>
	<?php endwhile; ?>
</main>
<?php
get_footer();
