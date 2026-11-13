<?php
/**
 * WordPress admin screens for Phase 1: overview and tools, contact requests,
 * settings. The dedicated /platform-admin/ dashboard arrives in Phase 2.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Admin {

	const CAP = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_amc_demo_import', array( __CLASS__, 'handle_demo_import' ) );
		add_action( 'admin_post_amc_demo_remove', array( __CLASS__, 'handle_demo_remove' ) );
		add_action( 'admin_post_amc_inquiry_status', array( __CLASS__, 'handle_inquiry_status' ) );
		add_action( 'update_option_' . Settings::OPTION, array( __CLASS__, 'settings_updated' ) );
	}

	public static function menu() {
		add_menu_page( __( 'Marketplace', 'automotive-marketplace-core' ), __( 'Marketplace', 'automotive-marketplace-core' ), self::CAP, 'amc-marketplace', array( __CLASS__, 'page_overview' ), 'dashicons-car', 25 );
		add_submenu_page( 'amc-marketplace', __( 'Overview', 'automotive-marketplace-core' ), __( 'Overview', 'automotive-marketplace-core' ), self::CAP, 'amc-marketplace', array( __CLASS__, 'page_overview' ) );
		add_submenu_page( 'amc-marketplace', __( 'Contact requests', 'automotive-marketplace-core' ), __( 'Contact requests', 'automotive-marketplace-core' ), self::CAP, 'amc-inquiries', array( __CLASS__, 'page_inquiries' ) );
		add_submenu_page( 'amc-marketplace', __( 'Settings', 'automotive-marketplace-core' ), __( 'Settings', 'automotive-marketplace-core' ), self::CAP, 'amc-settings', array( __CLASS__, 'page_settings' ) );
	}

	public static function register_settings() {
		register_setting(
			'amc_settings',
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'AMC\\Settings', 'sanitize' ),
			)
		);
	}

	public static function settings_updated() {
		Vehicles::flush_cache();
		Audit::log( 'settings.updated', 'settings' );
	}

	private static function counts() {
		global $wpdb;
		$v = Database::table( 'vehicles' );
		$d = Database::table( 'dealers' );
		$i = Database::table( 'inquiries' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array(
			'dealers'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $d WHERE status = 'active'" ),
			'vehicles'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $v WHERE status IN ('active','reserved')" ),
			'inquiries'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $i WHERE status = 'new'" ),
			'demo_dealers'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $d WHERE is_demo = 1" ),
			'demo_vehicles' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $v WHERE is_demo = 1" ),
		);
		// phpcs:enable
	}

	public static function page_overview() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$counts = self::counts();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag.
		$notice = isset( $_GET['amc_notice'] ) ? sanitize_key( wp_unslash( $_GET['amc_notice'] ) ) : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Marketplace', 'automotive-marketplace-core' ); ?></h1>
			<?php if ( 'demo_imported' === $notice ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Demo data imported.', 'automotive-marketplace-core' ); ?></p></div>
			<?php elseif ( 'demo_removed' === $notice ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Demo data removed.', 'automotive-marketplace-core' ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! get_option( 'permalink_structure' ) ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'Pretty permalinks are disabled. Enable them in Settings → Permalinks (choose "Post name") to get SEO-friendly marketplace URLs.', 'automotive-marketplace-core' ); ?></p></div>
			<?php endif; ?>

			<table class="widefat striped" style="max-width:640px;margin-top:16px">
				<tbody>
					<tr><th><?php esc_html_e( 'Active dealers', 'automotive-marketplace-core' ); ?></th><td><?php echo esc_html( $counts['dealers'] ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Visible listings', 'automotive-marketplace-core' ); ?></th><td><?php echo esc_html( $counts['vehicles'] ); ?></td></tr>
					<tr><th><?php esc_html_e( 'New contact requests', 'automotive-marketplace-core' ); ?></th><td><?php echo esc_html( $counts['inquiries'] ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Database schema version', 'automotive-marketplace-core' ); ?></th><td><?php echo esc_html( get_option( Database::OPTION ) ); ?></td></tr>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Demo data', 'automotive-marketplace-core' ); ?></h2>
			<p style="max-width:640px">
				<?php esc_html_e( 'Demo data creates fictional dealers, vehicles and testimonials so you can preview the marketplace. It is flagged in the database and can be removed in one click. Remove it before going live.', 'automotive-marketplace-core' ); ?>
			</p>
			<p>
				<?php
				/* translators: 1: number of demo dealers, 2: number of demo vehicles */
				echo esc_html( sprintf( __( 'Currently installed: %1$d demo dealers, %2$d demo vehicles.', 'automotive-marketplace-core' ), $counts['demo_dealers'], $counts['demo_vehicles'] ) );
				?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px">
				<input type="hidden" name="action" value="amc_demo_import">
				<?php wp_nonce_field( 'amc_demo_import' ); ?>
				<?php submit_button( __( 'Import demo data', 'automotive-marketplace-core' ), 'primary', 'submit', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block">
				<input type="hidden" name="action" value="amc_demo_remove">
				<?php wp_nonce_field( 'amc_demo_remove' ); ?>
				<?php submit_button( __( 'Remove demo data', 'automotive-marketplace-core' ), 'delete', 'submit', false ); ?>
			</form>

			<h2><?php esc_html_e( 'Recent activity', 'automotive-marketplace-core' ); ?></h2>
			<table class="widefat striped" style="max-width:900px">
				<thead><tr><th><?php esc_html_e( 'Date (UTC)', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Event', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'User', 'automotive-marketplace-core' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( Audit::latest( 15 ) as $event ) : ?>
					<?php $user = $event->actor_user_id ? get_userdata( (int) $event->actor_user_id ) : null; ?>
					<tr><td><?php echo esc_html( $event->created_at ); ?></td><td><code><?php echo esc_html( $event->action ); ?></code></td><td><?php echo esc_html( $user ? $user->user_login : '—' ); ?></td></tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public static function page_inquiries() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$rows = Inquiries::latest( 100 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Contact requests', 'automotive-marketplace-core' ); ?></h1>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Date (UTC)', 'automotive-marketplace-core' ); ?></th>
					<th><?php esc_html_e( 'Type', 'automotive-marketplace-core' ); ?></th>
					<th><?php esc_html_e( 'From', 'automotive-marketplace-core' ); ?></th>
					<th><?php esc_html_e( 'Message', 'automotive-marketplace-core' ); ?></th>
					<th><?php esc_html_e( 'Status', 'automotive-marketplace-core' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No request yet.', 'automotive-marketplace-core' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row->created_at ); ?></td>
						<td><?php echo esc_html( $row->type ); ?></td>
						<td>
							<strong><?php echo esc_html( $row->name ); ?></strong>
							<?php if ( $row->company ) : ?><br><?php echo esc_html( $row->company ); ?><?php endif; ?>
							<?php if ( $row->email ) : ?><br><a href="<?php echo esc_url( 'mailto:' . $row->email ); ?>"><?php echo esc_html( $row->email ); ?></a><?php endif; ?>
							<?php if ( $row->phone ) : ?><br><?php echo esc_html( $row->phone ); ?><?php endif; ?>
						</td>
						<td><?php if ( $row->subject ) : ?><strong><?php echo esc_html( $row->subject ); ?></strong><br><?php endif; ?><?php echo nl2br( esc_html( $row->message ) ); ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="amc_inquiry_status">
								<input type="hidden" name="id" value="<?php echo esc_attr( $row->id ); ?>">
								<input type="hidden" name="status" value="<?php echo esc_attr( 'new' === $row->status ? 'handled' : 'new' ); ?>">
								<?php wp_nonce_field( 'amc_inquiry_status' ); ?>
								<?php echo esc_html( 'new' === $row->status ? __( 'New', 'automotive-marketplace-core' ) : __( 'Handled', 'automotive-marketplace-core' ) ); ?><br>
								<button class="button-link"><?php echo esc_html( 'new' === $row->status ? __( 'Mark as handled', 'automotive-marketplace-core' ) : __( 'Reopen', 'automotive-marketplace-core' ) ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public static function page_settings() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$s    = Settings::all();
		$name = Settings::OPTION;
		$text = static function ( $key, $label, $type = 'text', $help = '' ) use ( $s, $name ) {
			echo '<tr><th scope="row"><label for="amc_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
			echo '<input class="regular-text" type="' . esc_attr( $type ) . '" id="amc_' . esc_attr( $key ) . '" name="' . esc_attr( $name . '[' . $key . ']' ) . '" value="' . esc_attr( $s[ $key ] ) . '">';
			if ( $help ) {
				echo '<p class="description">' . esc_html( $help ) . '</p>';
			}
			echo '</td></tr>';
		};
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Marketplace settings', 'automotive-marketplace-core' ); ?></h1>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'amc_settings' ); ?>

				<h2><?php esc_html_e( 'Market', 'automotive-marketplace-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="amc_default_currency"><?php esc_html_e( 'Default currency', 'automotive-marketplace-core' ); ?></label></th><td>
						<select id="amc_default_currency" name="<?php echo esc_attr( $name ); ?>[default_currency]">
							<?php foreach ( Catalog::currencies() as $code => $currency ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $s['default_currency'], $code ); ?>><?php echo esc_html( $code . ' (' . $currency['symbol'] . ')' ); ?></option>
							<?php endforeach; ?>
						</select>
					</td></tr>
					<?php
					$text( 'default_city', __( 'Main city', 'automotive-marketplace-core' ), 'text', __( 'Used in page titles and descriptions for local SEO.', 'automotive-marketplace-core' ) );
					$text( 'default_country', __( 'Country code (ISO, 2 letters)', 'automotive-marketplace-core' ) );
					$text( 'per_page', __( 'Listings per page', 'automotive-marketplace-core' ), 'number' );
					?>
				</table>

				<h2><?php esc_html_e( 'Company contact details', 'automotive-marketplace-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$text( 'contact_email', __( 'Email receiving contact requests', 'automotive-marketplace-core' ), 'email', __( 'Leave empty to use the site administrator email.', 'automotive-marketplace-core' ) );
					$text( 'company_phone', __( 'Phone', 'automotive-marketplace-core' ) );
					$text( 'company_whatsapp', __( 'WhatsApp number (international format)', 'automotive-marketplace-core' ) );
					$text( 'company_address', __( 'Address', 'automotive-marketplace-core' ) );
					?>
				</table>

				<h2><?php esc_html_e( 'Listing freshness and SEO', 'automotive-marketplace-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$text( 'stale_after_hours', __( 'Flag as "to be confirmed" after (hours)', 'automotive-marketplace-core' ), 'number', __( 'Applies to listings synchronised from dealer websites when the dealer site stops confirming them.', 'automotive-marketplace-core' ) );
					$text( 'hide_after_hours', __( 'Hide from the marketplace after (hours)', 'automotive-marketplace-core' ), 'number' );
					$text( 'api_rate_limit', __( 'Public API requests per minute and per IP', 'automotive-marketplace-core' ), 'number' );
					?>
					<tr><th scope="row"><label for="amc_canonical_policy"><?php esc_html_e( 'Canonical URL of vehicle pages', 'automotive-marketplace-core' ); ?></label></th><td>
						<select id="amc_canonical_policy" name="<?php echo esc_attr( $name ); ?>[canonical_policy]">
							<option value="self" <?php selected( $s['canonical_policy'], 'self' ); ?>><?php esc_html_e( 'Marketplace page (default)', 'automotive-marketplace-core' ); ?></option>
							<option value="dealer" <?php selected( $s['canonical_policy'], 'dealer' ); ?>><?php esc_html_e( 'Dealer website page', 'automotive-marketplace-core' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Tells search engines which page is the original when the same vehicle is on the dealer website and on the marketplace.', 'automotive-marketplace-core' ); ?></p>
					</td></tr>
				</table>

				<h2><?php esc_html_e( 'Data', 'automotive-marketplace-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><?php esc_html_e( 'Uninstall', 'automotive-marketplace-core' ); ?></th><td>
						<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[delete_on_uninstall]" value="1" <?php checked( $s['delete_on_uninstall'], 1 ); ?>>
						<?php esc_html_e( 'Delete all marketplace data when the plugin is deleted (irreversible).', 'automotive-marketplace-core' ); ?></label>
					</td></tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	private static function guard( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'automotive-marketplace-core' ), 403 );
		}
		check_admin_referer( $action );
	}

	public static function handle_demo_import() {
		self::guard( 'amc_demo_import' );
		$result = Demo_Data::import();
		Audit::log( 'demo.imported', 'demo', 0, $result );
		wp_safe_redirect( admin_url( 'admin.php?page=amc-marketplace&amc_notice=demo_imported' ) );
		exit;
	}

	public static function handle_demo_remove() {
		self::guard( 'amc_demo_remove' );
		Demo_Data::remove();
		Audit::log( 'demo.removed', 'demo' );
		wp_safe_redirect( admin_url( 'admin.php?page=amc-marketplace&amc_notice=demo_removed' ) );
		exit;
	}

	public static function handle_inquiry_status() {
		self::guard( 'amc_inquiry_status' );
		$id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		if ( Inquiries::set_status( $id, $status ) ) {
			Audit::log( 'inquiry.' . $status, 'inquiry', $id );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=amc-inquiries' ) );
		exit;
	}
}
