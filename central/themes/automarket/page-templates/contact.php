<?php
/**
 * Template Name: Contact
 *
 * @package AutoMarket
 */

defined( 'ABSPATH' ) || exit;

$has      = automarket_has_marketplace();
$phone    = $has ? AMC\Settings::get( 'company_phone' ) : '';
$whatsapp = $has ? amc_whatsapp_url( AMC\Settings::get( 'company_whatsapp' ) ) : '';
$address  = $has ? AMC\Settings::get( 'company_address' ) : '';

get_header();
?>
<main id="main" class="am-page">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<header class="amc-page-header">
			<div class="amds-container">
				<nav class="amds-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'automarket' ); ?>">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'automarket' ); ?></a>
					<span aria-current="page"><?php the_title(); ?></span>
				</nav>
				<h1 class="amc-page-header__title"><?php the_title(); ?></h1>
				<?php if ( has_excerpt() ) : ?>
					<p class="amc-page-header__lead"><?php echo esc_html( get_the_excerpt() ); ?></p>
				<?php endif; ?>
			</div>
		</header>
		<div class="amds-container am-split">
			<div>
				<div class="am-prose"><?php the_content(); ?></div>
				<?php if ( $address || $phone || $whatsapp ) : ?>
					<ul class="am-contact-list">
						<?php if ( $address ) : ?><li><?php echo automarket_icon( 'pin', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( $address ); ?></span></li><?php endif; ?>
						<?php if ( $phone ) : ?><li><?php echo automarket_icon( 'phone', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><a href="<?php echo esc_url( 'tel:' . amc_normalize_phone( $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></li><?php endif; ?>
						<?php if ( $whatsapp ) : ?><li><?php echo automarket_icon( 'chat', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><a href="<?php echo esc_url( $whatsapp ); ?>" target="_blank" rel="noopener">WhatsApp</a></li><?php endif; ?>
					</ul>
				<?php endif; ?>
			</div>
			<div class="amds-surface">
				<?php
				if ( $has ) {
					AMC\Inquiries::render_form( 'contact' );
				}
				?>
			</div>
		</div>
	<?php endwhile; ?>
</main>
<?php
get_footer();
