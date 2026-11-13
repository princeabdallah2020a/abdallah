<?php
/**
 * Minimal page wrapper for login and invitation screens.
 *
 * @var array $args { title: string, body: callable }
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

$business = adc_business();
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $args['title'] . ' – ' . $business->name ); ?></title>
	<?php wp_head(); ?>
</head>
<body class="adc-auth-body">
<main class="adc-auth">
	<div class="adc-auth__card">
		<div class="adc-auth__brand">
			<?php if ( $business->logo ) : ?>
				<img src="<?php echo esc_url( $business->logo ); ?>" alt="" width="48" height="48">
			<?php else : ?>
				<span class="adc-side__mark"><?php echo esc_html( adc_initials( $business->name ) ); ?></span>
			<?php endif; ?>
			<span><?php echo esc_html( $business->name ); ?></span>
		</div>
		<h1 class="adc-auth__title"><?php echo esc_html( $args['title'] ); ?></h1>
		<?php if ( isset( ADC\Manager::$errors['form'] ) ) : ?>
			<div class="amds-alert amds-alert--error" role="alert"><?php echo esc_html( ADC\Manager::$errors['form'] ); ?></div>
		<?php endif; ?>
		<?php call_user_func( $args['body'] ); ?>
	</div>
</main>
<?php wp_footer(); ?>
</body>
</html>
