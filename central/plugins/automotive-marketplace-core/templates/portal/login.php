<?php
/**
 * Dealer login (/dealer-login/).
 *
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- carried through the form.
$redirect_to = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';

get_header();
?>
<main id="main" class="amc-main amc-auth">
	<div class="amc-auth__card">
		<h1 class="amc-auth__title"><?php esc_html_e( 'Dealer space', 'automotive-marketplace-core' ); ?></h1>
		<p class="amc-auth__lead"><?php esc_html_e( 'Log in to follow your application, your subscription and your website.', 'automotive-marketplace-core' ); ?></p>
		<?php if ( isset( AMC\Portal::$errors['form'] ) ) : ?>
			<div class="amds-alert amds-alert--error" role="alert"><?php echo esc_html( AMC\Portal::$errors['form'] ); ?></div>
		<?php endif; ?>
		<form class="amds-form" method="post" action="<?php echo esc_url( AMC\Portal::url( 'login' ) ); ?>">
			<?php wp_nonce_field( 'amc_login', '_amc_nonce', false ); ?>
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>">
			<div class="amds-field">
				<label class="amds-label" for="amc-log"><?php esc_html_e( 'Email', 'automotive-marketplace-core' ); ?></label>
				<input class="amds-input" type="text" id="amc-log" name="log" value="<?php echo esc_attr( AMC\Portal::old( 'log' ) ); ?>" autocomplete="username" required>
			</div>
			<div class="amds-field">
				<label class="amds-label" for="amc-pwd"><?php esc_html_e( 'Password', 'automotive-marketplace-core' ); ?></label>
				<input class="amds-input" type="password" id="amc-pwd" name="pwd" autocomplete="current-password" required>
			</div>
			<label class="amc-check"><input type="checkbox" name="remember" value="1"> <?php esc_html_e( 'Remember me', 'automotive-marketplace-core' ); ?></label>
			<button class="amds-btn amds-btn--primary amds-btn--lg amds-btn--block" type="submit"><?php esc_html_e( 'Log in', 'automotive-marketplace-core' ); ?></button>
		</form>
		<p class="amc-auth__links">
			<a href="<?php echo esc_url( wp_lostpassword_url( AMC\Portal::url( 'login' ) ) ); ?>"><?php esc_html_e( 'Forgot your password?', 'automotive-marketplace-core' ); ?></a>
			<a href="<?php echo esc_url( AMC\Portal::url( 'register' ) ); ?>"><?php esc_html_e( 'Not a member yet? Apply', 'automotive-marketplace-core' ); ?></a>
		</p>
	</div>
</main>
<?php
get_footer();
