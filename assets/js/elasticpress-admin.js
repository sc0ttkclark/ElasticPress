(function ($) {

	var elasticPress = {
		pauseIndexing       : false,
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

		// Side drop down for newtwrok.
		site_selector       : $('#ep_site_select'),

		status            : $('#progressstats'),
		bar               : $('#progressbar'),
		index_progress_box: $('#indexprogresss'),

		/**
		 * Update the progress bar every 3 seconds
		 */
		performIndex: function (resetBar) {

			if (this.pauseIndexing) {
				return;
			}

			$(this.pause_index_button).val(ep.running_index_text).removeClass('button-primary').attr('disabled', true);
			$(this.keep_active_checkbox).attr('disabled', true);

			$(this.restart_index_button).removeClass('hidden');
			$(this.pause_index_button).addClass('hidden');

			this.showProgressBar();
			this.processIndex();

		},
		/**
		 * Send request to server and process response
		 */
		processIndex: function (bar, button, stopBtn, restartBtn, status) {
			var self = this;
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

						// Handle returned error appropriately.
						if ('undefined' === typeof response.responseJSON || 'undefined' === typeof response.responseJSON.data) {

							self.status.text(ep.failed_text);
							$(self.run_index_button).val(ep.index_complete_text).attr('disabled', false);
							$(self.pause_index_button).addClass('hidden');
							$(self.restart_index_button).addClass('hidden');
							self.index_progress_box.fadeOut('slow');

						} else {

							var sitesCompletedText = '';

							if (0 === response.responseJSON.data.is_network) {

								self.epTotalToIndex = response.responseJSON.data.ep_posts_total;
								self.epTotalIndexed = response.responseJSON.data.ep_posts_synced;

							} else {

								if (self.epSitesRemaining !== response.responseJSON.data.ep_sites_remaining) {

									self.epSitesRemaining = response.responseJSON.data.ep_sites_remaining;
									self.epTotalToIndex += response.responseJSON.data.ep_posts_total;
									self.epSitesCompleted++;

								}

								sitesCompletedText = self.epSitesCompleted + ep.sites;
								self.epTotalIndexed += response.responseJSON.data.ep_current_synced;

							}

							var progress = parseFloat(elasticPress.epTotalIndexed) / parseFloat(elasticPress.epTotalToIndex);


							console.log(progress);
							self.bar.progressbar(
								{
									value: progress * 100
								}
							);

							self.status.text(self.epTotalIndexed + '/' + self.epTotalToIndex + ' ' + ep.items_indexed + sitesCompletedText);

							if (1 == response.responseJSON.data.ep_sync_complete) { //indexing complete

								self.bar.progressbar(
									{
										value: 100
									}
								);

								setTimeout(function () {

									self.index_progress_box.fadeOut('slow');
									self.status.html(ep.complete_text);
									self.run_index_button.val(ep.index_complete_text).attr('disabled', false);
									self.pause_index_button.addClass('hidden');
									self.restart_index_button.addClass('hidden');
									self.resetIndex();

								}, 500);

							} else {

								self.performIndex(false );

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

			var self = this;
			var paused = this.pause_index_button.data('paused');

			if (paused === 'enabled') {

				self.pause_index_button.val(ep.index_pause_text).data('paused', 'disabled');

				self.pauseIndexing = false;

				self.performIndex( false );

			} else {

				var data = {
					action     : 'ep_pause_index',
					keep_active: keepActiveCheckbox.is(':checked'),
					nonce      : ep.pause_nonce
				};

				// call the ajax request to re-enable ElasticPress
				$.ajax(
					{
						url     : ajaxurl,
						type    : 'POST',
						data    : data,
						complete: function (response) {

							self.pause_index_button.val(ep.index_resume_text).data('paused', 'enabled');
							self.run_index_button.val(ep.index_paused_text).attr('disabled', true);
							self.restart_index_button.removeClass('hidden');

							self.pauseIndexing = true;

						}
					}
				);

			}
		},

		/**
		 * Allow indexing to be restarted.
		 */
		restartIndex: function ( ) {
			var self = this;
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

						self.resetIndex();

						self.restart_index_button.addClass('hidden');
						self.pause_index_button.val(ep.index_pause_text).data('paused', 'disabled').addClass('hidden');
						self.run_index_button.val(ep.index_complete_text).attr('disabled', false);
						self.keep_active_checkbox.attr('disabled', false);

						self.status.text('');
						self.index_progress_box.fadeOut('slow');

						elasticPress.pauseIndexing = false;

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

			var progress = parseFloat(ep.synced_posts) / parseFloat(ep.total_posts);

			this.bar.progressbar(
				{
					value: progress * 100
				}
			);

			this.status.text(ep.synced_posts + '/' + ep.total_posts + ' ' + ep.items_indexed);
		},

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

		addEventListeners: function () {
			var self = this;
			/**
			 * Process indexing operation
			 */
			self.run_index_button.on('click', function (event) {

				event.preventDefault();

				elasticPress.resetIndex();

				self.status.text(ep.running_index_text);
				elasticPress.performIndex(true ); //start the polling
			});
			/**
			 * Process the pause index operation
			 */
			self.pause_index_button.on('click', function (event) {
				event.preventDefault();
				elasticPress.pauseIndex();
			});
			/**
			 * Process the restart index operation
			 */
			self.restart_index_button.on('click', function (event) {
				event.preventDefault();
				elasticPress.restartIndex();
			});
			self.site_selector.on('change', this.changeSite);
		},

		init: function () {
			if (1 == ep.index_running && 1 != ep.paused) {
				elasticPress.performIndex(true);
			}

			if (1 == ep.index_running && 1 == ep.paused) {
				this.showProgressBar();
			}

			this.addEventListeners();
		}

	};

	elasticPress.init();
})(jQuery);
