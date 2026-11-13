<?php
/**
 * Fallback template.
 *
 * @package AutoDealer
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="ad-page">
	<div class="amds-container">
		<?php if ( have_posts() ) : ?>
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<article <?php post_class( 'adc-prose' ); ?>>
					<h1><?php the_title(); ?></h1>
					<?php is_singular() ? the_content() : the_excerpt(); ?>
				</article>
			<?php endwhile; ?>
		<?php else : ?>
			<div class="adc-empty"><h1><?php esc_html_e( 'Nothing to show here', 'autodealer' ); ?></h1></div>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
