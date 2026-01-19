<?php
/**
 * Admin Interface
 * Handles admin menu and list table for synced patterns
 *
 * @package SPPopups
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * SPPopups_Admin class.
 *
 * @package SPPopups
 */
class SPPopups_Admin {

	/**
	 * Pattern service instance
	 *
	 * @var SPPopups_Pattern
	 */
	private $pattern_service;

	/**
	 * Cache service instance
	 *
	 * @var SPPopups_Cache
	 */
	private $cache_service;

	/**
	 * Constructor
	 *
	 * @param SPPopups_Pattern $pattern_service Pattern service instance.
	 * @param SPPopups_Cache   $cache_service   Cache service instance.
	 */
	public function __construct( SPPopups_Pattern $pattern_service, SPPopups_Cache $cache_service = null ) {
		$this->pattern_service = $pattern_service;
		// Create cache service if not provided.
		$this->cache_service = $cache_service ? $cache_service : new SPPopups_Cache();
	}

	/**
	 * Initialize admin interface
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );

		// Add meta box for popup support toggle.
		add_action( 'add_meta_boxes', array( $this, 'register_popup_support_metabox' ) );
		add_action( 'save_post', array( $this, 'save_popup_support_metabox' ) );

		// Handle redirect after plugin activation.
		add_action( 'activated_plugin', array( $this, 'handle_plugin_activation_redirect' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'check_activation_redirect' ) );

		// Add link to Synced Pattern Popups on Patterns list table.
		add_action( 'admin_footer', array( $this, 'add_patterns_list_table_link' ) );

		// Add Settings link to plugin action links.
		$plugin_file = plugin_basename( SPPOPUPS_PLUGIN_DIR . 'sppopups.php' );
		add_filter( 'plugin_action_links_' . $plugin_file, array( $this, 'add_settings_link' ) );
	}

	/**
	 * Add admin submenu under Appearance
	 */
	public function add_admin_menu() {
		add_theme_page(
			__( 'Synced Patterns', 'synced-pattern-popups' ),
			__( 'Synced Patterns', 'synced-pattern-popups' ),
			'edit_posts',
			'simplest-popup-patterns',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'appearance_page_simplest-popup-patterns' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'simplest-popup-admin',
			SPPOPUPS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			SPPOPUPS_VERSION
		);

		wp_enqueue_script(
			'simplest-popup-admin',
			SPPOPUPS_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			SPPOPUPS_VERSION,
			true
		);

		wp_localize_script(
			'simplest-popup-admin',
			'sppopupsAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'strings' => array(
					'copied'     => __( 'Copied!', 'synced-pattern-popups' ),
					'copyFailed' => __( 'Failed to copy', 'synced-pattern-popups' ),
				),
			)
		);
	}

	/**
	 * Handle admin actions (delete, clear cache, etc.)
	 */
	public function handle_actions() {
		if ( ! isset( $_GET['page'] ) || 'simplest-popup-patterns' !== $_GET['page'] ) {
			return;
		}

		// Handle delete action.
		if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['pattern_id'] ) ) {
			check_admin_referer( 'delete_pattern_' . absint( $_GET['pattern_id'] ) );

			$pattern_id = absint( $_GET['pattern_id'] );
			if ( current_user_can( 'delete_post', $pattern_id ) ) {
				wp_delete_post( $pattern_id, true );
				wp_safe_redirect( admin_url( 'themes.php?page=simplest-popup-patterns&deleted=1' ) );
				exit;
			}
		}

		// Handle clear cache action.
		if ( isset( $_GET['action'] ) && 'clear_cache' === $_GET['action'] ) {
			check_admin_referer( 'clear_popup_cache' );

			if ( current_user_can( 'manage_options' ) ) {
				$deleted = $this->cache_service->clear_all();
				wp_safe_redirect( admin_url( 'themes.php?page=simplest-popup-patterns&cache_cleared=1&deleted=' . absint( $deleted ) ) );
				exit;
			}
		}

		// Handle delete transient for single pattern.
		if ( isset( $_GET['action'] ) && 'delete_transient' === $_GET['action'] && isset( $_GET['pattern_id'] ) ) {
			check_admin_referer( 'delete_transient_' . absint( $_GET['pattern_id'] ) );

			if ( current_user_can( 'manage_options' ) ) {
				$pattern_id = absint( $_GET['pattern_id'] );
				$deleted    = $this->cache_service->delete( $pattern_id );

				// Also clear pattern object cache.
				$pattern_cache_key = 'sppopups_pattern_' . $pattern_id;
				wp_cache_delete( $pattern_cache_key, 'sppopups_patterns' );

				wp_safe_redirect( admin_url( 'themes.php?page=simplest-popup-patterns&transient_deleted=1&pattern_id=' . $pattern_id ) );
				exit;
			}
		}

		// Handle TLDR settings save.
		if ( isset( $_POST['save_tldr_settings'] ) && isset( $_POST['sppopups_tldr_settings_nonce'] ) ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sppopups_tldr_settings_nonce'] ) ), 'sppopups_save_tldr_settings' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'synced-pattern-popups' ) );
			}

			if ( current_user_can( 'manage_options' ) ) {
				// Save TLDR enabled.
				$tldr_enabled = isset( $_POST['sppopups_tldr_enabled'] ) ? true : false;
				update_option( 'sppopups_tldr_enabled', $tldr_enabled );

				// Save TLDR prompt type and custom prompt.
				$prompt_type = isset( $_POST['sppopups_tldr_prompt_type'] ) ? sanitize_text_field( wp_unslash( $_POST['sppopups_tldr_prompt_type'] ) ) : 'default';
				$use_custom  = ( 'custom' === $prompt_type );
				update_option( 'sppopups_tldr_prompt_custom', $use_custom );

				if ( $use_custom && isset( $_POST['sppopups_tldr_prompt'] ) ) {
					$tldr_prompt = sanitize_textarea_field( wp_unslash( $_POST['sppopups_tldr_prompt'] ) );
					update_option( 'sppopups_tldr_prompt', $tldr_prompt );
				}

				// Save TLDR cache TTL.
				if ( isset( $_POST['sppopups_tldr_cache_ttl'] ) ) {
					$tldr_cache_ttl = absint( $_POST['sppopups_tldr_cache_ttl'] );
					// Validate range (1-168 hours).
					if ( $tldr_cache_ttl >= 1 && $tldr_cache_ttl <= 168 ) {
						update_option( 'sppopups_tldr_cache_ttl', $tldr_cache_ttl );
					}
				}

				// Get current tab from form submission, default to 'tldr' if not provided.
				$current_tab = isset( $_POST['sppopups_current_tab'] ) ? sanitize_text_field( wp_unslash( $_POST['sppopups_current_tab'] ) ) : 'tldr';
				// Ensure tab is valid.
				$valid_tabs = array( 'patterns', 'tldr', 'defaults', 'how-to-use' );
				if ( ! in_array( $current_tab, $valid_tabs, true ) ) {
					$current_tab = 'tldr';
				}
				wp_safe_redirect( admin_url( 'themes.php?page=simplest-popup-patterns&tldr_settings_saved=1#' . $current_tab ) );
				exit;
			}
		}

		// Handle defaults settings save.
		if ( isset( $_POST['save_defaults_settings'] ) && isset( $_POST['sppopups_defaults_settings_nonce'] ) ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sppopups_defaults_settings_nonce'] ) ), 'sppopups_save_defaults_settings' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'synced-pattern-popups' ) );
			}

			if ( current_user_can( 'manage_options' ) ) {
				$settings = new SPPopups_Settings();

				// Save pattern defaults.
				if ( isset( $_POST['sppopups_defaults_pattern'] ) && is_array( $_POST['sppopups_defaults_pattern'] ) ) {
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via sanitize_pattern_defaults callback
					$pattern_defaults = $settings->sanitize_pattern_defaults( wp_unslash( $_POST['sppopups_defaults_pattern'] ) );
					update_option( 'sppopups_defaults_pattern', $pattern_defaults );
				}

				// Save TLDR defaults.
				if ( isset( $_POST['sppopups_defaults_tldr'] ) && is_array( $_POST['sppopups_defaults_tldr'] ) ) {
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via sanitize_tldr_defaults callback
					$tldr_defaults = $settings->sanitize_tldr_defaults( wp_unslash( $_POST['sppopups_defaults_tldr'] ) );
					update_option( 'sppopups_defaults_tldr', $tldr_defaults );
				}

				// Save gallery defaults.
				if ( isset( $_POST['sppopups_defaults_gallery'] ) && is_array( $_POST['sppopups_defaults_gallery'] ) ) {
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via sanitize_gallery_defaults callback
					$gallery_defaults = $settings->sanitize_gallery_defaults( wp_unslash( $_POST['sppopups_defaults_gallery'] ) );
					update_option( 'sppopups_defaults_gallery', $gallery_defaults );
				}

				// Clear all transients when defaults are saved (defaults affect modal appearance).
				$deleted_count = $this->cache_service->clear_all();

				// Get current tab from form submission, default to 'defaults' if not provided.
				$current_tab = isset( $_POST['sppopups_current_tab'] ) ? sanitize_text_field( wp_unslash( $_POST['sppopups_current_tab'] ) ) : 'defaults';
				// Ensure tab is valid.
				$valid_tabs = array( 'patterns', 'tldr', 'defaults', 'how-to-use' );
				if ( ! in_array( $current_tab, $valid_tabs, true ) ) {
					$current_tab = 'defaults';
				}
				wp_safe_redirect( admin_url( 'themes.php?page=simplest-popup-patterns&defaults_settings_saved=1&cache_cleared=1&deleted=' . absint( $deleted_count ) . '#' . $current_tab ) );
				exit;
			}
		}

		// Handle install AI Experiments action.
		if ( isset( $_GET['action'] ) && 'install_ai_experiments' === $_GET['action'] ) {
			$this->handle_install_ai_experiments();
		}
	}

	/**
	 * Get all synced patterns
	 * Uses shared method from pattern service
	 *
	 * @return array Array of pattern objects
	 */
	private function get_synced_patterns() {
		return $this->pattern_service->get_synced_patterns( 'any' );
	}

	/**
	 * Render admin page
	 */
	public function render_admin_page() {
		$patterns = $this->get_synced_patterns();

		// Show success messages.
		// These $_GET values are sanitized and only used for display purposes (admin notices).
		// Nonce verification is handled in handle_actions() before redirects occur.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$deleted              = isset( $_GET['deleted'] ) ? sanitize_text_field( wp_unslash( $_GET['deleted'] ) ) : '';
		$cache_cleared        = isset( $_GET['cache_cleared'] ) ? sanitize_text_field( wp_unslash( $_GET['cache_cleared'] ) ) : '';
		$deleted_count        = isset( $_GET['deleted'] ) ? absint( $_GET['deleted'] ) : 0;
		$tldr_settings_saved     = isset( $_GET['tldr_settings_saved'] ) ? sanitize_text_field( wp_unslash( $_GET['tldr_settings_saved'] ) ) : '';
		$defaults_settings_saved = isset( $_GET['defaults_settings_saved'] ) ? sanitize_text_field( wp_unslash( $_GET['defaults_settings_saved'] ) ) : '';
		$transient_deleted       = isset( $_GET['transient_deleted'] ) ? sanitize_text_field( wp_unslash( $_GET['transient_deleted'] ) ) : '';
		$transient_pattern_id    = isset( $_GET['pattern_id'] ) ? absint( $_GET['pattern_id'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( '1' === $deleted ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Pattern deleted successfully.', 'synced-pattern-popups' ) . '</p></div>';
		}

		if ( '1' === $cache_cleared ) {
			$message = sprintf(
				/* translators: %d: number of cache entries deleted */
				_n(
					'Cache cleared successfully. %d entry deleted.',
					'Cache cleared successfully. %d entries deleted.',
					$deleted_count,
					'synced-pattern-popups'
				),
				$deleted_count
			);
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
		}

		if ( '1' === $transient_deleted && $transient_pattern_id > 0 ) {
			$message = sprintf(
				/* translators: %d: Pattern ID */
				__( 'Transient cache deleted successfully for pattern #%d.', 'synced-pattern-popups' ),
				$transient_pattern_id
			);
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
		}

		if ( '1' === $tldr_settings_saved ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'TLDR settings saved successfully.', 'synced-pattern-popups' ) . '</p></div>';
		}

		if ( '1' === $defaults_settings_saved ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Defaults settings saved successfully.', 'synced-pattern-popups' ) . '</p></div>';
		}

		?>
		<div class="wrap">
			<div class="sppopups-admin-header">
				<h1 class="wp-heading-inline">
					<?php esc_html_e( 'Synced Patterns', 'synced-pattern-popups' ); ?>
				</h1>
				<div class="sppopups-header-actions">
					<a href="#how-to-use" class="sppopups-learn-more-link">
						<span class="dashicons dashicons-editor-help"></span>
						<?php esc_html_e( 'Learn more about Synced Pattern Popups', 'synced-pattern-popups' ); ?>
					</a>
				</div>
			</div>
			<hr class="wp-header-end">

			<nav class="sppopups-tab-nav" role="tablist">
				<a href="#patterns" class="sppopups-tab-nav-link active" role="tab" aria-selected="true" aria-controls="sppopups-tab-patterns">
					<?php esc_html_e( 'Patterns', 'synced-pattern-popups' ); ?>
				</a>
				<a href="#tldr" class="sppopups-tab-nav-link" role="tab" aria-selected="false" aria-controls="sppopups-tab-tldr">
					<?php esc_html_e( 'TLDR', 'synced-pattern-popups' ); ?>
				</a>
				<a href="#defaults" class="sppopups-tab-nav-link" role="tab" aria-selected="false" aria-controls="sppopups-tab-defaults">
					<?php esc_html_e( 'Defaults', 'synced-pattern-popups' ); ?>
				</a>
				<a href="#how-to-use" class="sppopups-tab-nav-link" role="tab" aria-selected="false" aria-controls="sppopups-tab-how-to-use">
					<?php esc_html_e( 'How to Use', 'synced-pattern-popups' ); ?>
				</a>
				<a href="<?php echo esc_url( 'https://wordpress.org/support/plugin/synced-pattern-popups/' ); ?>" class="sppopups-tab-nav-link" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Get Support', 'synced-pattern-popups' ); ?> <span class="dashicons dashicons-external"></span>
				</a>
			</nav>

			<div class="sppopups-tab-content-wrapper">
				<!-- Patterns Tab -->
				<div id="sppopups-tab-patterns" class="sppopups-tab-content active" role="tabpanel" aria-labelledby="patterns">
					<div class="sppopups-tab-actions">
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wp_block' ) ); ?>" class="page-title-action">
							<?php esc_html_e( 'Add New', 'synced-pattern-popups' ); ?>
						</a>
						<?php
						$clear_cache_url = wp_nonce_url(
							admin_url( 'themes.php?page=simplest-popup-patterns&action=clear_cache' ),
							'clear_popup_cache'
						);
						?>
						<a href="<?php echo esc_url( $clear_cache_url ); ?>" class="page-title-action" style="margin-left: 8px;">
							<?php esc_html_e( 'Clear Transient Cache', 'synced-pattern-popups' ); ?>
						</a>
					</div>

					<p class="description">
						<?php esc_html_e( 'Manage synced patterns that can be used as popups. Only synced patterns are available for popup triggers.', 'synced-pattern-popups' ); ?>
					</p>

					<?php if ( empty( $patterns ) ) : ?>
						<div class="notice notice-info">
							<p>
								<?php esc_html_e( 'No synced patterns found.', 'synced-pattern-popups' ); ?>
								<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wp_block' ) ); ?>">
									<?php esc_html_e( 'Create your first synced pattern', 'synced-pattern-popups' ); ?>
								</a>
							</p>
						</div>
					<?php else : ?>
						<div class="sppopups-table-wrapper">
							<table class="wp-list-table widefat fixed striped sppopups-patterns-table">
							<thead>
								<tr>
									<th class="column-id"><?php esc_html_e( 'ID', 'synced-pattern-popups' ); ?></th>
									<th class="column-title"><?php esc_html_e( 'Title', 'synced-pattern-popups' ); ?></th>
									<th class="column-status"><?php esc_html_e( 'Status', 'synced-pattern-popups' ); ?></th>
									<th class="column-trigger"><?php esc_html_e( 'Trigger Code', 'synced-pattern-popups' ); ?></th>
									<th class="column-actions"><?php esc_html_e( 'Actions', 'synced-pattern-popups' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								// Cache post status objects to avoid repeated lookups.
								$status_cache = array();
								foreach ( $patterns as $pattern ) :
									// Skip if pattern is not a valid object.
									if ( ! is_object( $pattern ) || ! isset( $pattern->ID ) ) {
										continue;
									}

									$pattern_id  = (int) $pattern->ID;
									$post_status = isset( $pattern->post_status ) ? $pattern->post_status : 'publish';

									// Meta is already cached from update_post_meta_cache, so this is fast.
									$sync_status  = get_post_meta( $pattern_id, 'wp_pattern_sync_status', true );
									$is_synced    = ( 'unsynced' !== $sync_status );
									$trigger_code = 'spp-trigger-' . $pattern_id;
									$edit_url     = get_edit_post_link( $pattern_id );
									$delete_url   = wp_nonce_url(
										admin_url( 'themes.php?page=simplest-popup-patterns&action=delete&pattern_id=' . $pattern_id ),
										'delete_pattern_' . $pattern_id
									);

									// Cache post status object.
									if ( ! isset( $status_cache[ $post_status ] ) ) {
										$status_obj                   = get_post_status_object( $post_status );
										$status_cache[ $post_status ] = $status_obj ? $status_obj : null;
									}
									$status = isset( $status_cache[ $post_status ] ) && null !== $status_cache[ $post_status ] ? $status_cache[ $post_status ] : null;
									?>
									<tr>
										<td class="column-id" data-colname="<?php esc_attr_e( 'ID', 'synced-pattern-popups' ); ?>">
											<strong class="pattern-id"><?php echo esc_html( $pattern_id ); ?></strong>
										</td>
										<td class="column-title" data-colname="<?php esc_attr_e( 'Title', 'synced-pattern-popups' ); ?>">
											<strong>
												<?php
												$pattern_title = isset( $pattern->post_title ) && ! empty( $pattern->post_title ) ? $pattern->post_title : __( '(no title)', 'synced-pattern-popups' );
												if ( $edit_url ) :
													?>
													<a href="<?php echo esc_url( $edit_url ); ?>">
														<?php echo esc_html( $pattern_title ); ?>
													</a>
												<?php else : ?>
													<?php echo esc_html( $pattern_title ); ?>
												<?php endif; ?>
											</strong>
										</td>
										<td class="column-status" data-colname="<?php esc_attr_e( 'Status', 'synced-pattern-popups' ); ?>">
											<?php
											if ( $status && isset( $status->label ) ) {
												$status_class = 'publish' === $post_status ? 'status-publish' : 'status-' . esc_attr( $post_status );
												echo '<span class="status-badge ' . esc_attr( $status_class ) . '">' . esc_html( $status->label ) . '</span>';
											} elseif ( $post_status ) {
												// Fallback if status object not available.
												$status_class = 'publish' === $post_status ? 'status-publish' : 'status-' . esc_attr( $post_status );
												echo '<span class="status-badge ' . esc_attr( $status_class ) . '">' . esc_html( ucfirst( $post_status ) ) . '</span>';
											}
											?>
										</td>
										<td class="column-trigger" data-colname="<?php esc_attr_e( 'Trigger Code', 'synced-pattern-popups' ); ?>">
											<div class="sppopups-trigger-code-wrapper">
												<span class="sppopups-trigger-code-text"><?php echo esc_html( $trigger_code ); ?></span>
												<button
													type="button"
													class="button button-small sppopups-copy-trigger-icon"
													data-copy="<?php echo esc_attr( $trigger_code ); ?>"
													title="<?php esc_attr_e( 'Copy to Clipboard', 'synced-pattern-popups' ); ?>"
													aria-label="<?php esc_attr_e( 'Copy to Clipboard', 'synced-pattern-popups' ); ?>"
												>
													<span class="dashicons dashicons-clipboard"></span>
													<span class="screen-reader-text"><?php echo esc_html( $trigger_code ); ?></span>
												</button>
											</div>
										</td>
										<td class="column-actions" data-colname="<?php esc_attr_e( 'Actions', 'synced-pattern-popups' ); ?>">
											<div class="sppopups-actions-wrapper">
												<div class="sppopups-actions-row">
													<?php if ( $edit_url ) : ?>
														<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small">
															<?php esc_html_e( 'Edit', 'synced-pattern-popups' ); ?>
														</a>
													<?php endif; ?>
													<?php if ( current_user_can( 'delete_post', $pattern_id ) ) : ?>
														<a
															href="<?php echo esc_url( $delete_url ); ?>"
															class="button button-small delete-pattern"
															onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this pattern?', 'synced-pattern-popups' ) ); ?>');"
														>
															<?php esc_html_e( 'Delete', 'synced-pattern-popups' ); ?>
														</a>
													<?php endif; ?>
												</div>
												<?php
												$delete_transient_url = wp_nonce_url(
													admin_url( 'themes.php?page=simplest-popup-patterns&action=delete_transient&pattern_id=' . $pattern_id ),
													'delete_transient_' . $pattern_id
												);
												?>
												<a
													href="<?php echo esc_url( $delete_transient_url ); ?>"
													class="button button-small delete-transient sppopups-action-transient"
													onclick="return confirm('
													<?php
													/* translators: %d: Pattern ID */
													echo esc_js( sprintf( __( 'Are you sure you want to delete the transient cache for pattern #%d?', 'synced-pattern-popups' ), $pattern_id ) );
													?>
													');"
												>
													<?php
													/* translators: %d: Pattern ID */
													echo esc_html( sprintf( __( 'Delete Transient #%d', 'synced-pattern-popups' ), $pattern_id ) );
													?>
												</a>
											</div>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						</div>
					<?php endif; ?>
				</div>

				<!-- TLDR Tab -->
				<div id="sppopups-tab-tldr" class="sppopups-tab-content" role="tabpanel" aria-labelledby="tldr">
					<?php
					// Render TLDR settings section (without the box wrapper).
					$settings = new SPPopups_Settings();
					$settings->render_settings_section_for_tab();
					?>
				</div>

				<!-- Defaults Tab -->
				<div id="sppopups-tab-defaults" class="sppopups-tab-content" role="tabpanel" aria-labelledby="defaults">
					<?php
					// Render defaults settings section.
					$settings = new SPPopups_Settings();
					$settings->render_defaults_section();
					?>
				</div>

				<!-- How to Use Tab -->
				<div id="sppopups-tab-how-to-use" class="sppopups-tab-content" role="tabpanel" aria-labelledby="how-to-use">
					<div class="sppopups-tab-content-inner">
						<h2><?php esc_html_e( 'How to Use Synced Pattern Popups', 'synced-pattern-popups' ); ?></h2>
						<p class="description">
							<?php esc_html_e( 'There are two ways to trigger a popup on your site:', 'synced-pattern-popups' ); ?>
						</p>

						<div class="sppopups-usage-method">
							<h3><?php esc_html_e( 'Method 1: Class Name', 'synced-pattern-popups' ); ?></h3>
							<p><?php esc_html_e( 'Add the class', 'synced-pattern-popups' ); ?> <code>spp-trigger-{id}</code> <?php esc_html_e( 'to any clickable element, where', 'synced-pattern-popups' ); ?> <code>{id}</code> <?php esc_html_e( 'is the numeric ID of your Synced Pattern.', 'synced-pattern-popups' ); ?></p>
							<p><strong><?php esc_html_e( 'Examples:', 'synced-pattern-popups' ); ?></strong></p>
							<pre><code>&lt;a href="#" class="spp-trigger-123"&gt;<?php esc_html_e( 'Open Popup', 'synced-pattern-popups' ); ?>&lt;/a&gt;
&lt;button class="spp-trigger-123"&gt;<?php esc_html_e( 'Click Me', 'synced-pattern-popups' ); ?>&lt;/button&gt;</code></pre>
						</div>

						<div class="sppopups-usage-method">
							<h3><?php esc_html_e( 'Method 2: Href Attribute', 'synced-pattern-popups' ); ?></h3>
							<p><?php esc_html_e( 'Set the', 'synced-pattern-popups' ); ?> <code>href</code> <?php esc_html_e( 'attribute to', 'synced-pattern-popups' ); ?> <code>#spp-trigger-{id}</code> <?php esc_html_e( 'on any link element. This is especially useful in the WordPress Block Editor where you can\'t easily add custom classes.', 'synced-pattern-popups' ); ?></p>
							<p><strong><?php esc_html_e( 'Example:', 'synced-pattern-popups' ); ?></strong></p>
							<pre><code>&lt;a href="#spp-trigger-123"&gt;<?php esc_html_e( 'Open Popup', 'synced-pattern-popups' ); ?>&lt;/a&gt;</code></pre>
						</div>

						<div class="sppopups-usage-method">
							<h3><?php esc_html_e( 'Custom Width', 'synced-pattern-popups' ); ?></h3>
							<p><?php esc_html_e( 'You can specify a custom modal width by adding a width suffix:', 'synced-pattern-popups' ); ?> <code>spp-trigger-{id}-{width}</code> <?php esc_html_e( 'where width is in pixels (100-5000px).', 'synced-pattern-popups' ); ?></p>
							<p><strong><?php esc_html_e( 'Example:', 'synced-pattern-popups' ); ?></strong></p>
							<pre><code>&lt;a href="#" class="spp-trigger-123-800"&gt;<?php esc_html_e( 'Open 800px Modal', 'synced-pattern-popups' ); ?>&lt;/a&gt;</code></pre>
						</div>

						<div class="sppopups-usage-method">
							<h3><?php esc_html_e( 'Finding Pattern IDs', 'synced-pattern-popups' ); ?></h3>
							<p><?php esc_html_e( 'Go to WordPress Admin → Appearance → Synced Patterns. The ID column shows the pattern ID prominently. You can also click the "Copy Trigger" button in the Actions column to copy the complete trigger code.', 'synced-pattern-popups' ); ?></p>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Register meta box for popup support toggle
	 */
	public function register_popup_support_metabox() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );

		foreach ( $post_types as $post_type ) {
			// Skip attachment post type.
			if ( 'attachment' === $post_type ) {
				continue;
			}

			add_meta_box(
				'simplest-popup-support',
				__( 'Synced Pattern Popups', 'synced-pattern-popups' ),
				array( $this, 'render_popup_support_metabox' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render popup support meta box
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function render_popup_support_metabox( $post ) {
		// Add nonce for security.
		wp_nonce_field( 'sppopups_support_metabox', 'sppopups_support_nonce' );

		// Get current values.
		$modal_assets = get_post_meta( $post->ID, '_sppopups_modal_assets', true );
		if ( empty( $modal_assets ) ) {
			$modal_assets = 'auto-detect';
		}

		$gallery_assets = get_post_meta( $post->ID, '_sppopups_gallery_assets', true );
		if ( empty( $gallery_assets ) ) {
			$gallery_assets = 'auto-detect';
		}
		?>
		<div class="sppopups-support-metabox">
			<div style="margin-bottom: 16px;">
				<label style="display: block; margin-bottom: 4px; font-weight: 600;">
					<?php esc_html_e( 'Modal Assets:', 'synced-pattern-popups' ); ?>
				</label>
				<fieldset style="margin: 0; padding: 0; border: 0;">
					<label style="display: inline-block; margin-right: 12px;">
						<input type="radio" name="sppopups_modal_assets" value="auto-detect" <?php checked( $modal_assets, 'auto-detect' ); ?> />
						<?php esc_html_e( 'Auto-Detect', 'synced-pattern-popups' ); ?>
					</label>
					<label style="display: inline-block;">
						<input type="radio" name="sppopups_modal_assets" value="loaded" <?php checked( $modal_assets, 'loaded' ); ?> />
						<?php esc_html_e( 'Loaded', 'synced-pattern-popups' ); ?>
					</label>
				</fieldset>
			</div>

			<div style="margin-bottom: 12px;">
				<label style="display: block; margin-bottom: 4px; font-weight: 600;">
					<?php esc_html_e( 'Gallery Assets:', 'synced-pattern-popups' ); ?>
				</label>
				<fieldset style="margin: 0; padding: 0; border: 0;">
					<label style="display: inline-block; margin-right: 12px;">
						<input type="radio" name="sppopups_gallery_assets" value="auto-detect" <?php checked( $gallery_assets, 'auto-detect' ); ?> />
						<?php esc_html_e( 'Auto-Detect', 'synced-pattern-popups' ); ?>
					</label>
					<label style="display: inline-block;">
						<input type="radio" name="sppopups_gallery_assets" value="loaded" <?php checked( $gallery_assets, 'loaded' ); ?> />
						<?php esc_html_e( 'Loaded', 'synced-pattern-popups' ); ?>
					</label>
				</fieldset>
			</div>
		</div>
		<?php
	}

	/**
	 * Save popup support meta box
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_popup_support_metabox( $post_id ) {
		// Check if nonce is set.
		if ( ! isset( $_POST['sppopups_support_nonce'] ) ) {
			return;
		}

		// Verify nonce.
		$nonce = isset( $_POST['sppopups_support_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['sppopups_support_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'sppopups_support_metabox' ) ) {
			return;
		}

		// Check if autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check user permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Check if revision.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Get and sanitize modal assets value.
		$modal_assets  = isset( $_POST['sppopups_modal_assets'] ) ? sanitize_text_field( wp_unslash( $_POST['sppopups_modal_assets'] ) ) : 'auto-detect';
		$allowed_modal = array( 'auto-detect', 'loaded' );

		if ( ! in_array( $modal_assets, $allowed_modal, true ) ) {
			$modal_assets = 'auto-detect';
		}

		// Get and sanitize gallery assets value.
		$gallery_assets  = isset( $_POST['sppopups_gallery_assets'] ) ? sanitize_text_field( wp_unslash( $_POST['sppopups_gallery_assets'] ) ) : 'auto-detect';
		$allowed_gallery = array( 'auto-detect', 'loaded' );

		if ( ! in_array( $gallery_assets, $allowed_gallery, true ) ) {
			$gallery_assets = 'auto-detect';
		}

		// Update post meta.
		update_post_meta( $post_id, '_sppopups_modal_assets', $modal_assets );
		update_post_meta( $post_id, '_sppopups_gallery_assets', $gallery_assets );

		// Backward compatibility: Remove old meta key if it exists.
		delete_post_meta( $post_id, '_sppopups_support' );
	}

	/**
	 * Handle install AI Experiments
	 * Installs the plugin programmatically and redirects back to TLDR page
	 */
	private function handle_install_ai_experiments() {
		// Verify nonce.
		check_admin_referer( 'install_ai_experiments' );

		// Check user capability.
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to install plugins.', 'synced-pattern-popups' ) );
		}

		// Check if plugin is already installed.
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();
		if ( isset( $plugins['ai/ai.php'] ) ) {
			// Plugin already installed, just redirect.
			wp_safe_redirect( admin_url( 'themes.php?page=simplest-popup-patterns#tldr' ) );
			exit;
		}

		// Required WordPress core files for plugin installation.
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';

		// Get plugin information from WordPress repository.
		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => 'ai',
				'fields' => array(
					'sections' => false,
				),
			)
		);

		// Check for errors.
		if ( is_wp_error( $api ) ) {
			wp_die(
				sprintf(
					/* translators: %s: Error message */
					esc_html__( 'Error retrieving plugin information: %s', 'synced-pattern-popups' ),
					esc_html( $api->get_error_message() )
				)
			);
		}

		// Initialize filesystem.
		$url   = wp_nonce_url( admin_url( 'themes.php?page=simplest-popup-patterns' ), 'install_ai_experiments' );
		$creds = request_filesystem_credentials( $url, '', false, false, null );
		if ( false === $creds ) {
			wp_die( esc_html__( 'Filesystem credentials are required to install plugins.', 'synced-pattern-popups' ) );
		}

		if ( ! WP_Filesystem( $creds ) ) {
			wp_die( esc_html__( 'Filesystem initialization failed.', 'synced-pattern-popups' ) );
		}

		// Use Automatic_Upgrader_Skin for silent installation.
		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );

		// Install the plugin.
		$result = $upgrader->install( $api->download_link );

		// Check for errors.
		if ( is_wp_error( $result ) ) {
			wp_die(
				sprintf(
					/* translators: %s: Error message */
					esc_html__( 'Plugin installation failed: %s', 'synced-pattern-popups' ),
					esc_html( $result->get_error_message() )
				)
			);
		}

		if ( false === $result ) {
			wp_die( esc_html__( 'Plugin installation failed. Please try again.', 'synced-pattern-popups' ) );
		}

		// Clear plugin cache.
		wp_clean_plugins_cache();

		// Redirect back to TLDR page.
		wp_safe_redirect( admin_url( 'themes.php?page=simplest-popup-patterns#tldr' ) );
		exit;
	}

	/**
	 * Handle redirect after plugin activation
	 * Sets a transient to track that we should redirect back to TLDR page
	 *
	 * @param string $plugin Plugin file path that was activated.
	 * @param bool   $_network_wide Whether the plugin was activated network-wide.
	 */
	public function handle_plugin_activation_redirect( $plugin, $_network_wide ) {
		// Only handle redirect for AI Experiments plugin.
		if ( 'ai/ai.php' !== $plugin ) {
			return;
		}

		// Check if activation was initiated from our page.
		// The activation URL includes a nonce via wp_nonce_url(), but WordPress core's activation redirect.
		// doesn't preserve it. This GET parameter is only used as a flag to set a transient for redirect.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Used only as a flag, not for data processing.
		if ( isset( $_GET['sppopups_redirect'] ) && '1' === $_GET['sppopups_redirect'] ) {
			// Set a transient to indicate we should redirect.
			set_transient( 'sppopups_redirect_after_activation', true, 30 );
		}
	}

	/**
	 * Check if we should redirect after plugin activation
	 * Redirects to TLDR settings page if transient is set
	 */
	public function check_activation_redirect() {
		// Only check on plugins page.
		global $pagenow;
		if ( 'plugins.php' !== $pagenow ) {
			return;
		}

		// Check if transient is set.
		if ( get_transient( 'sppopups_redirect_after_activation' ) ) {
			// Delete the transient.
			delete_transient( 'sppopups_redirect_after_activation' );

			// Redirect to TLDR settings page.
			wp_safe_redirect( admin_url( 'themes.php?page=simplest-popup-patterns#tldr' ) );
			exit;
		}
	}

	/**
	 * Add link to Synced Pattern Popups on Patterns list table page
	 * Adds a link next to the "Add Pattern" button
	 */
	public function add_patterns_list_table_link() {
		global $pagenow, $typenow;

		// Only on the Patterns (wp_block) list table page.
		if ( 'edit.php' !== $pagenow || 'wp_block' !== $typenow ) {
			return;
		}

		$link_url  = admin_url( 'themes.php?page=simplest-popup-patterns' );
		$link_text = __( 'Go to Synced Pattern Popups', 'synced-pattern-popups' );
		?>
		<script type="text/javascript">
		(function() {
			// Find the page title actions area.
			var pageTitle = document.querySelector('.wp-heading-inline');
			if (!pageTitle) {
				return;
			}

			// Find the parent container that holds the title and buttons.
			var titleContainer = pageTitle.parentElement;
			if (!titleContainer) {
				return;
			}

			// Find all page-title-action buttons.
			var actionButtons = titleContainer.querySelectorAll('.page-title-action');
			if (actionButtons.length === 0) {
				return;
			}

			// Get the last button (usually "Add Pattern").
			var lastButton = actionButtons[actionButtons.length - 1];

			// Create the link with WordPress admin button classes.
			var link = document.createElement('a');
			link.href = <?php echo wp_json_encode( $link_url ); ?>;
			link.textContent = <?php echo wp_json_encode( $link_text ); ?>;
			link.className = 'page-title-action';

			// Insert after the last button.
			lastButton.parentNode.insertBefore(link, lastButton.nextSibling);
		})();
		</script>
		<?php
	}

	/**
	 * Add Settings link to plugin action links
	 * Adds the link as the first item in the action links array
	 *
	 * @param array $links Plugin action links array.
	 * @return array Modified plugin action links array
	 */
	public function add_settings_link( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'themes.php?page=simplest-popup-patterns' ) ) . '">' . esc_html__( 'Settings', 'synced-pattern-popups' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
}

