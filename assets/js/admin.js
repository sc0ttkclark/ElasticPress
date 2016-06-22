( function( $ ) {
	var $modules = $( document.getElementsByClassName( 'ep-modules' ) );
	var $outerWrap = $( 'js-ep-wrap' );

	var $progressBar = $( '.progress-bar' );
	var $syncStatusText = $( '.sync-status' );
	var $startSyncButton = $( '.start-sync' );
	var $resumeSyncButton = $( '.resume-sync' );
	var $pauseSyncButton = $( '.pause-sync' );
	var $cancelSyncButton = $( '.cancel-sync' );

	var syncStatus = 'sync';
	var processed = 0;
	var toProcess = 0;

	$modules.on( 'click', '.js-toggle-module', function( event ) {
		event.preventDefault();

		var module = event.target.getAttribute( 'data-module' );

		$.ajax( {
			method: 'post',
			url: ajaxurl,
			data: {
				action: 'ep_toggle_module',
				module: module,
				nonce: ep.nonce
			}
		} ).done( function(response) {
			$modules.find( '.ep-module-' + module ).toggleClass( 'module-active' );
		} );
	} );

	if ( ep.index_meta ) {
		processed = ep.index_meta.offset;
		toProcess = ep.index_meta['found_posts'];

		if ( 0 === toProcess ) {
			if ( response.data.start ) {
				// No posts to sync
				syncStatus = 'noposts';
				updateSyncDash();
			} else {
				// Sync finished
				syncStatus = 'finished';
				updateSyncDash();
			}
		} else {
			// We are mid sync
			if ( ep.auto_start_index ) {
				syncStatus = 'sync';
				sync();
			} else {
				syncStatus = 'pause';
				updateSyncDash();
			}
		}
	}

	function updateSyncDash() {
		if ( 0 === processed ) {
			$progressBar.css( { width: '1%' } );
		} else {
			var width = parseInt( processed ) / parseInt( toProcess ) * 100;
			$progressBar.css( { width: width + '%' } );
		}

		if ( 'sync' === syncStatus ) {
			$syncStatusText.text( 'Syncing ' + parseInt( processed ) + '/' + parseInt( toProcess ) );

			$syncStatusText.show();
			$progressBar.show();
			$pauseSyncButton.show();

			$cancelSyncButton.hide();
			$resumeSyncButton.hide();
			$startSyncButton.hide();
		} else if ( 'pause' === syncStatus ) {
			$syncStatusText.text( 'Syncing paused ' + parseInt( processed ) + '/' + parseInt( toProcess ) );

			$syncStatusText.show();
			$progressBar.show();
			$pauseSyncButton.hide();

			$cancelSyncButton.show();
			$resumeSyncButton.show();
			$startSyncButton.hide();
		} else if ( 'error' === syncStatus ) {
			$syncStatusText.text( 'An error occured while syncing' );
			$syncStatusText.show();
			$startSyncButton.show();
			$cancelSyncButton.hide();
			$resumeSyncButton.hide();
			$pauseSyncButton.hide();
			$progressBar.hide();
		} else if ( 'cancel' === syncStatus ) {
			$syncStatusText.hide();
			$progressBar.hide();
			$pauseSyncButton.hide();

			$cancelSyncButton.hide();
			$resumeSyncButton.hide();
			$startSyncButton.show();
		} else if ( 'finished' === syncStatus || 'noposts' === syncStatus ) {
			if ( 'noposts' === syncStatus ) {
				$syncStatusText.text( 'No posts to sync' );
			} else {
				$syncStatusText.text( 'Sync complete' );
			}

			$progressBar.hide();
			$pauseSyncButton.hide();
			$cancelSyncButton.hide();
			$resumeSyncButton.hide();
			$startSyncButton.show();

			setTimeout( function() {
				$syncStatusText.hide();
			}, 7000 );
		}
	}

	function cancelSync() {
		$.ajax( {
			method: 'post',
			url: ajaxurl,
			data: {
				action: 'ep_cancel_index',
				nonce: ep.nonce
			}
		} );
	}

	function sync() {
		$.ajax( {
			method: 'post',
			url: ajaxurl,
			data: {
				action: 'ep_index',
				nonce: ep.nonce
			}
		} ).done( function( response ) {
			if ( 'sync' !== syncStatus ) {
				return;
			}

			toProcess = response.data.found_posts;
			processed = response.data.offset;


			if ( 0 === response.data.found_posts ) {
				if ( response.data.start ) {
					// No posts to sync
					syncStatus = 'noposts';
					updateSyncDash();
				} else {
					// Sync finished
					syncStatus = 'finished';
					updateSyncDash();
				}
			} else {
				// We are starting a sync
				syncStatus = 'sync';
				updateSyncDash();

				//debugger;
				sync();
			}
		} ).error( function() {
			syncStatus = 'error';
			updateSyncDash();

			cancelSync();
		});
	}

	$startSyncButton.on( 'click', function() {
		syncStatus = 'sync';

		sync();
	} );

	$pauseSyncButton.on( 'click', function() {
		syncStatus = 'pause';

		updateSyncDash();
	} );

	$resumeSyncButton.on( 'click', function() {
		syncStatus = 'sync';

		sync();
	} );

	$cancelSyncButton.on( 'click', function() {
		syncStatus = 'cancel';

		updateSyncDash();

		cancelSync();
	} );
	
} )( jQuery );
