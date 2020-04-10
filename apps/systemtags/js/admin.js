/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
 * @copyright Copyright (c) 2019 Gary Kim <gary@garykim.dev>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

(function() {
	if (!OCA.SystemTags) {
		/**
		 * @namespace
		 */
		OCA.SystemTags = {};
	}

	OCA.SystemTags.Admin = {

		collection: null,

		init: function() {
			var self = this;

			this.collection = OC.SystemTags.collection;
			this.collection.fetch({
				success: function() {
					$('#systemtag').select2(_.extend(self.select2));
				}
			});

			var self = this;
			$('#systemtag_name').on('keyup', function(e) {
				if (e.which === 13) {
					_.bind(self._onClickSubmit, self)();
				}
			});
			$('#systemtag_submit').on('click', _.bind(this._onClickSubmit, this));
			$('#systemtag_delete').on('click', _.bind(this._onClickDelete, this));
			$('#systemtag_reset').on('click', _.bind(this._onClickReset, this));
		},

		/**
		 * Selecting a systemtag in select2
		 *
		 * @param {OC.SystemTags.SystemTagModel} tag
		 */
		onSelectTag: function (tag) {
			var level = 0;
			if (tag.get('userVisible')) {
				level += 2;
				if (tag.get('userAssignable')) {
					level += 1;
				}
			}

			$('#systemtag_name').val(tag.get('name'));
			$('#systemtag_level').val(level);

			this._prepareForm(tag.get('id'));
		},

		/**
		 * Clicking the "Create"/"Update" button
		 */
		_onClickSubmit: function () {
			var level = parseInt($('#systemtag_level').val(), 10),
				tagId = $('#systemtags').attr('data-systemtag-id');
			var data = {
				name: $('#systemtag_name').val(),
				userVisible: level === 2 || level === 3,
				userAssignable: level === 3
			};

			if (!data.name) {
				OCP.Toast.error(t('systemtags_manager', 'Tag name is empty'));
				return;
			}

			if (tagId) {
				var model = this.collection.get(tagId);
				model.save(data);
			} else {
				this.collection.create(data);
			}

			this._onClickReset();
		},

		/**
		 * Clicking the "Delete" button
		 */
		_onClickDelete: function () {
			var tagId = $('#systemtags').attr('data-systemtag-id');
			var model = this.collection.get(tagId);
			model.destroy();

			this._onClickReset();
		},

		/**
		 * Clicking the "Reset" button
		 */
		_onClickReset: function () {
			$('#systemtag_name').val('');
			$('#systemtag_level').val(3);
			this._prepareForm(0);
		},

		/**
		 * Prepare the form for create/update
		 *
		 * @param {int} tagId
		 */
		_prepareForm: function (tagId) {
			if (tagId > 0) {
				$('#systemtags').attr('data-systemtag-id', tagId);
				$('#systemtag_delete').removeClass('hidden');
				$('#systemtag_submit span').text(t('systemtags_manager', 'Update'));
				$('#systemtag_create').addClass('hidden');
			} else {
				$('#systemtag').select2('val', '');
				$('#systemtags').attr('data-systemtag-id', '');
				$('#systemtag_delete').addClass('hidden');
				$('#systemtag_submit span').text(t('systemtags_manager', 'Create'));
				$('#systemtag_create').removeClass('hidden');
			}
		},

		/**
		 * Select2 options for the SystemTag dropdown
		 */
		select2: {
			allowClear: false,
			multiple: false,
			placeholder: t('systemtags_manager', 'Select tag…'),
			query: _.debounce(function(query) {
				query.callback({
					results: OCA.SystemTags.Admin.collection.filterByName(query.term)
				});
			}, 100, true),
			id: function(element) {
				return element;
			},
			initSelection: function(element, callback) {
				var selection = ($(element).val() || []).split('|').sort();
				callback(selection);
			},
			formatResult: function (tag) {
				return OC.SystemTags.getDescriptiveTag(tag);
			},
			formatSelection: function (tag) {
				OCA.SystemTags.Admin.onSelectTag(tag);
				return OC.SystemTags.getDescriptiveTag(tag);
			},
			escapeMarkup: function(m) {
				return m;
			},
			sortResults: function(results) {
				results.sort(function(a, b) {
					return OC.Util.naturalSortCompare(a.get('name'), b.get('name'));
				});
				return results;
			}
		}
	};
})();

$(document).ready(function() {
	OCA.SystemTags.Admin.init();
});

