<?php
/**
 * Zub Plugin Factory — shared core.
 *
 * A tiny, dependency-free mini-framework that every factory plugin bundles.
 * It provides: a plugin bootstrap base, a simple settings-page builder, and
 * freemium ("Pro") gating helpers so the paywall logic is written once.
 *
 * The core is namespaced and version-guarded: if two active plugins bundle
 * different versions, the highest version loads first and the rest defer to it.
 *
 * @package ZubFactory
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'ZUB_FACTORY_VERSION' ) ) {
	define( 'ZUB_FACTORY_VERSION', '1.0.0' );
}

if ( ! class_exists( 'ZubFactory_Plugin' ) ) {

	/**
	 * Base class for a factory plugin. Extend it, set the props, and call
	 * boot() from your main plugin file.
	 */
	abstract class ZubFactory_Plugin {

		/** @var string Unique plugin slug, e.g. "duplicate-anything". */
		protected $slug = '';

		/** @var string Human title shown in admin. */
		protected $title = '';

		/** @var string Semver of the plugin (not the core). */
		protected $version = '1.0.0';

		/** @var string Absolute path to the main plugin file. */
		protected $file = '';

		/** @var ZubFactory_Settings|null */
		public $settings = null;

		/**
		 * @param string $file __FILE__ of the main plugin file.
		 */
		public function __construct( $file ) {
			$this->file = $file;
			$this->configure();
		}

		/** Child sets $slug/$title/$version here. */
		abstract protected function configure();

		/** Child registers its hooks here. */
		abstract protected function hooks();

		/** Call once from the main file to start the plugin. */
		public function boot() {
			if ( $this->settings_fields() ) {
				$this->settings = new ZubFactory_Settings(
					$this->slug,
					$this->title,
					$this->settings_fields()
				);
			}
			$this->hooks();
			return $this;
		}

		/**
		 * Optional: return an array of settings fields to auto-build a page.
		 * @return array
		 */
		protected function settings_fields() {
			return array();
		}

		public function slug() {
			return $this->slug;
		}

		public function version() {
			return $this->version;
		}

		public function dir() {
			return plugin_dir_path( $this->file );
		}

		public function url() {
			return plugin_dir_url( $this->file );
		}

		/** Read one saved option for this plugin. */
		public function option( $key, $default = '' ) {
			$opts = get_option( $this->slug . '_options', array() );
			return isset( $opts[ $key ] ) ? $opts[ $key ] : $default;
		}
	}
}

if ( ! class_exists( 'ZubFactory_Settings' ) ) {

	/**
	 * Declarative settings page. Pass an array of fields and it renders,
	 * sanitizes, and saves them under "{slug}_options".
	 */
	class ZubFactory_Settings {

		private $slug;
		private $title;
		private $fields;

		public function __construct( $slug, $title, array $fields ) {
			$this->slug   = $slug;
			$this->title  = $title;
			$this->fields = $fields;

			add_action( 'admin_menu', array( $this, 'menu' ) );
			add_action( 'admin_init', array( $this, 'register' ) );
		}

		public function menu() {
			add_options_page(
				$this->title,
				$this->title,
				'manage_options',
				$this->slug,
				array( $this, 'render' )
			);
		}

		public function register() {
			register_setting(
				$this->slug . '_group',
				$this->slug . '_options',
				array( $this, 'sanitize' )
			);
		}

		public function sanitize( $input ) {
			$clean = array();
			foreach ( $this->fields as $key => $field ) {
				$type = isset( $field['type'] ) ? $field['type'] : 'text';
				$val  = isset( $input[ $key ] ) ? $input[ $key ] : '';
				switch ( $type ) {
					case 'checkbox':
						$clean[ $key ] = $val ? 1 : 0;
						break;
					case 'textarea':
						$clean[ $key ] = sanitize_textarea_field( $val );
						break;
					case 'number':
						$clean[ $key ] = is_numeric( $val ) ? $val + 0 : 0;
						break;
					case 'color':
						$clean[ $key ] = sanitize_hex_color( $val );
						break;
					default:
						$clean[ $key ] = sanitize_text_field( $val );
				}
			}
			return $clean;
		}

		public function render() {
			$opts = get_option( $this->slug . '_options', array() );
			?>
			<div class="wrap">
				<h1><?php echo esc_html( $this->title ); ?></h1>
				<?php ZubFactory_Upsell::maybe_notice( $this->slug ); ?>
				<form method="post" action="options.php">
					<?php settings_fields( $this->slug . '_group' ); ?>
					<table class="form-table" role="presentation"><tbody>
					<?php foreach ( $this->fields as $key => $field ) :
						$name  = $this->slug . '_options[' . $key . ']';
						$type  = isset( $field['type'] ) ? $field['type'] : 'text';
						$value = isset( $opts[ $key ] ) ? $opts[ $key ] : ( isset( $field['default'] ) ? $field['default'] : '' );
						$pro   = ! empty( $field['pro'] ) && ! ZubFactory_Upsell::is_pro( $this->slug );
						?>
						<tr>
							<th scope="row">
								<?php echo esc_html( $field['label'] ); ?>
								<?php if ( ! empty( $field['pro'] ) ) {
									echo ' ' . ZubFactory_Upsell::badge(); // phpcs:ignore
								} ?>
							</th>
							<td <?php echo $pro ? 'style="opacity:.5;pointer-events:none"' : ''; ?>>
								<?php $this->field( $name, $type, $value, $field ); ?>
								<?php if ( ! empty( $field['desc'] ) ) : ?>
									<p class="description"><?php echo esc_html( $field['desc'] ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody></table>
					<?php submit_button(); ?>
				</form>
			</div>
			<?php
		}

		private function field( $name, $type, $value, $field ) {
			switch ( $type ) {
				case 'checkbox':
					printf(
						'<label><input type="checkbox" name="%s" value="1" %s> %s</label>',
						esc_attr( $name ),
						checked( 1, $value, false ),
						esc_html( isset( $field['cb_label'] ) ? $field['cb_label'] : '' )
					);
					break;
				case 'textarea':
					printf(
						'<textarea name="%s" rows="5" cols="50" class="large-text">%s</textarea>',
						esc_attr( $name ),
						esc_textarea( $value )
					);
					break;
				case 'select':
					echo '<select name="' . esc_attr( $name ) . '">';
					foreach ( (array) $field['options'] as $ok => $ol ) {
						printf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $ok ),
							selected( $ok, $value, false ),
							esc_html( $ol )
						);
					}
					echo '</select>';
					break;
				case 'color':
					printf(
						'<input type="text" name="%s" value="%s" class="regular-text" placeholder="#2271b1">',
						esc_attr( $name ),
						esc_attr( $value )
					);
					break;
				case 'number':
					printf(
						'<input type="number" name="%s" value="%s" class="small-text">',
						esc_attr( $name ),
						esc_attr( $value )
					);
					break;
				default:
					printf(
						'<input type="text" name="%s" value="%s" class="regular-text">',
						esc_attr( $name ),
						esc_attr( $value )
					);
			}
		}
	}
}

if ( ! class_exists( 'ZubFactory_Upsell' ) ) {

	/**
	 * Freemium gating helpers. Pro status is filterable so a paid add-on
	 * (or Freemius) can flip it on:  add_filter( '{slug}_is_pro', '__return_true' );
	 */
	class ZubFactory_Upsell {

		/** Is the Pro tier active for a given plugin slug? */
		public static function is_pro( $slug ) {
			return (bool) apply_filters( $slug . '_is_pro', false );
		}

		/** Small "PRO" badge markup. */
		public static function badge() {
			return '<span style="background:#2271b1;color:#fff;border-radius:3px;'
				. 'font-size:10px;padding:1px 6px;vertical-align:middle;letter-spacing:.5px;">PRO</span>';
		}

		/** Render a dismissible "upgrade" notice on the settings page. */
		public static function maybe_notice( $slug ) {
			if ( self::is_pro( $slug ) ) {
				return;
			}
			$url = apply_filters( $slug . '_upgrade_url', 'https://zubeidhendricks.dev/wp-plugins/' . $slug );
			printf(
				'<div class="notice notice-info inline" style="margin:12px 0;padding:10px 14px;">'
				. '%s <a class="button button-primary" href="%s" target="_blank" rel="noopener">%s</a></div>',
				esc_html__( 'Unlock automation, bulk actions and priority support with Pro.', 'zub-factory' ),
				esc_url( $url ),
				esc_html__( 'Upgrade', 'zub-factory' )
			);
		}
	}
}
