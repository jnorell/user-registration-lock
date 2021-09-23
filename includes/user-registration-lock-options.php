<?php
/**
 * The User Registration Lock Options plugin class.
 *
 * @link       https://github.com/jnorell/
 * @since      1.0.0
 * @package    User_Registration_Lock
 * @subpackage User_Registration_Lock/includes
 */

// Prevent direct access to this file.
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( !class_exists( 'User_Registration_Lock_Options' ) ) {

	/**
	 * The User Registration Lock Options plugin class.
	 *
	 * Emulates wordpress option handling functions, storing all managed options
	 * within a single wp_options entry.
	 *
	 * @package    User_Registration_Lock
	 * @subpackage User_Registration_Lock/includes
	 * @author     Jesse Norell <jesse@kci.net>
	 */
	class User_Registration_Lock_Options {

		/**
		 * The name of the wp_option used to be managed.
		 *
		 * @since    1.0.0
		 * @access   protected
		 * @var      string    $option_name    The name of the wp_option to be managed by this class.
		 */
		protected static $option_name;

		/**
		 * The default options to be used.
		 *
		 * @since    1.0.0
		 * @access   protected
		 * @var      array    $default_options    The default options to be used by this class.
		 */
		protected static $default_options;

		/**
		 * Set the name of the wp_option to be managed.
		 *
		 * @since     1.0.0
		 * @param     string   $option_name    The name of the wp_option to be managed by this class.
		 * @return    boolean  True if option name was set, false if not.
		 */
		public static function set_option_name( $option_name ) {
			if ( is_string( $option_name ) ) {
				self::$option_name = $option_name;
				return true;
			}

			return false;
		}

		/**
		 * Set the default options to be used.
		 *
		 * @since     1.0.0
		 * @param     array    The default options to be used by this class.
		 * @return    boolean  True if default options were set, false if not.
		 */
		public static function set_default_options( $default_options ) {
			if ( is_array( $default_options ) ) {
				self::$default_options = $default_options;
				return true;
			}

			return false;
		}

		/**
		 * Retrieve wp_option managed by this class and optionally fill default values.
		 *
		 * @since     1.0.0
		 * @param     boolean  $defaults    True to merge existing options with default options, false to return only existing options.
		 * @return    mixed    An array of options managed by this class, merged with default options if requested, or false on failure.
		 */
		public static function get_options( $defaults=false ) {

			if ( empty( self::$option_name ) ) {
				return false;
			}

			$options = get_option( self::$option_name );

			if ( ! $defaults ) {
				return is_array( $options ) ? $options : false;
			}

			if ( ! is_array( $options ) ) {
				return self::$default_options;
			}

			return array_merge( self::$default_options, $options );
		}

		/**
		 * Retrieve a single option within the wp_option array managed by this class.
		 *
		 * @since     1.0.0
		 * @param     string   $name    The name of the option to return.
		 * @return    mixed    The named option from the managed options or null.
		 */
		public static function get_option( $name ) {
			$options = self::get_options();

			return isset( $options[$name] ) ? $options[$name] : null;
		}

		/**
		 * Set an option within the wp_option array managed by this class.
		 *
		 * @since     1.0.0
		 * @param     string    $name    The name of the option to set.
		 * @param     mixed     $value   The value of the option to set.
		 * @return    boolean   True if option value has changed, false if not or if update failed.
		 */
		public static function update_option( $name, $value ) {
			$options = self::get_options();

			if ( isset( $options[$name] ) && $options[$name] === $value ) {
				return false;
			}

			$options[$name] = $value;

			return update_option( self::$option_name, $options );
		}

		/**
		 * Add an option within the wp_option array managed by this class.
		 *
		 * @todo      should probably make this not overwrite existing options,
		 *            as that is the wordpress add_option behavior.
		 *
		 * @since     1.0.0
		 * @param     string    $name    The name of the option to set.
		 * @param     mixed     $value   The value of the option to set.
		 * @return    boolean   True if option value has changed, false if not or if update failed.
		 */
		public static function add_option( $name, $value ) {
			return self::update_option( $name, $value );
		}

		/**
		 * Remove an option from within the wp_option array managed by this class.
		 *
		 * @since     1.0.0
		 * @param     string    $name    The name of the option to delete.
		 * @return    boolean   True if option was deleted, false otherwise.
		 */
		public static function delete_option( $name ) {
			$options = self::get_options();

			if ( isset( $options[$name] ) ) {
				unset( $options[$name] );
				return update_option( self::$option_name, $options );
			}

			return false;
		}

		/**
		 * Delete the wp_option managed by this class.
		 *
		 * @since     1.0.0
		 * @return    boolean   True if option was deleted, false otherwise.
		 */
		public static function delete_options() {
			return delete_option( self::$option_name );
		}

	} // end class User_Registration_Lock_Options

} // !class_exists
