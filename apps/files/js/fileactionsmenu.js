/*
 * Copyright (c) 2014
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

(function() {

	/**
	 * Construct a new FileActionsMenu instance
	 * @constructs FileActionsMenu
	 * @memberof OCA.Files
	 */
	var FileActionsMenu = OC.Backbone.View.extend({
		tagName: 'div',
		className: 'fileActionsMenu popovermenu bubble hidden open menu',

		/**
		 * Current context
		 *
		 * @type OCA.Files.FileActionContext
		 */
		_context: null,

		events: {
			'click a.action': '_onClickAction'
		},

		template: function(data) {
			return OCA.Files.Templates['fileactionsmenu'](data);
		},

		/**
		 * Event handler whenever an action has been clicked within the menu
		 *
		 * @param {Object} event event object
		 */
		_onClickAction: function(event) {
			var $target = $(event.target);
			if (!$target.is('a')) {
				$target = $target.closest('a');
			}
			var fileActions = this._context.fileActions;
			var actionName = $target.attr('data-action');
			var actions = fileActions.getActions(
				fileActions.getCurrentMimeType(),
				fileActions.getCurrentType(),
				fileActions.getCurrentPermissions()
			);
			var actionSpec = actions[actionName];
			var fileName = this._context.$file.attr('data-file');

			event.stopPropagation();
			event.preventDefault();

			OC.hideMenus();

			actionSpec.action(
				fileName,
				this._context
			);
		},

		/**
		 * Renders the menu with the currently set items
		 */
		render: function() {
			var self = this;
			var fileActions = this._context.fileActions;
			var actions = fileActions.getActions(
				fileActions.getCurrentMimeType(),
				fileActions.getCurrentType(),
				fileActions.getCurrentPermissions()
			);

			var defaultAction = fileActions.getCurrentDefaultFileAction();

			var items = _.filter(actions, function(actionSpec) {
				return !defaultAction || actionSpec.name !== defaultAction.name;
			});
			items = _.map(items, function(item) {
				if (_.isFunction(item.displayName)) {
					item = _.extend({}, item);
					item.displayName = item.displayName(self._context);
				}
				if (_.isFunction(item.iconClass)) {
					var fileName = self._context.$file.attr('data-file');
					item = _.extend({}, item);
					item.iconClass = item.iconClass(fileName, self._context);
				}
				if (_.isFunction(item.icon)) {
					var fileName = self._context.$file.attr('data-file');
					item = _.extend({}, item);
					item.icon = item.icon(fileName, self._context);
				}
				if (_.isFunction(item.deleteAlert)) {
					item = _.extend({}, item);
					item.deleteAlert = item.deleteAlert(self._context);
				}
				item.inline = item.type === OCA.Files.FileActions.TYPE_INLINE
				return item;
			});
			items = items.sort(function(actionA, actionB) {
				var orderA = actionA.order || 0;
				var orderB = actionB.order || 0;
				if (orderB === orderA) {
					return OC.Util.naturalSortCompare(actionA.displayName, actionB.displayName);
				}
				return orderA - orderB;
			});

			items = _.map(items, function(item) {
				item.nameLowerCase = item.name.toLowerCase();
				return item;
			});

			this.$el.html(this.template({
				items: items
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

			this.render();
			this.$el.removeClass('hidden');

			OC.showMenu(null, this.$el);
		}
	});

	OCA.Files.FileActionsMenu = FileActionsMenu;

})();

