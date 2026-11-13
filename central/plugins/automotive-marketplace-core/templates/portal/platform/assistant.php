<?php
/**
 * Platform admin — AI assistant.
 *
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

AMC\Portal::page_head( __( 'AI assistant', 'automotive-marketplace-core' ), __( 'Ask about memberships, payments, listings and activity, or have a message drafted. Read-only.', 'automotive-marketplace-core' ) );
if ( ! AMC\Assistant::ai_available() ) :
	?>
	<div class="amds-alert amds-alert--warning">
		<?php esc_html_e( 'The AI assistant is not configured yet. Add your Anthropic API key in Marketplace → Settings → AI assistant.', 'automotive-marketplace-core' ); ?>
		<?php if ( current_user_can( 'manage_options' ) ) : ?><a href="<?php echo esc_url( admin_url( 'admin.php?page=amc-settings' ) ); ?>"><?php esc_html_e( 'Open the settings', 'automotive-marketplace-core' ); ?></a><?php endif; ?>
	</div>
	<?php
endif;
AMC\Assistant_Ui::render_inline();
