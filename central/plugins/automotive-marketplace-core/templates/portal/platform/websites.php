<?php
/**
 * Platform admin — dealer website setup jobs.
 *
 * @var array $args { item: int }
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

use AMC\Portal;

$job = $args['item'] ? AMC\Provisioning::get( $args['item'] ) : null;

if ( $job ) :
	$provider             = AMC\Provisioning::provider( $job->provider );
	$labels               = $provider->steps();
	list( $done, $total ) = AMC\Provisioning::progress( $job );
	Portal::page_head( $job->business_name, $provider->label(), Portal::badge( $job->status ) );
	?>
	<div class="amc-grid-2">
		<section class="amc-panel">
			<div class="amc-panel__head"><h2><?php esc_html_e( 'Checklist', 'automotive-marketplace-core' ); ?></h2><span class="amc-panel__meta"><?php echo esc_html( $done . ' / ' . $total ); ?></span></div>
			<div class="amc-progress"><span style="width:<?php echo esc_attr( (int) round( 100 * $done / max( 1, $total ) ) ); ?>%"></span></div>
			<ul class="amc-checklist">
				<?php foreach ( $job->steps as $key => $step ) : ?>
					<?php $is_done = ! empty( $step['done'] ); ?>
					<li class="<?php echo $is_done ? 'is-done' : ''; ?>">
						<form method="post">
							<?php Portal::action_fields( 'job_step' ); ?>
							<input type="hidden" name="id" value="<?php echo esc_attr( $job->id ); ?>">
							<input type="hidden" name="step" value="<?php echo esc_attr( $key ); ?>">
							<input type="hidden" name="done" value="<?php echo $is_done ? '' : '1'; ?>">
							<button class="amc-checklist__box amc-checklist__toggle" type="submit" aria-label="<?php echo esc_attr( $is_done ? __( 'Mark as not done', 'automotive-marketplace-core' ) : __( 'Mark as done', 'automotive-marketplace-core' ) ); ?>"><?php echo $is_done ? amc_icon( 'check', 14 ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
						</form>
						<span class="amc-checklist__text"><?php echo esc_html( $labels[ $key ] ?? $key ); ?><?php if ( $is_done && ! empty( $step['at'] ) ) : ?><small><?php echo esc_html( Portal::date( $step['at'], true ) ); ?></small><?php endif; ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="amc-muted"><?php esc_html_e( 'When every step is done, the dealer is notified that the website is ready.', 'automotive-marketplace-core' ); ?></p>
		</section>
		<form class="amc-panel amds-form" method="post">
			<div class="amc-panel__head"><h2><?php esc_html_e( 'Website details', 'automotive-marketplace-core' ); ?></h2></div>
			<?php Portal::action_fields( 'job_details' ); ?>
			<input type="hidden" name="id" value="<?php echo esc_attr( $job->id ); ?>">
			<dl class="amc-dl">
				<div><dt><?php esc_html_e( 'Option', 'automotive-marketplace-core' ); ?></dt><dd><?php echo esc_html( AMC\Memberships::website_options()[ $job->website_option ] ?? $job->website_option ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Domain', 'automotive-marketplace-core' ); ?></dt><dd><?php echo esc_html( $job->domain ? $job->domain : '—' ); ?></dd></div>
			</dl>
			<div class="amds-field"><label class="amds-label" for="amc-j-site"><?php esc_html_e( 'Website URL', 'automotive-marketplace-core' ); ?></label><input class="amds-input" type="url" id="amc-j-site" name="site_url" value="<?php echo esc_attr( $job->site_url ); ?>" placeholder="https://"></div>
			<div class="amds-field"><label class="amds-label" for="amc-j-admin"><?php esc_html_e( 'CRM / administration URL', 'automotive-marketplace-core' ); ?></label><input class="amds-input" type="url" id="amc-j-admin" name="admin_url" value="<?php echo esc_attr( $job->admin_url ); ?>" placeholder="https://…/manager/"></div>
			<div class="amds-field"><label class="amds-label" for="amc-j-notes"><?php esc_html_e( 'Internal notes (never shown to the dealer — no passwords here)', 'automotive-marketplace-core' ); ?></label><textarea class="amds-textarea" id="amc-j-notes" name="notes" rows="5"><?php echo esc_textarea( $job->notes ); ?></textarea></div>
			<div><button class="amds-btn amds-btn--primary" type="submit"><?php esc_html_e( 'Save', 'automotive-marketplace-core' ); ?></button></div>
		</form>
	</div>
	<p><a class="amds-link" href="<?php echo esc_url( Portal::url( 'platform', 'dealers', $job->dealer_id ) ); ?>"><?php esc_html_e( 'Open the dealer record', 'automotive-marketplace-core' ); ?> →</a></p>
	<?php
	return;
endif;

$jobs = AMC\Provisioning::list();
Portal::page_head( __( 'Dealer websites', 'automotive-marketplace-core' ), __( 'A setup job is created automatically when an application is approved.', 'automotive-marketplace-core' ) );
?>
<section class="amc-panel">
	<div class="amc-table-wrap">
		<table class="amds-table">
			<thead><tr><th><?php esc_html_e( 'Dealership', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Domain', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Progress', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Updated', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Status', 'automotive-marketplace-core' ); ?></th></tr></thead>
			<tbody>
				<?php if ( ! $jobs ) : ?>
					<tr><td colspan="5" class="amc-muted"><?php esc_html_e( 'Nothing here.', 'automotive-marketplace-core' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $jobs as $row ) : ?>
					<?php list( $d, $t ) = AMC\Provisioning::progress( $row ); ?>
					<tr>
						<td><a href="<?php echo esc_url( Portal::url( 'platform', 'websites', $row->id ) ); ?>"><strong><?php echo esc_html( $row->business_name ); ?></strong></a></td>
						<td><?php echo esc_html( $row->domain ? $row->domain : '—' ); ?></td>
						<td><div class="amc-progress amc-progress--inline"><span style="width:<?php echo esc_attr( (int) round( 100 * $d / max( 1, $t ) ) ); ?>%"></span></div> <small><?php echo esc_html( $d . '/' . $t ); ?></small></td>
						<td><?php echo esc_html( Portal::date( $row->updated_at ) ); ?></td>
						<td><?php echo Portal::badge( $row->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>
