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

			// add unprivileged role to be the default
			$this->add_role();

			// enforce user registration lock via option filters
			$this->enable_option_filters();

			// filter user data to prevent new users from being created
			add_filter( 'wp_pre_insert_user_data', [ $this, 'wp_pre_insert_user_data' ], PHP_INT_MAX, 4 );

			// filter usermeta data to prevent capabilities and user_level from changing 
			add_filter( 'insert_user_meta', [ $this, 'insert_user_meta' ], PHP_INT_MAX, 4 );
			add_filter( 'update_user_metadata', [ $this, 'update_user_metadata' ], PHP_INT_MAX, 4 );
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
				user_registered datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				user_roles longtext,
				user_caps longtext,
				user_allcaps longtext,
				meta_capabilities longtext,
				meta_user_level longtext,
				meta_use_ssl longtext,
				PRIMARY KEY  (id)
			) $charset_collate;";

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );

			User_Registration_Lock_Options::update_option( 'db_version', $this->version );
		}

		/**
		 * Copy information from current users to our save_users table.
		 *
		 * @todo     should also monitor and prevent changes to $user->site_id:WP_User:private or $meta['_new_email'] ?
		 * @todo     monitor attempts to disable wordfence 2fa?
		 *
		 * @since    1.0.0
		 * @access   protected
		 */
		protected function save_users() {
			global $wpdb;

			// loop through all users
			$users = get_users();
			foreach ( $users as $user ) {
				$meta = get_user_meta( $user->ID );
				// these are arrays with single element:
				$meta_capabilities = isset( $meta[ $GLOBALS['wpdb']->prefix.'capabilities' ] ) ? $meta[ $GLOBALS['wpdb']->prefix.'capabilities' ][0] : '';
				$meta_user_level = isset( $meta[ $GLOBALS['wpdb']->prefix.'user_level' ] ) ?  $meta[ $GLOBALS['wpdb']->prefix.'user_level' ][0] : '';
				$meta_use_ssl = isset( $meta['use_ssl'] ) ?  $meta['use_ssl'][0] : '';

				$save_user = [
					'user_login' => $user->user_login,
					'user_id' => $user->ID,
					'user_pass' => $user->user_pass,
					'user_email' => $user->user_email,
					'user_registered' => $user->user_registered,
					'user_roles' => json_encode( $user->roles ),
					'user_caps' => json_encode( $user->caps ),
					'user_allcaps' => json_encode( $user->allcaps ),
					'meta_capabilities' => $meta_capabilities,
					'meta_user_level' => $meta_user_level,
					'meta_use_ssl' => $meta_use_ssl,
				];

				// insert requires raw data, no escaping
				$wpdb->insert( $this->table_save_users, $save_user );
			}
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
		 * Add unprivileged role to be set as the default
		 *
		 * @since   1.0.0
		 */
		public function add_role() {
			add_role(
				$this->plugin_name,
				__( 'User Registration Locked', 'user-registration-lock' ),
				[]
			);
		}

		/**
		 * Remove unprivileged role added by add_role()
		 *
		 * @since   1.0.0
		 */
		public function remove_role() {
			remove_role( $this->plugin_name );
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
			// default role is now the unprivileged role we created
			return $this->plugin_name;
		}

		/**
		 * Filter user data to prevent new users from being created and disallowed changes
		 *
		 * @todo:   looks like we could check $usermeta['role'], though only in 5.8
		 *
		 * @since   1.0.0
		 *
		 * @param array    $data {
		 *     Values and keys for the user.
		 *
		 *     @type string $user_login      The user's login. Only included if $update == false
		 *     @type string $user_pass       The user's password.
		 *     @type string $user_email      The user's email.
		 *     @type string $user_url        The user's url.
		 *     @type string $user_nicename   The user's nice name. Defaults to a URL-safe version of user's login
		 *     @type string $display_name    The user's display name.
		 *     @type string $user_registered MySQL timestamp describing the moment when the user registered. Defaults to
		 *                                   the current UTC timestamp.
		 * }
		 * @param bool     $update   Whether the user is being updated rather than created.
		 * @param int|null $id       ID of the user to be updated, or NULL if the user is being created.
		 * @param array    $userdata The raw array of data passed to wp_insert_user().
		 */
		public function wp_pre_insert_user_data( $data, $update, $id, $userdata ) {
			// new users cannot be added
			if ( is_null( $id ) ) {
				// @todo notify admin
				return false;
			}

			$save_user = $this->get_saved_userdata( $id );

			// no data saved for this user when User Registration Lock was enabled
			if ( ! is_array( $save_user ) ) {
				// @todo notify admin
				return false;
			}

			// existing users cannot change id, login, email or registered date
			if ( isset( $data['user_login'] ) && $data['user_login'] != $save_user['user_login'] ) {
				// @todo notify admin
				return false;
			}
			if ( isset( $data['user_email'] ) && $data['user_email'] != $save_user['user_email'] ) {
				// @todo notify admin
				return false;
			}
			if ( isset( $data['user_registered'] ) && $data['user_registered'] != $save_user['user_registered'] ) {
				// @todo notify admin
				return false;
			}

			// we allow but notify of password changes
			if ( isset( $data['user_pass'] ) && $data['user_pass'] != $save_user['user_pass'] ) {
				// @todo notify admin
			}

			return $data;
		}

		/**
		 * Filter user metadata to prohibit lowering of use_ssl
		 *
		 * This filter could probably be bypassed, the only meta of interest available here is use_ssl,
		 * which is also available in update_user_metadata.
		 *
		 * @since   1.0.0
		 *
		 * @param array $meta {
		 *     Default meta values and keys for the user.
		 *
		 *     @type string   $nickname             The user's nickname. Default is the user's username.
		 *     @type string   $first_name           The user's first name.
		 *     @type string   $last_name            The user's last name.
		 *     @type string   $description          The user's description.
		 *     @type string   $rich_editing         Whether to enable the rich-editor for the user. Default 'true'.
		 *     @type string   $syntax_highlighting  Whether to enable the rich code editor for the user. Default 'true'.
		 *     @type string   $comment_shortcuts    Whether to enable keyboard shortcuts for the user. Default 'false'.
		 *     @type string   $admin_color          The color scheme for a user's admin screen. Default 'fresh'.
		 *     @type int|bool $use_ssl              Whether to force SSL on the user's admin area. 0|false if SSL
		 *                                          is not forced.
		 *     @type string   $show_admin_bar_front Whether to show the admin bar on the front end for the user.
		 *                                          Default 'true'.
		 *     @type string   $locale               User's locale. Default empty.
		 * }
		 * @param WP_User $user     User object.
		 * @param bool    $update   Whether the user is being updated rather than created.
		 * @param array   $userdata The raw array of data passed to wp_insert_user().
		 */
		public function insert_user_meta( $meta, $user, $update, $userdata ) {
			// new users cannot be added, so their usermeta won't be either
			if ( is_null( $update ) ) {
				// @todo notify admin
				return false;
			}

			$save_user = $this->get_saved_userdata( $user->ID );

			// no data saved for this user when User Registration Lock was enabled
			if ( ! is_array( $save_user ) ) {
				// @todo notify admin
				return false;
			}

			// cannot disable use_ssl
			if ( $save_user['meta_use_ssl'] && ! $meta['use_ssl'] ) {
				// @todo notify admin
				unset( $meta['use_ssl'] );
			}

			return $meta;
		}

		/**
		 * Filter user metadata to prohibit lowering of use_ssl, adding administrator
		 * capability or setting user_level to 10 (admin).
		 *
		 * @since   1.0.0
		 *
		 * @param null|bool $check      Whether to allow updating metadata for the given type.
		 * @param int       $object_id  ID of the object metadata is for.
		 * @param string    $meta_key   Metadata key.
		 * @param mixed     $meta_value Metadata value. Must be serializable if non-scalar.
		 * @param mixed     $prev_value Optional. Previous value to check before updating.
		 *                              If specified, only update existing metadata entries with
		 *                              this value. Otherwise, update all entries.
		 */
		public function update_user_metadata( $check, $object_id, $meta_key, $meta_value ) {

			$save_user = $this->get_saved_userdata( $object_id );

			// no data saved for this user when User Registration Lock was enabled
			if ( ! is_array( $save_user ) ) {
				// @todo notify admin
				return false;
			}

			// prohibit changing to administrator from non-admin in meta_capabilities,
			// and notify of all other changes.  doesn't handle a renamed 'administrator' role.
			$key = $GLOBALS['wpdb']->prefix . 'capabilities';
			if ( $meta_key == $key ) {
				if ( is_array( $meta_value ) && isset( $meta_value['administrator'] ) ) {

					// Don't really want to unserialize $save_user['meta_capabilities'],
					// so we'll just treat as a string and match literal "administrator"
					if ( ! (str_contains( $save_user['meta_capabilities'], '"administrator"' ) ||
						str_contains( $save_user['meta_capabilities'], "'administrator'" ) ) )
					{
						// @todo notify admin
						return false;
					}

				}
			}

			// prohibit user_level change from <10 to 10
			$key = $GLOBALS['wpdb']->prefix . 'user_level';
			if ( $meta_key == $key ) {
				// monitor for change of user_level
				if ( $meta_value != $save_user['meta_user_level'] ) {
					// @todo notify admin
				}
				// cannot increase user_level
				if ( 10 == $meta_value && 10 > $save_user['meta_user_level'] ) {
					// @todo notify admin
					return false;
				}
			}

			// prohibit unsetting use_ssl
			if ( $meta_key == 'use_ssl' ) {
				if ( $save_user['meta_use_ssl'] && ! $meta_value ) {
					// @todo notify admin
					return false;
				}
			}

			return $check;
		}

		/**
		 * Retrieve saved user data
		 *
		 * @since   1.0.0
		 * @access protected
		 *
		 * @param  mixed  $id The id of the user whose data to retrieve or an instance of WP_User.
		 * @return mixed    Array of saved user data if found, otherwise false.
		 */
		protected function get_saved_userdata( $id ) {
			global $wpdb;

			if ( $id instanceof WP_User ) {
				$id = $id->ID;
			}

			$save_user = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_save_users} WHERE user_id=%d", $id ), ARRAY_A );

			if ( is_array( $save_user ) ) {
				$save_user['user_roles'] = json_decode( $save_user['user_roles'] );
				$save_user['user_caps'] = json_decode( $save_user['user_caps'] );
				$save_user['user_allcaps'] = json_decode( $save_user['user_allcaps'] );
			} else {
				return false;
			}

			return $save_user;
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
			// @todo @fixme
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

	    /* Currently not needed
			wp_register_style( 'user-registration-lock-admin-css', plugin_dir_url( USER_REGISTRATION_LOCK_PLUGIN_BASE ) . 'admin/css/user-registration-lock.css', false, $this->get_version() );
			wp_enqueue_style( 'user-registration-lock-admin-css' );
	     */

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

			// remove the unprivileged role we created
			$this->remove_role();

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
