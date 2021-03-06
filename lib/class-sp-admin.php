<?php

/**
 *
 */

if ( !class_exists( 'SP_Admin' ) ) :

class SP_Admin {

	private static $instance;

	/**
	 * @codeCoverageIgnore
	 */
	private function __construct() {
		/* Don't do anything, needs to be initialized via instance() method */
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function __clone() { wp_die( "Please don't __clone SP_Admin" ); }

	/**
	 * @codeCoverageIgnore
	 */
	public function __wakeup() { wp_die( "Please don't __wakeup SP_Admin" ); }

	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new SP_Admin;
			self::$instance->setup();
		}
		return self::$instance;
	}

	public function setup() {
		add_action( 'admin_menu',                array( $this, 'admin_menu' )     );
		add_action( 'admin_post_sp_full_sync',   array( $this, 'full_sync' )      );
		add_action( 'admin_post_sp_cancel_sync', array( $this, 'cancel_sync' )    );
		add_action( 'admin_post_sp_settings',    array( $this, 'save_settings' )  );
		add_action( 'admin_post_sp_clear_log',   array( $this, 'clear_log' )  );
		add_action( 'wp_ajax_sp_sync_status',    array( $this, 'sp_sync_status' ) );
		add_action( 'admin_notices',             array( $this, 'admin_notices' )  );
		add_action( 'admin_enqueue_scripts',     array( $this, 'assets' )         );
	}


	public function admin_menu() {
		// Add new admin menu and save returned page hook
		$hook_suffix = add_management_page( __( 'SearchPress', 'searchpress' ), __( 'SearchPress', 'searchpress' ), 'manage_options', 'searchpress', array( $this, 'sync' ) );
	}


	public function sync() {
		if ( !current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'searchpress' ) );
		}
		$sync = SP_Sync_Meta();
		if ( $sync->running ) {
			$active_tab = 'sync';
		} elseif ( ! empty( $sync->messages ) ) {
			$active_tab = 'log';
		} else {
			$active_tab = 'settings';
		}
		?>
		<div class="wrap">
			<h2><?php esc_html_e( 'SearchPress', 'searchpress' ); ?></h2>

			<?php if ( isset( $_GET['error'] ) ) : ?>
				<div class="error updated"><p><?php esc_html( sprintf( __( 'An error has occurred: %s', 'searchpress' ), $this->get_error( $_GET['error'] ) ) ) ?></p></div>
			<?php endif ?>

			<?php if ( isset( $_GET['complete'] ) ) : ?>
				<div class="updated success"><p><?php esc_html_e( 'Sync complete!', 'searchpress' ); ?></p></div>
			<?php endif ?>

			<h3 class="nav-tab-wrapper">
				<a class="nav-tab<?php $this->tab_active( 'settings', $active_tab ) ?>" href="#sp-settings"><?php esc_html_e( 'Settings', 'searchpress' ); ?></a>
				<a class="nav-tab<?php $this->tab_active( 'sync', $active_tab ) ?>" href="#sp-sync"><?php esc_html_e( 'Sync', 'searchpress' ); ?></a>
				<?php if ( ! empty( $sync->messages ) ) : ?>
					<a class="nav-tab<?php $this->tab_active( 'log', $active_tab ) ?>" href="#sp-log"><?php esc_html_e( 'Log', 'searchpress' ); ?></a>
				<?php endif ?>
				<!-- <a class="nav-tab" href="#sp-"><?php esc_html_e( '', 'searchpress' ); ?></a> -->
			</h3>

			<div id="sp-settings" class="tab-content">
				<form method="post" action="<?php echo admin_url( 'admin-post.php' ) ?>">
					<input type="hidden" name="action" value="sp_settings" />
					<?php wp_nonce_field( 'sp_settings', 'sp_settings_nonce' ); ?>
					<p>
						<input type="text" name="sp_host" value="<?php echo esc_url( SP_Config()->get_setting( 'host' ) ) ?>" style="width:100%;max-width:500px" />
					</p>
					<p>
						<label for="sp_reindex"><input type="checkbox" name="sp_reindex" id="sp_reindex" value="1" /> <?php esc_html_e( 'Immediately initiate a full sync', 'searchpress' ); ?>
					</p>
					<?php submit_button( __( 'Save Settings', 'searchpress' ), 'primary' ) ?>
				</form>
			</div>

			<div id="sp-sync" class="tab-content">
				<?php if ( $sync->running ) : ?>

					<h3><?php esc_html_e( 'Sync in progress', 'searchpress' ); ?></h3>
					<p><?php esc_html_e( 'You do not need to stay on this page while the sync runs.', 'searchpress' ); ?></p>
					<div class="progress">
						<div class="progress-text"><span id="sync-processed"><?php echo number_format( intval( $sync->processed ) ) ?></span> / <span id="sync-total"><?php echo number_format( intval( $sync->total ) ) ?></span></div>
						<div class="progress-bar" data-processed="<?php echo intval( $sync->processed ) ?>" data-total="<?php echo intval( $sync->total ) ?>" style="width:<?php echo intval( round( 100 * $sync->processed / $sync->total ) ) ?>%;"></div>
					</div>
					<form method="post" action="<?php echo admin_url( 'admin-post.php' ) ?>">
						<input type="hidden" name="action" value="sp_cancel_sync" />
						<?php wp_nonce_field( 'sp_sync', 'sp_sync_nonce' ); ?>
						<?php submit_button( __( 'Cancel Sync', 'searchpress' ), 'delete' ) ?>
					</form>

				<?php else : ?>

					<h3><?php esc_html_e( 'Full Sync', 'searchpress' ); ?></h3>
					<p><?php esc_html_e( 'Running a full sync will wipe the current index if there is one and rebuild it from scratch.', 'searchpress' ); ?></p>
					<p>
						<?php echo esc_html( sprintf( _n( 'Your site has %s post to index.', 'Your site has %s posts to index.', intval( SP_Sync_Manager()->count_posts() ), 'searchpress' ), number_format( intval( SP_Sync_Manager()->count_posts() ) ) ) ) ?>
						<?php if ( SP_Sync_Manager()->count_posts() > 25000 ) : ?>
							<?php esc_html_e( 'As a result of there being so many posts, this may take a long time to index.', 'searchpress' ); ?>
						<?php endif ?>
						<?php esc_html_e( "Exactly how long this will take will vary on a number of factors, like your server's CPU and memory, connection speed, current traffic, average post length, and associated terms and post meta.", 'searchpress' ); ?>
					</p>
					<p><?php esc_html_e( 'Your site will not use SearchPress until the indexing is complete.', 'searchpress' ); ?></p>

					<form method="post" action="<?php echo admin_url( 'admin-post.php' ) ?>">
						<input type="hidden" name="action" value="sp_full_sync" />
						<?php wp_nonce_field( 'sp_sync', 'sp_sync_nonce' ); ?>
						<?php submit_button( __( 'Run Full Sync', 'searchpress' ), 'delete' ) ?>
					</form>

					<h3><?php esc_html_e( 'Last full sync', 'searchpress' ); ?></h3>
					<p><?php printf( esc_html__( 'Started at %s', 'searchpress' ), date( 'Y-m-d H:i:s T', $sync->started ) ) ?></p>
					<p><?php printf( esc_html__( 'Completed at %s', 'searchpress' ), date( 'Y-m-d H:i:s T', $sync->finished ) ) ?></p>

				<?php endif ?>
			</div>

			<?php if ( ! empty( $sync->messages ) ) : ?>
				<?php SP_Sync_Meta()->clear_error_notice() ?>

				<div id="sp-log" class="tab-content">
					<?php foreach ( $sync->messages as $type => $messages ) : ?>
						<h3><?php echo esc_html( $this->error_type( $type ) ) ?></h3>
						<ol class="<?php echo esc_attr( $type ) ?>">
							<?php foreach ( $messages as $message ) : ?>
								<li><?php echo esc_html( $message ) ?></li>
							<?php endforeach ?>
						</ol>
					<?php endforeach ?>

					<form method="post" action="<?php echo admin_url( 'admin-post.php' ) ?>">
						<input type="hidden" name="action" value="sp_clear_log" />
						<?php wp_nonce_field( 'sp_flush_log_nonce', 'sp_sync_nonce' ); ?>
						<?php submit_button( __( 'Clear Log', 'searchpress' ), 'delete' ) ?>
					</form>
				</div>
			<?php endif ?>

		</div>
		<?php
	}

	protected function tab_active( $active, $compare = true ) {
		if ( $active === $compare ) {
			echo ' nav-tab-active';
		}
	}

	protected function error_type( $type ) {
		switch ( $type ) {
			case 'error' : return __( 'Errors', 'searchpress' );
			case 'warning' : return __( 'Warnings', 'searchpress' );
			case 'line' : return __( 'Messages', 'searchpress' );
			case 'success' : return __( 'Success', 'searchpress' );
		}
	}

	public function save_settings() {
		if ( !isset( $_POST['sp_settings_nonce'] ) || ! wp_verify_nonce( $_POST['sp_settings_nonce'], 'sp_settings' ) ) {
			wp_die( 'You are not authorized to perform that action' );
		} else {
			if ( isset( $_POST['sp_host'] ) ) {
				SP_Config()->update_settings( array( 'host' => esc_url( $_POST['sp_host'] ) ) );
			}
			if ( isset( $_POST['sp_reindex'] ) && '1' == $_POST['sp_reindex'] ) {
				# The full sync process checks the nonce, so we have to insert it into the postdata
				$_POST['sp_sync_nonce'] = wp_create_nonce( 'sp_sync' );
				$this->full_sync();
			} else {
				wp_redirect( admin_url( 'tools.php?page=searchpress&save=1' ) );
			}
		}

		exit;
	}

	public function full_sync() {
		if ( ! isset( $_POST['sp_sync_nonce'] ) || ! wp_verify_nonce( $_POST['sp_sync_nonce'], 'sp_sync' ) ) {
			wp_die( 'You are not authorized to perform that action' );
		} else {
			SP_Config()->update_settings( array( 'must_init' => false, 'active' => false, 'last_beat' => false ) );
			$result = SP_Config()->flush();
			if ( ! isset( SP_API()->last_request['response_code'] ) || ! in_array( SP_API()->last_request['response_code'], array( 200, 404 ) ) ) {
				wp_redirect( admin_url( 'tools.php?page=searchpress&error=100' ) );
			} else {
				SP_Config()->create_mapping();
				SP_Sync_Manager()->do_cron_reindex();
				wp_redirect( admin_url( 'tools.php?page=searchpress' ) );
			}
			exit;
		}
	}

	public function cancel_sync() {
		if ( ! isset( $_POST['sp_sync_nonce'] ) || ! wp_verify_nonce( $_POST['sp_sync_nonce'], 'sp_sync' ) ) {
			wp_die( __( 'You are not authorized to perform that action', 'searchpress' ) );
		} else {
			SP_Sync_Manager()->cancel_reindex();
			wp_redirect( admin_url( 'tools.php?page=searchpress&cancel=1' ) );
			exit;
		}
	}

	public function clear_log() {
		if ( ! isset( $_POST['sp_sync_nonce'] ) || ! wp_verify_nonce( $_POST['sp_sync_nonce'], 'sp_flush_log_nonce' ) ) {
			wp_die( __( 'You are not authorized to perform that action', 'searchpress' ) );
		} else {
			SP_Sync_Meta()->clear_log();
			wp_redirect( admin_url( 'tools.php?page=searchpress&clear_log=1' ) );
			exit;
		}
	}

	public function sp_sync_status() {
		if ( SP_Sync_Meta()->running ) {
			echo json_encode( array(
				'processed' => SP_Sync_Meta()->processed,
				'page' => SP_Sync_Meta()->page
			) );
		} else {
			echo json_encode( array(
				'processed' => 'complete'
			) );
		}
		exit;
	}

	public function assets() {
		if ( isset( $_GET['page'] ) && 'searchpress' == $_GET['page'] ) {
			wp_enqueue_style( 'searchpress-admin-css', SP_PLUGIN_URL . '/assets/admin.css', array(), '0.2' );
			wp_enqueue_script( 'searchpress-admin-js', SP_PLUGIN_URL . '/assets/admin.js', array( 'jquery' ), '0.2', true );
			wp_localize_script( 'searchpress-admin-js', 'searchpress', array(
				'admin_url' => esc_url_raw( admin_url( "tools.php?page=searchpress" ) ),
			) );
		}
	}

	public function get_error( $code ) {
		switch ( $code ) {
			case SP_ERROR_FLUSH_FAIL : return __( 'SearchPress could not flush the old data', 'searchpress' );
		}
		return __( 'Unknown error', 'searchpress' );
	}

	public function admin_notices() {
		if ( isset( $_GET['page'] ) && 'searchpress' == $_GET['page'] ) {
			return;
		} elseif ( SP_Sync_Meta()->running ) {
			printf(
				'<div class="updated"><p>%s <a href="%s">%s</a></p></div>',
				__( 'SearchPress sync is currently running.', 'searchpress' ),
				admin_url( 'tools.php?page=searchpress' ),
				__( 'View status', 'searchpress' )
			);
		} elseif ( SP_Config()->must_init() ) {
			printf(
				'<div class="updated error"><p>%s <a href="%s">%s</a></p></div>',
				__( 'SearchPress needs to be configured and synced before you can use it.', 'searchpress' ),
				admin_url( 'tools.php?page=searchpress' ),
				__( 'Go to SearchPress Settings', 'searchpress' )
			);
		} elseif ( SP_Sync_Meta()->has_errors() ) {
			printf(
				'<div class="updated error"><p>%s <a href="%s">%s</a></p></div>',
				__( 'SearchPress encountered an error while syncing.', 'searchpress' ),
				admin_url( 'tools.php?page=searchpress#sp-log' ),
				__( 'Go to Log', 'searchpress' )
			);
		}
	}
}

function SP_Admin() {
	return SP_Admin::instance();
}
add_action( 'after_setup_theme', 'SP_Admin' );

endif;
