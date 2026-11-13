<?php
/**
 * Dealer space — documents.
 *
 * @var array $args { dealer: object }
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

use AMC\Portal;

$docs  = AMC\Documents::for_dealer( $args['dealer']->id );
$types = AMC\Documents::types();
unset( $types['payment_proof'] );
Portal::page_head( __( 'Documents', 'automotive-marketplace-core' ), __( 'Stored privately: only you and our team can open them.', 'automotive-marketplace-core' ) );
?>
<div class="amc-grid-2">
	<section class="amc-panel">
		<div class="amc-panel__head"><h2><?php esc_html_e( 'Sent documents', 'automotive-marketplace-core' ); ?></h2></div>
		<?php if ( ! $docs ) : ?>
			<p class="amc-muted"><?php esc_html_e( 'No document yet.', 'automotive-marketplace-core' ); ?></p>
		<?php else : ?>
			<ul class="amc-files">
				<?php foreach ( $docs as $doc ) : ?>
					<li>
						<?php echo amc_icon( 'shield', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span><strong><?php echo esc_html( AMC\Documents::type_label( $doc->type ) ); ?></strong><small><?php echo esc_html( $doc->original_name . ' · ' . Portal::date( $doc->created_at ) ); ?></small></span>
						<a class="amds-link" href="<?php echo esc_url( AMC\Documents::url( $doc->id ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'automotive-marketplace-core' ); ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
	<form class="amc-panel amds-form" method="post" enctype="multipart/form-data">
		<div class="amc-panel__head"><h2><?php esc_html_e( 'Add a document', 'automotive-marketplace-core' ); ?></h2></div>
		<?php Portal::action_fields( 'document' ); ?>
		<div class="amds-field">
			<label class="amds-label" for="amc-doc-type"><?php esc_html_e( 'Type', 'automotive-marketplace-core' ); ?></label>
			<select class="amds-select" id="amc-doc-type" name="type">
				<?php foreach ( $types as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="amds-field">
			<label class="amds-label" for="amc-doc-file"><?php esc_html_e( 'File (PDF, JPG, PNG, WEBP · 5 MB max)', 'automotive-marketplace-core' ); ?></label>
			<input class="amds-input" type="file" id="amc-doc-file" name="document" accept="application/pdf,image/jpeg,image/png,image/webp" required>
		</div>
		<div><button class="amds-btn amds-btn--primary" type="submit"><?php esc_html_e( 'Upload', 'automotive-marketplace-core' ); ?></button></div>
	</form>
</div>
