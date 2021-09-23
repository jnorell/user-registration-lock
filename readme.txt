=== User Registration Lock ===
Contributors: jnorell
Tags: security, user-registration
Requires at least: 4.8
Tested up to: 5.8.1
Stable tag: trunk
Requires PHP: 5.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Ensures new user registration stays disabled and monitors changes to existing users.

== Description ==

User Registration Lock disables new user registration and ensures it stays disabled.

Sure, you can disable new user registration in the Wordpress General settings - but a common tactic of malware is to re-enable new user registration and set the default role for new users to `administrator`, thereby allowing takeover of your site.  This plugin was written to prevent that particular tactic, and adds some monitoring to ensure things haven't been changed under the hood.

= Features =

*   Disables new user registration.
*   Checks to ensure registration stays disabled.
*   Monitors users and settings for changes.

= Read Before Installing =

This plugin is not appropriate for every site.  Many sites allow user registration, eg. to participate in forums or create an e-commerce account - if your site works in this manner, do not use this plugin.

There are many sites which do not allow user registration, and have a fixed number of user accounts which shouldn't change often, if ever - this plugin is for those sites.

= How To Use =

This plugin has no settings, simply install and activate.  If you need to add users, change user roles, etc., deactivate the User Registration Lock plugin first, make your changes, then activate the plugin again.

Yes, this is a minor nuisance; that is the price of the increased security gained by preventing user registrations.

= Bugs, Patches & Feature Requests =

Please submit any security issues found and they will be addressed.

You can submit bug reports or feature requests in the [GitHub issue tracker].  Patches are preferred as pull requests.

[GitHub issue tracker]: https://github.com/jnorell/user-registration-lock/issues

== Installation ==

= WordPress Admin (coming soon) =

Go to the 'Plugins' menu in WordPress, click 'Add New', search for 'User Registration Lock', and click 'Install Now' for the 'User Registration Lock' plugin.  Once installed, click 'Activate'.

= Plugin Upload =

An alternative to installing via the WordPress admin page is to upload the plugin to the WordPress installation on your web server.

1. Upload the plugin (the entire `user-registration-lock` directory and everything in it) to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.

== Known Bugs & Compatibility ==

You can check bug reports in the [GitHub issue tracker] and check the [Changelog] for fixes.

[Changelog]: https://wordpress.org/plugins/user-registration-lock/#developers

1. Notification of user account changes are not sent.  This is coming soon.

== Frequently Asked Questions ==

= How can I create a new user if user registrations are locked? =

Simply deactivate the User Registration Lock plugin, add the user, then active the plugin again.

= I can't find any settings for the plugin, are there any? =

No.  The plugin does disable the user registration and default role settings in General options, but does not have any settings of it's own.

== Changelog ==

= 1.0.0 =

Release Date:  Sep 23, 2021

* Initial plugin version.
* Feature: new user registration is disabled.
* Feature: changes to users are monitored.
* Anti-Feature: notifications for changes are not sent.

