<?php
/**
 * Fallback template (blog index, archives, single posts).
 *
 * @package AutoMarket
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="am-page">
	<header class="amc-page-header">
		<div class="amds-container">
			<h1 class="amc-page-header__title">
				<?php
				if ( is_singular() ) {
					single_post_title();
				} elseif ( is_search() ) {
					/* translators: %s: search query */
					echo esc_html( sprintf( __( 'Search results for "%s"', 'automarket' ), get_search_query() ) );
				} elseif ( is_archive() ) {
					the_archive_title();
				} else {
					esc_html_e( 'News', 'automarket' );
				}
				?>
			</h1>
		</div>
	</header>
	<div class="amds-container">
		<?php if ( have_posts() ) : ?>
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<article <?php post_class( 'am-prose' ); ?>>
					<?php if ( ! is_singular() ) : ?>
						<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						<?php the_excerpt(); ?>
					<?php else : ?>
						<?php the_content(); ?>
					<?php endif; ?>
				</article>
			<?php endwhile; ?>
			<?php the_posts_pagination(); ?>
		<?php else : ?>
			<div class="amds-empty"><h2 class="amds-empty__title"><?php esc_html_e( 'Nothing to show yet', 'automarket' ); ?></h2></div>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
