/**
* ownCloud
*
* @author Vincent Petry
* @copyright 2014 Vincent Petry <pvince81@owncloud.com>
*
* This library is free software; you can redistribute it and/or
* modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
* License as published by the Free Software Foundation; either
* version 3 of the License, or any later version.
*
* This library is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU AFFERO GENERAL PUBLIC LICENSE for more details.
*
* You should have received a copy of the GNU Affero General Public
* License along with this library.  If not, see <http://www.gnu.org/licenses/>.
*
*/

(function() {
	/**
	 * @class BreadCrumb
	 * @memberof OCA.Files
	 * @classdesc Breadcrumbs that represent the current path.
	 *
	 * @param {Object} [options] options
	 * @param {Function} [options.onClick] click event handler
	 * @param {Function} [options.onDrop] drop event handler
	 * @param {Function} [options.getCrumbUrl] callback that returns
	 * the URL of a given breadcrumb
	 */
	var BreadCrumb = function(options){
		this.$el = $('<div class="breadcrumb"></div>');
		this.$menu = $('<div class="popovermenu menu-center"><ul></ul></div>');

		this.crumbSelector = '.crumb:not(.hidden):not(.crumbhome):not(.crumbmenu)';
		this.hiddenCrumbSelector = '.crumb.hidden:not(.crumbhome):not(.crumbmenu)';
		options = options || {};
		if (options.onClick) {
			this.onClick = options.onClick;
		}
		if (options.onDrop) {
			this.onDrop = options.onDrop;
			this.onOver = options.onOver;
			this.onOut = options.onOut;
		}
		if (options.getCrumbUrl) {
			this.getCrumbUrl = options.getCrumbUrl;
		}
		this._detailViews = [];
	};

	/**
	 * @memberof OCA.Files
	 */
	BreadCrumb.prototype = {
		$el: null,
		dir: null,
		dirInfo: null,

		/**
		 * Total width of all breadcrumbs
		 * @type int
		 * @private
		 */
		totalWidth: 0,
		breadcrumbs: [],
		onClick: null,
		onDrop: null,
		onOver: null,
		onOut: null,

		/**
		 * Sets the directory to be displayed as breadcrumb.
		 * This will re-render the breadcrumb.
		 * @param dir path to be displayed as breadcrumb
		 */
		setDirectory: function(dir) {
			dir = dir.replace(/\\/g, '/');
			dir = dir || '/';
			if (dir !== this.dir) {
				this.dir = dir;
				this.render();
			}
		},

		setDirectoryInfo: function(dirInfo) {
			if (dirInfo !== this.dirInfo) {
				this.dirInfo = dirInfo;
				this.render();
			}
		},

		/**
		 * @param {Backbone.View} detailView
		 */
		addDetailView: function(detailView) {
			this._detailViews.push(detailView);
		},

		/**
		 * Returns the full URL to the given directory
		 *
		 * @param {Object.<String, String>} part crumb data as map
		 * @param {int} index crumb index
		 * @return full URL
		 */
		getCrumbUrl: function(part, index) {
			return '#';
		},

		/**
		 * Renders the breadcrumb elements
		 */
		render: function() {
			// Menu is destroyed on every change, we need to init it
			OC.unregisterMenu($('.crumbmenu > .icon-more'), $('.crumbmenu > .popovermenu'));

			var parts = this._makeCrumbs(this.dir || '/');
			var $crumb;
			var $menuItem;
			this.$el.empty();
			this.breadcrumbs = [];

			for (var i = 0; i < parts.length; i++) {
				var part = parts[i];
				var $image;
				var $link = $('<a></a>');
				$crumb = $('<div class="crumb svg"></div>');
				if(part.dir) {
					$link.attr('href', this.getCrumbUrl(part, i));
				}
				if(part.name) {
					$link.text(part.name);
				}
				$link.addClass(part.linkclass);
				$crumb.append($link);
				$crumb.data('dir', part.dir);
				// Ignore menu button
				$crumb.data('crumb-id', i - 1);
				$crumb.addClass(part.class);

				if (part.img) {
					$image = $('<img class="svg"></img>');
					$image.attr('src', part.img);
					$image.attr('alt', part.alt);
					$link.append($image);
				}
				this.breadcrumbs.push($crumb);
				this.$el.append($crumb);
				// Only add feedback if not menu
				if (this.onClick && i !== 0) {
					$link.on('click', this.onClick);
				}
			}

			// Menu creation
			this._createMenu();
			for (var j = 0; j < parts.length; j++) {
				var menuPart = parts[j];
				if(menuPart.dir) {
					$menuItem = $('<li class="crumblist"><a><span class="icon-folder"></span><span></span></a></li>');
					$menuItem.data('dir', menuPart.dir);
					$menuItem.find('a').attr('href', this.getCrumbUrl(part, j));
					$menuItem.find('span:eq(1)').text(menuPart.name);
					this.$menu.children('ul').append($menuItem);
					if (this.onClick) {
						$menuItem.on('click', this.onClick);
					}
				}
			}
			_.each(this._detailViews, function(view) {
				view.render({
					dirInfo: this.dirInfo
				});
				$crumb.append(view.$el);
			}, this);

			// setup drag and drop
			if (this.onDrop) {
				this.$el.find('.crumb:not(:last-child):not(.crumbmenu), .crumblist:not(:last-child)').droppable({
					drop: this.onDrop,
					over: this.onOver,
					out: this.onOut,
					tolerance: 'pointer',
					hoverClass: 'canDrop',
					greedy: true
				});
			}

			// Menu is destroyed on every change, we need to init it
			OC.registerMenu($('.crumbmenu > .icon-more'), $('.crumbmenu > .popovermenu'));

			this._resize();
		},

		/**
		 * Makes a breadcrumb structure based on the given path
		 *
		 * @param {String} dir path to split into a breadcrumb structure
		 * @param {String} [rootIcon=icon-home] icon to use for root
		 * @return {Object.<String, String>} map of {dir: path, name: displayName}
		 */
		_makeCrumbs: function(dir, rootIcon) {
			var crumbs = [];
			var pathToHere = '';
			// trim leading and trailing slashes
			dir = dir.replace(/^\/+|\/+$/g, '');
			var parts = dir.split('/');
			if (dir === '') {
				parts = [];
			}
			// menu part
			crumbs.push({
				class: 'crumbmenu hidden',
				linkclass: 'icon-more menutoggle'
			});
			// root part
			crumbs.push({
				name: t('core', 'Home'),
				dir: '/',
				class: 'crumbhome',
				linkclass: rootIcon || 'icon-home'
			});
			for (var i = 0; i < parts.length; i++) {
				var part = parts[i];
				pathToHere = pathToHere + '/' + part;
				crumbs.push({
					dir: pathToHere,
					name: part
				});
			}
			return crumbs;
		},

		/**
		 * Calculate real width based on individual crumbs
		 *
		 * @param {boolean} ignoreHidden ignore hidden crumbs
		 */
		getTotalWidth: function(ignoreHidden) {
			// The width has to be calculated by adding up the width of all the
			// crumbs; getting the width of the breadcrumb element is not a
			// valid approach, as the returned value could be clamped to its
			// parent width.
			var totalWidth = 0;
			for (var i = 0; i < this.breadcrumbs.length; i++ ) {
				var $crumb = $(this.breadcrumbs[i]);
				if(!$crumb.hasClass('hidden') || ignoreHidden === true) {
					totalWidth += $crumb.outerWidth(true);
				}
			}
			return totalWidth;
		},

 		/**
 		 * Hide the middle crumb
 		 */
 		_hideCrumb: function() {
			var length = this.$el.find(this.crumbSelector).length;
			// Get the middle one floored down
			var elmt = Math.floor(length / 2 - 0.5);
			this.$el.find(this.crumbSelector+':eq('+elmt+')').addClass('hidden');
 		},

 		/**
 		 * Get the crumb to show
 		 */
 		_getCrumbElement: function() {
			var hidden = this.$el.find(this.hiddenCrumbSelector).length;
			var shown = this.$el.find(this.crumbSelector).length;
			// Get the outer one with priority to the highest
			var elmt = (1 - shown % 2) * (hidden - 1);
			return this.$el.find(this.hiddenCrumbSelector + ':eq('+elmt+')');
		},

 		/**
 		 * Show the middle crumb
 		 */
 		_showCrumb: function() {
			if(this.$el.find(this.hiddenCrumbSelector).length === 1) {
				this.$el.find(this.hiddenCrumbSelector).removeClass('hidden');
			}
			this._getCrumbElement().removeClass('hidden');
 		},

		/**
		 * Create and append the popovermenu
		 */
		_createMenu: function() {
			this.$el.find('.crumbmenu').append(this.$menu);
			this.$menu.children('ul').empty();
		},

		/**
		 * Update the popovermenu
		 */
		_updateMenu: function() {
			var menuItems = this.$el.find(this.hiddenCrumbSelector);

			this.$menu.find('li').addClass('in-breadcrumb');
			for (var i = 0; i < menuItems.length; i++) {
				var crumbId = $(menuItems[i]).data('crumb-id');
				this.$menu.find('li:eq('+crumbId+')').removeClass('in-breadcrumb');
			}
		},

		_resize: function() {

			if (this.breadcrumbs.length <= 2) {
				// home & menu
				return;
			}

			// Always hide the menu to ensure that it does not interfere with
			// the width calculations; otherwise, the result could be different
			// depending on whether the menu was previously being shown or not.
			this.$el.find('.crumbmenu').addClass('hidden');

			// Show the crumbs to compress the siblings before hidding again the
			// crumbs. This is needed when the siblings expand to fill all the
			// available width, as in that case their old width would limit the
			// available width for the crumbs.
			// Note that the crumbs shown always overflow the parent width
			// (except, of course, when they all fit in).
			while (this.$el.find(this.hiddenCrumbSelector).length > 0
				&& this.getTotalWidth() <= this.$el.parent().width()) {
				this._showCrumb();
			}

			var siblingsWidth = 0;
			this.$el.prevAll(':visible').each(function () {
				siblingsWidth += $(this).outerWidth(true);
			});
			this.$el.nextAll(':visible').each(function () {
				siblingsWidth += $(this).outerWidth(true);
			});

			var availableWidth = this.$el.parent().width() - siblingsWidth;

			// If container is smaller than content
			// AND if there are crumbs left to hide
			while (this.getTotalWidth() > availableWidth
				&& this.$el.find(this.crumbSelector).length > 0) {
				// As soon as one of the crumbs is hidden the menu will be
				// shown. This is needed for proper results in further width
				// checks.
				// Note that the menu is not shown only when all the crumbs were
				// being shown and they all fit the available space; if any of
				// the crumbs was not being shown then those shown would
				// overflow the available width, so at least one will be hidden
				// and thus the menu will be shown.
				this.$el.find('.crumbmenu').removeClass('hidden');
				this._hideCrumb();
			}

			this._updateMenu();
		}
	};

	OCA.Files.BreadCrumb = BreadCrumb;
})();
