/**
 * User Registration Lock admin js
 */

(function( $ ) {
	'use strict';

	$(function() {
		// ensure user registration checkbox is unchecked
		$( '#users_can_register' ).prop( 'checked', false );
		// then disable it
		$( '#users_can_register' ).attr( 'disabled', true );

		// replace the 'Anyone can register' message 
		var checkbox_found = false;
		$( '#users_can_register' ).parent().contents().each(function() {
			if (this.nodeType == 1)
				checkbox_found = true;
			if (checkbox_found && this.nodeType == 3)
				this.data = user_registration_lock_strings.str_registration_disabled;
		});

		// ensure default role is set to subscriber
		$( '#default_role' ).val( 'subscriber' ).prop( 'selected', true );
		// then disable it
		$( '#default_role' ).attr( 'disabled', true );

	});

})( jQuery );
