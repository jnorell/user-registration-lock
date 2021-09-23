<?php
/**
 * The User Registration Lock plugin class.
 *
 * @link       https://github.com/jnorell/
 * @since      1.0.0
 * @package    User_Registration_Lock
 * @subpackage User_Registration_Lock/includes
 */

// tmp debug:
function x( $msg ) {
	error_log( "DEBUG: $msg" );
}

// Prevent direct access to this file.
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

require_once( __DIR__.'/user-registration-lock-options.php' );

if ( !class_exists( 'User_Registration_Lock' ) ) {

	/**
	 * The User Registration Lock plugin class.
	 *
	 * This is a small plugin with little immediate plans for expansion,
	 * so this main User Registration Lock class includes most plugin functionality.
	 *
	 * @package    User_Registration_Lock
	 * @subpackage User_Registration_Lock/includes
	 * @author     Jesse Norell <jesse@kci.net>
	 */
	class User_Registration_Lock {

		/**
		 * The unique identifier of this plugin.
		 *
		 * @since    1.0.0
		 * @access   protected
		 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
		 */
		protected $plugin_name;

		/**
		 * The current version of the plugin.
		 *
		 * @since    1.0.0
		 * @access   protected
		 * @var      string    $version    The current version of the plugin.
		 */
		protected $version;

		/**
		 * The default plugin options.
		 *
		 * @since    1.0.0
		 * @access   protected
		 * @var      array    $default_options    The default plugin options.
		 */
		protected $default_options;

		/**
		 * The name of the database table used to store user information.
		 *
		 * @since    1.0.0
		 * @access   protected
		 * @var      string    $table_save_users    The name of the db table used by this plugin.
		 */
		protected $table_save_users;


		/**
		 * Class constructor
		 *
		 * Set class variables (the plugin name, plugin version, default options, etc.)
		 *
		 * @since    1.0.0
		 */
		public function __construct() {
			global $wpdb;

			if ( defined( 'USER_REGISTRATION_LOCK_VERSION' ) ) {
				$this->version = USER_REGISTRATION_LOCK_VERSION;
			} else {
				$this->version = '1.0.0';
			}

			$this->plugin_name = 'user-registration-lock';
			$this->table_save_users = $wpdb->prefix . 'user_registration_lock_save_users';

			$default_options = array(
				'version'               => $this->version,
				'allow_user_changes'    => true,
				'admin_email'           => get_option( 'admin_email' ),
			);

			User_Registration_Lock_Options::set_option_name( $this->plugin_name );
			User_Registration_Lock_Options::set_default_options( $default_options );
		}


		/**
		 * Initialize class.
		 *
		 * Load locale strings, check if user registraion lock has been setup,
		 * run periodic checks if needed, set action hooks and filters.
		 *
		 * @since    1.0.0
		 */
		public function init() {
			$this->load_plugin_textdomain();

			$options = User_Registration_Lock_Options::get_options();

			if ( ! is_array( $options ) ) {
				// save current options for monitoring and later restore
				$this->save_options();

				// set users_can_register and default_role to disable user registration
				$this->user_registration_lock();

				// create db table(s)
				$this->create_db();

				// copy user info to our save_users table
				$this->save_users();

				// save last_run timestamp
				User_Registration_Lock_Options::update_option( 'last_run', time() );

			} else {
				$current_version = User_Registration_Lock_Options::get_option( 'version' );
				$current_db_version = User_Registration_Lock_Options::get_option( 'db_version' );

				if ( ( version_compare( $current_version, $this->version ) < 0 ) ||
					( version_compare( $current_db_version, $this->version ) < 0 ) ) {
					$this->version_update();
				}
			}

			if ( $this->run_plugin_checks_needed() ) {
				$this->run_plugin_checks();
			}

			// set action hooks and filters
			if ( is_admin() ) {	// admin hooks
				add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
			}

			// enforce user registration lock via option filters
			$this->enable_option_filters();

		}

		/**
		 * Load plugin translated strings for internationalization.
		 *
		 * @since   1.0.0
		 * @access  private
		 */
		private function load_plugin_textdomain() {

			load_plugin_textdomain(
				'user-registration-lock',
				false,
				plugin_dir_url( USER_REGISTRATION_LOCK_PLUGIN_BASE ) . 'languages/'
			);

		}

		/**
		 * Save current options for monitoring and later restore.
		 *
		 * @since   1.0.0
		 * @access  protected
		 */
		protected function save_options() {

			$option = get_option( 'users_can_register' );
			if ( $option !== false ) {
				User_Registration_Lock_Options::add_option( 'save_users_can_register', $option );
			}
			$option = get_option( 'default_role' );
			if ( $option !== false ) {
				User_Registration_Lock_Options::add_option( 'save_default_role', $option );
			}
			$option = get_option( 'admin_email' );
			if ( $option !== false ) {
				User_Registration_Lock_Options::add_option( 'save_admin_email', $option );
			}
			$option = get_option( 'siteurl' );
			if ( $option !== false ) {
				User_Registration_Lock_Options::add_option( 'save_siteurl', $option );
			}
			$option = get_option( 'home' );
			if ( $option !== false ) {
				User_Registration_Lock_Options::add_option( 'save_home', $option );
			}

			// also save site_url() and home_url() (filtered) values
			User_Registration_Lock_Options::add_option( 'save_site_url', site_url() );
			User_Registration_Lock_Options::add_option( 'save_home_url', home_url() );
		}

		/**
		 * Set wordpress options to disallow user registration.
		 *
		 * @since   1.0.0
		 */
		public function user_registration_lock() {
			update_option( 'users_can_register', false );
			update_option( 'default_role', 'subscriber' );
		}

		/**
		 * Create db tables.
		 *
		 * @since    1.0.0
		 * @access   protected
		 */
		protected function create_db() {
			global $wpdb;

			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE $this->table_save_users (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				user_login varchar(60) NOT NULL DEFAULT '',
				user_pass varchar(255) NOT NULL DEFAULT '',
				user_email varchar(100) NOT NULL DEFAULT '',
				capabilities longtext,
				user_level longtext,
				PRIMARY KEY  (id)
			) $charset_collate;";

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );

			User_Registration_Lock_Options::update_option( 'db_version', $this->version );
		}

		/**
		 * Copy information from current users to our save_users table.
		 *
		 * @since    1.0.0
		 * @access   protected
		 */
		protected function save_users() {
			global $wpdb;

			//fixme: finish function
		}

		/**
		 * Perform needed updates from previous versions and store current version number.
		 *
		 * - No updates currently needed, merely save the version number.
		 *
		 * @since    1.0.0
		 * @access   protected
		 */
		protected function version_update() {

			$updated_to = User_Registration_Lock_Options::get_option( 'version' );
			$db_version = User_Registration_Lock_Options::get_option( 'db_version' );

			// create_db() will apply any schema changes via dbDelta()
			if ( version_compare( $db_version, $this->version ) < 0 ) {
				$this->create_db();
			}

			/* version-dependent updates when needed...
			if ( version_compare( $updated_to, '1.2.3' ) < 0 ) {
			    // do updates for 1.2.3
			    $updated_to = '1.2.3';
			}
			if ( version_compare( $updated_to, '2.3.4' ) < 0 ) {
			    // do updates for 2.3.4
			    $updated_to = '2.3.4';
			}
			*/

			if ( version_compare( $updated_to, $this->version ) < 0 ) {
				$updated_to = $this->version;
			}

			User_Registration_Lock_Options::update_option( 'version', $updated_to );
		}

		/**
		 * Enforce user registration is locked by filtering returned options.
		 *
		 * @since   1.0.0
		 * @access  protected
		 */
		protected function enable_option_filters() {
			add_filter( 'option_users_can_register', array( $this, 'filter_option_users_can_register' ), PHP_INT_MAX, 1 );
			add_filter( 'default_option_users_can_register', array( $this, 'filter_option_users_can_register' ), PHP_INT_MAX, 1 );

			add_filter( 'option_default_role', array( $this, 'filter_option_default_role' ), PHP_INT_MAX, 1 );
			add_filter( 'default_option_default_role', array( $this, 'filter_option_default_role' ), PHP_INT_MAX, 1 );
		}

		/**
		 * Removes the filters set by enable_option_fiters().
		 *
		 * @since   1.0.0
		 * @access  protected
		 */
		protected function disable_option_filters() {
			remove_filter( 'option_users_can_register', array( $this, 'filter_option_users_can_register' ), PHP_INT_MAX );
			remove_filter( 'default_option_users_can_register', array( $this, 'filter_option_users_can_register' ), PHP_INT_MAX );

			remove_filter( 'option_default_role', array( $this, 'filter_option_default_role' ), PHP_INT_MAX );
			remove_filter( 'default_option_default_role', array( $this, 'filter_option_default_role' ), PHP_INT_MAX );
		}

		/**
		 * Filter users_can_register option.
		 *
		 * @since   1.0.0
		 */
		public function filter_option_users_can_register() {
			return false;
		}

		/**
		 * Filter default_role option.
		 *
		 * @since   1.0.0
		 */
		public function filter_option_default_role() {
			return 'subscriber';
		}

		/**
		 * Check if User Registration Lock checks should be performed.
		 *
		 * Keep checks here lightweight, as they run from init hook.
		 *
		 * @since   1.0.0
		 * @param   integer $interval   The frequency to run plugin checks in seconds.
		 * @return  boolean True if option plugin checks need to be run, false if not.
		 */
		public function run_plugin_checks_needed( $interval=3600 ) {
			$last_run = (int)User_Registration_Lock_Options::get_option( 'last_run' );

			if ( time() - $last_run >= $interval ) {
				return true;
			}

			return false;
		}

		/**
		 * Runs User Registration Lock checks.
		 *
		 * This checks the 'users_can_register' and 'default_role' options to
		 * ensure they haven't been changed and compares current users with our
		 * saved users to notify of changes.
		 *
		 * @todo    Add an option to revert user changes, currently we only monitor
		 *          and notify of changes.
		 *
		 * @since   1.0.0
		 * @return  array   Array of notice messages regarding checks which failed.
		 */
		public function run_plugin_checks() {
			$notices = array();

			// disable our own option filters to check what is actually set
			$this->disable_option_filters();

			// read users_can_register option and check if still disabled
			$option = get_option( 'users_can_register' );
			if ( $option === false ) {  // get_option failed
				update_option( 'users_can_register', false );
				$notices[] = __( 'Error: There was an error reading the users_can_register option.', 'user-registration-lock' );
			} elseif ( $option !== 0 ) {    // false value is saved as 0
				update_option( 'users_can_register', false );
				$notices[] = __( 'Warning: User registration was found to be enabled via the user_can_register option.', 'user-registration-lock' );
			}

			// read default_role option and check if still set to subscriber
			$option = get_option( 'default_role' );
			if ( $option === false ) {  // get_option failed
				update_option( 'default_role', 'subscriber' );
				$notices[] = __( 'Error: There was an error reading the default_role option.', 'user-registration-lock' );
			} elseif ( $option !== 'subscriber' ) {
				update_option( 'default_role', 'subscriber' );
				/* translators: %s: Value of default_role option */
				$notices[] = sprintf( __( 'Warning: The default role for new users should be subscriber, but was found to be %s.', 'user-registration-lock' ), $option );
			}

			// enable our option filters again
			$this->enable_option_filters();

			// monitor a few other options for changes
			$saved_option = User_Registration_Lock_Options::get_option( 'save_admin_email' );
			$option = get_option( 'admin_email' );
			if ( $saved_option != $option ) {
				/* translators: %s: Value of previous admin_email option */
				/* translators: %s: Value of new admin_email option */
				$notices[] = sprintf( __( 'Notice: The Administration Email Address has been changed from %s to %s.', 'user-registration-lock' ), $saved_option, $option );
			}

			$saved_option = User_Registration_Lock_Options::get_option( 'save_siteurl' );
			$option = get_option( 'siteurl' );
			if ( $saved_option != $option ) {
				/* translators: %s: Value of previous siteurl option */
				/* translators: %s: Value of new siteurl option */
				$notices[] = sprintf( __( 'Notice: The Wordpress Address (URL) has been changed from %s to %s.', 'user-registration-lock' ), $saved_option, $option );
			} else {
				$saved_option = User_Registration_Lock_Options::get_option( 'save_site_url' );
				$option = site_url();
				if ( $saved_option != $option ) {
					/* translators: %s: Previous value of site_url() */
					/* translators: %s: New value of site_url() */
					$notices[] = sprintf( __( 'Notice: The Wordpress site_url() has changed from %s to %s.', 'user-registration-lock' ), $saved_option, $option );
				}
			}

			$saved_option = User_Registration_Lock_Options::get_option( 'save_home' );
			$option = get_option( 'home' );
			if ( $saved_option != $option ) {
				/* translators: %s: Value of previous home option */
				/* translators: %s: Value of new home option */
				$notices[] = sprintf( __( 'Notice: The Site Address (URL) has been changed from %s to %s.', 'user-registration-lock' ), $saved_option, $option );
			} else {
				$saved_option = User_Registration_Lock_Options::get_option( 'save_home_url' );
				$option = home_url();
				if ( $saved_option != $option ) {
					/* translators: %s: Previous value of home_url() */
					/* translators: %s: New value of home_url() */
					$notices[] = sprintf( __( 'Notice: The Wordpress home_url() has changed from %s to %s.', 'user-registration-lock' ), $saved_option, $option );
				}
			}

			# read users and check for monitored or disallowed changes
			// @todo
		}

		/**
		 * Enqueue admin css and scripts.
		 *
		 * @since   1.0.0
		 * @param   int $hook_suffix    The current admin page.
		 */
		public function admin_enqueue_scripts( $hook_suffix ) {
			// needed on general options page to disable user registration checkbox
			if ( 'options-general.php' != $hook_suffix ) {
				return;
			}

			wp_register_style( 'user-registration-lock-admin-css', plugin_dir_url( USER_REGISTRATION_LOCK_PLUGIN_BASE ) . 'admin/css/user-registration-lock.css', false, $this->get_version() );
			wp_enqueue_style( 'user-registration-lock-admin-css' );

			wp_register_script( 'user-registration-lock-admin-js', plugin_dir_url( USER_REGISTRATION_LOCK_PLUGIN_BASE ) . 'admin/js/user-registration-lock.js', array( 'jquery' ), $this->get_version(), true );

			wp_localize_script( 'user-registration-lock-admin-js', 'user_registration_lock_strings',
				array(
					'str_registration_disabled' => __( 'User registration is disabled.', 'user-registration-lock' ),
				)
			);

			wp_enqueue_script( 'user-registration-lock-admin-js' );

		}

		/**
		 * Plugin activation
		 *
		 * Create options and db tables, save old 'users_can_register' and 
		 * 'default_role' for restore, save current users/roles to monitor
		 * changes.  This is a subset of init() functionality.
		 *
		 * @since     1.0.0
		 */
		public function activate() {

			User_Registration_Lock_Options::set_option_name( $this->plugin_name );

			$options = User_Registration_Lock_Options::get_options();

			if ( is_array( $options ) ) {
				$current_version = User_Registration_Lock_Options::get_option( 'version' );
				$current_db_version = User_Registration_Lock_Options::get_option( 'db_version' );

				if ( ( version_compare( $current_version, $this->version ) < 0 ) ||
					( version_compare( $current_db_version, $this->version ) < 0 ) ) {
					$this->version_update();
				}
			}

			// save current options for monitoring and later restore
			$this->save_options();

			// set users_can_register and default_role to disable user registration
			$this->user_registration_lock();

			// create db table(s)
			$this->create_db();

			// copy user info to our save_users table
			$this->save_users();

			// save last_run timestamp
			User_Registration_Lock_Options::update_option( 'last_run', time() );
		}

		/**
		 * Plugin deactivation
		 *
		 * Restore old 'users_can_register' and * 'default_role' options,
		 * and truncate user_registration_lock_save_users table.
		 *
		 * @since     1.0.0
		 */
		public function deactivate() {
			global $wpdb;

			// disable our filters first, or the upcoming update_options fail
			$this->disable_option_filters();

			// Restore old options
			User_Registration_Lock_Options::set_option_name( $this->plugin_name );
			if ( User_Registration_Lock_Options::get_option( 'save_users_can_register' ) !== null ) {
				update_option( 'users_can_register', User_Registration_Lock_Options::get_option( 'save_users_can_register' ) );
			}
			if ( User_Registration_Lock_Options::get_option( 'save_default_role' ) !== null ) {
				update_option( 'default_role', User_Registration_Lock_Options::get_option( 'save_default_role' ) );
			}

			// Remove saved user info
			$wpdb->query( 'TRUNCATE TABLE '.$this->table_save_users );
		}

		/**
		 * Plugin uninstall
		 *
		 * Delete users table and plugin option.
		 *
		 * @since     1.0.0
		 */
		public function uninstall() {
			global $wpdb;

			// run deactivate hook to ensure options are reset
			$this->deactivate();

			// drop db table
			$wpdb->query( 'DROP TABLE IF EXISTS '.$this->table_save_users );

			// delete our option
			User_Registration_Lock_Options::set_option_name( $this->plugin_name );
			User_Registration_Lock_Options::delete_options();
		}

		/**
		 * Retrieve the name of the plugin.
		 *
		 * @since     1.0.0
		 * @return    string    The name of the plugin.
		 */
		public function get_plugin_name() {
			return $this->plugin_name;
		}

		/**
		 * Retrieve the version number of the plugin.
		 *
		 * @since     1.0.0
		 * @return    string    The version number of the plugin.
		 */
		public function get_version() {
			return $this->version;
		}

	} // end class User_Registration_Lock

} // !class_exists
