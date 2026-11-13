<?php
/**
 * Assistant user interface: floating chat on the public site, full-page chat
 * in the WordPress dashboard.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Assistant_Ui {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_public' ), 30 );
		add_action( 'wp_footer', array( __CLASS__, 'render_widget' ), 5 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ) );
	}

	/** Assistant display name, e.g. "Assistant AutoBuzy". */
	public static function name() {
		/* translators: %s: site name */
		return sprintf( __( '%s Assistant', 'automotive-marketplace-core' ), get_bloginfo( 'name' ) );
	}

	private static function i18n() {
		return array(
			'thinking' => __( 'The assistant is writing…', 'automotive-marketplace-core' ),
			'error'    => __( 'The assistant is unavailable right now. Please try again.', 'automotive-marketplace-core' ),
			'seeAll'   => __( 'See all results', 'automotive-marketplace-core' ),
			'link'     => __( 'Open the page', 'automotive-marketplace-core' ),
		);
	}

	public static function enabled_for_visitors() {
		return (bool) Settings::get( 'ai_visitor_enabled' );
	}

	public static function enqueue_public() {
		if ( ! self::enabled_for_visitors() || Portal::is_app() ) {
			return;
		}
		self::enqueue_for( 'visitor' );
	}

	public static function enqueue_admin( $hook ) {
		if ( false !== strpos( (string) $hook, 'amc-' ) ) {
			self::enqueue_for( 'admin' );
		}
	}

	/**
	 * Load the chat assets.
	 *
	 * @param string $scope visitor|admin.
	 */
	public static function enqueue_for( $scope ) {
		wp_enqueue_style( 'amc-assistant', AMC_URL . 'assets/css/assistant.css', array(), AMC_VERSION );
		wp_enqueue_script( 'amc-assistant', AMC_URL . 'assets/js/assistant.js', array(), AMC_VERSION, true );
		$config = array(
			'scope'    => $scope,
			'endpoint' => esc_url_raw( rest_url( Api::NS . ( 'admin' === $scope ? '/admin/assistant' : '/assistant' ) ) ),
			'home'     => home_url( '/' ),
			'i18n'     => self::i18n(),
		);
		if ( 'admin' === $scope ) {
			$config['nonce']         = wp_create_nonce( 'wp_rest' );
			$config['draftEndpoint'] = esc_url_raw( rest_url( Api::NS . '/admin/inquiries/%d/draft-reply' ) );
		}
		wp_localize_script( 'amc-assistant', 'amcAssistant', $config );
	}

	/** Inline admin chat (WordPress admin page and /platform-admin/). */
	public static function render_inline() {
		echo '<div class="amc-ai amc-ai--inline" data-amc-ai-inline>';
		self::panel(
			'admin',
			__( 'Hello. Ask me about the platform: figures, listings, dealers, contact requests, recent activity — or ask me to draft a message. I can read data but I never change anything.', 'automotive-marketplace-core' ),
			array(
				__( 'Give me today\'s overview of the platform', 'automotive-marketplace-core' ),
				__( 'What needs my attention in memberships?', 'automotive-marketplace-core' ),
				__( 'Summarise the new contact requests', 'automotive-marketplace-core' ),
				__( 'Draft a welcome message for a new dealer', 'automotive-marketplace-core' ),
			)
		);
		echo '</div>';
	}

	/**
	 * Chat panel markup shared by both contexts.
	 *
	 * @param string   $scope       visitor|admin.
	 * @param string   $welcome     First assistant message.
	 * @param string[] $suggestions Suggestion chips.
	 */
	private static function panel( $scope, $welcome, array $suggestions ) {
		$widget = 'visitor' === $scope;
		?>
		<div class="amc-ai__panel" data-amc-ai-panel role="<?php echo $widget ? 'dialog' : 'region'; ?>" aria-label="<?php echo esc_attr( self::name() ); ?>"<?php echo $widget ? ' hidden' : ''; ?>>
			<div class="amc-ai__head">
				<span class="amc-ai__spark"><?php echo amc_icon( 'sparkle', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span class="amc-ai__head-text">
					<span class="amc-ai__title"><?php echo esc_html( self::name() ); ?></span>
					<span class="amc-ai__subtitle"><?php echo esc_html( $widget ? __( 'Answers based on the live catalog', 'automotive-marketplace-core' ) : __( 'Read-only: figures, listings, requests, drafts', 'automotive-marketplace-core' ) ); ?></span>
				</span>
				<button class="amc-ai__icon-btn" type="button" data-amc-ai-reset title="<?php esc_attr_e( 'New conversation', 'automotive-marketplace-core' ); ?>" aria-label="<?php esc_attr_e( 'New conversation', 'automotive-marketplace-core' ); ?>"><?php echo amc_icon( 'sync', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
				<?php if ( $widget ) : ?>
					<button class="amc-ai__icon-btn" type="button" data-amc-ai-close aria-label="<?php esc_attr_e( 'Close the assistant', 'automotive-marketplace-core' ); ?>"><?php echo amc_icon( 'close', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
				<?php endif; ?>
			</div>
			<div class="amc-ai__log" data-amc-ai-log aria-live="polite">
				<div class="amc-ai__msg amc-ai__msg--assistant" data-amc-ai-welcome><div class="amc-ai__bubble"><p><?php echo esc_html( $welcome ); ?></p></div></div>
			</div>
			<div class="amc-ai__chips" data-amc-ai-chips>
				<?php foreach ( $suggestions as $suggestion ) : ?>
					<button type="button"><?php echo esc_html( $suggestion ); ?></button>
				<?php endforeach; ?>
			</div>
			<form class="amc-ai__form" data-amc-ai-form>
				<label class="screen-reader-text" for="amc-ai-input-<?php echo esc_attr( $scope ); ?>"><?php esc_html_e( 'Your message', 'automotive-marketplace-core' ); ?></label>
				<textarea class="amc-ai__input" id="amc-ai-input-<?php echo esc_attr( $scope ); ?>" data-amc-ai-input rows="1" maxlength="1000" placeholder="<?php esc_attr_e( 'Write your message…', 'automotive-marketplace-core' ); ?>"></textarea>
				<button class="amc-ai__send" type="submit" aria-label="<?php esc_attr_e( 'Send', 'automotive-marketplace-core' ); ?>"><?php echo amc_icon( 'send', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
			</form>
			<p class="amc-ai__note"><?php esc_html_e( 'Automated assistant: it can make mistakes. Always confirm details with the dealership.', 'automotive-marketplace-core' ); ?></p>
		</div>
		<?php
	}

	public static function render_widget() {
		if ( ! self::enabled_for_visitors() || Portal::is_app() ) {
			return;
		}
		?>
		<div class="amc-ai amc-ai--widget" data-amc-ai-widget>
			<?php
			self::panel(
				'visitor',
				__( 'Hello! Tell me what you are looking for — a budget, a type of vehicle, a city — and I will search the catalog for you. You can write in French, English or Wolof.', 'automotive-marketplace-core' ),
				array(
					__( 'Automatic SUV under 20 million', 'automotive-marketplace-core' ),
					__( 'Car to rent in Dakar', 'automotive-marketplace-core' ),
					__( 'Economical city car', 'automotive-marketplace-core' ),
					__( 'How do I become a dealer?', 'automotive-marketplace-core' ),
				)
			);
			?>
			<button class="amc-ai__launcher" type="button" data-amc-ai-open aria-expanded="false">
				<span class="amc-ai__spark"><?php echo amc_icon( 'sparkle', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span class="amc-ai__launcher-label"><?php esc_html_e( 'Ask the assistant', 'automotive-marketplace-core' ); ?></span>
			</button>
		</div>
		<?php
	}

	/** Dashboard page: Marketplace → AI assistant. */
	public static function render_admin_page() {
		if ( ! Permissions::is_staff() ) {
			return;
		}
		$error = get_transient( 'amc_ai_last_error' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AI assistant', 'automotive-marketplace-core' ); ?></h1>
			<?php if ( ! Assistant::sdk_available() ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'The AI library is not installed. Run "composer install --no-dev" in the plugin folder (see docs/INSTALL.md), then reload this page.', 'automotive-marketplace-core' ); ?></p></div>
			<?php elseif ( '' === Assistant::api_key() ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'No API key configured. Add your Anthropic API key in Marketplace → Settings → AI assistant. Until then, the public assistant uses the built-in search engine and this admin assistant is inactive.', 'automotive-marketplace-core' ); ?></p></div>
			<?php elseif ( $error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( __( 'Last AI error:', 'automotive-marketplace-core' ) . ' ' . $error ); ?></p></div>
			<?php endif; ?>
			<div style="margin-top:16px"><?php self::render_inline(); ?></div>
		</div>
		<?php
	}
}
