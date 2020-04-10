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
	 * The FileSummary class encapsulates the file summary values and
	 * the logic to render it in the given container
	 *
	 * @constructs FileSummary
	 * @memberof OCA.Files
	 *
	 * @param $tr table row element
	 * @param {OC.Backbone.Model} [options.filesConfig] files app configuration
	 */
	var FileSummary = function($tr, options) {
		options = options || {};
		var self = this;
		this.$el = $tr;
		var filesConfig = options.config;
		if (filesConfig) {
			this._showHidden = !!filesConfig.get('showhidden');
			filesConfig.on('change:showhidden', function() {
				self._showHidden = !!this.get('showhidden');
				self.update();
			});
		}
		this.clear();
		this.render();
	};

	FileSummary.prototype = {
		_showHidden: null,

		summary: {
			totalFiles: 0,
			totalDirs: 0,
			totalHidden: 0,
			totalSize: 0,
			filter:'',
			sumIsPending:false
		},

		/**
		 * Returns whether the given file info must be hidden
		 *
		 * @param {OC.Files.FileInfo} fileInfo file info
		 *
		 * @return {boolean} true if the file is a hidden file, false otherwise
		 */
		_isHiddenFile: function(file) {
			return file.name && file.name.charAt(0) === '.';
		},

		/**
		 * Adds file
		 * @param {OC.Files.FileInfo} file file to add
		 * @param {boolean} update whether to update the display
		 */
		add: function(file, update) {
			if (file.name && file.name.toLowerCase().indexOf(this.summary.filter) === -1) {
				return;
			}
			if (file.type === 'dir' || file.mime === 'httpd/unix-directory') {
				this.summary.totalDirs++;
			}
			else {
				this.summary.totalFiles++;
			}
			if (this._isHiddenFile(file)) {
				this.summary.totalHidden++;
			}

			var size = parseInt(file.size, 10) || 0;
			if (size >=0) {
				this.summary.totalSize += size;
			} else {
				this.summary.sumIsPending = true;
			}
			if (!!update) {
				this.update();
			}
		},
		/**
		 * Removes file
		 * @param {OC.Files.FileInfo} file file to remove
		 * @param {boolean} update whether to update the display
		 */
		remove: function(file, update) {
			if (file.name && file.name.toLowerCase().indexOf(this.summary.filter) === -1) {
				return;
			}
			if (file.type === 'dir' || file.mime === 'httpd/unix-directory') {
				this.summary.totalDirs--;
			}
			else {
				this.summary.totalFiles--;
			}
			if (this._isHiddenFile(file)) {
				this.summary.totalHidden--;
			}
			var size = parseInt(file.size, 10) || 0;
			if (size >=0) {
				this.summary.totalSize -= size;
			}
			if (!!update) {
				this.update();
			}
		},
		setFilter: function(filter, files){
			this.summary.filter = filter.toLowerCase();
			this.calculate(files);
		},
		/**
		 * Returns the total of files and directories
		 */
		getTotal: function() {
			return this.summary.totalDirs + this.summary.totalFiles;
		},
		/**
		 * Recalculates the summary based on the given files array
		 * @param files array of files
		 */
		calculate: function(files) {
			var file;
			var summary = {
				totalDirs: 0,
				totalFiles: 0,
				totalHidden: 0,
				totalSize: 0,
				filter: this.summary.filter,
				sumIsPending: false
			};

			for (var i = 0; i < files.length; i++) {
				file = files[i];
				if (file.name && file.name.toLowerCase().indexOf(this.summary.filter) === -1) {
					continue;
				}
				if (file.type === 'dir' || file.mime === 'httpd/unix-directory') {
					summary.totalDirs++;
				}
				else {
					summary.totalFiles++;
				}
				if (this._isHiddenFile(file)) {
					summary.totalHidden++;
				}
				var size = parseInt(file.size, 10) || 0;
				if (size >=0) {
					summary.totalSize += size;
				} else {
					summary.sumIsPending = true;
				}
			}
			this.setSummary(summary);
		},
		/**
		 * Clears the summary
		 */
		clear: function() {
			this.calculate([]);
		},
		/**
		 * Sets the current summary values
		 * @param summary map
		 */
		setSummary: function(summary) {
			this.summary = summary;
			if (typeof this.summary.filter === 'undefined') {
				this.summary.filter = '';
			}
			this.update();
		},

		_infoTemplate: function(data) {
			/* NOTE: To update the template make changes in filesummary.handlebars
			 * and run:
			 *
			 * handlebars -n OCA.Files.FileSummary.Templates filesummary.handlebars -f filesummary_template.js
			 */
			return OCA.Files.Templates['filesummary'](_.extend({
				connectorLabel: t('files', '{dirs} and {files}', {dirs: '', files: ''})
			}, data));
		},

		/**
		 * Renders the file summary element
		 */
		update: function() {
			if (!this.$el) {
				return;
			}
			if (!this.summary.totalFiles && !this.summary.totalDirs) {
				this.$el.addClass('hidden');
				return;
			}
			// There's a summary and data -> Update the summary
			this.$el.removeClass('hidden');
			var $dirInfo = this.$el.find('.dirinfo');
			var $fileInfo = this.$el.find('.fileinfo');
			var $connector = this.$el.find('.connector');
			var $filterInfo = this.$el.find('.filter');
			var $hiddenInfo = this.$el.find('.hiddeninfo');

			// Substitute old content with new translations
			$dirInfo.html(n('files', '%n folder', '%n folders', this.summary.totalDirs));
			$fileInfo.html(n('files', '%n file', '%n files', this.summary.totalFiles));
			$hiddenInfo.html(' (' + n('files', 'including %n hidden', 'including %n hidden', this.summary.totalHidden) + ')');
			var fileSize = this.summary.sumIsPending ? t('files', 'Pending') : OC.Util.humanFileSize(this.summary.totalSize);
			this.$el.find('.filesize').html(fileSize);

			// Show only what's necessary (may be hidden)
			if (this.summary.totalDirs === 0) {
				$dirInfo.addClass('hidden');
				$connector.addClass('hidden');
			} else {
				$dirInfo.removeClass('hidden');
			}
			if (this.summary.totalFiles === 0) {
				$fileInfo.addClass('hidden');
				$connector.addClass('hidden');
			} else {
				$fileInfo.removeClass('hidden');
			}
			if (this.summary.totalDirs > 0 && this.summary.totalFiles > 0) {
				$connector.removeClass('hidden');
			}
			$hiddenInfo.toggleClass('hidden', this.summary.totalHidden === 0 || this._showHidden)
			if (this.summary.filter === '') {
				$filterInfo.html('');
				$filterInfo.addClass('hidden');
			} else {
				$filterInfo.html(' ' + n('files', 'matches \'{filter}\'', 'match \'{filter}\'', this.summary.totalDirs + this.summary.totalFiles, {filter: this.summary.filter}));
				$filterInfo.removeClass('hidden');
			}
		},
		render: function() {
			if (!this.$el) {
				return;
			}
			var summary = this.summary;

			// don't show the filesize column, if filesize is NaN (e.g. in trashbin)
			var fileSize = '';
			if (!isNaN(summary.totalSize)) {
				fileSize = summary.sumIsPending ? t('files', 'Pending') : OC.Util.humanFileSize(summary.totalSize);
				fileSize = '<td class="filesize">' + fileSize + '</td>';
			}

			var $summary = $(
				'<td class="filesummary">'+ this._infoTemplate() + '</td>' +
				fileSize +
				'<td class="date"></td>'
			);
			this.$el.addClass('hidden');
			this.$el.append($summary);
			this.update();
		}
	};
	OCA.Files.FileSummary = FileSummary;
})();

