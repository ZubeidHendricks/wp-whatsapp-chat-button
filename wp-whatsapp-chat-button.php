<?php
/**
 * Plugin Name:       WhatsApp Chat Button
 * Plugin URI:        https://zubeidhendricks.dev/wp-plugins/whatsapp-chat-button
 * Description:        Add a floating WhatsApp click-to-chat button to your site so visitors can message you in one tap.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.2
 * Author:            Zubeid Hendricks
 * Author URI:        https://zubeidhendricks.dev
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       whatsapp-chat-button
 *
 * @package WhatsAppChatButton
 */

defined( 'ABSPATH' ) || exit;

define( 'WHATSAPP_CHAT_BUTTON_VERSION', '1.0.0' );

require_once __DIR__ . '/includes/factory-core.php';

/**
 * WhatsApp Chat Button.
 */
final class WhatsAppChatButton extends ZubFactory_Plugin {

	protected function configure() {
		$this->slug    = 'whatsapp-chat-button';
		$this->title   = 'WhatsApp Chat Button';
		$this->version = WHATSAPP_CHAT_BUTTON_VERSION;
	}

	protected function settings_fields() {
		return array(
			'enabled'  => array(
				'label'    => __( 'Status', 'whatsapp-chat-button' ),
				'type'     => 'checkbox',
				'cb_label' => __( 'Show the WhatsApp button', 'whatsapp-chat-button' ),
				'default'  => 0,
			),
			'number'   => array(
				'label'   => __( 'WhatsApp number', 'whatsapp-chat-button' ),
				'type'    => 'text',
				'desc'    => __( 'International format, digits only. e.g. 14155552671', 'whatsapp-chat-button' ),
				'default' => '',
			),
			'prefill'  => array(
				'label'   => __( 'Pre-filled message', 'whatsapp-chat-button' ),
				'type'    => 'text',
				'default' => 'Hi! I have a question.',
			),
			'label'    => array(
				'label'   => __( 'Button label', 'whatsapp-chat-button' ),
				'type'    => 'text',
				'desc'    => __( 'Leave blank for an icon-only button.', 'whatsapp-chat-button' ),
				'default' => 'Chat with us',
			),
			'position' => array(
				'label'   => __( 'Position', 'whatsapp-chat-button' ),
				'type'    => 'select',
				'options' => array(
					'right' => __( 'Bottom right', 'whatsapp-chat-button' ),
					'left'  => __( 'Bottom left', 'whatsapp-chat-button' ),
				),
				'default' => 'right',
			),
			'hours'    => array(
				'label'    => __( 'Business hours', 'whatsapp-chat-button' ),
				'type'     => 'checkbox',
				'cb_label' => __( 'Only show during business hours', 'whatsapp-chat-button' ),
				'pro'      => true,
			),
		);
	}

	protected function hooks() {
		add_action( 'wp_footer', array( $this, 'render' ) );
	}

	public function render() {
		if ( ! $this->option( 'enabled', 0 ) || is_admin() ) {
			return;
		}
		$number = preg_replace( '/\D/', '', (string) $this->option( 'number', '' ) );
		if ( '' === $number ) {
			return;
		}

		$prefill = rawurlencode( (string) $this->option( 'prefill', '' ) );
		$href    = 'https://wa.me/' . $number . ( $prefill ? '?text=' . $prefill : '' );
		$label   = trim( (string) $this->option( 'label', '' ) );
		$side     = 'left' === $this->option( 'position', 'right' ) ? 'left' : 'right';
		?>
		<style>
			#zwa{position:fixed;bottom:22px;<?php echo esc_attr( $side ); ?>:22px;z-index:99991;
				display:inline-flex;align-items:center;gap:10px;
				background:#25D366;color:#fff;text-decoration:none;
				padding:12px 18px;border-radius:50px;font-weight:600;font-size:15px;
				box-shadow:0 4px 14px rgba(0,0,0,.25);font-family:inherit}
			#zwa:hover{background:#1ebe5b}
			#zwa svg{width:24px;height:24px;flex:0 0 auto;fill:#fff}
			#zwa.icon-only{padding:14px;border-radius:50%}
		</style>
		<a id="zwa" class="<?php echo $label ? '' : 'icon-only'; ?>"
			href="<?php echo esc_url( $href ); ?>" target="_blank" rel="noopener nofollow"
			aria-label="<?php esc_attr_e( 'Chat on WhatsApp', 'whatsapp-chat-button' ); ?>">
			<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 018.413 3.488 11.824 11.824 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 001.51 5.26l-.999 3.648 3.978-1.607zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413z"/></svg>
			<?php if ( $label ) : ?><span><?php echo esc_html( $label ); ?></span><?php endif; ?>
		</a>
		<?php
	}
}

add_action(
	'plugins_loaded',
	function () {
		( new WhatsAppChatButton( __FILE__ ) )->boot();
	}
);
