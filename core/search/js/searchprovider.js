/*
 * @copyright Copyright (c) 2014 Jörn Friedrich Dreyer <jfd@owncloud.com>
 *
 * @author Jörn Friedrich Dreyer <jfd@owncloud.com>
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
(function() {
	/**
	 * @class OCA.Search.Core
	 * @classdesc
	 *
	 * The Search class manages a search queries and their results
	 *
	 * @param $searchBox container element with existing markup for the #searchbox form
	 * @param $searchResults container element for results und status message
	 */
	var Search = function($searchBox, $searchResults) {
		this.initialize($searchBox, $searchResults);
	};
	/**
	 * @memberof OC
	 */
	Search.prototype = {
		/**
		 * Initialize the search box
		 *
		 * @param $searchBox container element with existing markup for the #searchbox form
		 * @param $searchResults container element for results und status message
		 * @private
		 */
		initialize: function($searchBox, $searchResults) {
			var self = this;

			/**
			 * contains closures that are called to filter the current content
			 */
			var filters = {};
			this.setFilter = function(type, filter) {
				filters[type] = filter;
			};
			this.hasFilter = function(type) {
				return typeof filters[type] !== 'undefined';
			};
			this.getFilter = function(type) {
				return filters[type];
			};

			/**
			 * contains closures that are called to render search results
			 */
			var renderers = {};
			this.setRenderer = function(type, renderer) {
				renderers[type] = renderer;
			};
			this.hasRenderer = function(type) {
				return typeof renderers[type] !== 'undefined';
			};
			this.getRenderer = function(type) {
				return renderers[type];
			};

			/**
			 * contains closures that are called when a search result has been clicked
			 */
			var handlers = {};
			this.setHandler = function(type, handler) {
				handlers[type] = handler;
			};
			this.hasHandler = function(type) {
				return typeof handlers[type] !== 'undefined';
			};
			this.getHandler = function(type) {
				return handlers[type];
			};

			var currentResult = -1;
			var lastQuery = '';
			var lastInApps = [];
			var lastPage = 0;
			var lastSize = 30;
			var lastResults = [];
			var timeoutID = null;

			this.getLastQuery = function() {
				return lastQuery;
			};

			/**
			 * Do a search query and display the results
			 * @param {string} query the search query
			 * @param inApps
			 * @param page
			 * @param size
			 */
			this.search = function(query, inApps, page, size) {
				if (query) {
					if (typeof page !== 'number') {
						page = 1;
					}
					if (typeof size !== 'number') {
						size = 30;
					}
					if (typeof inApps !== 'object') {
						var currentApp = getCurrentApp();
						if (currentApp) {
							inApps = [currentApp];
						} else {
							inApps = [];
						}
					}
					// prevent double pages
					if (
						$searchResults &&
						query === lastQuery &&
						page === lastPage &&
						size === lastSize
					) {
						return;
					}
					window.clearTimeout(timeoutID);
					timeoutID = window.setTimeout(function() {
						lastQuery = query;
						lastInApps = inApps;
						lastPage = page;
						lastSize = size;

						//show spinner
						$searchResults.removeClass('hidden');
						$status.addClass('status emptycontent');
						$status.html('<div class="icon-loading"></div><h2>' + t('core', 'Searching other places') + '</h2>');

						// do the actual search query
						$.getJSON(
							OC.generateUrl('core/search'),
							{
								query: query,
								inApps: inApps,
								page: page,
								size: size
							},
							function(results) {
								lastResults = results;
								if (page === 1) {
									showResults(results);
								} else {
									addResults(results);
								}
							}
						);
					}, 500);
				}
			};

			//TODO should be a core method, see https://github.com/owncloud/core/issues/12557
			function getCurrentApp() {
				var content = document.getElementById('content');
				if (content) {
					var classList = document.getElementById('content').className.split(/\s+/);
					for (var i = 0; i < classList.length; i++) {
						if (classList[i].indexOf('app-') === 0) {
							return classList[i].substr(4);
						}
					}
				}
				return false;
			}

			var $status = $searchResults.find('#status');
			// summaryAndStatusHeight is a constant
			var summaryAndStatusHeight = 118;

			function isStatusOffScreen() {
				return (
					$searchResults.position() &&
					$searchResults.position().top + summaryAndStatusHeight >
						window.innerHeight
				);
			}

			function placeStatus() {
				if (isStatusOffScreen()) {
					$status.addClass('fixed');
				} else {
					$status.removeClass('fixed');
				}
			}

			function showResults(results) {
				lastResults = results;
				$searchResults.find('tr.result').remove();
				$searchResults.removeClass('hidden');
				addResults(results);
			}

			function addResults(results) {
				var $template = $searchResults.find('tr.template');
				jQuery.each(results, function(i, result) {
					var $row = $template.clone();
					$row.removeClass('template');
					$row.addClass('result');

					$row.data('result', result);

					// generic results only have four attributes
					$row.find('td.info div.name').text(result.name);
					$row.find('td.info a').attr('href', result.link);

					/**
					 * Give plugins the ability to customize the search results. see result.js for examples
					 */
					if (self.hasRenderer(result.type)) {
						$row = self.getRenderer(result.type)($row, result);
					} else {
						// for backward compatibility add text div
						$row.find('td.info div.name').addClass('result');
						$row.find('td.result div.name').after('<div class="text"></div>');
						$row.find('td.result div.text').text(result.name);
						if (OC.search.customResults && OC.search.customResults[result.type]) {
							OC.search.customResults[result.type]($row, result);
						}
					}
					if ($row) {
						$searchResults.find('tbody').append($row);
					}
				});

				var count = $searchResults.find('tr.result').length;
				$status.data('count', count);
	
				if (count === 0) {
					$status.addClass('emptycontent').removeClass('status');
					$status.html('');
					$status.append($('<div>').addClass('icon-search'));
					var error = t('core', 'No search results in other folders for {tag}{filter}{endtag}', { filter: lastQuery });
					$status.append($('<h2>').html(error.replace('{tag}', '<strong>').replace('{endtag}', '</strong>')));
				} else {
					$status.removeClass('emptycontent').addClass('status summary');
					$status.text(n('core','{count} search result in another folder','{count} search results in other folders', count,{ count: count }));
					$status.html('<span class="info">' + n( 'core', '{count} search result in another folder', '{count} search results in other folders', count, { count: count } ) + '</span>');
				}
			}

			function renderCurrent() {
				var result = $searchResults.find('tr.result')[currentResult];
				if (result) {
					var $result = $(result);
					var currentOffset = $(window).scrollTop();
					$(window).animate(
						{
							// Scrolling to the top of the new result
							scrollTop:
								currentOffset +
								$result.offset().top -
								$result.height() * 2
						},
						{
							duration: 100
						}
					);
					$searchResults.find('tr.result.current').removeClass('current');
					$result.addClass('current');
				}
			}
			this.hideResults = function() {
				$searchResults.addClass('hidden');
				$searchResults.find('tr.result').remove();
				lastQuery = false;
			};

			this.clear = function() {
				self.hideResults();
				if (self.hasFilter(getCurrentApp())) {
					self.getFilter(getCurrentApp())('');
				}
				$searchBox.val('');
				$searchBox.blur();
			};

			/**
			 * Event handler for when scrolling the list container.
			 * This appends/renders the next page of entries when reaching the bottom.
			 */
			function onScroll() {
				if (
					$searchResults &&
					lastQuery !== false &&
					lastResults.length > 0
				) {
					if ($(window).scrollTop() + $(window).height() > $searchResults.height() - 300) {
  						self.search(lastQuery, lastInApps, lastPage + 1);
					}
					placeStatus();
				}
			}

			$(window).on('scroll', _.bind(onScroll, this)); // For desktop browser
			$("body").on('scroll', _.bind(onScroll, this)); // For mobile browser

			/**
			 * scrolls the search results to the top
			 */
			function scrollToResults() {
				setTimeout(function() {
					if (isStatusOffScreen()) {
						var newScrollTop = $(window).prop('scrollHeight') - $searchResults.height();
						console.log('scrolling to ' + newScrollTop);
						$(window).animate(
							{
								scrollTop: newScrollTop
							},
							{
								duration: 100,
								complete: function() {
									scrollToResults();
								}
							}
						);
					}
				}, 150);
			}

			$searchBox.keyup(function(event) {
				if (event.keyCode === 13) {
					//enter
					if (currentResult > -1) {
						var result = $searchResults.find('tr.result a')[currentResult];
						window.location = $(result).attr('href');
					}
				} else if (event.keyCode === 38) {
					//up
					if (currentResult > 0) {
						currentResult--;
						renderCurrent();
					}
				} else if (event.keyCode === 40) {
					//down
					if (lastResults.length > currentResult + 1) {
						currentResult++;
						renderCurrent();
					}
				}
			});

			$searchResults.on('click', 'tr.result', function(event) {
				var $row = $(this);
				var item = $row.data('result');
				if (self.hasHandler(item.type)) {
					var result = self.getHandler(item.type)($row, item, event);
					$searchBox.val('');
					if (self.hasFilter(getCurrentApp())) {
						self.getFilter(getCurrentApp())('');
					}
					self.hideResults();
					return result;
				}
			});
			$searchResults.on('click', '#status', function(event) {
				event.preventDefault();
				scrollToResults();
				return false;
			});
			placeStatus();

			OC.Plugins.attach('OCA.Search.Core', this);

			// Finally use default Search registration
			return new OCA.Search(
				// Search handler
				function(query) {
					if (lastQuery !== query) {
						currentResult = -1;
						if (query.length > 2) {
							self.search(query);
						} else {
							self.hideResults();
						}
						if (self.hasFilter(getCurrentApp())) {
							self.getFilter(getCurrentApp())(query);
						}
					}
				},
				// Reset handler
				function() {
					if ($searchBox.val() === '') {
						if (self.hasFilter(getCurrentApp())) {
							self.getFilter(getCurrentApp())('');
						}
						self.hideResults();
					}
				}
			);
		}
	};
	OCA.Search.Core = Search;
})();

$(document).ready(function() {
	var $searchResults = $('#searchresults');
	var $searchBox = $('#searchbox');
	if ($searchResults.length > 0 && $searchBox.length > 0) {
		$searchResults.addClass('hidden');
		$searchResults.html('<table>\n' +
			'\t<tbody>\n' +
			'\t\t<tr class="template">\n' +
			'\t\t\t<td class="icon"></td>\n' +
			'\t\t\t<td class="info">\n' +
			'\t\t\t\t<a class="link">\n' +
			'\t\t\t\t\t<div class="name"></div>\n' +
			'\t\t\t\t</a>\n' +
			'\t\t\t</td>\n' +
			'\t\t</tr>\n' +
			'\t</tbody>\n' +
			'</table>\n' +
			'<div id="status"><span></span></div>');
		OC.Search = new OCA.Search.Core(
			$searchBox,
			$searchResults
		);
	} else {
		// check again later
		_.defer(function() {
			if ($searchResults.length > 0 && $searchBox.length > 0) {
				OC.Search = new OCA.Search.Core(
					$searchBox,
					$searchResults
				);
			}
		});
	}
});

/**
 * @deprecated use get/setRenderer() instead
 */
OC.search.customResults = {};
/**
 * @deprecated use get/setRenderer() instead
 */
OC.search.resultTypes = {};
