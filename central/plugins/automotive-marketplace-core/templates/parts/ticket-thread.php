<?php
/**
 * Support conversation messages.
 *
 * @var array $args { ticket: object, staff: bool }
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="amc-thread">
	<?php foreach ( AMC\Support::messages( $args['ticket']->id ) as $message ) : ?>
		<?php
		$author = $message->is_staff ? get_bloginfo( 'name' ) : $args['ticket']->business_name;
		$mine   = $args['staff'] ? (bool) $message->is_staff : ! $message->is_staff;
		?>
		<article class="amc-thread__msg<?php echo $mine ? ' is-mine' : ''; ?>">
			<header><strong><?php echo esc_html( $author ); ?></strong> <time><?php echo esc_html( AMC\Portal::date( $message->created_at, true ) ); ?></time></header>
			<div><?php echo wp_kses_post( wpautop( esc_html( $message->body ) ) ); ?></div>
		</article>
	<?php endforeach; ?>
</section>
