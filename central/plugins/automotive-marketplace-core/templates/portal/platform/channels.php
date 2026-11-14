<?php
/**
 * Platform admin — WhatsApp AI assistant and voice channel.
 *
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

use AMC\Portal;
use AMC\Settings;

$s       = Settings::all();
$sources = array(
	'constant' => __( 'Set in wp-config.php', 'automotive-marketplace-core' ),
	'settings' => __( 'Saved (encrypted)', 'automotive-marketplace-core' ),
	'none'     => __( 'Missing', 'automotive-marketplace-core' ),
);
$secret  = static function ( $key, $constant, $label, $help = '' ) use ( $sources ) {
	$source = Settings::secret_source( $key, $constant );
	echo '<div class="amds-field"><label class="amds-label" for="amc-c-' . esc_attr( $key ) . '">' . esc_html( $label ) . ' <span class="amds-badge amds-badge--' . ( 'none' === $source ? 'warning' : 'success' ) . '">' . esc_html( $sources[ $source ] ) . '</span></label>';
	if ( 'constant' !== $source ) {
		echo '<input class="amds-input" type="password" id="amc-c-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="" autocomplete="new-password" placeholder="' . esc_attr( 'none' === $source ? '' : '••••••••' ) . '">';
		if ( 'settings' === $source ) {
			echo '<label class="amc-check"><input type="checkbox" name="' . esc_attr( $key ) . '_delete" value="1"> ' . esc_html__( 'Delete the saved value', 'automotive-marketplace-core' ) . '</label>';
		}
	}
	echo '<p class="amds-help">' . esc_html( $help ? $help : sprintf( /* translators: %s: constant name */ __( 'Recommended: define %s in wp-config.php instead.', 'automotive-marketplace-core' ), $constant ) ) . '</p></div>';
};
$field   = static function ( $key, $label, $help = '' ) use ( $s ) {
	echo '<div class="amds-field"><label class="amds-label" for="amc-c-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label><input class="amds-input" type="text" id="amc-c-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $s[ $key ] ) . '">' . ( $help ? '<p class="amds-help">' . esc_html( $help ) . '</p>' : '' ) . '</div>';
};
$live     = AMC\Whatsapp::enabled();
$contacts = AMC\Ai_Contacts::all();
$messages = AMC\Whatsapp::recent( 25 );
$calls    = AMC\Telephony::recent( 15 );

Portal::page_head(
	__( 'WhatsApp & voice', 'automotive-marketplace-core' ),
	__( 'The AI assistant on WhatsApp (text and voice notes) and on the phone, for dealers and buyers. Everything stays off until you enable it.', 'automotive-marketplace-core' ),
	$live ? '<span class="amds-badge amds-badge--success">' . esc_html__( 'WhatsApp live', 'automotive-marketplace-core' ) . '</span>' : '<span class="amds-badge amds-badge--neutral">' . esc_html__( 'WhatsApp off', 'automotive-marketplace-core' ) . '</span>'
);
?>
<?php if ( ! AMC\Assistant::ai_available() ) : ?>
	<div class="amds-alert amds-alert--warning"><?php esc_html_e( 'The AI is not configured (Anthropic API key and SDK). Configure it in Marketplace → Settings before enabling WhatsApp.', 'automotive-marketplace-core' ); ?></div>
<?php endif; ?>

<form class="amds-form" method="post" autocomplete="off">
	<?php Portal::action_fields( 'settings_save' ); ?>
	<input type="hidden" name="channels_form" value="1">
	<div class="amc-grid-2">
		<section class="amc-panel">
			<div class="amc-panel__head"><h2><?php esc_html_e( 'WhatsApp Business (Cloud API)', 'automotive-marketplace-core' ); ?></h2></div>
			<ol class="amc-steps-list">
				<li><?php esc_html_e( 'In Meta for Developers, create an app with the WhatsApp product and register your business number.', 'automotive-marketplace-core' ); ?></li>
				<li><?php esc_html_e( 'Paste the phone number ID, a permanent access token (system user) and the app secret below, then save.', 'automotive-marketplace-core' ); ?></li>
				<li><?php esc_html_e( 'In the app webhook settings, use the callback URL and verify token shown here, and subscribe to "messages".', 'automotive-marketplace-core' ); ?></li>
				<li><?php esc_html_e( 'Send a test message from your phone, then enable the assistant.', 'automotive-marketplace-core' ); ?></li>
			</ol>
			<dl class="amc-dl">
				<div><dt><?php esc_html_e( 'Callback URL', 'automotive-marketplace-core' ); ?></dt><dd><code><?php echo esc_html( AMC\Whatsapp::webhook_url() ); ?></code></dd></div>
				<div><dt><?php esc_html_e( 'Verify token', 'automotive-marketplace-core' ); ?></dt><dd><code><?php echo esc_html( $s['wa_verify_token'] ? $s['wa_verify_token'] : __( 'generated when you save', 'automotive-marketplace-core' ) ); ?></code></dd></div>
			</dl>
			<?php
			$field( 'wa_phone_number_id', __( 'Phone number ID', 'automotive-marketplace-core' ) );
			$field( 'wa_business_number', __( 'WhatsApp number shown to dealers', 'automotive-marketplace-core' ), __( 'Example: +221 77 000 00 00', 'automotive-marketplace-core' ) );
			$field( 'wa_graph_version', __( 'Graph API version', 'automotive-marketplace-core' ) );
			$secret( 'wa_access_token', 'AMC_WA_ACCESS_TOKEN', __( 'Access token', 'automotive-marketplace-core' ) );
			$secret( 'wa_app_secret', 'AMC_WA_APP_SECRET', __( 'App secret (webhook signature)', 'automotive-marketplace-core' ) );
			?>
			<input type="hidden" name="wa_enabled" value="0">
			<input type="hidden" name="wa_public_assistant" value="0">
			<label class="amc-check"><input type="checkbox" name="wa_enabled" value="1" <?php checked( $s['wa_enabled'], 1 ); ?>> <strong><?php esc_html_e( 'Enable the WhatsApp assistant', 'automotive-marketplace-core' ); ?></strong></label>
			<label class="amc-check"><input type="checkbox" name="wa_public_assistant" value="1" <?php checked( $s['wa_public_assistant'], 1 ); ?>> <?php esc_html_e( 'Also answer buyers (numbers not registered by a dealer) with the vehicle search assistant', 'automotive-marketplace-core' ); ?></label>
		</section>
		<div>
			<section class="amc-panel">
				<div class="amc-panel__head"><h2><?php esc_html_e( 'Voice notes (speech to text)', 'automotive-marketplace-core' ); ?></h2></div>
				<div class="amds-field">
					<label class="amds-label" for="amc-c-stt"><?php esc_html_e( 'Provider', 'automotive-marketplace-core' ); ?></label>
					<select class="amds-select" id="amc-c-stt" name="stt_provider">
						<?php foreach ( AMC\Speech::providers() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['stt_provider'], $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<?php
				$field( 'stt_base_url', __( 'API base URL', 'automotive-marketplace-core' ), __( 'HTTPS only. The file is sent to {base URL}/audio/transcriptions.', 'automotive-marketplace-core' ) );
				$field( 'stt_model', __( 'Model', 'automotive-marketplace-core' ) );
				$secret( 'stt_api_key', 'AMC_STT_API_KEY', __( 'API key', 'automotive-marketplace-core' ) );
				?>
				<p class="amds-help"><?php esc_html_e( 'Test Wolof recognition with real voice notes before enabling: quality depends on the model.', 'automotive-marketplace-core' ); ?></p>
			</section>
			<section class="amc-panel">
				<div class="amc-panel__head"><h2><?php esc_html_e( 'Phone calls (voice gateway)', 'automotive-marketplace-core' ); ?></h2></div>
				<p class="amds-help"><?php esc_html_e( 'Your SIP / telephony gateway handles the call, speech recognition and synthesis, and sends each sentence to AutoBuzy, which answers with the text to speak. Enable it only after testing the gateway end to end.', 'automotive-marketplace-core' ); ?></p>
				<div class="amds-field">
					<label class="amds-label" for="amc-c-voice"><?php esc_html_e( 'Voice channel', 'automotive-marketplace-core' ); ?></label>
					<select class="amds-select" id="amc-c-voice" name="voice_provider">
						<?php foreach ( AMC\Telephony::providers() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['voice_provider'], $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<dl class="amc-dl"><div><dt><?php esc_html_e( 'Gateway endpoint', 'automotive-marketplace-core' ); ?></dt><dd><code><?php echo esc_html( AMC\Telephony::endpoint() ); ?></code></dd></div></dl>
				<?php $secret( 'voice_secret', 'AMC_VOICE_SECRET', __( 'Shared secret (signature)', 'automotive-marketplace-core' ) ); ?>
			</section>
		</div>
	</div>
	<div><button class="amds-btn amds-btn--primary amds-btn--lg" type="submit"><?php esc_html_e( 'Save', 'automotive-marketplace-core' ); ?></button></div>
</form>

<?php if ( $live ) : ?>
	<section class="amc-panel">
		<div class="amc-panel__head"><h2><?php esc_html_e( 'Send a test message', 'automotive-marketplace-core' ); ?></h2></div>
		<p class="amds-help"><?php esc_html_e( 'WhatsApp only delivers free text to a number that wrote to your business number during the last 24 hours: write to it first from your phone.', 'automotive-marketplace-core' ); ?></p>
		<form class="amds-form amc-inline-form" method="post">
			<?php Portal::action_fields( 'wa_test' ); ?>
			<input class="amds-input" type="tel" name="phone" placeholder="+221 77 000 00 00" required>
			<button class="amds-btn amds-btn--secondary" type="submit"><?php esc_html_e( 'Send', 'automotive-marketplace-core' ); ?></button>
		</form>
	</section>
<?php endif; ?>

<div class="amc-grid-2">
	<section class="amc-panel">
		<div class="amc-panel__head"><h2><?php esc_html_e( 'Registered dealer numbers', 'automotive-marketplace-core' ); ?></h2><span class="amc-panel__meta"><?php echo esc_html( number_format_i18n( count( $contacts ) ) ); ?></span></div>
		<p class="amds-help"><?php esc_html_e( 'Each team member registers their own number in their CRM (Marketplace section). The assistant then acts with their CRM permissions.', 'automotive-marketplace-core' ); ?></p>
		<div class="amc-table-wrap">
			<table class="amds-table">
				<thead><tr><th><?php esc_html_e( 'Number', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Person', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Dealership', 'automotive-marketplace-core' ); ?></th></tr></thead>
				<tbody>
					<?php if ( ! $contacts ) : ?>
						<tr><td colspan="3" class="amc-muted"><?php esc_html_e( 'Nothing here.', 'automotive-marketplace-core' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $contacts as $c ) : ?>
						<tr><td><?php echo esc_html( $c->phone ); ?></td><td><?php echo esc_html( $c->name ); ?><br><small class="amc-muted"><?php echo esc_html( $c->role ); ?></small></td><td><?php echo esc_html( $c->business_name ); ?></td></tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>
	<section class="amc-panel">
		<div class="amc-panel__head"><h2><?php esc_html_e( 'Latest messages', 'automotive-marketplace-core' ); ?></h2></div>
		<p class="amds-help"><?php esc_html_e( 'Kept 90 days, then deleted automatically.', 'automotive-marketplace-core' ); ?></p>
		<ul class="amc-wa-log">
			<?php if ( ! $messages ) : ?>
				<li class="amc-muted"><?php esc_html_e( 'Nothing here.', 'automotive-marketplace-core' ); ?></li>
			<?php endif; ?>
			<?php foreach ( $messages as $m ) : ?>
				<li class="amc-wa-log__item amc-wa-log__item--<?php echo esc_attr( $m->direction ); ?>">
					<small class="amc-muted"><?php echo esc_html( ( 'in' === $m->direction ? $m->phone : '→ ' . $m->phone ) . ( $m->business_name ? ' · ' . $m->business_name : '' ) . ' · ' . Portal::date( $m->created_at, true ) ); ?></small>
					<span><?php echo esc_html( '' !== $m->body ? wp_trim_words( $m->body, 30 ) : '[' . $m->kind . ']' ); ?></span>
					<?php if ( $m->error ) : ?><small class="amc-text-danger"><?php echo esc_html( $m->error ); ?></small><?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
</div>

<?php if ( $calls ) : ?>
	<section class="amc-panel">
		<div class="amc-panel__head"><h2><?php esc_html_e( 'Latest calls', 'automotive-marketplace-core' ); ?></h2></div>
		<div class="amc-table-wrap">
			<table class="amds-table">
				<thead><tr><th><?php esc_html_e( 'Date', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Number', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Dealership', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Duration', 'automotive-marketplace-core' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $calls as $call ) : ?>
						<tr><td><?php echo esc_html( Portal::date( $call->created_at, true ) ); ?></td><td><?php echo esc_html( $call->from_number ); ?></td><td><?php echo esc_html( $call->business_name ? $call->business_name : '—' ); ?></td><td><?php echo esc_html( $call->duration_seconds ? gmdate( 'i:s', $call->duration_seconds ) : '—' ); ?></td></tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>
<?php endif; ?>
