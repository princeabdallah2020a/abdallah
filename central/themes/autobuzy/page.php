<?php
/**
 * Default page template (About, Services, How it works, FAQ, legal pages).
 *
 * @package AutoBuzy
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="am-page">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<header class="amc-page-header">
			<div class="amds-container">
				<nav class="amds-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'autobuzy' ); ?>">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'autobuzy' ); ?></a>
					<span aria-current="page"><?php the_title(); ?></span>
				</nav>
				<h1 class="amc-page-header__title"><?php the_title(); ?></h1>
				<?php if ( has_excerpt() ) : ?>
					<p class="amc-page-header__lead"><?php echo esc_html( get_the_excerpt() ); ?></p>
				<?php endif; ?>
			</div>
		</header>
		<div class="amds-container">
			<div class="am-prose"><?php the_content(); ?></div>
		</div>
	<?php endwhile; ?>
</main>
<?php
get_footer();
