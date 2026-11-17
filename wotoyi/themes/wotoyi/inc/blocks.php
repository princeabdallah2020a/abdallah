<?php
/**
 * Dynamic blocks (server-rendered). Each renders from filterable data and degrades to nothing (home sections) or a branded
 * empty state (search / vehicle / dealer) when the marketplace plugin is absent.
 *
 * @package Wotoyi\Theme
 */

defined( 'ABSPATH' ) || exit;

/** @return array<string,array{title:string,attrs?:array,desc?:string}> */
function wotoyi_block_registry(): array {
	$str = static fn( string $d = '' ): array => array( 'type' => 'string', 'default' => $d );
	return array(
		'notice-strip'   => array( 'title' => __( 'Bandeau d\'information', 'wotoyi' ) ),
		'header'         => array( 'title' => __( 'En-tete Wotoyi', 'wotoyi' ) ),
		'logo'           => array( 'title' => __( 'Logo Wotoyi', 'wotoyi' ), 'attrs' => array( 'variant' => $str( 'f' ) ) ),
		'footer-brand'   => array( 'title' => __( 'Pied de page - marque', 'wotoyi' ) ),
		'footer-columns' => array( 'title' => __( 'Pied de page - liens', 'wotoyi' ) ),
		'footer-legal'   => array( 'title' => __( 'Pied de page - mentions', 'wotoyi' ) ),
		'not-found'      => array( 'title' => __( 'Page introuvable (404)', 'wotoyi' ) ),
		'chrome'         => array( 'title' => __( 'Menu mobile, assistant et bascules', 'wotoyi' ) ),
		'quicksearch'    => array( 'title' => __( 'Recherche rapide (hero)', 'wotoyi' ) ),
		'shortcuts'      => array( 'title' => __( 'Raccourcis par carrosserie', 'wotoyi' ) ),
		'listings'       => array(
			'title' => __( 'Annonces (rail ou grille)', 'wotoyi' ),
			'attrs' => array(
				'source'    => $str( 'featured' ),
				'layout'    => $str( 'rail' ),
				'eyebrow'   => $str(),
				'title'     => $str(),
				'linkLabel' => $str(),
				'linkUrl'   => $str(),
				'limit'     => array( 'type' => 'integer', 'default' => 8 ),
				'id'        => $str(),
			),
		),
		'marquee'        => array( 'title' => __( 'Defilement des marques', 'wotoyi' ) ),
		'services'       => array( 'title' => __( 'Services', 'wotoyi' ) ),
		'dealers'        => array( 'title' => __( 'Concessionnaires verifies', 'wotoyi' ) ),
		'figures'        => array( 'title' => __( 'Chiffres de la plateforme', 'wotoyi' ) ),
		'recent'         => array( 'title' => __( 'Vus recemment', 'wotoyi' ) ),
		'search'         => array( 'title' => __( 'Recherche et resultats (marketplace)', 'wotoyi' ) ),
		'vehicle'        => array( 'title' => __( 'Fiche vehicule (marketplace)', 'wotoyi' ) ),
		'dealer'         => array( 'title' => __( 'Page concessionnaire (marketplace)', 'wotoyi' ) ),
		'slot'           => array( 'title' => __( 'Emplacement extensible', 'wotoyi' ), 'attrs' => array( 'name' => $str() ) ),
	);
}

add_action(
	'init',
	static function (): void {
		foreach ( wotoyi_block_registry() as $slug => $def ) {
			register_block_type(
				'wotoyi/' . $slug,
				array(
					'api_version'     => 3,
					'title'           => $def['title'],
					'category'        => 'design',
					'icon'            => 'car' === $slug ? 'car' : 'layout',
					'attributes'      => $def['attrs'] ?? array(),
					'supports'        => array( 'html' => false, 'autoRegister' => true, 'align' => false ),
					'render_callback' => static function ( array $attrs = array(), string $content = '', $block = null ) use ( $slug ): string {
						return wotoyi_view_html( 'block-' . $slug, array( 'attrs' => $attrs, 'content' => $content, 'block' => $block ) );
					},
				)
			);
		}
	}
);

/** [wotoyi_slot name="..."] - same extension point as the block, for classic content and page builders. */
add_shortcode(
	'wotoyi_slot',
	static function ( $atts ): string {
		$atts = shortcode_atts( array( 'name' => '' ), (array) $atts, 'wotoyi_slot' );
		return wotoyi_view_html( 'block-slot', array( 'attrs' => array( 'name' => sanitize_key( $atts['name'] ) ) ) );
	}
);

/** Section wrapper used by home blocks. */
function wotoyi_section_open( string $id, string $tone = '', string $label_id = '' ): string {
	return '<section class="wy-section' . ( 'sand' === $tone ? ' wy-section--sand' : '' ) . '"' . ( '' !== $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . ( '' !== $label_id ? ' aria-labelledby="' . esc_attr( $label_id ) . '"' : '' ) . '>';
}

/** Block comment string for pattern files: <!-- wp:wotoyi/name {"a":"b"} /--> (attribute values already translated by the caller). */
function wotoyi_block_comment( string $name, array $attrs = array() ): string {
	$json = $attrs ? ' ' . wp_json_encode( $attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ) : '';
	return '<!-- wp:wotoyi/' . sanitize_key( $name ) . $json . ' /-->';
}
