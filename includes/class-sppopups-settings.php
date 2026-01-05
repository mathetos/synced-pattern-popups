<?php
/**
 * Settings Page
 * Handles plugin settings including AI TLDR configuration
 *
 * @package SPPopups
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
		// Register settings (no page needed, we'll handle saving manually)
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
	 * Get default prompt template
	 *
	 * @return string Default prompt
	 */
	private function get_default_prompt() {
		return "Create a concise TLDR (Too Long; Didn't Read) summary of the following content. Format your response using Markdown syntax (use **bold** for emphasis, * for lists, ## for headings, etc.). The summary should be 2-3 sentences and capture the main points:\n\n{content}";
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
			<?php esc_html_e( 'The prompt template used to generate TLDR summaries. Use {content} as a placeholder for the page content.', 'synced-pattern-popups' ); ?>
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
		$plugin_active = class_exists( 'WordPress\AI_Client\AI_Client' );
		$credentials = false;

		if ( $plugin_active && function_exists( 'WordPress\AI\has_valid_ai_credentials' ) ) {
			$credentials = \WordPress\AI\has_valid_ai_credentials();
		}

		return array(
			'plugin_active' => $plugin_active,
			'credentials'   => $credentials,
		);
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
								<label for="sppopups_tldr_prompt">
									<?php esc_html_e( 'TLDR Prompt Template', 'synced-pattern-popups' ); ?>
								</label>
							</th>
							<td>
								<textarea name="sppopups_tldr_prompt" id="sppopups_tldr_prompt" rows="5" cols="50" class="large-text code" style="font-family: 'Courier New', Courier, monospace; font-size: 13px;"><?php echo esc_textarea( get_option( 'sppopups_tldr_prompt', $this->get_default_prompt() ) ); ?></textarea>
								<p class="description" style="margin-top: 8px;">
									<?php esc_html_e( 'The prompt template used to generate TLDR summaries. Use {content} as a placeholder for the page content.', 'synced-pattern-popups' ); ?>
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
		$ai_available = $this->check_ai_availability();
		?>
		<div class="sppopups-tab-content-inner">
			<h2><?php esc_html_e( 'AI TLDR Settings', 'synced-pattern-popups' ); ?></h2>
			
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

			<form method="post" action="">
				<?php wp_nonce_field( 'sppopups_save_tldr_settings', 'sppopups_tldr_settings_nonce' ); ?>
				
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
								<label for="sppopups_tldr_prompt_tab">
									<?php esc_html_e( 'TLDR Prompt Template', 'synced-pattern-popups' ); ?>
								</label>
							</th>
							<td>
								<textarea name="sppopups_tldr_prompt" id="sppopups_tldr_prompt_tab" rows="5" cols="50" class="large-text code"><?php echo esc_textarea( get_option( 'sppopups_tldr_prompt', $this->get_default_prompt() ) ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'The prompt template used to generate TLDR summaries. Use {content} as a placeholder for the page content.', 'synced-pattern-popups' ); ?>
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
		</div>
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
		$default = "Create a concise TLDR (Too Long; Didn't Read) summary of the following content. Format your response using Markdown syntax (use **bold** for emphasis, * for lists, ## for headings, etc.). The summary should be 2-3 sentences and capture the main points:\n\n{content}";
		return get_option( 'sppopups_tldr_prompt', $default );
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
}

