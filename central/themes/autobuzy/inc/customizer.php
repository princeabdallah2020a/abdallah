<?php
/**
 * Customizer: homepage hero content (Appearance → Customize → Homepage).
 *
 * @package AutoBuzy
 */

defined( 'ABSPATH' ) || exit;

/**
 * Theme setting with a translated default.
 *
 * @param string $key Setting key.
 */
function autobuzy_option( $key ) {
	$defaults = array(
		'hero_title'    => __( 'Find your next car from trusted dealerships', 'autobuzy' ),
		'hero_subtitle' => __( 'Vehicles for sale and for rent, published directly by verified dealerships.', 'autobuzy' ),
		'hero_image'    => '',
	);
	$value    = get_theme_mod( 'autobuzy_' . $key, '' );
	return '' !== $value ? $value : ( isset( $defaults[ $key ] ) ? $defaults[ $key ] : '' );
}

add_action(
	'customize_register',
	static function ( $wp_customize ) {
		$wp_customize->add_section(
			'autobuzy_home',
			array(
				'title'    => __( 'Homepage', 'autobuzy' ),
				'priority' => 30,
			)
		);

		$fields = array(
			'hero_title'    => array( __( 'Hero title', 'autobuzy' ), 'text' ),
			'hero_subtitle' => array( __( 'Hero subtitle', 'autobuzy' ), 'textarea' ),
		);
		foreach ( $fields as $key => $field ) {
			$wp_customize->add_setting(
				'autobuzy_' . $key,
				array(
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				)
			);
			$wp_customize->add_control(
				'autobuzy_' . $key,
				array(
					'label'       => $field[0],
					'description' => __( 'Leave empty to use the default text.', 'autobuzy' ),
					'section'     => 'autobuzy_home',
					'type'        => $field[1],
				)
			);
		}

		$wp_customize->add_setting(
			'autobuzy_hero_image',
			array(
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
			)
		);
		$wp_customize->add_control(
			new WP_Customize_Image_Control(
				$wp_customize,
				'autobuzy_hero_image',
				array(
					'label'       => __( 'Hero background photo', 'autobuzy' ),
					'description' => __( 'Optional. Use a wide, high-quality vehicle photo you own the rights to (at least 1920 px wide).', 'autobuzy' ),
					'section'     => 'autobuzy_home',
				)
			)
		);
	}
);
