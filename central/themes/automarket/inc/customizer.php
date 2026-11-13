<?php
/**
 * Customizer: homepage hero content (Appearance → Customize → Homepage).
 *
 * @package AutoMarket
 */

defined( 'ABSPATH' ) || exit;

/**
 * Theme setting with a translated default.
 *
 * @param string $key Setting key.
 */
function automarket_option( $key ) {
	$defaults = array(
		'hero_title'    => __( 'Find your next car from trusted dealerships', 'automarket' ),
		'hero_subtitle' => __( 'Vehicles for sale and for rent, published directly by verified dealerships.', 'automarket' ),
		'hero_image'    => '',
	);
	$value    = get_theme_mod( 'automarket_' . $key, '' );
	return '' !== $value ? $value : ( isset( $defaults[ $key ] ) ? $defaults[ $key ] : '' );
}

add_action(
	'customize_register',
	static function ( $wp_customize ) {
		$wp_customize->add_section(
			'automarket_home',
			array(
				'title'    => __( 'Homepage', 'automarket' ),
				'priority' => 30,
			)
		);

		$fields = array(
			'hero_title'    => array( __( 'Hero title', 'automarket' ), 'text' ),
			'hero_subtitle' => array( __( 'Hero subtitle', 'automarket' ), 'textarea' ),
		);
		foreach ( $fields as $key => $field ) {
			$wp_customize->add_setting(
				'automarket_' . $key,
				array(
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				)
			);
			$wp_customize->add_control(
				'automarket_' . $key,
				array(
					'label'       => $field[0],
					'description' => __( 'Leave empty to use the default text.', 'automarket' ),
					'section'     => 'automarket_home',
					'type'        => $field[1],
				)
			);
		}

		$wp_customize->add_setting(
			'automarket_hero_image',
			array(
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
			)
		);
		$wp_customize->add_control(
			new WP_Customize_Image_Control(
				$wp_customize,
				'automarket_hero_image',
				array(
					'label'       => __( 'Hero background photo', 'automarket' ),
					'description' => __( 'Optional. Use a wide, high-quality vehicle photo you own the rights to (at least 1920 px wide).', 'automarket' ),
					'section'     => 'automarket_home',
				)
			)
		);
	}
);
