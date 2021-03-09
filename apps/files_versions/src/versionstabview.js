/*
 * Copyright (c) 2015
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

import ItemTemplate from './templates/item.handlebars'
import Template from './templates/template.handlebars'
import { emit } from '@nextcloud/event-bus'

(function() {
	/**
	 * @memberof OCA.Versions
	 */
	const VersionsTabView = OCA.Files.DetailTabView.extend(/** @lends OCA.Versions.VersionsTabView.prototype */{
		id: 'versionsTabView',
		className: 'tab versionsTabView',

		_template: null,

		$versionsContainer: null,

		events: {
			'click .revertVersion': '_onClickRevertVersion',
			'click .downloadVersion': '_onClickDownloadVersion',
		},

		initialize() {
			OCA.Files.DetailTabView.prototype.initialize.apply(this, arguments)
			this.collection = new OCA.Versions.VersionCollection()
			this.collection.on('request', this._onRequest, this)
			this.collection.on('sync', this._onEndRequest, this)
			this.collection.on('update', this._onUpdate, this)
			this.collection.on('error', this._onError, this)
			this.collection.on('add', this._onAddModel, this)
		},

		getLabel() {
			return t('files_versions', 'Versions')
		},

		getIcon() {
			return 'icon-history'
		},

		nextPage() {
			if (this._loading) {
				return
			}

			if (this.collection.getFileInfo() && this.collection.getFileInfo().isDirectory()) {
				return
			}
			this.collection.fetch()
		},

		_onClickRevertVersion(ev) {
			const self = this
			let $target = $(ev.target)
			const fileInfoModel = this.collection.getFileInfo()
			if (!$target.is('li')) {
				$target = $target.closest('li')
			}

			ev.preventDefault()
			const revision = $target.attr('data-revision')

			const versionModel = this.collection.get(revision)
			const restoreStartedEventState = {
				preventDefault: false,
				fileInfoModel,
				versionModel,
			}
			emit('files_versions:restore:started', restoreStartedEventState)
			if (restoreStartedEventState.preventDefault) {
				return
			}
			versionModel.revert({
				success() {
					// reset and re-fetch the updated collection
					self.$versionsContainer.empty()
					self.collection.setFileInfo(fileInfoModel)
					self.collection.reset([], { silent: true })
					self.collection.fetch()

					self.$el.find('.versions').removeClass('hidden')

					// update original model
					fileInfoModel.trigger('busy', fileInfoModel, false)
					fileInfoModel.set({
						size: versionModel.get('size'),
						mtime: versionModel.get('timestamp') * 1000,
						// temp dummy, until we can do a PROPFIND
						etag: versionModel.get('id') + versionModel.get('timestamp'),
					})
					emit('files_versions:restore:finished', {
						fileInfoModel,
						versionModel,
					})
				},

				error() {
					fileInfoModel.trigger('busy', fileInfoModel, false)
					self.$el.find('.versions').removeClass('hidden')
					self._toggleLoading(false)
					OC.Notification.show(t('files_version', 'Failed to revert {file} to revision {timestamp}.',
						{
							file: versionModel.getFullPath(),
							timestamp: OC.Util.formatDate(versionModel.get('timestamp') * 1000),
						}),
					{
						type: 'error',
					}
					)
					emit('files_versions:restore:failed', {
						fileInfoModel,
						versionModel,
					})
				},
			})

			// spinner
			this._toggleLoading(true)
			fileInfoModel.trigger('busy', fileInfoModel, true)
		},

		_onClickDownloadVersion(ev) {
			let $target = $(ev.target)
			const fileInfoModel = this.collection.getFileInfo()
			if (!$target.is('li')) {
				$target = $target.closest('li')
			}
			const revision = $target.attr('data-revision')
			const versionModel = this.collection.get(revision)

			const downloadVersionEventState = {
				preventDefault: false,
				fileInfoModel,
				versionModel,
			}
			emit('files_versions:download:triggered', downloadVersionEventState)
			if (downloadVersionEventState.preventDefault) {
				ev.preventDefault()
			}
		},

		_toggleLoading(state) {
			this._loading = state
			this.$el.find('.loading').toggleClass('hidden', !state)
		},

		_onRequest() {
			this._toggleLoading(true)
		},

		_onEndRequest() {
			this._toggleLoading(false)
			this.$el.find('.empty').toggleClass('hidden', !!this.collection.length)
		},

		_onAddModel(model) {
			const $el = $(this.itemTemplate(this._formatItem(model)))
			this.$versionsContainer.append($el)
			$el.find('.has-tooltip').tooltip()
		},

		template(data) {
			return Template(data)
		},

		itemTemplate(data) {
			return ItemTemplate(data)
		},

		setFileInfo(fileInfo) {
			if (fileInfo) {
				this.render()
				this.collection.setFileInfo(fileInfo)
				this.collection.reset([], { silent: true })
				this.nextPage()
			} else {
				this.render()
				this.collection.reset()
			}
		},

		_formatItem(version) {
			const timestamp = version.get('timestamp') * 1000
			const size = version.has('size') ? version.get('size') : 0
			const preview = OC.MimeType.getIconUrl(version.get('mimetype'))
			const img = new Image()
			img.onload = function() {
				$('li[data-revision=' + version.get('id') + '] .preview').attr('src', version.getPreviewUrl())
			}
			img.src = version.getPreviewUrl()

			return _.extend({
				versionId: version.get('id'),
				formattedTimestamp: OC.Util.formatDate(timestamp),
				relativeTimestamp: OC.Util.relativeModifiedDate(timestamp),
				millisecondsTimestamp: timestamp,
				humanReadableSize: OC.Util.humanFileSize(size, true),
				altSize: n('files', '%n byte', '%n bytes', size),
				hasDetails: version.has('size'),
				downloadUrl: version.getDownloadUrl(),
				downloadIconUrl: OC.imagePath('core', 'actions/download'),
				downloadName: version.get('name'),
				revertIconUrl: OC.imagePath('core', 'actions/history'),
				previewUrl: preview,
				revertLabel: t('files_versions', 'Restore'),
				canRevert: (this.collection.getFileInfo().get('permissions') & OC.PERMISSION_UPDATE) !== 0,
			}, version.attributes)
		},

		/**
		 * Renders this details view
		 */
		render() {
			this.$el.html(this.template({
				emptyResultLabel: t('files_versions', 'No other versions available'),
			}))
			this.$el.find('.has-tooltip').tooltip()
			this.$versionsContainer = this.$el.find('ul.versions')
			this.delegateEvents()
		},

		/**
		 * Returns true for files, false for folders.
		 * @param {FileInfo} fileInfo fileInfo
		 * @returns {bool} true for files, false for folders
		 */
		canDisplay(fileInfo) {
			if (!fileInfo) {
				return false
			}
			return !fileInfo.isDirectory()
		},
	})

	OCA.Versions = OCA.Versions || {}

	OCA.Versions.VersionsTabView = VersionsTabView
})()
