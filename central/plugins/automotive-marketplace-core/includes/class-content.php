<?php
/**
 * Editorial content managed with native WordPress tools:
 * - company pages created once at activation (then fully editable);
 * - testimonials (private custom post type).
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Content {

	const PAGES_OPTION = 'amc_pages';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_types' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_amc_testimonial', array( __CLASS__, 'save_testimonial' ) );
	}

	public static function register_post_types() {
		register_post_type(
			'amc_testimonial',
			array(
				'labels'              => array(
					'name'          => __( 'Testimonials', 'automotive-marketplace-core' ),
					'singular_name' => __( 'Testimonial', 'automotive-marketplace-core' ),
					'add_new_item'  => __( 'Add testimonial', 'automotive-marketplace-core' ),
					'edit_item'     => __( 'Edit testimonial', 'automotive-marketplace-core' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'amc-marketplace',
				'exclude_from_search' => true,
				'supports'            => array( 'title', 'editor' ),
				'capability_type'     => 'page',
			)
		);
	}

	public static function meta_boxes() {
		add_meta_box(
			'amc_testimonial_role',
			__( 'Author role or company', 'automotive-marketplace-core' ),
			static function ( $post ) {
				wp_nonce_field( 'amc_testimonial', '_amc_testimonial_nonce' );
				echo '<p><label class="screen-reader-text" for="amc_role">' . esc_html__( 'Author role or company', 'automotive-marketplace-core' ) . '</label>';
				echo '<input type="text" class="widefat" id="amc_role" name="amc_role" value="' . esc_attr( get_post_meta( $post->ID, '_amc_role', true ) ) . '"></p>';
				echo '<p class="description">' . esc_html__( 'The title is the author name; the content is the quote. Only publish testimonials from real customers, with their consent.', 'automotive-marketplace-core' ) . '</p>';
			},
			'amc_testimonial',
			'normal'
		);
	}

	public static function save_testimonial( $post_id ) {
		if ( ! isset( $_POST['_amc_testimonial_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_amc_testimonial_nonce'] ) ), 'amc_testimonial' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, '_amc_role', isset( $_POST['amc_role'] ) ? sanitize_text_field( wp_unslash( $_POST['amc_role'] ) ) : '' );
	}

	/**
	 * Published testimonials.
	 *
	 * @param int $limit Max items.
	 * @return array[] { name, role, quote }
	 */
	public static function testimonials( $limit = 3 ) {
		$posts = get_posts(
			array(
				'post_type'      => 'amc_testimonial',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'menu_order date',
				'order'          => 'DESC',
			)
		);
		$out   = array();
		foreach ( $posts as $post ) {
			$out[] = array(
				'name'  => get_the_title( $post ),
				'role'  => get_post_meta( $post->ID, '_amc_role', true ),
				'quote' => wp_strip_all_tags( $post->post_content ),
			);
		}
		return $out;
	}

	/**
	 * URL of a company page created by the installer, '' when the page does
	 * not exist or is not published (links are then hidden by the theme).
	 *
	 * @param string $key about|services|how-it-works|contact|faq|privacy-policy|terms|join-dealer.
	 */
	public static function page_url( $key ) {
		$pages = get_option( self::PAGES_OPTION, array() );
		if ( empty( $pages[ $key ] ) || 'publish' !== get_post_status( (int) $pages[ $key ] ) ) {
			return '';
		}
		return get_permalink( (int) $pages[ $key ] );
	}

	/**
	 * Create the company pages that do not exist yet. Existing pages (matched
	 * by slug) are adopted and never modified.
	 */
	public static function install_pages() {
		$pages = get_option( self::PAGES_OPTION, array() );
		$pages = is_array( $pages ) ? $pages : array();

		foreach ( self::page_definitions() as $slug => $definition ) {
			if ( ! empty( $pages[ $slug ] ) && get_post( (int) $pages[ $slug ] ) ) {
				continue;
			}
			$existing = get_page_by_path( $slug );
			if ( $existing ) {
				$pages[ $slug ] = $existing->ID;
				continue;
			}
			$id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => $definition['status'],
					'post_name'    => $slug,
					'post_title'   => $definition['title'],
					'post_excerpt' => $definition['excerpt'],
					'post_content' => $definition['content'],
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				$pages[ $slug ] = $id;
				if ( ! empty( $definition['template'] ) ) {
					update_post_meta( $id, '_wp_page_template', $definition['template'] );
				}
			}
		}
		update_option( self::PAGES_OPTION, $pages, false );
	}

	/** Build block-editor markup from a compact list of [ tag, text ] pairs. */
	private static function blocks( array $items ) {
		$html = '';
		foreach ( $items as $item ) {
			list( $tag, $text ) = $item;
			switch ( $tag ) {
				case 'h2':
					$html .= "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . esc_html( $text ) . "</h2>\n<!-- /wp:heading -->\n\n";
					break;
				case 'ul':
					$html .= "<!-- wp:list -->\n<ul class=\"wp-block-list\">";
					foreach ( $text as $li ) {
						$html .= "<!-- wp:list-item -->\n<li>" . esc_html( $li ) . "</li>\n<!-- /wp:list-item -->";
					}
					$html .= "</ul>\n<!-- /wp:list -->\n\n";
					break;
				case 'faq':
					$html .= "<!-- wp:details -->\n<details class=\"wp-block-details\"><summary>" . esc_html( $text[0] ) . "</summary><!-- wp:paragraph -->\n<p>" . esc_html( $text[1] ) . "</p>\n<!-- /wp:paragraph --></details>\n<!-- /wp:details -->\n\n";
					break;
				default:
					$html .= "<!-- wp:paragraph -->\n<p>" . esc_html( $text ) . "</p>\n<!-- /wp:paragraph -->\n\n";
			}
		}
		return $html;
	}

	/**
	 * Initial French content (launch language). Everything is editable in
	 * Pages afterwards. Legal pages are created as DRAFTS: they are templates
	 * that must be completed and validated by a legal professional first.
	 */
	private static function page_definitions() {
		$site = get_bloginfo( 'name' );

		return array(
			'about'          => array(
				'title'   => 'À propos',
				'status'  => 'publish',
				'excerpt' => 'Une plateforme technologique au service des concessionnaires et des acheteurs de véhicules.',
				'content' => self::blocks(
					array(
						array( 'p', $site . ' réunit sur une seule plateforme les véhicules proposés par des concessionnaires indépendants, et fournit à chaque concessionnaire ses propres outils numériques : un site web à son nom, un logiciel de gestion commerciale et une présence sur la marketplace.' ),
						array( 'h2', 'Notre mission' ),
						array( 'p', 'Rendre l’achat et la location de véhicules plus simples et plus transparents, en donnant aux professionnels de l’automobile des outils modernes, accessibles et adaptés à leur marché.' ),
						array( 'h2', 'Notre approche' ),
						array( 'ul', array( 'Chaque concessionnaire reste propriétaire de son site, de ses données et de sa relation client.', 'Les annonces de la marketplace proviennent directement du stock des concessionnaires et sont mises à jour automatiquement.', 'La sécurité et la confidentialité des données sont intégrées dès la conception.' ) ),
						array( 'h2', 'Où nous opérons' ),
						array( 'p', 'Nous démarrons à Dakar, avec l’ambition de servir l’ensemble du Sénégal puis d’autres pays.' ),
					)
				),
			),
			'services'       => array(
				'title'   => 'Nos services',
				'status'  => 'publish',
				'excerpt' => 'Site web indépendant, CRM, marketplace et assistant intelligent pour les concessionnaires.',
				'content' => self::blocks(
					array(
						array( 'h2', 'Un site web indépendant pour chaque concessionnaire' ),
						array( 'p', 'Votre propre nom de domaine, votre identité visuelle, votre catalogue. Le site vous appartient et fonctionne indépendamment de la marketplace.' ),
						array( 'h2', 'Un CRM conçu pour l’automobile' ),
						array( 'p', 'Gestion du stock, des prospects, des clients, des ventes et des locations, avec des accès adaptés à chaque membre de votre équipe.' ),
						array( 'h2', 'La marketplace' ),
						array( 'p', 'Vos véhicules publiés sont automatiquement visibles sur la marketplace, auprès d’acheteurs qui comparent les offres de plusieurs concessionnaires.' ),
						array( 'h2', 'Un assistant intelligent (à venir)' ),
						array( 'p', 'Un assistant accessible par WhatsApp, en français, en wolof et en anglais, pour consulter et mettre à jour votre activité. Cette fonctionnalité est en cours de développement et sera activée progressivement.' ),
					)
				),
			),
			'how-it-works'   => array(
				'title'   => 'Comment ça marche',
				'status'  => 'publish',
				'excerpt' => 'Pour les acheteurs comme pour les concessionnaires, tout est pensé pour être simple.',
				'content' => self::blocks(
					array(
						array( 'h2', 'Vous cherchez un véhicule' ),
						array( 'ul', array( 'Recherchez par marque, prix, année, kilométrage ou ville.', 'Comparez les annonces de plusieurs concessionnaires.', 'Contactez directement le concessionnaire par téléphone ou WhatsApp.' ) ),
						array( 'h2', 'Vous êtes concessionnaire' ),
						array( 'ul', array( 'Choisissez une formule et envoyez votre demande d’adhésion.', 'Notre équipe vérifie votre dossier et met en place votre site web et votre CRM.', 'Vous publiez vos véhicules depuis votre CRM : ils apparaissent sur votre site et sur la marketplace.', 'Vous recevez et suivez vos prospects au même endroit.' ) ),
					)
				),
			),
			'contact'        => array(
				'title'    => 'Contact',
				'status'   => 'publish',
				'excerpt'  => 'Une question, un projet ? Écrivez-nous, nous vous répondons rapidement.',
				'content'  => self::blocks(
					array(
						array( 'p', 'Notre équipe répond à vos questions sur la marketplace et sur nos services pour les concessionnaires.' ),
					)
				),
				'template' => 'page-templates/contact.php',
			),
			'faq'            => array(
				'title'   => 'Questions fréquentes',
				'status'  => 'publish',
				'excerpt' => 'Les réponses aux questions les plus courantes.',
				'content' => self::blocks(
					array(
						array( 'faq', array( 'Qui vend les véhicules affichés sur la marketplace ?', 'Les véhicules sont proposés par des concessionnaires indépendants. La vente ou la location se conclut directement entre vous et le concessionnaire.' ) ),
						array( 'faq', array( 'Comment contacter un concessionnaire ?', 'Chaque annonce affiche les coordonnées du concessionnaire : téléphone, WhatsApp et lien vers son site web.' ) ),
						array( 'faq', array( 'Les annonces sont-elles à jour ?', 'Les annonces sont synchronisées avec le stock de chaque concessionnaire. Lorsqu’une annonce n’a pas été confirmée récemment, elle est signalée puis retirée automatiquement.' ) ),
						array( 'faq', array( 'Que signifie « Concessionnaire vérifié » ?', 'Cela signifie que notre équipe a contrôlé les informations d’identification de l’entreprise fournies lors de son adhésion.' ) ),
						array( 'faq', array( 'Je suis concessionnaire, comment rejoindre la plateforme ?', 'Rendez-vous sur la page « Devenir concessionnaire » et envoyez-nous votre demande. Notre équipe vous recontacte pour la mise en place.' ) ),
						array( 'faq', array( 'Mon site de concessionnaire dépend-il de la marketplace ?', 'Non. Chaque concessionnaire dispose de son propre site, de son propre hébergement et de ses propres données.' ) ),
					)
				),
			),
			'join-dealer'    => array(
				'title'    => 'Devenir concessionnaire',
				'status'   => 'publish',
				'excerpt'  => 'Votre site web, votre CRM et vos véhicules sur la marketplace, dans une seule formule.',
				'content'  => self::blocks(
					array(
						array( 'p', 'Laissez-nous vos coordonnées : notre équipe vous présente les formules et vous accompagne dans la mise en place.' ),
					)
				),
				'template' => 'page-templates/dealer-landing.php',
			),
			'privacy-policy' => array(
				'title'   => 'Politique de confidentialité',
				'status'  => 'draft',
				'excerpt' => '',
				'content' => self::blocks(
					array(
						array( 'p', '[MODÈLE À COMPLÉTER — ce texte doit être adapté à votre société et validé par un juriste avant publication, notamment au regard de la loi sénégalaise n° 2008-12 sur la protection des données à caractère personnel et des obligations de déclaration auprès de la CDP.]' ),
						array( 'h2', 'Responsable du traitement' ),
						array( 'p', '[Raison sociale, adresse, contact.]' ),
						array( 'h2', 'Données collectées' ),
						array( 'p', 'Formulaire de contact : nom, adresse e-mail, téléphone, société et message. Données techniques : une empreinte anonymisée de l’adresse IP est conservée pour prévenir les abus.' ),
						array( 'h2', 'Finalités' ),
						array( 'p', 'Répondre aux demandes, gérer les adhésions des concessionnaires, assurer la sécurité du service.' ),
						array( 'h2', 'Durée de conservation' ),
						array( 'p', '[À définir.]' ),
						array( 'h2', 'Vos droits' ),
						array( 'p', 'Vous disposez d’un droit d’accès, de rectification, d’opposition et de suppression de vos données. [Modalités d’exercice.]' ),
					)
				),
			),
			'terms'          => array(
				'title'   => 'Conditions générales d’utilisation',
				'status'  => 'draft',
				'excerpt' => '',
				'content' => self::blocks(
					array(
						array( 'p', '[MODÈLE À COMPLÉTER — ce texte doit être rédigé et validé par un juriste avant publication.]' ),
						array( 'h2', 'Objet' ),
						array( 'p', 'La marketplace met en relation des acheteurs et des concessionnaires indépendants. Elle n’est pas partie aux ventes et locations conclues entre eux.' ),
						array( 'h2', 'Annonces' ),
						array( 'p', 'Les informations des annonces sont fournies par les concessionnaires, qui en sont responsables.' ),
						array( 'h2', 'Responsabilité' ),
						array( 'p', '[À définir.]' ),
						array( 'h2', 'Droit applicable' ),
						array( 'p', '[À définir.]' ),
					)
				),
			),
		);
	}
}
