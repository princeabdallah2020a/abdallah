<?php
/**
 * Platform admin — AI usage.
 *
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;
$table = AMC\Database::table( 'ai_usage' );
$since = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$by_scope = $wpdb->get_results( $wpdb->prepare( "SELECT scope, model, COUNT(*) AS requests, SUM(input_tokens) AS input_tokens, SUM(output_tokens) AS output_tokens FROM $table WHERE created_at >= %s GROUP BY scope, model ORDER BY requests DESC", $since ) );
$by_day   = $wpdb->get_results( $wpdb->prepare( "SELECT SUBSTR(created_at, 1, 10) AS day, COUNT(*) AS requests, SUM(input_tokens + output_tokens) AS tokens FROM $table WHERE created_at >= %s GROUP BY SUBSTR(created_at, 1, 10) ORDER BY day DESC LIMIT 30", $since ) );
$today    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE created_at >= %s", gmdate( 'Y-m-d 00:00:00' ) ) );
// phpcs:enable
$cap   = (int) AMC\Settings::get( 'ai_daily_cap' );
$error = get_transient( 'amc_ai_last_error' );

AMC\Portal::page_head( __( 'AI usage', 'automotive-marketplace-core' ), __( 'Last 30 days. Only counts are stored, never conversations.', 'automotive-marketplace-core' ) );
?>
<div class="amc-kpis">
	<div class="amc-kpi"><span class="amc-kpi__label"><?php esc_html_e( 'Requests today', 'automotive-marketplace-core' ); ?></span><strong class="amc-kpi__value"><?php echo esc_html( number_format_i18n( $today ) ); ?><?php if ( $cap ) : ?><small> / <?php echo esc_html( number_format_i18n( $cap ) ); ?></small><?php endif; ?></strong></div>
	<div class="amc-kpi"><span class="amc-kpi__label"><?php esc_html_e( 'Model', 'automotive-marketplace-core' ); ?></span><strong class="amc-kpi__value"><?php echo esc_html( AMC\Assistant::models()[ AMC\Settings::get( 'ai_model' ) ] ?? AMC\Settings::get( 'ai_model' ) ); ?></strong></div>
	<div class="amc-kpi"><span class="amc-kpi__label"><?php esc_html_e( 'Status', 'automotive-marketplace-core' ); ?></span><strong class="amc-kpi__value"><?php echo AMC\Assistant::ai_available() ? AMC\Portal::badge( 'active' ) : AMC\Portal::badge( 'pending' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong></div>
</div>
<?php if ( $error ) : ?>
	<p class="amds-alert amds-alert--error"><?php echo esc_html( __( 'Last AI error:', 'automotive-marketplace-core' ) . ' ' . $error ); ?></p>
<?php endif; ?>
<div class="amc-grid-2">
	<section class="amc-panel">
		<div class="amc-panel__head"><h2><?php esc_html_e( 'By assistant and model', 'automotive-marketplace-core' ); ?></h2></div>
		<div class="amc-table-wrap"><table class="amds-table">
			<thead><tr><th><?php esc_html_e( 'Assistant', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Model', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Requests', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Tokens in / out', 'automotive-marketplace-core' ); ?></th></tr></thead>
			<tbody>
				<?php if ( ! $by_scope ) : ?><tr><td colspan="4" class="amc-muted"><?php esc_html_e( 'Nothing here.', 'automotive-marketplace-core' ); ?></td></tr><?php endif; ?>
				<?php foreach ( (array) $by_scope as $row ) : ?>
					<tr><td><?php
					$amc_scopes = array(
						'admin'           => __( 'Administration', 'automotive-marketplace-core' ),
						'visitor'         => __( 'Website visitors', 'automotive-marketplace-core' ),
						'whatsapp_dealer' => __( 'WhatsApp — dealers', 'automotive-marketplace-core' ),
					);
					echo esc_html( $amc_scopes[ $row->scope ] ?? $row->scope );
					?></td><td><?php echo esc_html( $row->model ); ?></td><td><?php echo esc_html( number_format_i18n( $row->requests ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row->input_tokens ) . ' / ' . number_format_i18n( $row->output_tokens ) ); ?></td></tr>
				<?php endforeach; ?>
			</tbody>
		</table></div>
	</section>
	<section class="amc-panel">
		<div class="amc-panel__head"><h2><?php esc_html_e( 'By day', 'automotive-marketplace-core' ); ?></h2></div>
		<?php $max = max( 1, (int) max( array_merge( array( 1 ), wp_list_pluck( (array) $by_day, 'requests' ) ) ) ); ?>
		<ul class="amc-bars">
			<?php foreach ( (array) $by_day as $row ) : ?>
				<li><span class="amc-bars__label"><?php echo esc_html( wp_date( 'j M', strtotime( $row->day . ' 12:00:00 UTC' ) ) ); ?></span><span class="amc-bars__bar"><span style="width:<?php echo esc_attr( (int) round( 100 * $row->requests / $max ) ); ?>%"></span></span><span class="amc-bars__value"><?php echo esc_html( number_format_i18n( $row->requests ) ); ?></span></li>
			<?php endforeach; ?>
			<?php if ( ! $by_day ) : ?><li class="amc-muted"><?php esc_html_e( 'Nothing here.', 'automotive-marketplace-core' ); ?></li><?php endif; ?>
		</ul>
	</section>
</div>
