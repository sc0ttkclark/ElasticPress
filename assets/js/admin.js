( function( $ ) {
	var $modules = $( document.getElementsByClassName( 'ep-modules' ) );

	$modules.on( 'click', '.js-toggle-module', function( event ) {
		event.preventDefault();

		var module = event.target.getAttribute( 'data-module' );

		$.ajax( {
			method: 'post',
			url: ajaxurl,
			data: {
				action: 'toggle_module',
				module: module,
				nonce: ep.nonce
			}
		} ).done( function() {
			$modules.find( '.ep-module-' + module ).toggleClass( 'module-active' );
		} );
	} );
} )( jQuery );
