/* eslint-disable */
/*
 * Copyright (c) 2016
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

(function(OC) {
	/**
	 * @namespace
	 */
	OC.SystemTags = {
		/**
		 *
		 * @param {OC.SystemTags.SystemTagModel|Object|String} tag
		 * @returns {jQuery}
		 */
		getDescriptiveTag: function(tag) {
			if (_.isUndefined(tag.name) && !_.isUndefined(tag.toJSON)) {
				tag = tag.toJSON()
			}

			if (_.isUndefined(tag.name)) {
				return $('<span>').addClass('non-existing-tag').text(
					t('core', 'Non-existing tag #{tag}', {
						tag: tag
					})
				)
			}

			var $span = $('<span>')
			$span.append(escapeHTML(tag.name))

			var scope
			if (!tag.userAssignable) {
				scope = t('core', 'restricted')
			}
			if (!tag.userVisible) {
				// invisible also implicitly means not assignable
				scope = t('core', 'invisible')
			}
			if (scope) {
				$span.append($('<em>').text(' (' + scope + ')'))
			}
			return $span
		}
	}
})(OC)
