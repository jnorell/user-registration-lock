<?php

/**
 * User Registration Lock
 *
 * @link              https://github.com/jnorell/
 * @author            Jesse Norell <jesse@kci.net>
 * @copyright         2021 Jesse Norell
 * @license           GPL-2.0-or-later
 * @since             1.0.0
 * @package           User_Registration_Lock
 *
 * @wordpress-plugin
 * Plugin Name:       User Registration Lock
 * Plugin URI:        https://github.com/jnorell/user-registration-lock/
 * Description:       New User Registration stays disabled and existing users are monitored.
 * Version:           1.0.0
 * Author:            Jesse Norell
 * Author URI:        https://github.com/jnorell/
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       user-registration-lock
 * Domain Path:       /languages
 * Requires at least: 4.8
 * Requires PHP:      5.4
 *
 * @todo              Add notification class to email and log monitored changes.
 */

/*
User Registration Lock is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

User Registration Lock is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with User Registration Lock. If not, see //www.gnu.org/licenses/gpl-2.0.txt.
 */

// Prevent direct access to this file.
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * Current plugin version.
 */
if ( ! defined( 'USER_REGISTRATION_LOCK_VERSION' ) ) {
	define( 'USER_REGISTRATION_LOCK_VERSION', '1.0.0' );
}

/**
 * Plugin basename.
 */
if ( ! defined( 'USER_REGISTRATION_LOCK_PLUGIN_BASE' ) ) {
	define( 'USER_REGISTRATION_LOCK_PLUGIN_BASE', plugin_basename( __FILE__ ) );
}

if ( ! class_exists( 'User_Registration_Lock' ) ) {
	require_once( 'includes/user-registration-lock.php' );
}

/**
 * Plugin init hook.
 */
if ( ! function_exists( 'user_registration_lock_init' ) ) {
	function user_registration_lock_init() {
		$user_registration_lock = new User_Registration_Lock;
		$user_registration_lock->init();
	}
	add_action( 'init', 'user_registration_lock_init' );
}

/**
 * Plugin activation hook.
 */
if ( ! function_exists( 'user_registration_lock_activate' ) ) {
	function user_registration_lock_activate() {
		$user_registration_lock = new User_Registration_Lock;
		$user_registration_lock->activate();
	}
	register_activation_hook( __FILE__, 'user_registration_lock_activate' );
}

/**
 * Plugin deactivation hook.
 */
if ( ! function_exists( 'user_registration_lock_deactivate' ) ) {
	function user_registration_lock_deactivate() {
		$user_registration_lock = new User_Registration_Lock;
		$user_registration_lock->deactivate();
	}
	register_deactivation_hook( __FILE__, 'user_registration_lock_deactivate' );
}

/**
 * Plugin uninstall hook.
 */
if ( ! function_exists( 'user_registration_lock_uninstall' ) ) {
	function user_registration_lock_uninstall() {
		$user_registration_lock = new User_Registration_Lock;
		$user_registration_lock->uninstall();
	}
	register_uninstall_hook( __FILE__, 'user_registration_lock_uninstall' );
}

