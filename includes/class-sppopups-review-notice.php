<?php
/**
 * Review Notice
 * Handles delayed admin notice for review requests
 *
 * @package SPPopups
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SPPopups_Review_Notice {

	/**
	 * Delay in days before showing notice
	 */
	private const DELAY_DAYS = 10;

	/**
	 * Plugin name for display
	 */
	private const PLUGIN_NAME = 'Synced Pattern Popups';

	/**
	 * WordPress.org review URL
	 */
	private const REVIEW_URL = 'https://wordpress.org/support/plugin/synced-pattern-popups/reviews/';

	/**
	 * Option name for trigger date
	 */
	private const OPTION_NAME = 'sppopups_review_notice_trigger_date';

	/**
	 * User meta key for dismissal
	 */
	private const USER_META_KEY = 'sppopups_review_notice_dismissed';

	/**
	 * AJAX action name
	 */
	private const AJAX_ACTION = 'sppopups_dismiss_review_notice';

	/**
	 * Nonce action name
	 */
	private const NONCE_ACTION = 'sppopups_review_notice';

	/**
	 * Initialize review notice
	 */
	public function init() {
		// Only run in admin
		if ( ! is_admin() ) {
			return;
		}

		// Hook into admin notices
		add_action( 'admin_notices', array( $this, 'maybe_show_notice' ) );

		// Register AJAX handler
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_dismiss_ajax' ) );
	}

	/**
	 * Set trigger date on plugin activation
	 * Static method to be called from activation hook
	 */
	public static function set_trigger_date() {
		// Only set if option doesn't exist
		if ( get_option( self::OPTION_NAME ) === false ) {
			$trigger_date = time() + ( DAY_IN_SECONDS * self::DELAY_DAYS );
			add_option( self::OPTION_NAME, $trigger_date, '', false );
		}
	}

	/**
	 * Check if notice should be shown
	 *
	 * @return bool True if notice should be shown
	 */
	private function should_show_notice() {
		// Check if user has capability (super admin, admin, or editor)
		if ( ! is_super_admin() && ! current_user_can( 'edit_posts' ) ) {
			return false;
		}

		// Check if user has dismissed the notice
		$user_id = get_current_user_id();
		if ( empty( $user_id ) ) {
			return false;
		}

		$dismissed = get_user_meta( $user_id, self::USER_META_KEY, true );
		if ( '1' === $dismissed ) {
			return false;
		}

		// Check if we're on the correct admin page (do this BEFORE option check for efficiency)
		$screen = get_current_screen();
		if ( ! $screen || 'appearance_page_simplest-popup-patterns' !== $screen->id ) {
			return false;
		}

		// Check if trigger date option exists, initialize if not (for existing users upgrading)
		$trigger_date = get_option( self::OPTION_NAME );
		if ( false === $trigger_date ) {
			// Initialize for existing users who upgraded to version with this feature
			// Only runs once because option will exist after this
			$this->initialize_trigger_date_for_existing_user();
			// Re-fetch the option (should now exist)
			$trigger_date = get_option( self::OPTION_NAME, 0 );
		}

		if ( empty( $trigger_date ) || ! is_numeric( $trigger_date ) ) {
			return false;
		}

		$current_time = time();
		if ( $current_time < (int) $trigger_date ) {
			return false;
		}

		return true;
	}

	/**
	 * Initialize trigger date for existing users who upgraded
	 * Only called when option doesn't exist and we're on the relevant page
	 */
	private function initialize_trigger_date_for_existing_user() {
		// Ensure plugin.php is loaded for is_plugin_active()
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Double-check plugin is active (safety check)
		$plugin_file = plugin_basename( SPPOPUPS_PLUGIN_DIR . 'sppopups.php' );
		if ( ! is_plugin_active( $plugin_file ) ) {
			return;
		}

		// Set trigger date to 30 days from now for existing users
		$trigger_date = time() + ( DAY_IN_SECONDS * self::DELAY_DAYS );
		add_option( self::OPTION_NAME, $trigger_date, '', false );
	}

	/**
	 * Maybe show admin notice
	 */
	public function maybe_show_notice() {
		if ( ! $this->should_show_notice() ) {
			return;
		}

		$plugin_name = self::PLUGIN_NAME;
		$review_url = self::REVIEW_URL;
		$nonce = wp_create_nonce( self::NONCE_ACTION );

		?>
		<div class="sppopups-review-notice-wrapper">
			<div class="sppopups-review-notice" data-nonce="<?php echo esc_attr( $nonce ); ?>">
				<h3 class="sppopups-review-notice-heading">
					<?php
					/* translators: %s: Plugin name */
					echo esc_html( sprintf( __( 'Enjoying %s?', 'synced-pattern-popups' ), $plugin_name ) );
					?>
				</h3>
				
				<p class="sppopups-review-notice-subheading">
					<?php esc_html_e( 'Leave us a kind review on WordPress.org', 'synced-pattern-popups' ); ?>
				</p>
				
				<div class="sppopups-review-notice-stars">
					⭐⭐⭐⭐⭐
				</div>
				
				<div class="sppopups-review-notice-actions">
					<a href="<?php echo esc_url( $review_url ); ?>" target="_blank" rel="noopener noreferrer" class="sppopups-review-notice-button">
						<?php esc_html_e( 'Leave your Review Here', 'synced-pattern-popups' ); ?>
					</a>
					<a href="#" class="sppopups-review-notice-dismiss-link" data-action="dismiss-review-notice" aria-label="<?php echo esc_attr__( 'Dismiss this notice', 'synced-pattern-popups' ); ?>">
						<?php esc_html_e( "I'd rather not (dismiss)", 'synced-pattern-popups' ); ?>
					</a>
				</div>
				
				<p class="sppopups-review-notice-footer">
					<em><?php esc_html_e( 'Your review and feedback keeps us developing this plugin for more users like you!', 'synced-pattern-popups' ); ?></em>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle AJAX dismissal request
	 */
	public function handle_dismiss_ajax() {
		// Check user capability
		if ( ! is_super_admin() && ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'synced-pattern-popups' ) ) );
			return;
		}

		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token. Please refresh the page and try again.', 'synced-pattern-popups' ) ) );
			return;
		}

		// Get current user ID
		$user_id = get_current_user_id();
		if ( empty( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'User not found.', 'synced-pattern-popups' ) ) );
			return;
		}

		// Save dismissal
		update_user_meta( $user_id, self::USER_META_KEY, '1' );

		wp_send_json_success( array( 'message' => __( 'Notice dismissed.', 'synced-pattern-popups' ) ) );
	}

	/**
	 * Cleanup on plugin uninstall
	 * Static method to be called from uninstall hook
	 */
	public static function cleanup() {
		// Delete option
		delete_option( self::OPTION_NAME );

		// Delete user meta for all users
		$users = get_users( array( 'fields' => 'ID' ) );
		foreach ( $users as $user_id ) {
			delete_user_meta( $user_id, self::USER_META_KEY );
		}
	}
}