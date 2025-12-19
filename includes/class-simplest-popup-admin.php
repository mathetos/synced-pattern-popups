<?php
/**
 * Admin Interface
 * Handles admin menu and list table for synced patterns
 *
 * @package Simplest_Popup
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simplest_Popup_Admin {

	/**
	 * Pattern service instance
	 *
	 * @var Simplest_Popup_Pattern
	 */
	private $pattern_service;

	/**
	 * Constructor
	 *
	 * @param Simplest_Popup_Pattern $pattern_service Pattern service instance
	 */
	public function __construct( Simplest_Popup_Pattern $pattern_service ) {
		$this->pattern_service = $pattern_service;
	}

	/**
	 * Initialize admin interface
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
	}

	/**
	 * Add admin submenu under Appearance
	 */
	public function add_admin_menu() {
		add_theme_page(
			__( 'Synced Patterns', 'simplest-popup' ),
			__( 'Synced Patterns', 'simplest-popup' ),
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
			SIMPLEST_POPUP_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			SIMPLEST_POPUP_VERSION
		);

		wp_enqueue_script(
			'simplest-popup-admin',
			SIMPLEST_POPUP_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			SIMPLEST_POPUP_VERSION,
			true
		);

		wp_localize_script(
			'simplest-popup-admin',
			'simplestPopupAdmin',
			array(
				'strings' => array(
					'copied' => __( 'Copied!', 'simplest-popup' ),
					'copyFailed' => __( 'Failed to copy', 'simplest-popup' ),
				),
			)
		);
	}

	/**
	 * Handle admin actions (delete, etc.)
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
				wp_redirect( admin_url( 'themes.php?page=simplest-popup-patterns&deleted=1' ) );
				exit;
			}
		}
	}

	/**
	 * Get all synced patterns
	 *
	 * @return array Array of pattern objects
	 */
	private function get_synced_patterns() {
		$args = array(
			'post_type'      => 'wp_block',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => 'wp_pattern_sync_status',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => 'wp_pattern_sync_status',
					'value'   => 'unsynced',
					'compare' => '!=',
				),
			),
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		$query = new WP_Query( $args );
		$posts = $query->posts;

		// Prime meta cache for all patterns to eliminate N+1 queries
		if ( ! empty( $posts ) && is_array( $posts ) ) {
			$post_ids = wp_list_pluck( $posts, 'ID' );
			if ( ! empty( $post_ids ) && is_array( $post_ids ) ) {
				// Correct WP core meta-cache priming (update_post_meta_cache() is not a core function)
				if ( function_exists( 'update_postmeta_cache' ) ) {
					update_postmeta_cache( $post_ids );
				} else {
					update_meta_cache( 'post', $post_ids );
				}
			}
		}

		return is_array( $posts ) ? $posts : array();
	}

	/**
	 * Render admin page
	 */
	public function render_admin_page() {
		$patterns = $this->get_synced_patterns();

		// Show success message
		if ( isset( $_GET['deleted'] ) && '1' === $_GET['deleted'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Pattern deleted successfully.', 'simplest-popup' ) . '</p></div>';
		}

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">
				<?php esc_html_e( 'Synced Patterns', 'simplest-popup' ); ?>
			</h1>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wp_block' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New', 'simplest-popup' ); ?>
			</a>
			<hr class="wp-header-end">

			<p class="description">
				<?php esc_html_e( 'Manage synced patterns that can be used as popups. Only synced patterns are available for popup triggers.', 'simplest-popup' ); ?>
			</p>

			<?php if ( empty( $patterns ) ) : ?>
				<div class="notice notice-info">
					<p>
						<?php esc_html_e( 'No synced patterns found.', 'simplest-popup' ); ?>
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wp_block' ) ); ?>">
							<?php esc_html_e( 'Create your first synced pattern', 'simplest-popup' ); ?>
						</a>
					</p>
				</div>
			<?php else : ?>
				<div class="simplest-popup-table-wrapper">
					<table class="wp-list-table widefat fixed striped simplest-popup-patterns-table">
					<thead>
						<tr>
							<th class="column-id"><?php esc_html_e( 'ID', 'simplest-popup' ); ?></th>
							<th class="column-title"><?php esc_html_e( 'Title', 'simplest-popup' ); ?></th>
							<th class="column-status"><?php esc_html_e( 'Status', 'simplest-popup' ); ?></th>
							<th class="column-sync-status"><?php esc_html_e( 'Sync Status', 'simplest-popup' ); ?></th>
							<th class="column-trigger"><?php esc_html_e( 'Trigger Code', 'simplest-popup' ); ?></th>
							<th class="column-actions"><?php esc_html_e( 'Actions', 'simplest-popup' ); ?></th>
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
							$trigger_code = 'wppt-popup-' . $pattern_id;
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
										$pattern_title = isset( $pattern->post_title ) && ! empty( $pattern->post_title ) ? $pattern->post_title : __( '(no title)', 'simplest-popup' );
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
										<span class="status-badge status-synced"><?php esc_html_e( 'Synced', 'simplest-popup' ); ?></span>
									<?php else : ?>
										<span class="status-badge status-unsynced"><?php esc_html_e( 'Unsynced', 'simplest-popup' ); ?></span>
									<?php endif; ?>
								</td>
								<td class="column-trigger">
									<input type="text" readonly value="<?php echo esc_attr( $trigger_code ); ?>" class="trigger-code-input" />
								</td>
								<td class="column-actions">
									<?php if ( $edit_url ) : ?>
										<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small">
											<?php esc_html_e( 'Edit', 'simplest-popup' ); ?>
										</a>
									<?php endif; ?>
									<?php if ( current_user_can( 'delete_post', $pattern_id ) ) : ?>
										<a 
											href="<?php echo esc_url( $delete_url ); ?>" 
											class="button button-small delete-pattern"
											onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this pattern?', 'simplest-popup' ) ); ?>');"
										>
											<?php esc_html_e( 'Delete', 'simplest-popup' ); ?>
										</a>
									<?php endif; ?>
									<button 
										type="button" 
										class="button button-small copy-trigger" 
										data-copy="<?php echo esc_attr( $trigger_code ); ?>"
									>
										<?php esc_html_e( 'Copy Trigger', 'simplest-popup' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				</div>

				<div class="simplest-popup-usage-instructions">
					<strong><?php esc_html_e( 'How to use:', 'simplest-popup' ); ?></strong>
					<div>
						<?php esc_html_e( 'Method 1 - Class name:', 'simplest-popup' ); ?>
						<code>&lt;a href="#" class="wppt-popup-123"&gt;Open Popup&lt;/a&gt;</code>
					</div>
					<div>
						<?php esc_html_e( 'Method 2 - Href attribute (for Block Editor):', 'simplest-popup' ); ?>
						<code>&lt;a href="#wppt-popup-123"&gt;Open Popup&lt;/a&gt;</code>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}

