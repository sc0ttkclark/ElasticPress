(function ($) {

	var elasticPress = {
		pauseIndexing       : false,
		indexing			: false,
		epSitesRemaining    : 0,
		epTotalToIndex      : 0,
		epTotalIndexed      : 0,
		epSitesCompleted    : 0,

		// The run index button
		run_index_button    : $('#ep_run_index'),

		// The pause index button
		pause_index_button  : $('#ep_pause_index'),

		// The restart index button
		restart_index_button: $('#ep_restart_index'),

		// The keep active Elasticsearch integration checkbox.
		keep_active_checkbox: $('#ep_keep_active'),

		// Side drop down for network
		site_selector       : $('#ep_site_select'),

		// Site drop down for network post type screen
		post_type_site : $('#ep_site_select_post_type'),

		// Progress bar status box
		status            : $('#progressstats'),

		// Progress bar status box
		progress_percent  : $('#progresspercent'),

		// Progress bar
		bar               : $('#progressbar'),

		// Progress bar container
		index_progress_box: $('#indexprogresss'),

		// Success notice box
		notice: $('.ep-notice'),

		/**
		 * Update the progress bar every 3 seconds
		 */
		performIndex: function () {

			if (this.pauseIndexing) {
				return;
			}

			$(this.run_index_button).val(ep.running_index_text).attr('disabled', true);
			$(this.keep_active_checkbox).attr('disabled', true);

			$(this.restart_index_button).addClass('hidden');
			$(this.pause_index_button).removeClass('hidden');

			if( ! this.indexing ){
				this.showProgressBar();
			}
			this.processIndex();

		},
		/**
		 * Send request to server and process response
		 */
		processIndex: function (bar, button, stopBtn, restartBtn, status) {
			var SELF = this;
			
			var data = {
				action: 'ep_launch_index',
				nonce : ep.nonce
			};

			//call the ajax
			$.ajax(
				{
					url     : ajaxurl,
					type    : 'POST',
					data    : data,
					complete: function (response) {
						var sitesCompletedText = '';

						// Handle returned error appropriately.
						if ('undefined' === typeof response.responseJSON || 'undefined' === typeof response.responseJSON.data) {

							SELF.status.text(ep.failed_text);
							$(SELF.run_index_button).val(ep.index_complete_text).attr('disabled', false);
							$(SELF.pause_index_button).addClass('hidden');
							$(SELF.restart_index_button).addClass('hidden');
							SELF.index_progress_box.fadeOut('slow');

						} else {
							if (0 === response.responseJSON.data.is_network) {

								SELF.epTotalToIndex = response.responseJSON.data.ep_posts_total;
								SELF.epTotalIndexed = response.responseJSON.data.ep_posts_synced;

							} else {

								if (SELF.epSitesRemaining !== response.responseJSON.data.ep_sites_remaining) {

									SELF.epSitesRemaining = response.responseJSON.data.ep_sites_remaining;
									SELF.epTotalToIndex += response.responseJSON.data.ep_posts_total;
									SELF.epSitesCompleted++;

								}

								sitesCompletedText = SELF.epSitesCompleted + ep.sites;
								SELF.epTotalIndexed += response.responseJSON.data.ep_current_synced;

							}

							var progress = Math.ceil( ( parseFloat(SELF.epTotalIndexed) / parseFloat(SELF.epTotalToIndex) ) * 100 );
							SELF.indexing = true;

							SELF.bar.progressbar(
								{
									value:  progress
								}
							);

							SELF.status.text(SELF.epTotalIndexed + '/' + SELF.epTotalToIndex + ' ' + ep.items_indexed + sitesCompletedText);
							SELF.progress_percent.text( progress + '%' );


							if (1 == response.responseJSON.data.ep_sync_complete) { //indexing complete

								SELF.bar.progressbar(
									{
										value: 100
									}
								);
								SELF.progress_percent.text( 100 + '%' );

								SELF.notice.removeClass('hidden').find('p').text( ep.complete_text );
								$('.ep-error').remove();
								$('#ep_activate').prop( 'disabled', false );

								setTimeout(function () {

									SELF.index_progress_box.fadeOut('slow');
									SELF.status.html(ep.complete_text);
									SELF.run_index_button.val(ep.index_complete_text).attr('disabled', false);
									SELF.pause_index_button.addClass('hidden');
									SELF.restart_index_button.addClass('hidden');
									SELF.keep_active_checkbox.attr('disabled', false);

									SELF.resetIndex();
									SELF.indexing = false;


								}, 1000 );

							} else {

								SELF.performIndex( );

							}
						}
					}
				}
			);

		},

		/**
		 * Set our variable to pause indexing
		 */
		pauseIndex: function ( ) {

			var SELF = this;
			var paused = this.pause_index_button.data('paused');

			if (paused === 'enabled') {

				SELF.pause_index_button.val(ep.index_pause_text).data('paused', 'disabled');

				SELF.pauseIndexing = false;

				SELF.performIndex(  );

			} else {

				var data = {
					action     : 'ep_pause_index',
					keep_active: SELF.keep_active_checkbox.is(':checked'),
					nonce      : ep.pause_nonce
				};

				// call the ajax request to re-enable ElasticPress
				$.ajax(
					{
						url     : ajaxurl,
						type    : 'POST',
						data    : data,
						complete: function (response) {

							SELF.pause_index_button.val(ep.index_resume_text).data('paused', 'enabled');
							SELF.run_index_button.val(ep.index_paused_text).attr('disabled', true);
							SELF.restart_index_button.removeClass('hidden');

							SELF.pauseIndexing = true;

						}
					}
				);

			}
		},

		/**
		 * Allow indexing to be restarted.
		 */
		restartIndex: function ( ) {
			var SELF = this;
			var data = {
				action: 'ep_restart_index',
				nonce : ep.restart_nonce
			};

			// call the ajax request to un-pause indexing
			$.ajax(
				{
					url     : ajaxurl,
					type    : 'POST',
					data    : data,
					complete: function (response) {

						SELF.resetIndex();

						SELF.restart_index_button.addClass('hidden');
						SELF.pause_index_button.val(ep.index_pause_text).data('paused', 'disabled').addClass('hidden');
						SELF.run_index_button.val(ep.index_complete_text).attr('disabled', false);
						SELF.keep_active_checkbox.attr('disabled', false);

						SELF.status.text('');
						SELF.index_progress_box.fadeOut('slow');

						SELF.pauseIndexing = false;
						SELF.indexing = false;


					}
				}
			);

		},
		// Resets index counts
		resetIndex  : function () {

			this.epSitesRemaining = 0;
			this.epTotalToIndex = 0;
			this.epTotalIndexed = 0;

		},

		/**
		 * Show the progress bar when indexing is paused.
		 */
		showProgressBar: function () {

			this.index_progress_box.show();

			var progress = Math.ceil( ( parseFloat(ep.synced_posts) / parseFloat(ep.total_posts) ) * 100 );

			this.bar.progressbar(
				{
					value:  progress
				}
			);

			this.status.text(ep.synced_posts + '/' + ep.total_posts + ' ' + ep.items_indexed);
			this.progress_percent.text( progress + '%' );
		},

		/**
		 * Toggle between site stats on network screen
		 *
		 * @param event
		 */
		changeSite: function (event) {
			event.preventDefault();

			var data = {
				action: 'ep_get_site_stats',
				nonce : ep.stats_nonce,
				site  : elasticPress.site_selector.val()
			};

			//call the ajax
			$.ajax(
				{
					url     : ajaxurl,
					type    : 'POST',
					data    : data,
					complete: function (response) {

						$('#ep_site_stats').html(response.responseJSON.data);

					}

				}
			);
		},

		/**
		 * Toggle between site stats on network screen
		 *
		 * @param event
		 */
		changePostTypeSite: function (event) {
			event.preventDefault();

			console.log(  elasticPress.post_type_site.val() )

			var data = {
				action: 'ep_get_site_post_types',
				nonce : ep.post_type_nonce,
				site  : elasticPress.post_type_site.val()
			};

			//call the ajax
			$.ajax(
				{
					url     : ajaxurl,
					type    : 'POST',
					data    : data,
					complete: function (response) {

						console.log( response );

					}

				}
			);

		},

		addEventListeners: function () {
			var SELF = this;
			/**
			 * Process indexing operation
			 */
			SELF.run_index_button.on('click', function (event) {

				event.preventDefault();

				elasticPress.resetIndex();

				SELF.status.text(ep.running_index_text);
				elasticPress.performIndex( ); //start the polling
			});
			/**
			 * Process the pause index operation
			 */
			SELF.pause_index_button.on('click', function (event) {
				event.preventDefault();
				elasticPress.pauseIndex();
			});
			/**
			 * Process the restart index operation
			 */
			SELF.restart_index_button.on('click', function (event) {
				event.preventDefault();
				elasticPress.restartIndex();
			});
			SELF.site_selector.on('change', this.changeSite);

			// Process site selector on post type tab
			SELF.post_type_site.on('change', this.changePostTypeSite);
		},

		/**
		 * Initialize ElasticPress UI
		 */
		init: function () {
			if (1 == ep.index_running && 1 != ep.paused) {
				elasticPress.performIndex( );
			}

			if (1 == ep.index_running && 1 == ep.paused) {
				this.showProgressBar();
			}

			this.addEventListeners();
		}

	};

	elasticPress.init();
})(jQuery);
