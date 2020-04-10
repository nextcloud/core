/**
 * @author Joas Schilling <coding@schilljs.com>
 * Copyright (c) 2016
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 */

(function() {
	OCA.Comments.ActivityTabViewPlugin = {

		/**
		 * Prepare activity for display
		 *
		 * @param {OCA.Activity.ActivityModel} model for this activity
		 * @param {jQuery} $el jQuery handle for this activity
		 * @param {string} view The view that displayes this activity
		 */
		prepareModelForDisplay: function(model, $el, view) {
			if (model.get('app') !== 'comments' || model.get('type') !== 'comments') {
				return
			}

			if (view === 'ActivityTabView') {
				$el.addClass('comment')
				if (model.get('message') && this._isLong(model.get('message'))) {
					$el.addClass('collapsed')
					const $overlay = $('<div>').addClass('message-overlay')
					$el.find('.activitymessage').after($overlay)
					$el.on('click', this._onClickCollapsedComment)
				}
			}
		},

		/*
		 * Copy of CommentsTabView._onClickComment()
		 */
		_onClickCollapsedComment: function(ev) {
			let $row = $(ev.target)
			if (!$row.is('.comment')) {
				$row = $row.closest('.comment')
			}
			$row.removeClass('collapsed')
		},

		/*
		 * Copy of CommentsTabView._isLong()
		 */
		_isLong: function(message) {
			return message.length > 250 || (message.match(/\n/g) || []).length > 1
		},
	}

})()

OC.Plugins.register('OCA.Activity.RenderingPlugins', OCA.Comments.ActivityTabViewPlugin)
