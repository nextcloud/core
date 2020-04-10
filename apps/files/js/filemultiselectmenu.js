/*
 * Copyright (c) 2018
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

(function() {
	var FileMultiSelectMenu = OC.Backbone.View.extend({
		tagName: 'div',
		className: 'filesSelectMenu popovermenu bubble menu-center',
		_scopes: null,
		initialize: function(menuItems) {
			this._scopes = menuItems;
		},
		events: {
			'click a.action': '_onClickAction'
		},

		/**
		 * Renders the menu with the currently set items
		 */
		render: function() {
			this.$el.html(OCA.Files.Templates['filemultiselectmenu']({
				items: this._scopes
			}));
		},
		/**
		 * Displays the menu under the given element
		 *
		 * @param {OCA.Files.FileActionContext} context context
		 * @param {Object} $trigger trigger element
		 */
		show: function(context) {
			this._context = context;
			this.$el.removeClass('hidden');
			if (window.innerWidth < 480) {
				this.$el.removeClass('menu-center').addClass('menu-right');
			} else {
				this.$el.removeClass('menu-right').addClass('menu-center');
			}
			OC.showMenu(null, this.$el);
			return false;
		},
		toggleItemVisibility: function (itemName, show) {
			if (show) {
				this.$el.find('.item-' + itemName).removeClass('hidden');
			} else {
				this.$el.find('.item-' + itemName).addClass('hidden');
			}
		},
		updateItemText: function (itemName, translation) {
			this.$el.find('.item-' + itemName).find('.label').text(translation);
		},
		toggleLoading: function (itemName, showLoading) {
			var $actionElement = this.$el.find('.item-' + itemName);
			if ($actionElement.length === 0) {
				return;
			}
			var $icon = $actionElement.find('.icon');
			if (showLoading) {
				var $loadingIcon = $('<span class="icon icon-loading-small"></span>');
				$icon.after($loadingIcon);
				$icon.addClass('hidden');
				$actionElement.addClass('disabled');
			} else {
				$actionElement.find('.icon-loading-small').remove();
				$actionElement.find('.icon').removeClass('hidden');
				$actionElement.removeClass('disabled');
			}
		},
		isDisabled: function (itemName) {
			var $actionElement = this.$el.find('.item-' + itemName);
			return $actionElement.hasClass('disabled');
		},
		/**
		 * Event handler whenever an action has been clicked within the menu
		 *
		 * @param {Object} event event object
		 */
		_onClickAction: function (event) {
			var $target = $(event.currentTarget);
			if (!$target.hasClass('menuitem')) {
				$target = $target.closest('.menuitem');
			}

			OC.hideMenus();
			this._context.multiSelectMenuClick(event, $target.data('action'));
			return false;
		}
	});

	OCA.Files.FileMultiSelectMenu = FileMultiSelectMenu;
})(OC, OCA);
