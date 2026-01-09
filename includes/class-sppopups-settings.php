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
		$plugin_slug = 'ai/ai.php';
		$plugin_installed = $this->is_ai_plugin_installed();
		$plugin_active = $this->is_ai_plugin_active();
		$credentials = false;

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
		// Try to get URL from AI Experiments plugin if it exposes a function
		if ( function_exists( 'WordPress\AI\get_settings_url' ) ) {
			$url = \WordPress\AI\get_settings_url();
			if ( ! empty( $url ) ) {
				return $url;
			}
		}

		// Fallback to standard settings page
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
								$prompt_type = get_option( 'sppopups_tldr_prompt_custom', false ) ? 'custom' : 'default';
								$custom_prompt = get_option( 'sppopups_tldr_prompt', '' );
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
		$ai_available = $this->check_ai_availability();
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
									$prompt_type = get_option( 'sppopups_tldr_prompt_custom', false ) ? 'custom' : 'default';
									$custom_prompt = get_option( 'sppopups_tldr_prompt', '' );
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
	 * @param array $status AI availability status array
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
							admin_url( 'plugins.php?action=activate&plugin=' . urlencode( $status['plugin_slug'] ) . '&plugin_status=all&paged=1&s=&sppopups_redirect=1' ),
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
		$default = "Create a concise TLDR (Too Long; Didn't Read) summary of the following content. The summary should be 2-3 sentences and capture the main points.";
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
}

