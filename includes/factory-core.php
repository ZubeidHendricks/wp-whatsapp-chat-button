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
	define( 'ZUB_FACTORY_VERSION', '1.2.0' );
}

if ( ! function_exists( 'zub_factory_freemius_boot' ) ) {

	/**
	 * Bootstrap Freemius for a factory plugin — but only if it's ready.
	 *
	 * Zero hardcoding. A plugin becomes monetizable the moment two files exist:
	 *   - includes/freemius/start.php   (the Freemius SDK; run bin/add-freemius.sh)
	 *   - freemius-config.php           (returns array: id, public_key, plan, …)
	 * Until then this is a no-op and the plugin runs free-only — nothing breaks.
	 *
	 * Once wired, it flips the factory Pro gate ('{slug}_is_pro') based on the
	 * visitor's actual license, so every gated feature unlocks automatically.
	 *
	 * @param string $slug Plugin slug (also the filter prefix).
	 * @param string $dir  Plugin directory, trailing slash.
	 * @param string $file Main plugin file.
	 * @return object|null Freemius instance, or null in free-only mode.
	 */
	function zub_factory_freemius_boot( $slug, $dir, $file ) {
		$config_file = $dir . 'freemius-config.php';
		$sdk         = $dir . 'includes/freemius/start.php';

		if ( ! file_exists( $config_file ) || ! file_exists( $sdk ) ) {
			return null; // Free-only mode.
		}

		$config = include $config_file;
		if ( ! is_array( $config ) || empty( $config['id'] ) || empty( $config['public_key'] ) ) {
			return null;
		}

		require_once $sdk;

		if ( ! function_exists( 'fs_dynamic_init' ) ) {
			return null;
		}

		$instance = fs_dynamic_init(
			array(
				'id'                  => $config['id'],
				'slug'                => $slug,
				'type'                => 'plugin',
				'public_key'          => $config['public_key'],
				'is_premium'          => false,
				'has_premium_version' => true,
				'has_paid_plans'      => true,
				'menu'                => array(
					'slug'    => $slug,
					'account' => true,
					'contact' => true,
					'support' => false,
					'parent'  => array( 'slug' => 'options-general.php' ),
				),
			)
		);

		// Drive the factory Pro gate from the real license state.
		add_filter(
			$slug . '_is_pro',
			function ( $is_pro ) use ( $instance ) {
				return $instance->is_paying() || $instance->can_use_premium_code();
			}
		);

		do_action( $slug . '_fs_loaded', $instance );

		return $instance;
	}
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

		/** @var object|null Freemius instance, when wired. */
		public $fs = null;

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
			// Wire monetization first so the Pro gate is live before hooks run.
			$ls = $this->dir() . 'lemonsqueezy.php';
			if ( file_exists( $ls ) ) {
				$cfg = include $ls;
				if ( is_array( $cfg ) ) {
					ZubFactory_License::boot( $this->slug, $cfg );
				}
			} else {
				$this->fs = zub_factory_freemius_boot( $this->slug, $this->dir(), $this->file );
			}

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
				<?php
				ZubFactory_License::render_box( $this->slug );
				ZubFactory_Upsell::maybe_notice( $this->slug );
				?>
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

if ( ! class_exists( 'ZubFactory_License' ) ) {

	/**
	 * Lemon Squeezy license-key gating.
	 *
	 * Activated per plugin by a `lemonsqueezy.php` config returning at least a
	 * 'product_id' (and ideally a 'buy_url'). The customer pastes their license
	 * key on the settings page; we activate it against the Lemon Squeezy License
	 * API (no secret key required — those endpoints are public), cache the
	 * result, and flip the '{slug}_is_pro' gate accordingly.
	 *
	 * @link https://docs.lemonsqueezy.com/help/licensing/license-api
	 */
	class ZubFactory_License {

		const API = 'https://api.lemonsqueezy.com/v1/licenses/';

		/** @var array slug => config */
		private static $config = array();

		/** Wire a plugin up: store config, register handlers and the Pro gate. */
		public static function boot( $slug, array $config ) {
			self::$config[ $slug ] = $config;

			add_filter(
				$slug . '_is_pro',
				function ( $is_pro ) use ( $slug ) {
					return self::is_active( $slug ) ? true : $is_pro;
				}
			);

			if ( ! empty( $config['buy_url'] ) ) {
				add_filter(
					$slug . '_upgrade_url',
					function () use ( $config ) {
						return $config['buy_url'];
					}
				);
			}

			add_action( 'admin_init', function () use ( $slug ) {
				self::handle( $slug );
			} );
		}

		/** Is there a valid, active license for this plugin? */
		public static function is_active( $slug ) {
			$state = get_option( $slug . '_license', array() );
			if ( empty( $state['key'] ) || empty( $state['instance_id'] ) ) {
				return false;
			}
			// Re-validate at most once a day.
			$age = isset( $state['checked'] ) ? ( time() - (int) $state['checked'] ) : DAY_IN_SECONDS + 1;
			if ( $age > DAY_IN_SECONDS ) {
				self::validate( $slug, $state );
				$state = get_option( $slug . '_license', array() );
			}
			return ! empty( $state['active'] );
		}

		/** Handle activate / deactivate form posts from the settings page. */
		public static function handle( $slug ) {
			if ( empty( $_POST[ $slug . '_license_action' ] ) || ! current_user_can( 'manage_options' ) ) {
				return;
			}
			check_admin_referer( $slug . '_license' );
			$action = sanitize_text_field( wp_unslash( $_POST[ $slug . '_license_action' ] ) );

			if ( 'activate' === $action ) {
				$key = isset( $_POST[ $slug . '_license_key' ] )
					? sanitize_text_field( wp_unslash( $_POST[ $slug . '_license_key' ] ) ) : '';
				self::activate( $slug, $key );
			} elseif ( 'deactivate' === $action ) {
				self::deactivate( $slug );
			}
		}

		private static function post( $endpoint, $body ) {
			$res = wp_remote_post(
				self::API . $endpoint,
				array(
					'timeout' => 15,
					'headers' => array( 'Accept' => 'application/json' ),
					'body'    => $body,
				)
			);
			if ( is_wp_error( $res ) ) {
				return null;
			}
			return json_decode( wp_remote_retrieve_body( $res ), true );
		}

		/** Belt-and-braces: confirm the key is for THIS product. */
		private static function product_ok( $slug, $data ) {
			$cfg = isset( self::$config[ $slug ] ) ? self::$config[ $slug ] : array();
			if ( empty( $cfg['product_id'] ) ) {
				return true; // No product lock configured.
			}
			$pid = isset( $data['meta']['product_id'] ) ? (int) $data['meta']['product_id'] : 0;
			return (int) $cfg['product_id'] === $pid;
		}

		private static function activate( $slug, $key ) {
			if ( '' === $key ) {
				return;
			}
			$data = self::post( 'activate', array(
				'license_key'   => $key,
				'instance_name' => home_url(),
			) );

			$ok = $data && ! empty( $data['activated'] ) && self::product_ok( $slug, $data );
			update_option( $slug . '_license', array(
				'key'         => $key,
				'instance_id' => $ok ? ( $data['instance']['id'] ?? '' ) : '',
				'active'      => $ok,
				'status'      => $ok ? 'active' : ( $data['error'] ?? 'invalid' ),
				'checked'     => time(),
			), false );
		}

		private static function validate( $slug, $state ) {
			$data = self::post( 'validate', array(
				'license_key' => $state['key'],
				'instance_id' => $state['instance_id'],
			) );
			$ok = $data && ! empty( $data['valid'] ) && self::product_ok( $slug, $data );
			$state['active']  = $ok;
			$state['status']  = $ok ? 'active' : ( $data['error'] ?? 'invalid' );
			$state['checked'] = time();
			update_option( $slug . '_license', $state, false );
		}

		private static function deactivate( $slug ) {
			$state = get_option( $slug . '_license', array() );
			if ( ! empty( $state['key'] ) && ! empty( $state['instance_id'] ) ) {
				self::post( 'deactivate', array(
					'license_key' => $state['key'],
					'instance_id' => $state['instance_id'],
				) );
			}
			delete_option( $slug . '_license' );
		}

		/** Render the license box on the settings page (no-op if not configured). */
		public static function render_box( $slug ) {
			if ( empty( self::$config[ $slug ] ) ) {
				return;
			}
			$state  = get_option( $slug . '_license', array() );
			$active = ! empty( $state['active'] );
			$buy    = isset( self::$config[ $slug ]['buy_url'] ) ? self::$config[ $slug ]['buy_url'] : '';
			?>
			<div class="card" style="max-width:560px;padding:14px 18px;margin:14px 0;">
				<h2 style="margin-top:6px;">
					<?php esc_html_e( 'License', 'zub-factory' ); ?>
					<?php echo $active
						? '<span style="color:#1a7f37;">— ' . esc_html__( 'Pro active', 'zub-factory' ) . ' ✓</span>'
						: ''; // phpcs:ignore ?>
				</h2>
				<form method="post">
					<?php wp_nonce_field( $slug . '_license' ); ?>
					<?php if ( $active ) : ?>
						<p><?php esc_html_e( 'Your Pro license is active on this site.', 'zub-factory' ); ?></p>
						<input type="hidden" name="<?php echo esc_attr( $slug ); ?>_license_action" value="deactivate">
						<?php submit_button( __( 'Deactivate license', 'zub-factory' ), 'secondary', 'submit', false ); ?>
					<?php else : ?>
						<p>
							<?php esc_html_e( 'Enter your license key to unlock Pro features.', 'zub-factory' ); ?>
							<?php if ( $buy ) : ?>
								<a href="<?php echo esc_url( $buy ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Need a key? Get Pro →', 'zub-factory' ); ?></a>
							<?php endif; ?>
						</p>
						<input type="text" name="<?php echo esc_attr( $slug ); ?>_license_key"
							class="regular-text" placeholder="XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX"
							value="">
						<input type="hidden" name="<?php echo esc_attr( $slug ); ?>_license_action" value="activate">
						<?php submit_button( __( 'Activate', 'zub-factory' ), 'primary', 'submit', false ); ?>
						<?php if ( ! empty( $state['status'] ) && 'active' !== $state['status'] ) : ?>
							<p style="color:#b32d2e;"><?php
								/* translators: %s: error status from Lemon Squeezy. */
								printf( esc_html__( 'Last attempt failed: %s', 'zub-factory' ), esc_html( $state['status'] ) );
							?></p>
						<?php endif; ?>
					<?php endif; ?>
				</form>
			</div>
			<?php
		}
	}
}
