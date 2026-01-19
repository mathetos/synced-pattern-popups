<?php
/**
 * Settings Page
 * Handles plugin settings including AI TLDR configuration
 *
 * @package SPPopups
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * SPPopups_Settings class.
 *
 * @package SPPopups
 */
class SPPopups_Settings {

	/**
	 * Settings group name
	 *
	 * @var string
	 */
	private $option_group = 'sppopups_settings';

	/**
	 * Settings page slug
	 *
	 * @var string
	 */
	private $page_slug = 'sppopups-settings';

	/**
	 * Initialize settings
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		// Register settings (no page needed, we'll handle saving manually).
		register_setting(
			$this->option_group,
			'sppopups_tldr_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_boolean' ),
				'default'           => false,
			)
		);

		register_setting(
			$this->option_group,
			'sppopups_tldr_prompt',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => $this->get_default_prompt(),
			)
		);

		register_setting(
			$this->option_group,
			'sppopups_tldr_cache_ttl',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 12,
			)
		);

		// Register defaults settings.
		register_setting(
			$this->option_group,
			'sppopups_defaults_pattern',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_pattern_defaults' ),
				'default'           => $this->get_default_pattern_defaults(),
			)
		);

		register_setting(
			$this->option_group,
			'sppopups_defaults_tldr',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_tldr_defaults' ),
				'default'           => $this->get_default_tldr_defaults(),
			)
		);

		register_setting(
			$this->option_group,
			'sppopups_defaults_gallery',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_gallery_defaults' ),
				'default'           => $this->get_default_gallery_defaults(),
			)
		);
	}

	/**
	 * Sanitize boolean value
	 *
	 * @param mixed $value Value to sanitize.
	 * @return bool Sanitized boolean.
	 */
	public function sanitize_boolean( $value ) {
		return (bool) $value;
	}

	/**
	 * Get default pattern defaults
	 *
	 * @return array Default pattern defaults
	 */
	private function get_default_pattern_defaults() {
		return array(
			'maxWidth'        => 600,
			'borderRadius'    => 6,
			'maxHeight'       => 90,
			'overlayColor'    => 'rgba(0, 0, 0, 0.1)',
			'backdropBlur'    => 8,
			'showIconClose'   => true,
			'showFooterClose' => true,
			'footerCloseText' => 'Close →',
		);
	}

	/**
	 * Get default TLDR defaults
	 *
	 * @return array Default TLDR defaults
	 */
	private function get_default_tldr_defaults() {
		return array(
			'inheritModalAppearance' => true,
			'inheritOverlay'         => true,
			'inheritCloseButtons'    => true,
			'maxWidth'               => 600,
			'borderRadius'           => 6,
			'maxHeight'              => 90,
			'overlayColor'           => 'rgba(0, 0, 0, 0.1)',
			'backdropBlur'           => 8,
			'showIconClose'          => true,
			'showFooterClose'        => true,
			'footerCloseText'        => 'Close →',
			'loadingText'            => 'Generating TLDR',
			'titleText'              => 'TLDR',
		);
	}

	/**
	 * Get default gallery defaults
	 *
	 * @return array Default gallery defaults
	 */
	private function get_default_gallery_defaults() {
		return array(
			'inheritModalAppearance' => true,
			'inheritOverlay'         => true,
			'inheritCloseButtons'    => true,
			'maxWidth'               => 600,
			'borderRadius'           => 6,
			'maxHeight'              => 90,
			'overlayColor'           => 'rgba(0, 0, 0, 0.1)',
			'backdropBlur'           => 8,
			'showIconClose'          => true,
			'showFooterClose'        => true,
			'footerCloseText'        => 'Close →',
			'imageNavigation'        => 'both',
			'showCaptions'           => true,
			'crossfadeTransition'    => true,
			'transitionDuration'     => 500,
			'preloadAdjacentImages'  => true,
			'showNavOnHover'         => true,
		);
	}

	/**
	 * Sanitize rgba color value
	 *
	 * @param string $value Color value to sanitize.
	 * @return string Sanitized color value or default.
	 */
	private function sanitize_rgba_color( $value ) {
		if ( ! is_string( $value ) ) {
			return 'rgba(0, 0, 0, 0.1)';
		}

		// Validate rgba format: rgba(r, g, b, a) where r,g,b are 0-255 and a is 0-1.
		$pattern = '/^rgba\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(0|1|0?\.\d+)\s*\)$/';
		if ( preg_match( $pattern, $value ) ) {
			return $value;
		}

		return 'rgba(0, 0, 0, 0.1)';
	}

	/**
	 * Sanitize number with range validation
	 *
	 * @param mixed  $value Value to sanitize.
	 * @param int    $min   Minimum value.
	 * @param int    $max   Maximum value.
	 * @param int    $default Default value if invalid.
	 * @return int Sanitized number.
	 */
	private function sanitize_number_range( $value, $min, $max, $default ) {
		$value = absint( $value );
		if ( $value < $min || $value > $max ) {
			return $default;
		}
		return $value;
	}

	/**
	 * Sanitize pattern defaults
	 *
	 * @param mixed $value Value to sanitize.
	 * @return array Sanitized pattern defaults.
	 */
	public function sanitize_pattern_defaults( $value ) {
		if ( ! is_array( $value ) ) {
			return $this->get_default_pattern_defaults();
		}

		$defaults = $this->get_default_pattern_defaults();
		$sanitized = array();

		// Sanitize maxWidth (100-5000).
		$sanitized['maxWidth'] = isset( $value['maxWidth'] ) ? $this->sanitize_number_range( $value['maxWidth'], 100, 5000, $defaults['maxWidth'] ) : $defaults['maxWidth'];

		// Sanitize borderRadius (0-50).
		$sanitized['borderRadius'] = isset( $value['borderRadius'] ) ? $this->sanitize_number_range( $value['borderRadius'], 0, 50, $defaults['borderRadius'] ) : $defaults['borderRadius'];

		// Sanitize maxHeight (50-100).
		$sanitized['maxHeight'] = isset( $value['maxHeight'] ) ? $this->sanitize_number_range( $value['maxHeight'], 50, 100, $defaults['maxHeight'] ) : $defaults['maxHeight'];

		// Sanitize overlayColor.
		$sanitized['overlayColor'] = isset( $value['overlayColor'] ) ? $this->sanitize_rgba_color( $value['overlayColor'] ) : $defaults['overlayColor'];

		// Sanitize backdropBlur (0-20).
		$sanitized['backdropBlur'] = isset( $value['backdropBlur'] ) ? $this->sanitize_number_range( $value['backdropBlur'], 0, 20, $defaults['backdropBlur'] ) : $defaults['backdropBlur'];

		// Sanitize booleans.
		// Note: Unchecked checkboxes don't appear in POST data, so we use array_key_exists to detect if they were present.
		// If the key exists, use the value; if not, it means unchecked, so set to false.
		$sanitized['showIconClose'] = array_key_exists( 'showIconClose', $value ) ? (bool) $value['showIconClose'] : false;
		$sanitized['showFooterClose'] = array_key_exists( 'showFooterClose', $value ) ? (bool) $value['showFooterClose'] : false;

		// Sanitize footerCloseText.
		$sanitized['footerCloseText'] = isset( $value['footerCloseText'] ) ? sanitize_text_field( wp_unslash( $value['footerCloseText'] ) ) : $defaults['footerCloseText'];

		return $sanitized;
	}

	/**
	 * Sanitize TLDR defaults
	 *
	 * @param mixed $value Value to sanitize.
	 * @return array Sanitized TLDR defaults.
	 */
	public function sanitize_tldr_defaults( $value ) {
		if ( ! is_array( $value ) ) {
			return $this->get_default_tldr_defaults();
		}

		$defaults = $this->get_default_tldr_defaults();
		$sanitized = array();

		// Sanitize inheritance flags.
		$sanitized['inheritModalAppearance'] = isset( $value['inheritModalAppearance'] ) ? (bool) $value['inheritModalAppearance'] : $defaults['inheritModalAppearance'];
		$sanitized['inheritOverlay'] = isset( $value['inheritOverlay'] ) ? (bool) $value['inheritOverlay'] : $defaults['inheritOverlay'];
		$sanitized['inheritCloseButtons'] = isset( $value['inheritCloseButtons'] ) ? (bool) $value['inheritCloseButtons'] : $defaults['inheritCloseButtons'];

		// Only sanitize modal appearance if not inheriting.
		if ( ! $sanitized['inheritModalAppearance'] ) {
			$sanitized['maxWidth'] = isset( $value['maxWidth'] ) ? $this->sanitize_number_range( $value['maxWidth'], 100, 5000, $defaults['maxWidth'] ) : $defaults['maxWidth'];
			$sanitized['borderRadius'] = isset( $value['borderRadius'] ) ? $this->sanitize_number_range( $value['borderRadius'], 0, 50, $defaults['borderRadius'] ) : $defaults['borderRadius'];
			$sanitized['maxHeight'] = isset( $value['maxHeight'] ) ? $this->sanitize_number_range( $value['maxHeight'], 50, 100, $defaults['maxHeight'] ) : $defaults['maxHeight'];
		} else {
			$sanitized['maxWidth'] = $defaults['maxWidth'];
			$sanitized['borderRadius'] = $defaults['borderRadius'];
			$sanitized['maxHeight'] = $defaults['maxHeight'];
		}

		// Only sanitize overlay if not inheriting.
		if ( ! $sanitized['inheritOverlay'] ) {
			$sanitized['overlayColor'] = isset( $value['overlayColor'] ) ? $this->sanitize_rgba_color( $value['overlayColor'] ) : $defaults['overlayColor'];
			$sanitized['backdropBlur'] = isset( $value['backdropBlur'] ) ? $this->sanitize_number_range( $value['backdropBlur'], 0, 20, $defaults['backdropBlur'] ) : $defaults['backdropBlur'];
		} else {
			$sanitized['overlayColor'] = $defaults['overlayColor'];
			$sanitized['backdropBlur'] = $defaults['backdropBlur'];
		}

		// Only sanitize close buttons if not inheriting.
		if ( ! $sanitized['inheritCloseButtons'] ) {
			// Note: Unchecked checkboxes don't appear in POST data, so we use array_key_exists to detect if they were present.
			// If the key exists, use the value; if not, it means unchecked, so set to false.
			$sanitized['showIconClose'] = array_key_exists( 'showIconClose', $value ) ? (bool) $value['showIconClose'] : false;
			$sanitized['showFooterClose'] = array_key_exists( 'showFooterClose', $value ) ? (bool) $value['showFooterClose'] : false;
			$sanitized['footerCloseText'] = isset( $value['footerCloseText'] ) ? sanitize_text_field( wp_unslash( $value['footerCloseText'] ) ) : $defaults['footerCloseText'];
		} else {
			$sanitized['showIconClose'] = $defaults['showIconClose'];
			$sanitized['showFooterClose'] = $defaults['showFooterClose'];
			$sanitized['footerCloseText'] = $defaults['footerCloseText'];
		}

		// TLDR-specific settings.
		$sanitized['loadingText'] = isset( $value['loadingText'] ) ? sanitize_text_field( wp_unslash( $value['loadingText'] ) ) : $defaults['loadingText'];
		$sanitized['titleText'] = isset( $value['titleText'] ) ? sanitize_text_field( wp_unslash( $value['titleText'] ) ) : $defaults['titleText'];

		return $sanitized;
	}

	/**
	 * Sanitize gallery defaults
	 *
	 * @param mixed $value Value to sanitize.
	 * @return array Sanitized gallery defaults.
	 */
	public function sanitize_gallery_defaults( $value ) {
		if ( ! is_array( $value ) ) {
			return $this->get_default_gallery_defaults();
		}

		$defaults = $this->get_default_gallery_defaults();
		$sanitized = array();

		// Sanitize inheritance flags.
		$sanitized['inheritModalAppearance'] = isset( $value['inheritModalAppearance'] ) ? (bool) $value['inheritModalAppearance'] : $defaults['inheritModalAppearance'];
		$sanitized['inheritOverlay'] = isset( $value['inheritOverlay'] ) ? (bool) $value['inheritOverlay'] : $defaults['inheritOverlay'];
		$sanitized['inheritCloseButtons'] = isset( $value['inheritCloseButtons'] ) ? (bool) $value['inheritCloseButtons'] : $defaults['inheritCloseButtons'];

		// Only sanitize modal appearance if not inheriting.
		if ( ! $sanitized['inheritModalAppearance'] ) {
			$sanitized['maxWidth'] = isset( $value['maxWidth'] ) ? $this->sanitize_number_range( $value['maxWidth'], 100, 5000, $defaults['maxWidth'] ) : $defaults['maxWidth'];
			$sanitized['borderRadius'] = isset( $value['borderRadius'] ) ? $this->sanitize_number_range( $value['borderRadius'], 0, 50, $defaults['borderRadius'] ) : $defaults['borderRadius'];
			$sanitized['maxHeight'] = isset( $value['maxHeight'] ) ? $this->sanitize_number_range( $value['maxHeight'], 50, 100, $defaults['maxHeight'] ) : $defaults['maxHeight'];
		} else {
			$sanitized['maxWidth'] = $defaults['maxWidth'];
			$sanitized['borderRadius'] = $defaults['borderRadius'];
			$sanitized['maxHeight'] = $defaults['maxHeight'];
		}

		// Only sanitize overlay if not inheriting.
		if ( ! $sanitized['inheritOverlay'] ) {
			$sanitized['overlayColor'] = isset( $value['overlayColor'] ) ? $this->sanitize_rgba_color( $value['overlayColor'] ) : $defaults['overlayColor'];
			$sanitized['backdropBlur'] = isset( $value['backdropBlur'] ) ? $this->sanitize_number_range( $value['backdropBlur'], 0, 20, $defaults['backdropBlur'] ) : $defaults['backdropBlur'];
		} else {
			$sanitized['overlayColor'] = $defaults['overlayColor'];
			$sanitized['backdropBlur'] = $defaults['backdropBlur'];
		}

		// Only sanitize close buttons if not inheriting.
		if ( ! $sanitized['inheritCloseButtons'] ) {
			// Note: Unchecked checkboxes don't appear in POST data, so we use array_key_exists to detect if they were present.
			// If the key exists, use the value; if not, it means unchecked, so set to false.
			$sanitized['showIconClose'] = array_key_exists( 'showIconClose', $value ) ? (bool) $value['showIconClose'] : false;
			$sanitized['showFooterClose'] = array_key_exists( 'showFooterClose', $value ) ? (bool) $value['showFooterClose'] : false;
			$sanitized['footerCloseText'] = isset( $value['footerCloseText'] ) ? sanitize_text_field( wp_unslash( $value['footerCloseText'] ) ) : $defaults['footerCloseText'];
		} else {
			$sanitized['showIconClose'] = $defaults['showIconClose'];
			$sanitized['showFooterClose'] = $defaults['showFooterClose'];
			$sanitized['footerCloseText'] = $defaults['footerCloseText'];
		}

		// Gallery-specific settings.
		$allowed_navigation = array( 'image', 'footer', 'both' );
		$sanitized['imageNavigation'] = isset( $value['imageNavigation'] ) && in_array( $value['imageNavigation'], $allowed_navigation, true ) ? $value['imageNavigation'] : $defaults['imageNavigation'];

		// Note: Unchecked checkboxes don't appear in POST data, so we use array_key_exists to detect if they were present.
		// If the key exists, use the value; if not, it means unchecked, so set to false.
		$sanitized['showCaptions'] = array_key_exists( 'showCaptions', $value ) ? (bool) $value['showCaptions'] : false;
		$sanitized['crossfadeTransition'] = array_key_exists( 'crossfadeTransition', $value ) ? (bool) $value['crossfadeTransition'] : false;
		$sanitized['transitionDuration'] = isset( $value['transitionDuration'] ) ? $this->sanitize_number_range( $value['transitionDuration'], 0, 2000, $defaults['transitionDuration'] ) : $defaults['transitionDuration'];
		$sanitized['preloadAdjacentImages'] = array_key_exists( 'preloadAdjacentImages', $value ) ? (bool) $value['preloadAdjacentImages'] : false;
		$sanitized['showNavOnHover'] = array_key_exists( 'showNavOnHover', $value ) ? (bool) $value['showNavOnHover'] : false;

		return $sanitized;
	}

	/**
	 * Get default prompt template
	 *
	 * @return string Default prompt
	 */
	private function get_default_prompt() {
		return "Create a concise TLDR (Too Long; Didn't Read) summary of the following content. The summary should be 2-3 sentences and capture the main points.";
	}

	/**
	 * Render settings section description
	 */
	public function render_tldr_section_description() {
		$ai_available = $this->check_ai_availability();
		?>
		<p><?php esc_html_e( 'Configure the AI-powered TLDR feature that generates page summaries on-demand.', 'synced-pattern-popups' ); ?></p>
		<?php if ( ! $ai_available['plugin_active'] ) : ?>
			<div class="notice notice-warning inline">
				<p><?php esc_html_e( 'AI Experiments plugin is not active. TLDR feature requires the AI Experiments plugin to be installed and activated.', 'synced-pattern-popups' ); ?></p>
			</div>
		<?php elseif ( ! $ai_available['credentials'] ) : ?>
			<div class="notice notice-warning inline">
				<p><?php esc_html_e( 'AI credentials are not configured. Please configure AI credentials in Settings → AI Experiments.', 'synced-pattern-popups' ); ?></p>
			</div>
		<?php else : ?>
			<div class="notice notice-success inline">
				<p><?php esc_html_e( 'AI Experiments plugin is active and credentials are configured.', 'synced-pattern-popups' ); ?></p>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render TLDR enabled field
	 */
	public function render_tldr_enabled_field() {
		$value = get_option( 'sppopups_tldr_enabled', false );
		?>
		<label>
			<input type="checkbox" name="sppopups_tldr_enabled" value="1" <?php checked( $value, true ); ?> />
			<?php esc_html_e( 'Enable AI-powered TLDR feature', 'synced-pattern-popups' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When enabled, users can click elements with class "spp-trigger-tldr" to generate and display AI-powered summaries of the current page.', 'synced-pattern-popups' ); ?>
		</p>
		<?php
	}

	/**
	 * Render TLDR prompt field
	 */
	public function render_tldr_prompt_field() {
		$value = get_option( 'sppopups_tldr_prompt', $this->get_default_prompt() );
		?>
		<textarea name="sppopups_tldr_prompt" rows="5" cols="50" class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'The prompt template used to generate TLDR summaries. The page content will be automatically appended to your prompt.', 'synced-pattern-popups' ); ?>
		</p>
		<?php
	}

	/**
	 * Render TLDR cache TTL field
	 */
	public function render_tldr_cache_ttl_field() {
		$value = get_option( 'sppopups_tldr_cache_ttl', 12 );
		?>
		<input type="number" name="sppopups_tldr_cache_ttl" value="<?php echo esc_attr( $value ); ?>" min="1" max="168" step="1" />
		<p class="description">
			<?php esc_html_e( 'How long to cache generated TLDR summaries (in hours). Default: 12 hours.', 'synced-pattern-popups' ); ?>
		</p>
		<?php
	}

	/**
	 * Check AI availability
	 *
	 * @return array Status of AI plugin and credentials
	 */
	private function check_ai_availability() {
		$plugin_slug      = 'ai/ai.php';
		$plugin_installed = $this->is_ai_plugin_installed();
		$plugin_active    = $this->is_ai_plugin_active();
		$credentials      = false;

		if ( $plugin_active && function_exists( 'WordPress\AI\has_valid_ai_credentials' ) ) {
			$credentials = \WordPress\AI\has_valid_ai_credentials();
		}

		return array(
			'plugin_installed' => $plugin_installed,
			'plugin_active'    => $plugin_active,
			'credentials'      => $credentials,
			'plugin_slug'      => $plugin_slug,
			'settings_url'     => $this->get_ai_experiments_settings_url(),
		);
	}

	/**
	 * Check if AI Experiments plugin is installed
	 *
	 * @return bool True if plugin is installed
	 */
	private function is_ai_plugin_installed() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();
		return isset( $plugins['ai/ai.php'] );
	}

	/**
	 * Check if AI Experiments plugin is active
	 *
	 * @return bool True if plugin is active
	 */
	private function is_ai_plugin_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( 'ai/ai.php' );
	}

	/**
	 * Get AI Experiments settings page URL
	 *
	 * @return string Settings page URL
	 */
	private function get_ai_experiments_settings_url() {
		// Try to get URL from AI Experiments plugin if it exposes a function.
		if ( function_exists( 'WordPress\AI\get_settings_url' ) ) {
			$url = \WordPress\AI\get_settings_url();
			if ( ! empty( $url ) ) {
				return $url;
			}
		}

		// Fallback to standard settings page.
		return admin_url( 'options-general.php?page=wp-ai-client' );
	}

	/**
	 * Render settings section for admin page
	 */
	public function render_settings_section() {
		$ai_available = $this->check_ai_availability();
		?>
		<div class="sppopups-settings-section" style="margin-top: 40px; padding: 24px; background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 4px; max-width: 800px;">
			<h2 style="margin-top: 0; margin-bottom: 16px; font-size: 18px; font-weight: 600; color: #1d2327;">
				<?php esc_html_e( 'AI TLDR Settings', 'synced-pattern-popups' ); ?>
			</h2>

			<?php if ( ! $ai_available['plugin_active'] ) : ?>
				<div class="notice notice-warning inline" style="margin: 0 0 20px 0;">
					<p><?php esc_html_e( 'AI Experiments plugin is not active. TLDR feature requires the AI Experiments plugin to be installed and activated.', 'synced-pattern-popups' ); ?></p>
				</div>
			<?php elseif ( ! $ai_available['credentials'] ) : ?>
				<div class="notice notice-warning inline" style="margin: 0 0 20px 0;">
					<p><?php esc_html_e( 'AI credentials are not configured. Please configure AI credentials in Settings → AI Experiments.', 'synced-pattern-popups' ); ?></p>
				</div>
			<?php else : ?>
				<div class="notice notice-success inline" style="margin: 0 0 20px 0;">
					<p><?php esc_html_e( 'AI Experiments plugin is active and credentials are configured.', 'synced-pattern-popups' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="">
				<?php wp_nonce_field( 'sppopups_save_tldr_settings', 'sppopups_tldr_settings_nonce' ); ?>
				<input type="hidden" name="sppopups_current_tab" id="sppopups-tldr-current-tab-alt" value="tldr" />

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="sppopups_tldr_enabled">
									<?php esc_html_e( 'Enable TLDR Feature', 'synced-pattern-popups' ); ?>
								</label>
							</th>
							<td>
								<label>
									<input type="checkbox" name="sppopups_tldr_enabled" id="sppopups_tldr_enabled" value="1" <?php checked( get_option( 'sppopups_tldr_enabled', false ), true ); ?> />
									<?php esc_html_e( 'Enable AI-powered TLDR feature', 'synced-pattern-popups' ); ?>
								</label>
								<p class="description" style="margin-top: 8px;">
									<?php esc_html_e( 'When enabled, users can click elements with class "spp-trigger-tldr" to generate and display AI-powered summaries of the current page.', 'synced-pattern-popups' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="sppopups_tldr_prompt_type">
									<?php esc_html_e( 'TLDR Prompt', 'synced-pattern-popups' ); ?>
								</label>
							</th>
							<td>
								<?php
								$prompt_type    = get_option( 'sppopups_tldr_prompt_custom', false ) ? 'custom' : 'default';
								$custom_prompt  = get_option( 'sppopups_tldr_prompt', '' );
								$default_prompt = $this->get_default_prompt();
								?>
								<fieldset>
									<label style="margin-right: 20px;">
										<input type="radio" name="sppopups_tldr_prompt_type" value="default" <?php checked( $prompt_type, 'default' ); ?> />
										<?php esc_html_e( 'Default', 'synced-pattern-popups' ); ?>
									</label>
									<label>
										<input type="radio" name="sppopups_tldr_prompt_type" value="custom" <?php checked( $prompt_type, 'custom' ); ?> />
										<?php esc_html_e( 'Custom', 'synced-pattern-popups' ); ?>
									</label>
								</fieldset>
								<div id="sppopups-tldr-prompt-custom-wrapper" style="margin-top: 12px; <?php echo ( 'custom' !== $prompt_type ) ? 'display: none;' : ''; ?>">
									<textarea name="sppopups_tldr_prompt" id="sppopups_tldr_prompt" rows="5" cols="50" class="large-text code" style="font-family: 'Courier New', Courier, monospace; font-size: 13px;"><?php echo esc_textarea( ! empty( $custom_prompt ) ? $custom_prompt : $default_prompt ); ?></textarea>
								</div>
								<p class="description" style="margin-top: 8px;">
									<?php esc_html_e( 'The prompt template used to generate TLDR summaries. The page content will be automatically appended to your prompt.', 'synced-pattern-popups' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="sppopups_tldr_cache_ttl">
									<?php esc_html_e( 'Cache Duration', 'synced-pattern-popups' ); ?>
								</label>
							</th>
							<td>
								<input type="number" name="sppopups_tldr_cache_ttl" id="sppopups_tldr_cache_ttl" value="<?php echo esc_attr( get_option( 'sppopups_tldr_cache_ttl', 12 ) ); ?>" min="1" max="168" step="1" style="width: 80px;" />
								<span style="margin-left: 8px;"><?php esc_html_e( 'hours', 'synced-pattern-popups' ); ?></span>
								<p class="description" style="margin-top: 8px;">
									<?php esc_html_e( 'How long to cache generated TLDR summaries. Default: 12 hours.', 'synced-pattern-popups' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button( __( 'Save TLDR Settings', 'synced-pattern-popups' ), 'primary', 'save_tldr_settings', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render settings section for tab (without box wrapper)
	 */
	public function render_settings_section_for_tab() {
		$ai_available         = $this->check_ai_availability();
		$all_requirements_met = $ai_available['plugin_installed'] && $ai_available['plugin_active'] && $ai_available['credentials'];
		?>
		<div class="sppopups-tab-content-inner">
			<h2><?php esc_html_e( 'AI TLDR Settings', 'synced-pattern-popups' ); ?></h2>

			<?php if ( ! $all_requirements_met ) : ?>
				<p class="description">
					<?php esc_html_e( 'The AI-powered TLDR feature generates concise summaries of your page content on-demand. To use this feature, you need to complete the following requirements:', 'synced-pattern-popups' ); ?>
				</p>
				<?php $this->render_requirements_checklist( $ai_available ); ?>
			<?php else : ?>
				<div class="notice notice-success inline">
					<p><?php esc_html_e( 'AI Experiments plugin is active and credentials are configured. You can now configure the TLDR feature settings below.', 'synced-pattern-popups' ); ?></p>
				</div>

				<form method="post" action="">
					<?php wp_nonce_field( 'sppopups_save_tldr_settings', 'sppopups_tldr_settings_nonce' ); ?>
					<input type="hidden" name="sppopups_current_tab" id="sppopups-tldr-current-tab" value="tldr" />

					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row">
									<label for="sppopups_tldr_enabled_tab">
										<?php esc_html_e( 'Enable TLDR Feature', 'synced-pattern-popups' ); ?>
									</label>
								</th>
								<td>
									<label>
										<input type="checkbox" name="sppopups_tldr_enabled" id="sppopups_tldr_enabled_tab" value="1" <?php checked( get_option( 'sppopups_tldr_enabled', false ), true ); ?> />
										<?php esc_html_e( 'Enable AI-powered TLDR feature', 'synced-pattern-popups' ); ?>
									</label>
									<p class="description">
										<?php esc_html_e( 'When enabled, users can click elements with class "spp-trigger-tldr" to generate and display AI-powered summaries of the current page.', 'synced-pattern-popups' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="sppopups_tldr_prompt_type_tab">
										<?php esc_html_e( 'TLDR Prompt', 'synced-pattern-popups' ); ?>
									</label>
								</th>
								<td>
									<?php
									$prompt_type    = get_option( 'sppopups_tldr_prompt_custom', false ) ? 'custom' : 'default';
									$custom_prompt  = get_option( 'sppopups_tldr_prompt', '' );
									$default_prompt = $this->get_default_prompt();
									?>
									<fieldset>
										<label style="margin-right: 20px;">
											<input type="radio" name="sppopups_tldr_prompt_type" value="default" id="sppopups_tldr_prompt_type_tab_default" <?php checked( $prompt_type, 'default' ); ?> />
											<?php esc_html_e( 'Default', 'synced-pattern-popups' ); ?>
										</label>
										<label>
											<input type="radio" name="sppopups_tldr_prompt_type" value="custom" id="sppopups_tldr_prompt_type_tab_custom" <?php checked( $prompt_type, 'custom' ); ?> />
											<?php esc_html_e( 'Custom', 'synced-pattern-popups' ); ?>
										</label>
									</fieldset>
									<div id="sppopups-tldr-prompt-custom-wrapper-tab" style="margin-top: 12px; <?php echo ( 'custom' !== $prompt_type ) ? 'display: none;' : ''; ?>">
										<textarea name="sppopups_tldr_prompt" id="sppopups_tldr_prompt_tab" rows="5" cols="50" class="large-text code"><?php echo esc_textarea( ! empty( $custom_prompt ) ? $custom_prompt : $default_prompt ); ?></textarea>
									</div>
									<p class="description">
										<?php esc_html_e( 'The prompt template used to generate TLDR summaries. The page content will be automatically appended to your prompt.', 'synced-pattern-popups' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="sppopups_tldr_cache_ttl_tab">
										<?php esc_html_e( 'Cache Duration', 'synced-pattern-popups' ); ?>
									</label>
								</th>
								<td>
									<input type="number" name="sppopups_tldr_cache_ttl" id="sppopups_tldr_cache_ttl_tab" value="<?php echo esc_attr( get_option( 'sppopups_tldr_cache_ttl', 12 ) ); ?>" min="1" max="168" step="1" style="width: 80px;" />
									<span style="margin-left: 8px;"><?php esc_html_e( 'hours', 'synced-pattern-popups' ); ?></span>
									<p class="description">
										<?php esc_html_e( 'How long to cache generated TLDR summaries. Default: 12 hours.', 'synced-pattern-popups' ); ?>
									</p>
								</td>
							</tr>
						</tbody>
					</table>

					<?php submit_button( __( 'Save TLDR Settings', 'synced-pattern-popups' ), 'primary', 'save_tldr_settings', false ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render requirements checklist
	 *
	 * @param array $status AI availability status array.
	 */
	private function render_requirements_checklist( $status ) {
		?>
		<ul class="sppopups-requirements-checklist">
			<li class="sppopups-requirement-item <?php echo $status['plugin_installed'] ? 'requirement-met' : 'requirement-not-met'; ?>">
				<span class="sppopups-requirement-status">
					<span class="sppopups-status-circle"></span>
				</span>
				<div class="sppopups-requirement-content">
					<div class="sppopups-requirement-header">
						<span class="sppopups-requirement-number"><?php esc_html_e( 'Step 1', 'synced-pattern-popups' ); ?></span>
						<span class="sppopups-requirement-text">
							<?php esc_html_e( 'AI Experiments plugin installed', 'synced-pattern-popups' ); ?>
						</span>
					</div>
					<p class="sppopups-requirement-description">
						<?php esc_html_e( 'Install the AI Experiments plugin from the WordPress repository', 'synced-pattern-popups' ); ?>
					</p>
				</div>
				<?php if ( ! $status['plugin_installed'] ) : ?>
					<span class="sppopups-requirement-action">
						<?php
						$install_url = wp_nonce_url(
							admin_url( 'themes.php?page=simplest-popup-patterns&action=install_ai_experiments' ),
							'install_ai_experiments'
						);
						?>
						<a href="<?php echo esc_url( $install_url ); ?>" class="sppopups-action-button sppopups-install-button">
							<span class="sppopups-button-text"><?php esc_html_e( 'Install', 'synced-pattern-popups' ); ?></span>
							<span class="sppopups-loading-dots" style="display: none;">
								<span></span>
								<span></span>
								<span></span>
							</span>
						</a>
					</span>
				<?php endif; ?>
			</li>
			<li class="sppopups-requirement-item <?php echo $status['plugin_active'] ? 'requirement-met' : 'requirement-not-met'; ?>">
				<span class="sppopups-requirement-status">
					<span class="sppopups-status-circle"></span>
				</span>
				<div class="sppopups-requirement-content">
					<div class="sppopups-requirement-header">
						<span class="sppopups-requirement-number"><?php esc_html_e( 'Step 2', 'synced-pattern-popups' ); ?></span>
						<span class="sppopups-requirement-text">
							<?php esc_html_e( 'AI Experiments plugin activated', 'synced-pattern-popups' ); ?>
						</span>
					</div>
					<p class="sppopups-requirement-description">
						<?php esc_html_e( 'Activate the AI Experiments plugin to enable AI features', 'synced-pattern-popups' ); ?>
					</p>
				</div>
				<?php if ( $status['plugin_installed'] && ! $status['plugin_active'] ) : ?>
					<span class="sppopups-requirement-action">
						<?php
						$activate_url = wp_nonce_url(
							admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $status['plugin_slug'] ) . '&plugin_status=all&paged=1&s=&sppopups_redirect=1' ),
							'activate-plugin_' . $status['plugin_slug']
						);
						?>
						<a href="<?php echo esc_url( $activate_url ); ?>" class="sppopups-action-button">
							<?php esc_html_e( 'Activate', 'synced-pattern-popups' ); ?>
						</a>
					</span>
				<?php endif; ?>
			</li>
			<li class="sppopups-requirement-item <?php echo $status['credentials'] ? 'requirement-met' : 'requirement-not-met'; ?>">
				<span class="sppopups-requirement-status">
					<span class="sppopups-status-circle"></span>
				</span>
				<div class="sppopups-requirement-content">
					<div class="sppopups-requirement-header">
						<span class="sppopups-requirement-number"><?php esc_html_e( 'Step 3', 'synced-pattern-popups' ); ?></span>
						<span class="sppopups-requirement-text">
							<?php esc_html_e( 'AI credentials saved in settings', 'synced-pattern-popups' ); ?>
						</span>
					</div>
					<p class="sppopups-requirement-description">
						<?php esc_html_e( 'Configure your AI API credentials in the plugin settings', 'synced-pattern-popups' ); ?>
					</p>
				</div>
				<?php if ( $status['plugin_active'] && ! $status['credentials'] ) : ?>
					<span class="sppopups-requirement-action">
						<a href="<?php echo esc_url( $status['settings_url'] ); ?>" class="sppopups-action-button">
							<?php esc_html_e( 'Start', 'synced-pattern-popups' ); ?>
						</a>
					</span>
				<?php endif; ?>
			</li>
		</ul>
		<?php
	}

	/**
	 * Get TLDR enabled status
	 *
	 * @return bool True if enabled
	 */
	public static function is_tldr_enabled() {
		return (bool) get_option( 'sppopups_tldr_enabled', false );
	}

	/**
	 * Get TLDR prompt template
	 *
	 * @return string Prompt template
	 */
	public static function get_tldr_prompt() {
		$default    = "Create a concise TLDR (Too Long; Didn't Read) summary of the following content. The summary should be 2-3 sentences and capture the main points.";
		$use_custom = get_option( 'sppopups_tldr_prompt_custom', false );

		if ( $use_custom ) {
			$custom_prompt = get_option( 'sppopups_tldr_prompt', '' );
			return ! empty( $custom_prompt ) ? $custom_prompt : $default;
		}

		return $default;
	}

	/**
	 * Get TLDR cache TTL in seconds
	 *
	 * @return int Cache TTL in seconds
	 */
	public static function get_tldr_cache_ttl() {
		$hours = (int) get_option( 'sppopups_tldr_cache_ttl', 12 );
		return $hours * HOUR_IN_SECONDS;
	}

	/**
	 * Render defaults section for admin page
	 */
	public function render_defaults_section() {
		?>
		<div class="sppopups-tab-content-inner">
			<h2><?php esc_html_e( 'Popup Defaults', 'synced-pattern-popups' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Configure default appearance and behavior settings for all popup types. These settings will be used unless overridden by individual popups.', 'synced-pattern-popups' ); ?>
			</p>

			<form method="post" action="">
				<?php wp_nonce_field( 'sppopups_save_defaults_settings', 'sppopups_defaults_settings_nonce' ); ?>
				<input type="hidden" name="sppopups_current_tab" id="sppopups-defaults-current-tab" value="defaults" />

				<?php $this->render_pattern_defaults_section(); ?>
				<?php $this->render_tldr_defaults_section(); ?>
				<?php $this->render_gallery_defaults_section(); ?>

				<?php submit_button( __( 'Save Defaults', 'synced-pattern-popups' ), 'primary', 'save_defaults_settings', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render pattern defaults section
	 */
	private function render_pattern_defaults_section() {
		$defaults = $this->get_default_pattern_defaults();
		$saved = get_option( 'sppopups_defaults_pattern', array() );
		$values = wp_parse_args( $saved, $defaults );
		?>
		<div class="sppopups-defaults-accordion" style="margin-top: 30px;">
			<div class="sppopups-defaults-accordion-header">
				<button type="button" class="sppopups-defaults-accordion-trigger" aria-expanded="false" aria-controls="sppopups-defaults-pattern-content">
					<span class="sppopups-defaults-accordion-title"><?php esc_html_e( 'Pattern Popups Defaults', 'synced-pattern-popups' ); ?></span>
					<span class="sppopups-defaults-accordion-icon" aria-hidden="true"></span>
				</button>
			</div>
			<div id="sppopups-defaults-pattern-content" class="sppopups-defaults-accordion-content" style="display: none; padding: 24px; background: #f6f7f7; border: 1px solid #dcdcde; border-top: none; border-radius: 0 0 4px 4px;">

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="pattern_max_width">
								<?php esc_html_e( 'Default Width', 'synced-pattern-popups' ); ?>
							</label>
						</th>
						<td>
							<input type="number" name="sppopups_defaults_pattern[maxWidth]" id="pattern_max_width" value="<?php echo esc_attr( $values['maxWidth'] ); ?>" min="100" max="5000" step="1" style="width: 100px;" />
							<span style="margin-left: 8px;"><?php esc_html_e( 'px', 'synced-pattern-popups' ); ?></span>
							<p class="description">
								<?php esc_html_e( 'Default modal width in pixels (100-5000).', 'synced-pattern-popups' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pattern_border_radius">
								<?php esc_html_e( 'Border Radius', 'synced-pattern-popups' ); ?>
							</label>
						</th>
						<td>
							<input type="number" name="sppopups_defaults_pattern[borderRadius]" id="pattern_border_radius" value="<?php echo esc_attr( $values['borderRadius'] ); ?>" min="0" max="50" step="1" style="width: 100px;" />
							<span style="margin-left: 8px;"><?php esc_html_e( 'px', 'synced-pattern-popups' ); ?></span>
							<p class="description">
								<?php esc_html_e( 'Modal border radius in pixels (0-50).', 'synced-pattern-popups' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pattern_max_height">
								<?php esc_html_e( 'Max Height', 'synced-pattern-popups' ); ?>
							</label>
						</th>
						<td>
							<input type="number" name="sppopups_defaults_pattern[maxHeight]" id="pattern_max_height" value="<?php echo esc_attr( $values['maxHeight'] ); ?>" min="50" max="100" step="1" style="width: 100px;" />
							<span style="margin-left: 8px;"><?php esc_html_e( '%', 'synced-pattern-popups' ); ?></span>
							<p class="description">
								<?php esc_html_e( 'Maximum modal height as percentage of viewport (50-100).', 'synced-pattern-popups' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pattern_overlay_color">
								<?php esc_html_e( 'Overlay Color', 'synced-pattern-popups' ); ?>
							</label>
						</th>
						<td>
							<input type="text" name="sppopups_defaults_pattern[overlayColor]" id="pattern_overlay_color" value="<?php echo esc_attr( $values['overlayColor'] ); ?>" class="regular-text" placeholder="rgba(0, 0, 0, 0.1)" />
							<p class="description">
								<?php esc_html_e( 'Overlay background color in rgba format (e.g., rgba(0, 0, 0, 0.1)).', 'synced-pattern-popups' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pattern_backdrop_blur">
								<?php esc_html_e( 'Backdrop Blur', 'synced-pattern-popups' ); ?>
							</label>
						</th>
						<td>
							<input type="number" name="sppopups_defaults_pattern[backdropBlur]" id="pattern_backdrop_blur" value="<?php echo esc_attr( $values['backdropBlur'] ); ?>" min="0" max="20" step="1" style="width: 100px;" />
							<span style="margin-left: 8px;"><?php esc_html_e( 'px', 'synced-pattern-popups' ); ?></span>
							<p class="description">
								<?php esc_html_e( 'Backdrop blur amount in pixels (0-20).', 'synced-pattern-popups' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Close Buttons', 'synced-pattern-popups' ); ?>
						</th>
						<td>
							<fieldset>
								<label>
									<input type="checkbox" name="sppopups_defaults_pattern[showIconClose]" value="1" <?php checked( $values['showIconClose'], true ); ?> />
									<?php esc_html_e( 'Show icon close button', 'synced-pattern-popups' ); ?>
								</label>
								<br />
								<label>
									<input type="checkbox" name="sppopups_defaults_pattern[showFooterClose]" value="1" <?php checked( $values['showFooterClose'], true ); ?> />
									<?php esc_html_e( 'Show footer close button', 'synced-pattern-popups' ); ?>
								</label>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pattern_footer_close_text">
								<?php esc_html_e( 'Footer Button Text', 'synced-pattern-popups' ); ?>
							</label>
						</th>
						<td>
							<input type="text" name="sppopups_defaults_pattern[footerCloseText]" id="pattern_footer_close_text" value="<?php echo esc_attr( $values['footerCloseText'] ); ?>" class="regular-text" />
							<p class="description">
								<?php esc_html_e( 'Text displayed on the footer close button.', 'synced-pattern-popups' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render TLDR defaults section
	 */
	private function render_tldr_defaults_section() {
		$defaults = $this->get_default_tldr_defaults();
		$saved = get_option( 'sppopups_defaults_tldr', array() );
		$values = wp_parse_args( $saved, $defaults );
		$pattern_defaults = self::get_pattern_defaults();
		?>
		<div class="sppopups-defaults-accordion" style="margin-top: 30px;">
			<div class="sppopups-defaults-accordion-header">
				<button type="button" class="sppopups-defaults-accordion-trigger" aria-expanded="false" aria-controls="sppopups-defaults-tldr-content">
					<span class="sppopups-defaults-accordion-title"><?php esc_html_e( 'TLDR Popups Defaults', 'synced-pattern-popups' ); ?></span>
					<span class="sppopups-defaults-accordion-icon" aria-hidden="true"></span>
				</button>
			</div>
			<div id="sppopups-defaults-tldr-content" class="sppopups-defaults-accordion-content" style="display: none; padding: 24px; background: #f6f7f7; border: 1px solid #dcdcde; border-top: none; border-radius: 0 0 4px 4px;">

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Modal Appearance', 'synced-pattern-popups' ); ?>
						</th>
						<td>
							<fieldset>
								<label>
									<input type="radio" name="sppopups_defaults_tldr[inheritModalAppearance]" value="1" <?php checked( $values['inheritModalAppearance'], true ); ?> class="tldr-inherit-modal-appearance" />
									<?php esc_html_e( 'Inherit from Pattern Modal', 'synced-pattern-popups' ); ?>
								</label>
								<br />
								<label>
									<input type="radio" name="sppopups_defaults_tldr[inheritModalAppearance]" value="0" <?php checked( $values['inheritModalAppearance'], false ); ?> class="tldr-inherit-modal-appearance" />
									<?php esc_html_e( 'Custom', 'synced-pattern-popups' ); ?>
								</label>
							</fieldset>
							<div id="tldr-modal-appearance-custom" style="margin-top: 12px; <?php echo $values['inheritModalAppearance'] ? 'display: none;' : ''; ?>">
								<table class="form-table" role="presentation" style="margin-top: 0;">
									<tbody>
										<tr>
											<th scope="row">
												<label for="tldr_max_width">
													<?php esc_html_e( 'Default Width', 'synced-pattern-popups' ); ?>
												</label>
											</th>
											<td>
												<input type="number" name="sppopups_defaults_tldr[maxWidth]" id="tldr_max_width" value="<?php echo esc_attr( $values['maxWidth'] ); ?>" min="100" max="5000" step="1" style="width: 100px;" />
												<span style="margin-left: 8px;"><?php esc_html_e( 'px', 'synced-pattern-popups' ); ?></span>
											</td>
										</tr>
										<tr>
											<th scope="row">
												<label for="tldr_border_radius">
													<?php esc_html_e( 'Border Radius', 'synced-pattern-popups' ); ?>
												</label>
											</th>
											<td>
												<input type="number" name="sppopups_defaults_tldr[borderRadius]" id="tldr_border_radius" value="<?php echo esc_attr( $values['borderRadius'] ); ?>" min="0" max="50" step="1" style="width: 100px;" />
												<span style="margin-left: 8px;"><?php esc_html_e( 'px', 'synced-pattern-popups' ); ?></span>
											</td>
										</tr>
										<tr>
											<th scope="row">
												<label for="tldr_max_height">
													<?php esc_html_e( 'Max Height', 'synced-pattern-popups' ); ?>
												</label>
											</th>
											<td>
												<input type="number" name="sppopups_defaults_tldr[maxHeight]" id="tldr_max_height" value="<?php echo esc_attr( $values['maxHeight'] ); ?>" min="50" max="100" step="1" style="width: 100px;" />
												<span style="margin-left: 8px;"><?php esc_html_e( '%', 'synced-pattern-popups' ); ?></span>
											</td>
										</tr>
									</tbody>
								</table>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Overlay', 'synced-pattern-popups' ); ?>
						</th>
						<td>
							<fieldset>
								<label>
									<input type="radio" name="sppopups_defaults_tldr[inheritOverlay]" value="1" <?php checked( $values['inheritOverlay'], true ); ?> class="tldr-inherit-overlay" />
									<?php esc_html_e( 'Inherit from Pattern Modal', 'synced-pattern-popups' ); ?>
								</label>
								<br />
								<label>
									<input type="radio" name="sppopups_defaults_tldr[inheritOverlay]" value="0" <?php checked( $values['inheritOverlay'], false ); ?> class="tldr-inherit-overlay" />
									<?php esc_html_e( 'Custom', 'synced-pattern-popups' ); ?>
								</label>
							</fieldset>
							<div id="tldr-overlay-custom" style="margin-top: 12px; <?php echo $values['inheritOverlay'] ? 'display: none;' : ''; ?>">
								<table class="form-table" role="presentation" style="margin-top: 0;">
									<tbody>
										<tr>
											<th scope="row">
												<label for="tldr_overlay_color">
													<?php esc_html_e( 'Overlay Color', 'synced-pattern-popups' ); ?>
												</label>
											</th>
											<td>
												<input type="text" name="sppopups_defaults_tldr[overlayColor]" id="tldr_overlay_color" value="<?php echo esc_attr( $values['overlayColor'] ); ?>" class="regular-text" placeholder="rgba(0, 0, 0, 0.1)" />
											</td>
										</tr>
										<tr>
											<th scope="row">
												<label for="tldr_backdrop_blur">
													<?php esc_html_e( 'Backdrop Blur', 'synced-pattern-popups' ); ?>
												</label>
											</th>
											<td>
												<input type="number" name="sppopups_defaults_tldr[backdropBlur]" id="tldr_backdrop_blur" value="<?php echo esc_attr( $values['backdropBlur'] ); ?>" min="0" max="20" step="1" style="width: 100px;" />
												<span style="margin-left: 8px;"><?php esc_html_e( 'px', 'synced-pattern-popups' ); ?></span>
											</td>
										</tr>
									</tbody>
								</table>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Close Buttons', 'synced-pattern-popups' ); ?>
						</th>
						<td>
							<fieldset>
								<label>
									<input type="radio" name="sppopups_defaults_tldr[inheritCloseButtons]" value="1" <?php checked( $values['inheritCloseButtons'], true ); ?> class="tldr-inherit-close-buttons" />
									<?php esc_html_e( 'Inherit from Pattern Modal', 'synced-pattern-popups' ); ?>
								</label>
								<br />
								<label>
									<input type="radio" name="sppopups_defaults_tldr[inheritCloseButtons]" value="0" <?php checked( $values['inheritCloseButtons'], false ); ?> class="tldr-inherit-close-buttons" />
									<?php esc_html_e( 'Custom', 'synced-pattern-popups' ); ?>
								</label>
							</fieldset>
							<div id="tldr-close-buttons-custom" style="margin-top: 12px; <?php echo $values['inheritCloseButtons'] ? 'display: none;' : ''; ?>">
								<table class="form-table" role="presentation" style="margin-top: 0;">
									<tbody>
										<tr>
											<th scope="row">
												<?php esc_html_e( 'Options', 'synced-pattern-popups' ); ?>
											</th>
											<td>
												<fieldset>
													<label>
														<input type="checkbox" name="sppopups_defaults_tldr[showIconClose]" value="1" <?php checked( $values['showIconClose'], true ); ?> />
														<?php esc_html_e( 'Show icon close button', 'synced-pattern-popups' ); ?>
													</label>
													<br />
													<label>
														<input type="checkbox" name="sppopups_defaults_tldr[showFooterClose]" value="1" <?php checked( $values['showFooterClose'], true ); ?> />
														<?php esc_html_e( 'Show footer close button', 'synced-pattern-popups' ); ?>
													</label>
												</fieldset>
											</td>
										</tr>
										<tr>
											<th scope="row">
												<label for="tldr_footer_close_text">
													<?php esc_html_e( 'Footer Button Text', 'synced-pattern-popups' ); ?>
												</label>
											</th>
											<td>
												<input type="text" name="sppopups_defaults_tldr[footerCloseText]" id="tldr_footer_close_text" value="<?php echo esc_attr( $values['footerCloseText'] ); ?>" class="regular-text" />
											</td>
										</tr>
									</tbody>
								</table>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="tldr_loading_text">
								<?php esc_html_e( 'Loading Text', 'synced-pattern-popups' ); ?>
							</label>
						</th>
						<td>
							<input type="text" name="sppopups_defaults_tldr[loadingText]" id="tldr_loading_text" value="<?php echo esc_attr( $values['loadingText'] ); ?>" class="regular-text" />
							<p class="description">
								<?php esc_html_e( 'Text displayed while generating TLDR summary.', 'synced-pattern-popups' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="tldr_title_text">
								<?php esc_html_e( 'Title Text', 'synced-pattern-popups' ); ?>
							</label>
						</th>
						<td>
							<input type="text" name="sppopups_defaults_tldr[titleText]" id="tldr_title_text" value="<?php echo esc_attr( $values['titleText'] ); ?>" class="regular-text" />
							<p class="description">
								<?php esc_html_e( 'Title displayed in TLDR modal header.', 'synced-pattern-popups' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render gallery defaults section
	 */
	private function render_gallery_defaults_section() {
		$defaults = $this->get_default_gallery_defaults();
		$saved = get_option( 'sppopups_defaults_gallery', array() );
		$values = wp_parse_args( $saved, $defaults );
		$pattern_defaults = self::get_pattern_defaults();
		?>
		<div class="sppopups-defaults-accordion" style="margin-top: 30px;">
			<div class="sppopups-defaults-accordion-header">
				<button type="button" class="sppopups-defaults-accordion-trigger" aria-expanded="false" aria-controls="sppopups-defaults-gallery-content">
					<span class="sppopups-defaults-accordion-title"><?php esc_html_e( 'Gallery Popups Defaults', 'synced-pattern-popups' ); ?></span>
					<span class="sppopups-defaults-accordion-icon" aria-hidden="true"></span>
				</button>
			</div>
			<div id="sppopups-defaults-gallery-content" class="sppopups-defaults-accordion-content" style="display: none; padding: 24px; background: #f6f7f7; border: 1px solid #dcdcde; border-top: none; border-radius: 0 0 4px 4px;">

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Modal Appearance', 'synced-pattern-popups' ); ?>
						</th>
						<td>
							<fieldset>
								<label>
									<input type="radio" name="sppopups_defaults_gallery[inheritModalAppearance]" value="1" <?php checked( $values['inheritModalAppearance'], true ); ?> class="gallery-inherit-modal-appearance" />
									<?php esc_html_e( 'Inherit from Pattern Modal', 'synced-pattern-popups' ); ?>
								</label>
								<br />
								<label>
									<input type="radio" name="sppopups_defaults_gallery[inheritModalAppearance]" value="0" <?php checked( $values['inheritModalAppearance'], false ); ?> class="gallery-inherit-modal-appearance" />
									<?php esc_html_e( 'Custom', 'synced-pattern-popups' ); ?>
								</label>
							</fieldset>
							<div id="gallery-modal-appearance-custom" style="margin-top: 12px; <?php echo $values['inheritModalAppearance'] ? 'display: none;' : ''; ?>">
								<table class="form-table" role="presentation" style="margin-top: 0;">
									<tbody>
										<tr>
											<th scope="row">
												<label for="gallery_max_width">
													<?php esc_html_e( 'Default Width', 'synced-pattern-popups' ); ?>
												</label>
											</th>
											<td>
												<input type="number" name="sppopups_defaults_gallery[maxWidth]" id="gallery_max_width" value="<?php echo esc_attr( $values['maxWidth'] ); ?>" min="100" max="5000" step="1" style="width: 100px;" />
												<span style="margin-left: 8px;"><?php esc_html_e( 'px', 'synced-pattern-popups' ); ?></span>
											</td>
										</tr>
										<tr>
											<th scope="row">
												<label for="gallery_border_radius">
													<?php esc_html_e( 'Border Radius', 'synced-pattern-popups' ); ?>
												</label>
											</th>
											<td>
												<input type="number" name="sppopups_defaults_gallery[borderRadius]" id="gallery_border_radius" value="<?php echo esc_attr( $values['borderRadius'] ); ?>" min="0" max="50" step="1" style="width: 100px;" />
												<span style="margin-left: 8px;"><?php esc_html_e( 'px', 'synced-pattern-popups' ); ?></span>
											</td>
										</tr>
										<tr>
											<th scope="row">
												<label for="gallery_max_height">
													<?php esc_html_e( 'Max Height', 'synced-pattern-popups' ); ?>
												</label>
											</th>
											<td>
												<input type="number" name="sppopups_defaults_gallery[maxHeight]" id="gallery_max_height" value="<?php echo esc_attr( $values['maxHeight'] ); ?>" min="50" max="100" step="1" style="width: 100px;" />
												<span style="margin-left: 8px;"><?php esc_html_e( '%', 'synced-pattern-popups' ); ?></span>
											</td>
										</tr>
									</tbody>
								</table>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Overlay', 'synced-pattern-popups' ); ?>
						</th>
						<td>
							<fieldset>
								<label>
									<input type="radio" name="sppopups_defaults_gallery[inheritOverlay]" value="1" <?php checked( $values['inheritOverlay'], true ); ?> class="gallery-inherit-overlay" />
									<?php esc_html_e( 'Inherit from Pattern Modal', 'synced-pattern-popups' ); ?>
								</label>
								<br />
								<label>
									<input type="radio" name="sppopups_defaults_gallery[inheritOverlay]" value="0" <?php checked( $values['inheritOverlay'], false ); ?> class="gallery-inherit-overlay" />
									<?php esc_html_e( 'Custom', 'synced-pattern-popups' ); ?>
								</label>
							</fieldset>
							<div id="gallery-overlay-custom" style="margin-top: 12px; <?php echo $values['inheritOverlay'] ? 'display: none;' : ''; ?>">
								<table class="form-table" role="presentation" style="margin-top: 0;">
									<tbody>
										<tr>
											<th scope="row">
												<label for="gallery_overlay_color">
													<?php esc_html_e( 'Overlay Color', 'synced-pattern-popups' ); ?>
												</label>
											</th>
											<td>
												<input type="text" name="sppopups_defaults_gallery[overlayColor]" id="gallery_overlay_color" value="<?php echo esc_attr( $values['overlayColor'] ); ?>" class="regular-text" placeholder="rgba(0, 0, 0, 0.1)" />
											</td>
										</tr>
										<tr>
											<th scope="row">
												<label for="gallery_backdrop_blur">
													<?php esc_html_e( 'Backdrop Blur', 'synced-pattern-popups' ); ?>
												</label>
											</th>
											<td>
												<input type="number" name="sppopups_defaults_gallery[backdropBlur]" id="gallery_backdrop_blur" value="<?php echo esc_attr( $values['backdropBlur'] ); ?>" min="0" max="20" step="1" style="width: 100px;" />
												<span style="margin-left: 8px;"><?php esc_html_e( 'px', 'synced-pattern-popups' ); ?></span>
											</td>
										</tr>
									</tbody>
								</table>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Close Buttons', 'synced-pattern-popups' ); ?>
						</th>
						<td>
							<fieldset>
								<label>
									<input type="radio" name="sppopups_defaults_gallery[inheritCloseButtons]" value="1" <?php checked( $values['inheritCloseButtons'], true ); ?> class="gallery-inherit-close-buttons" />
									<?php esc_html_e( 'Inherit from Pattern Modal', 'synced-pattern-popups' ); ?>
								</label>
								<br />
								<label>
									<input type="radio" name="sppopups_defaults_gallery[inheritCloseButtons]" value="0" <?php checked( $values['inheritCloseButtons'], false ); ?> class="gallery-inherit-close-buttons" />
									<?php esc_html_e( 'Custom', 'synced-pattern-popups' ); ?>
								</label>
							</fieldset>
							<div id="gallery-close-buttons-custom" style="margin-top: 12px; <?php echo $values['inheritCloseButtons'] ? 'display: none;' : ''; ?>">
								<table class="form-table" role="presentation" style="margin-top: 0;">
									<tbody>
										<tr>
											<th scope="row">
												<?php esc_html_e( 'Options', 'synced-pattern-popups' ); ?>
											</th>
											<td>
												<fieldset>
													<label>
														<input type="checkbox" name="sppopups_defaults_gallery[showIconClose]" value="1" <?php checked( $values['showIconClose'], true ); ?> />
														<?php esc_html_e( 'Show icon close button', 'synced-pattern-popups' ); ?>
													</label>
													<br />
													<label>
														<input type="checkbox" name="sppopups_defaults_gallery[showFooterClose]" value="1" <?php checked( $values['showFooterClose'], true ); ?> />
														<?php esc_html_e( 'Show footer close button', 'synced-pattern-popups' ); ?>
													</label>
												</fieldset>
											</td>
										</tr>
										<tr>
											<th scope="row">
												<label for="gallery_footer_close_text">
													<?php esc_html_e( 'Footer Button Text', 'synced-pattern-popups' ); ?>
												</label>
											</th>
											<td>
												<input type="text" name="sppopups_defaults_gallery[footerCloseText]" id="gallery_footer_close_text" value="<?php echo esc_attr( $values['footerCloseText'] ); ?>" class="regular-text" />
											</td>
										</tr>
									</tbody>
								</table>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="gallery_image_navigation">
								<?php esc_html_e( 'Image Navigation', 'synced-pattern-popups' ); ?>
							</label>
						</th>
						<td>
							<select name="sppopups_defaults_gallery[imageNavigation]" id="gallery_image_navigation">
								<option value="image" <?php selected( $values['imageNavigation'], 'image' ); ?>><?php esc_html_e( 'Image', 'synced-pattern-popups' ); ?></option>
								<option value="footer" <?php selected( $values['imageNavigation'], 'footer' ); ?>><?php esc_html_e( 'Footer', 'synced-pattern-popups' ); ?></option>
								<option value="both" <?php selected( $values['imageNavigation'], 'both' ); ?>><?php esc_html_e( 'Both', 'synced-pattern-popups' ); ?></option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Where to display navigation controls for gallery images.', 'synced-pattern-popups' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Gallery Options', 'synced-pattern-popups' ); ?>
						</th>
						<td>
							<fieldset>
								<label>
									<input type="checkbox" name="sppopups_defaults_gallery[showCaptions]" value="1" <?php checked( $values['showCaptions'], true ); ?> />
									<?php esc_html_e( 'Show captions', 'synced-pattern-popups' ); ?>
								</label>
								<br />
								<label>
									<input type="checkbox" name="sppopups_defaults_gallery[crossfadeTransition]" value="1" <?php checked( $values['crossfadeTransition'], true ); ?> />
									<?php esc_html_e( 'Crossfade transition', 'synced-pattern-popups' ); ?>
								</label>
								<br />
								<label>
									<input type="checkbox" name="sppopups_defaults_gallery[preloadAdjacentImages]" value="1" <?php checked( $values['preloadAdjacentImages'], true ); ?> />
									<?php esc_html_e( 'Preload adjacent images', 'synced-pattern-popups' ); ?>
								</label>
								<br />
								<label>
									<input type="checkbox" name="sppopups_defaults_gallery[showNavOnHover]" value="1" <?php checked( $values['showNavOnHover'], true ); ?> />
									<?php esc_html_e( 'Show navigation on hover/touch', 'synced-pattern-popups' ); ?>
								</label>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="gallery_transition_duration">
								<?php esc_html_e( 'Transition Duration', 'synced-pattern-popups' ); ?>
							</label>
						</th>
						<td>
							<input type="number" name="sppopups_defaults_gallery[transitionDuration]" id="gallery_transition_duration" value="<?php echo esc_attr( $values['transitionDuration'] ); ?>" min="0" max="2000" step="1" style="width: 100px;" />
							<span style="margin-left: 8px;"><?php esc_html_e( 'ms', 'synced-pattern-popups' ); ?></span>
							<p class="description">
								<?php esc_html_e( 'Transition duration in milliseconds (0-2000).', 'synced-pattern-popups' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Get pattern defaults with fallbacks
	 *
	 * @return array Pattern defaults
	 */
	public static function get_pattern_defaults() {
		$instance = new self();
		$defaults = $instance->get_default_pattern_defaults();
		$saved = get_option( 'sppopups_defaults_pattern', array() );

		if ( ! is_array( $saved ) || empty( $saved ) ) {
			return $defaults;
		}

		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Get TLDR defaults with inheritance logic applied
	 *
	 * @return array TLDR defaults with inheritance resolved
	 */
	public static function get_tldr_defaults() {
		$instance = new self();
		$pattern_defaults = self::get_pattern_defaults();
		$tldr_defaults = $instance->get_default_tldr_defaults();
		$saved = get_option( 'sppopups_defaults_tldr', array() );

		if ( ! is_array( $saved ) || empty( $saved ) ) {
			$saved = $tldr_defaults;
		}

		$result = wp_parse_args( $saved, $tldr_defaults );

		// Apply inheritance from pattern defaults.
		if ( ! empty( $result['inheritModalAppearance'] ) ) {
			$result['maxWidth'] = $pattern_defaults['maxWidth'];
			$result['borderRadius'] = $pattern_defaults['borderRadius'];
			$result['maxHeight'] = $pattern_defaults['maxHeight'];
		}

		if ( ! empty( $result['inheritOverlay'] ) ) {
			$result['overlayColor'] = $pattern_defaults['overlayColor'];
			$result['backdropBlur'] = $pattern_defaults['backdropBlur'];
		}

		if ( ! empty( $result['inheritCloseButtons'] ) ) {
			$result['showIconClose'] = $pattern_defaults['showIconClose'];
			$result['showFooterClose'] = $pattern_defaults['showFooterClose'];
			$result['footerCloseText'] = $pattern_defaults['footerCloseText'];
		}

		return $result;
	}

	/**
	 * Get gallery defaults with inheritance logic applied
	 *
	 * @return array Gallery defaults with inheritance resolved
	 */
	public static function get_gallery_defaults() {
		$instance = new self();
		$pattern_defaults = self::get_pattern_defaults();
		$gallery_defaults = $instance->get_default_gallery_defaults();
		$saved = get_option( 'sppopups_defaults_gallery', array() );

		if ( ! is_array( $saved ) || empty( $saved ) ) {
			$saved = $gallery_defaults;
		}

		$result = wp_parse_args( $saved, $gallery_defaults );

		// Apply inheritance from pattern defaults.
		if ( ! empty( $result['inheritModalAppearance'] ) ) {
			$result['maxWidth'] = $pattern_defaults['maxWidth'];
			$result['borderRadius'] = $pattern_defaults['borderRadius'];
			$result['maxHeight'] = $pattern_defaults['maxHeight'];
		}

		if ( ! empty( $result['inheritOverlay'] ) ) {
			$result['overlayColor'] = $pattern_defaults['overlayColor'];
			$result['backdropBlur'] = $pattern_defaults['backdropBlur'];
		}

		if ( ! empty( $result['inheritCloseButtons'] ) ) {
			$result['showIconClose'] = $pattern_defaults['showIconClose'];
			$result['showFooterClose'] = $pattern_defaults['showFooterClose'];
			$result['footerCloseText'] = $pattern_defaults['footerCloseText'];
		}

		return $result;
	}
}

