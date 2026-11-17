<?php
/**
 * wp-admin screens: Tools > Wotoyi Demo (load / remove) and Wotoyi > Dealers (read-only list).
 * Capability: wotoyi_manage_marketplace. Every write is a POST to admin-post.php with a nonce.
 *
 * @package Wotoyi\Demo
 */

declare(strict_types=1);

namespace Wotoyi\Demo;

defined( 'ABSPATH' ) || exit;

final class Admin {

	public const CAP = 'wotoyi_manage_marketplace';

	public static function boot(): void {
		add_action( 'admin_menu', array( self::class, 'menus' ) );
		add_action( 'admin_post_wotoyi_demo_load', static fn() => self::handle( 'load' ) );
		add_action( 'admin_post_wotoyi_demo_remove', static fn() => self::handle( 'remove' ) );
	}

	public static function menus(): void {
		add_menu_page( 'Wotoyi', 'Wotoyi', self::CAP, 'wotoyi-dealers', array( self::class, 'render_dealers' ), 'dashicons-car', 58 );
		add_submenu_page( 'wotoyi-dealers', 'Wotoyi — Dealers', 'Dealers', self::CAP, 'wotoyi-dealers', array( self::class, 'render_dealers' ) );
		add_management_page( 'Wotoyi Demo', 'Wotoyi Demo', self::CAP, 'wotoyi-demo', array( self::class, 'render_demo' ) );
	}

	/** admin-post handler: capability + nonce, then redirect with a whitelisted result code. */
	public static function handle( string $action ): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'wotoyi-demo' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'wotoyi_demo_' . $action );
		$confirm = ! empty( $_POST['wotoyi_demo_confirm'] ) && '1' === $_POST['wotoyi_demo_confirm']; // phpcs:ignore WordPress.Security.NonceVerification -- verified above.
		$out     = self::run_action( $action, $confirm );
		wp_safe_redirect( add_query_arg( array( 'page' => 'wotoyi-demo' ) + $out, admin_url( 'tools.php' ) ) );
		exit;
	}

	/**
	 * Testable core of the handlers.
	 *
	 * @return array<string,string|int> Query args: wd (result code) + counts.
	 */
	public static function run_action( string $action, bool $confirm ): array {
		if ( ! current_user_can( self::CAP ) ) {
			return array( 'wd' => 'forbidden' );
		}
		try {
			if ( 'load' === $action ) {
				$r = Seeder::load( $confirm );
				return array( 'wd' => $r['errors'] ? 'partial' : 'loaded', 'nd' => $r['dealers'], 'nv' => $r['vehicles'], 'ns' => $r['services'], 'ne' => count( $r['errors'] ) );
			}
			if ( 'remove' === $action ) {
				$r = Seeder::remove();
				return array( 'wd' => 'removed', 'nd' => $r['dealers'] ?? 0, 'nv' => $r['vehicles'] ?? 0, 'ns' => $r['services'] ?? 0 );
			}
		} catch ( \Throwable $e ) {
			$known = array( 'production_confirmation_required' => 'need_confirm', 'marketplace_missing' => 'no_marketplace', 'demo_busy' => 'busy', 'local_mode_locked_by_constant' => 'locked', 'local_mode_unavailable' => 'locked' );
			if ( isset( $known[ $e->getMessage() ] ) ) {
				return array( 'wd' => $known[ $e->getMessage() ] );
			}
			error_log( '[wotoyi-demo] ' . $action . ' failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return array( 'wd' => 'error' );
		}
		return array( 'wd' => 'error' );
	}

	/** @return array{0:string,1:string} [css class, message] */
	private static function message( string $code, int $nd, int $nv, int $ns, int $ne ): array {
		$map = array(
			'loaded'         => array( 'updated', sprintf( 'Données de démonstration chargées : %d concessionnaires, %d véhicules, %d services.', $nd, $nv, $ns ) ),
			'partial'        => array( 'notice-warning', sprintf( 'Chargement partiel : %d concessionnaires, %d véhicules, %d services, %d éléments refusés. Consultez le journal d’erreurs du serveur.', $nd, $nv, $ns, $ne ) ),
			'removed'        => array( 'updated', sprintf( 'Données de démonstration supprimées : %d concessionnaires, %d véhicules, %d services. Vos vraies données n’ont pas été touchées.', $nd, $nv, $ns ) ),
			'need_confirm'   => array( 'notice-error', 'Ce site est en environnement « production » : cochez la case de confirmation pour charger des données fictives.' ),
			'no_marketplace' => array( 'notice-error', 'Le plugin Wotoyi Marketplace doit être installé et activé.' ),
			'busy'           => array( 'notice-warning', 'Une opération est déjà en cours. Réessayez dans deux minutes.' ),
			'locked'         => array( 'notice-error', 'Le mode local du marketplace est verrouillé par la configuration (constante ou filtre) : chargement impossible.' ),
			'forbidden'      => array( 'notice-error', 'Accès refusé.' ),
			'error'          => array( 'notice-error', 'Une erreur est survenue. Rien n’a été supprimé de vos vraies données. Consultez le journal d’erreurs du serveur.' ),
		);
		return $map[ $code ] ?? array( '', '' );
	}

	public static function render_demo(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'wotoyi-demo' ), '', array( 'response' => 403 ) );
		}
		$ready  = Seeder::marketplace_ready();
		$counts = $ready ? Seeder::counts() : array( 'dealers' => 0, 'vehicles' => 0, 'services' => 0 );
		$prod   = 'production' === wp_get_environment_type();
		$code   = isset( $_GET['wd'] ) ? sanitize_key( wp_unslash( (string) $_GET['wd'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- display only, whitelisted.
		$num    = static fn( string $k ): int => isset( $_GET[ $k ] ) ? absint( wp_unslash( (string) $_GET[ $k ] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="wrap"><h1>Wotoyi Demo</h1>';
		echo '<div class="notice notice-warning" style="padding:12px"><p><strong>DONNÉES FICTIVES.</strong> Les concessionnaires, véhicules, services, prix et contacts créés ici sont inventés, uniquement pour visualiser le site. Ils portent la mention « démo ». Ne les utilisez jamais comme de vraies annonces.</p></div>';
		if ( $code ) {
			[ $cls, $msg ] = self::message( $code, $num( 'nd' ), $num( 'nv' ), $num( 'ns' ), $num( 'ne' ) );
			if ( $msg ) {
				echo '<div class="notice ' . esc_attr( $cls ) . ' is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
			}
		}
		if ( ! $ready ) {
			echo '<div class="notice notice-error"><p>Le plugin <strong>Wotoyi Marketplace</strong> doit être installé et activé avant la démo.</p></div></div>';
			return;
		}
		if ( ! Dataset::theme_images_present() ) {
			echo '<div class="notice notice-warning"><p>Le thème <strong>Wotoyi</strong> (dossier <code>wotoyi</code>) est introuvable : les véhicules s’afficheront sans image. Installez le thème, puis rechargez la démo.</p></div>';
		}
		printf(
			'<p>Actuellement en base : <strong>%d</strong> concessionnaires démo, <strong>%d</strong> véhicules démo, <strong>%d</strong> services démo.</p>',
			(int) $counts['dealers'],
			(int) $counts['vehicles'],
			(int) $counts['services']
		);
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:1em 0">';
		echo '<input type="hidden" name="action" value="wotoyi_demo_load">';
		wp_nonce_field( 'wotoyi_demo_load' );
		if ( $prod ) {
			echo '<p><label><input type="checkbox" name="wotoyi_demo_confirm" value="1"> Je comprends que ce site est en environnement « production » et que je charge volontairement des données fictives.</label></p>';
		}
		echo '<button type="submit" class="button button-primary">Charger les données de démo</button></form>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'' . esc_js( 'Supprimer toutes les données de démo ? Vos vraies données ne seront pas touchées.' ) . '\');">';
		echo '<input type="hidden" name="action" value="wotoyi_demo_remove">';
		wp_nonce_field( 'wotoyi_demo_remove' );
		echo '<button type="submit" class="button">Supprimer les données de démo</button></form>';
		echo '<p class="description">La suppression retire uniquement les concessionnaires de démo et ce qui leur appartient (véhicules, services, équipements, demandes, favoris, journal de synchronisation). Les journaux d’audit ne sont pas modifiés.</p></div>';
	}

	public static function render_dealers(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'wotoyi-demo' ), '', array( 'response' => 403 ) );
		}
		echo '<div class="wrap"><h1>Wotoyi — Dealers</h1>';
		if ( ! Seeder::marketplace_ready() ) {
			echo '<div class="notice notice-error"><p>Le plugin Wotoyi Marketplace doit être installé et activé.</p></div></div>';
			return;
		}
		$page = isset( $_GET['paged'] ) ? absint( wp_unslash( (string) $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification -- read-only paging.
		$data = DealerList::page( max( 1, $page ) );
		$labels = array( 'pending' => 'En attente', 'active' => 'Actif', 'suspended' => 'Suspendu', 'disconnected' => 'Déconnecté' );
		if ( Seeder::counts()['dealers'] > 0 ) {
			echo '<div class="notice notice-warning"><p><strong>Des données fictives de démonstration sont présentes</strong> (lignes marquées « DÉMO »). Voir Outils &gt; Wotoyi Demo.</p></div>';
		}
		echo '<table class="widefat striped"><thead><tr><th>Nom</th><th>Statut</th><th>Vérifié</th><th>Ville</th><th>Véhicules publiés</th><th>Véhicules (total)</th></tr></thead><tbody>';
		if ( ! $data['rows'] ) {
			echo '<tr><td colspan="6">Aucun concessionnaire pour le moment.</td></tr>';
		}
		foreach ( $data['rows'] as $r ) {
			printf(
				'<tr><td>%s%s</td><td>%s</td><td>%s</td><td>%s</td><td>%d</td><td>%d</td></tr>',
				esc_html( (string) $r['name'] ),
				$r['is_demo'] ? ' <span style="background:#fcf0c0;padding:1px 6px;border-radius:3px">DÉMO</span>' : '',
				esc_html( $labels[ $r['status'] ] ?? (string) $r['status'] ),
				$r['verified'] ? 'Oui' : 'Non',
				esc_html( (string) $r['city'] ),
				(int) $r['vehicles_published'],
				(int) $r['vehicles_total']
			);
		}
		echo '</tbody></table>';
		printf( '<p>%d concessionnaire(s). Page %d / %d.', (int) $data['total'], (int) $data['page'], (int) $data['pages'] );
		if ( $data['page'] > 1 ) {
			echo ' <a href="' . esc_url( add_query_arg( array( 'page' => 'wotoyi-dealers', 'paged' => $data['page'] - 1 ), admin_url( 'admin.php' ) ) ) . '">&laquo; Précédent</a>';
		}
		if ( $data['page'] < $data['pages'] ) {
			echo ' <a href="' . esc_url( add_query_arg( array( 'page' => 'wotoyi-dealers', 'paged' => $data['page'] + 1 ), admin_url( 'admin.php' ) ) ) . '">Suivant &raquo;</a>';
		}
		echo '</p><p class="description">Liste en lecture seule.</p></div>';
	}
}
