<?php
/**
 * Admin Interface
 * Handles admin menu and list table for synced patterns
 *
 * @package SPPopups
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
	 * @param SPPopups_Pattern $pattern_service Pattern service instance
	 * @param SPPopups_Cache   $cache_service   Cache service instance
	 */
	public function __construct( SPPopups_Pattern $pattern_service, SPPopups_Cache $cache_service = null ) {
		$this->pattern_service = $pattern_service;
		// Create cache service if not provided
		$this->cache_service = $cache_service ? $cache_service : new SPPopups_Cache();
	}

	/**
	 * Initialize admin interface
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		
		// Add meta box for popup support toggle
		add_action( 'add_meta_boxes', array( $this, 'register_popup_support_metabox' ) );
		add_action( 'save_post', array( $this, 'save_popup_support_metabox' ) );
	}

	/**
	 * Add admin submenu under Appearance
	 */
	public function add_admin_menu() {
		add_theme_page(
			__( 'Synced Patterns', 'sppopups' ),
			__( 'Synced Patterns', 'sppopups' ),
			'edit_posts',
			'simplest-popup-patterns',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook
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
				'strings' => array(
					'copied' => __( 'Copied!', 'sppopups' ),
					'copyFailed' => __( 'Failed to copy', 'sppopups' ),
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

		// Handle delete action
		if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['pattern_id'] ) ) {
			check_admin_referer( 'delete_pattern_' . absint( $_GET['pattern_id'] ) );

			$pattern_id = absint( $_GET['pattern_id'] );
			if ( current_user_can( 'delete_post', $pattern_id ) ) {
				wp_delete_post( $pattern_id, true );
				wp_safe_redirect( admin_url( 'themes.php?page=simplest-popup-patterns&deleted=1' ) );
				exit;
			}
		}

		// Handle clear cache action
		if ( isset( $_GET['action'] ) && 'clear_cache' === $_GET['action'] ) {
			check_admin_referer( 'clear_popup_cache' );

			if ( current_user_can( 'manage_options' ) ) {
				$deleted = $this->cache_service->clear_all();
				wp_safe_redirect( admin_url( 'themes.php?page=simplest-popup-patterns&cache_cleared=1&deleted=' . absint( $deleted ) ) );
				exit;
			}
		}

		// Handle TLDR settings save
		if ( isset( $_POST['save_tldr_settings'] ) && isset( $_POST['sppopups_tldr_settings_nonce'] ) ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sppopups_tldr_settings_nonce'] ) ), 'sppopups_save_tldr_settings' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'sppopups' ) );
			}

			if ( current_user_can( 'manage_options' ) ) {
				// Save TLDR enabled
				$tldr_enabled = isset( $_POST['sppopups_tldr_enabled'] ) ? true : false;
				update_option( 'sppopups_tldr_enabled', $tldr_enabled );

				// Save TLDR prompt
				if ( isset( $_POST['sppopups_tldr_prompt'] ) ) {
					$tldr_prompt = sanitize_textarea_field( wp_unslash( $_POST['sppopups_tldr_prompt'] ) );
					update_option( 'sppopups_tldr_prompt', $tldr_prompt );
				}

				// Save TLDR cache TTL
				if ( isset( $_POST['sppopups_tldr_cache_ttl'] ) ) {
					$tldr_cache_ttl = absint( $_POST['sppopups_tldr_cache_ttl'] );
					// Validate range (1-168 hours)
					if ( $tldr_cache_ttl >= 1 && $tldr_cache_ttl <= 168 ) {
						update_option( 'sppopups_tldr_cache_ttl', $tldr_cache_ttl );
					}
				}

				wp_safe_redirect( admin_url( 'themes.php?page=simplest-popup-patterns&tldr_settings_saved=1' ) );
				exit;
			}
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

		// Show success messages
		// These $_GET values are sanitized and only used for display purposes (admin notices)
		// Nonce verification is handled in handle_actions() before redirects occur
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$deleted = isset( $_GET['deleted'] ) ? sanitize_text_field( wp_unslash( $_GET['deleted'] ) ) : '';
		$cache_cleared = isset( $_GET['cache_cleared'] ) ? sanitize_text_field( wp_unslash( $_GET['cache_cleared'] ) ) : '';
		$deleted_count = isset( $_GET['deleted'] ) ? absint( $_GET['deleted'] ) : 0;
		$tldr_settings_saved = isset( $_GET['tldr_settings_saved'] ) ? sanitize_text_field( wp_unslash( $_GET['tldr_settings_saved'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		
		if ( '1' === $deleted ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Pattern deleted successfully.', 'sppopups' ) . '</p></div>';
		}

		if ( '1' === $cache_cleared ) {
			$message = sprintf(
				/* translators: %d: number of cache entries deleted */
				_n(
					'Cache cleared successfully. %d entry deleted.',
					'Cache cleared successfully. %d entries deleted.',
					$deleted_count,
					'sppopups'
				),
				$deleted_count
			);
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
		}

		if ( '1' === $tldr_settings_saved ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'TLDR settings saved successfully.', 'sppopups' ) . '</p></div>';
		}

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">
				<?php esc_html_e( 'Synced Patterns', 'sppopups' ); ?>
			</h1>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wp_block' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New', 'sppopups' ); ?>
			</a>
			<?php
			$clear_cache_url = wp_nonce_url(
				admin_url( 'themes.php?page=simplest-popup-patterns&action=clear_cache' ),
				'clear_popup_cache'
			);
			?>
			<a href="<?php echo esc_url( $clear_cache_url ); ?>" class="page-title-action" style="margin-left: 8px;">
				<?php esc_html_e( 'Clear Transient Cache', 'sppopups' ); ?>
			</a>
			<hr class="wp-header-end">

			<p class="description">
				<?php esc_html_e( 'Manage synced patterns that can be used as popups. Only synced patterns are available for popup triggers.', 'sppopups' ); ?>
			</p>

			<?php if ( empty( $patterns ) ) : ?>
				<div class="notice notice-info">
					<p>
						<?php esc_html_e( 'No synced patterns found.', 'sppopups' ); ?>
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wp_block' ) ); ?>">
							<?php esc_html_e( 'Create your first synced pattern', 'sppopups' ); ?>
						</a>
					</p>
				</div>
			<?php else : ?>
				<div class="sppopups-table-wrapper">
					<table class="wp-list-table widefat fixed striped sppopups-patterns-table">
					<thead>
						<tr>
							<th class="column-id"><?php esc_html_e( 'ID', 'sppopups' ); ?></th>
							<th class="column-title"><?php esc_html_e( 'Title', 'sppopups' ); ?></th>
							<th class="column-status"><?php esc_html_e( 'Status', 'sppopups' ); ?></th>
							<th class="column-sync-status"><?php esc_html_e( 'Sync Status', 'sppopups' ); ?></th>
							<th class="column-trigger"><?php esc_html_e( 'Trigger Code', 'sppopups' ); ?></th>
							<th class="column-actions"><?php esc_html_e( 'Actions', 'sppopups' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						// Cache post status objects to avoid repeated lookups
						$status_cache = array();
						foreach ( $patterns as $pattern ) :
							// Skip if pattern is not a valid object
							if ( ! is_object( $pattern ) || ! isset( $pattern->ID ) ) {
								continue;
							}

							$pattern_id = (int) $pattern->ID;
							$post_status = isset( $pattern->post_status ) ? $pattern->post_status : 'publish';

							// Meta is already cached from update_post_meta_cache, so this is fast
							$sync_status = get_post_meta( $pattern_id, 'wp_pattern_sync_status', true );
							$is_synced = ( 'unsynced' !== $sync_status );
							$trigger_code = 'spp-trigger-' . $pattern_id;
							$edit_url = get_edit_post_link( $pattern_id );
							$delete_url = wp_nonce_url(
								admin_url( 'themes.php?page=simplest-popup-patterns&action=delete&pattern_id=' . $pattern_id ),
								'delete_pattern_' . $pattern_id
							);

							// Cache post status object
							if ( ! isset( $status_cache[ $post_status ] ) ) {
								$status_obj = get_post_status_object( $post_status );
								$status_cache[ $post_status ] = $status_obj ? $status_obj : null;
							}
							$status = isset( $status_cache[ $post_status ] ) && $status_cache[ $post_status ] !== null ? $status_cache[ $post_status ] : null;
							?>
							<tr>
								<td class="column-id">
									<strong class="pattern-id"><?php echo esc_html( $pattern_id ); ?></strong>
								</td>
								<td class="column-title">
									<strong>
										<?php
										$pattern_title = isset( $pattern->post_title ) && ! empty( $pattern->post_title ) ? $pattern->post_title : __( '(no title)', 'sppopups' );
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
								<td class="column-status">
									<?php
									if ( $status && isset( $status->label ) ) {
										$status_class = 'publish' === $post_status ? 'status-publish' : 'status-' . esc_attr( $post_status );
										echo '<span class="status-badge ' . esc_attr( $status_class ) . '">' . esc_html( $status->label ) . '</span>';
									} elseif ( $post_status ) {
										// Fallback if status object not available
										$status_class = 'publish' === $post_status ? 'status-publish' : 'status-' . esc_attr( $post_status );
										echo '<span class="status-badge ' . esc_attr( $status_class ) . '">' . esc_html( ucfirst( $post_status ) ) . '</span>';
									}
									?>
								</td>
								<td class="column-sync-status">
									<?php if ( $is_synced ) : ?>
										<span class="status-badge status-synced"><?php esc_html_e( 'Synced', 'sppopups' ); ?></span>
										<?php else : ?>
										<span class="status-badge status-unsynced"><?php esc_html_e( 'Unsynced', 'sppopups' ); ?></span>
									<?php endif; ?>
								</td>
								<td class="column-trigger">
									<input type="text" readonly value="<?php echo esc_attr( $trigger_code ); ?>" class="trigger-code-input" />
								</td>
								<td class="column-actions">
									<?php if ( $edit_url ) : ?>
										<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small">
											<?php esc_html_e( 'Edit', 'sppopups' ); ?>
										</a>
									<?php endif; ?>
									<?php if ( current_user_can( 'delete_post', $pattern_id ) ) : ?>
										<a 
											href="<?php echo esc_url( $delete_url ); ?>" 
											class="button button-small delete-pattern"
											onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this pattern?', 'sppopups' ) ); ?>');"
										>
											<?php esc_html_e( 'Delete', 'sppopups' ); ?>
										</a>
									<?php endif; ?>
									<button 
										type="button" 
										class="button button-small copy-trigger" 
										data-copy="<?php echo esc_attr( $trigger_code ); ?>"
									>
										<?php esc_html_e( 'Copy Trigger', 'sppopups' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				</div>

				<div class="sppopups-usage-instructions">
					<strong><?php esc_html_e( 'How to use:', 'sppopups' ); ?></strong>
					<div>
						<?php esc_html_e( 'Method 1 - Class name:', 'sppopups' ); ?>
						<code>&lt;a href="#" class="spp-trigger-123"&gt;Open Popup&lt;/a&gt;</code>
					</div>
					<div>
						<?php esc_html_e( 'Method 2 - Href attribute (for Block Editor):', 'sppopups' ); ?>
						<code>&lt;a href="#spp-trigger-123"&gt;Open Popup&lt;/a&gt;</code>
					</div>
				</div>
			<?php endif; ?>

			<?php
			// Render TLDR settings section
			$settings = new SPPopups_Settings();
			$settings->render_settings_section();
			?>
		</div>
		<?php
	}

	/**
	 * Register meta box for popup support toggle
	 */
	public function register_popup_support_metabox() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		
		foreach ( $post_types as $post_type ) {
			// Skip attachment post type
			if ( 'attachment' === $post_type ) {
				continue;
			}

			add_meta_box(
				'simplest-popup-support',
				__( 'Synced Pattern Popup Support', 'sppopups' ),
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
	 * @param WP_Post $post Current post object
	 */
	public function render_popup_support_metabox( $post ) {
		// Add nonce for security
		wp_nonce_field( 'sppopups_support_metabox', 'sppopups_support_nonce' );

		// Get current value
		$current_value = get_post_meta( $post->ID, '_sppopups_support', true );
		if ( empty( $current_value ) ) {
			$current_value = 'default';
		}
		?>
		<div class="sppopups-support-metabox">
			<fieldset>
				<label>
					<input type="radio" name="sppopups_support" value="default" <?php checked( $current_value, 'default' ); ?> />
					<strong><?php esc_html_e( 'Default', 'sppopups' ); ?></strong>
				</label>
				<br>
				<label>
					<input type="radio" name="sppopups_support" value="forced" <?php checked( $current_value, 'forced' ); ?> />
					<strong><?php esc_html_e( 'Forced On', 'sppopups' ); ?></strong>
				</label>
			</fieldset>
			<p class="description" style="margin-top: 12px; margin-bottom: 0;">
				<?php esc_html_e( 'In most cases you can leave this on Default. Use Forced On if your trigger link/class is injected dynamically (e.g., forms, AJAX, page builders) and the popup assets aren\'t loading.', 'sppopups' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Save popup support meta box
	 *
	 * @param int $post_id Post ID
	 */
	public function save_popup_support_metabox( $post_id ) {
		// Check if nonce is set
		if ( ! isset( $_POST['sppopups_support_nonce'] ) ) {
			return;
		}

		// Verify nonce
		$nonce = isset( $_POST['sppopups_support_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['sppopups_support_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'sppopups_support_metabox' ) ) {
			return;
		}

		// Check if autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check user permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Check if revision
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Get and sanitize the value
		$value = isset( $_POST['sppopups_support'] ) ? sanitize_text_field( wp_unslash( $_POST['sppopups_support'] ) ) : 'default';
		$allowed = array( 'default', 'forced' );
		
		if ( ! in_array( $value, $allowed, true ) ) {
			$value = 'default';
		}

		// Update post meta
		update_post_meta( $post_id, '_sppopups_support', $value );
	}
}

