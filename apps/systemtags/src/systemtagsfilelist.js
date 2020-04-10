/*
 * Copyright (c) 2016 Vincent Petry <pvince81@owncloud.com>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */
(function() {
	/**
	 * @class OCA.SystemTags.FileList
	 * @augments OCA.Files.FileList
	 *
	 * @classdesc SystemTags file list.
	 * Contains a list of files filtered by system tags.
	 *
	 * @param {Object} $el container element with existing markup for the #controls and a table
	 * @param {Array} [options] map of options, see other parameters
	 * @param {Array.<string>} [options.systemTagIds] array of system tag ids to
	 * filter by
	 */
	const FileList = function($el, options) {
		this.initialize($el, options)
	}
	FileList.prototype = _.extend(
		{},
		OCA.Files.FileList.prototype,
		/** @lends OCA.SystemTags.FileList.prototype */ {
			id: 'systemtagsfilter',
			appName: t('systemtags', 'Tagged files'),

			/**
			 * Array of system tag ids to filter by
			 *
			 * @type Array.<string>
			 */
			_systemTagIds: [],
			_lastUsedTags: [],

			_clientSideSort: true,
			_allowSelection: false,

			_filterField: null,

			/**
			 * @private
			 * @param {Object} $el container element
			 * @param {Object} [options] map of options, see other parameters
			 */
			initialize: function($el, options) {
				OCA.Files.FileList.prototype.initialize.apply(this, arguments)
				if (this.initialized) {
					return
				}

				if (options && options.systemTagIds) {
					this._systemTagIds = options.systemTagIds
				}

				OC.Plugins.attach('OCA.SystemTags.FileList', this)

				const $controls = this.$el.find('#controls').empty()

				_.defer(_.bind(this._getLastUsedTags, this))
				this._initFilterField($controls)
			},

			destroy: function() {
				this.$filterField.remove()

				OCA.Files.FileList.prototype.destroy.apply(this, arguments)
			},

			_getLastUsedTags: function() {
				const self = this
				$.ajax({
					type: 'GET',
					url: OC.generateUrl('/apps/systemtags/lastused'),
					success: function(response) {
						self._lastUsedTags = response
					},
				})
			},

			_initFilterField: function($container) {
				const self = this
				this.$filterField = $('<input type="hidden" name="tags"/>')
				$container.append(this.$filterField)
				this.$filterField.select2({
					placeholder: t('systemtags', 'Select tags to filter by'),
					allowClear: false,
					multiple: true,
					toggleSelect: true,
					separator: ',',
					query: _.bind(this._queryTagsAutocomplete, this),

					id: function(tag) {
						return tag.id
					},

					initSelection: function(element, callback) {
						const val = $(element)
							.val()
							.trim()
						if (val) {
							const tagIds = val.split(',')
							const tags = []

							OC.SystemTags.collection.fetch({
								success: function() {
									_.each(tagIds, function(tagId) {
										const tag = OC.SystemTags.collection.get(
											tagId
										)
										if (!_.isUndefined(tag)) {
											tags.push(tag.toJSON())
										}
									})

									callback(tags)
								},
							})
						} else {
							// eslint-disable-next-line standard/no-callback-literal
							callback([])
						}
					},

					formatResult: function(tag) {
						return OC.SystemTags.getDescriptiveTag(tag)
					},

					formatSelection: function(tag) {
						return OC.SystemTags.getDescriptiveTag(tag)[0]
							.outerHTML
					},

					sortResults: function(results) {
						results.sort(function(a, b) {
							const aLastUsed = self._lastUsedTags.indexOf(a.id)
							const bLastUsed = self._lastUsedTags.indexOf(b.id)

							if (aLastUsed !== bLastUsed) {
								if (bLastUsed === -1) {
									return -1
								}
								if (aLastUsed === -1) {
									return 1
								}
								return aLastUsed < bLastUsed ? -1 : 1
							}

							// Both not found
							return OC.Util.naturalSortCompare(a.name, b.name)
						})
						return results
					},

					escapeMarkup: function(m) {
						// prevent double markup escape
						return m
					},
					formatNoMatches: function() {
						return t('systemtags', 'No tags found')
					},
				})
				this.$filterField.on(
					'change',
					_.bind(this._onTagsChanged, this)
				)
				return this.$filterField
			},

			/**
			 * Autocomplete function for dropdown results
			 *
			 * @param {Object} query select2 query object
			 */
			_queryTagsAutocomplete: function(query) {
				OC.SystemTags.collection.fetch({
					success: function() {
						const results = OC.SystemTags.collection.filterByName(
							query.term
						)

						query.callback({
							results: _.invoke(results, 'toJSON'),
						})
					},
				})
			},

			/**
			 * Event handler for when the URL changed
			 *
			 * @param {Event} e the urlchanged event
			 */
			_onUrlChanged: function(e) {
				if (e.dir) {
					const tags = _.filter(e.dir.split('/'), function(val) {
						return val.trim() !== ''
					})
					this.$filterField.select2('val', tags || [])
					this._systemTagIds = tags
					this.reload()
				}
			},

			_onTagsChanged: function(ev) {
				const val = $(ev.target)
					.val()
					.trim()
				if (val !== '') {
					this._systemTagIds = val.split(',')
				} else {
					this._systemTagIds = []
				}

				this.$el.trigger(
					$.Event('changeDirectory', {
						dir: this._systemTagIds.join('/'),
					})
				)
				this.reload()
			},

			updateEmptyContent: function() {
				const dir = this.getCurrentDirectory()
				if (dir === '/') {
					// root has special permissions
					if (!this._systemTagIds.length) {
						// no tags selected
						this.$el
							.find('#emptycontent')
							.html(
								'<div class="icon-systemtags"></div>'
									+ '<h2>'
									+ t(
										'systemtags',
										'Please select tags to filter by'
									)
									+ '</h2>'
							)
					} else {
						// tags selected but no results
						this.$el
							.find('#emptycontent')
							.html(
								'<div class="icon-systemtags"></div>'
									+ '<h2>'
									+ t(
										'systemtags',
										'No files found for the selected tags'
									)
									+ '</h2>'
							)
					}
					this.$el
						.find('#emptycontent')
						.toggleClass('hidden', !this.isEmpty)
					this.$el
						.find('#filestable thead th')
						.toggleClass('hidden', this.isEmpty)
				} else {
					OCA.Files.FileList.prototype.updateEmptyContent.apply(
						this,
						arguments
					)
				}
			},

			getDirectoryPermissions: function() {
				return OC.PERMISSION_READ | OC.PERMISSION_DELETE
			},

			updateStorageStatistics: function() {
				// no op because it doesn't have
				// storage info like free space / used space
			},

			reload: function() {
				// there is only root
				this._setCurrentDir('/', false)

				if (!this._systemTagIds.length) {
					// don't reload
					this.updateEmptyContent()
					this.setFiles([])
					return $.Deferred().resolve()
				}

				this._selectedFiles = {}
				this._selectionSummary.clear()
				if (this._currentFileModel) {
					this._currentFileModel.off()
				}
				this._currentFileModel = null
				this.$el.find('.select-all').prop('checked', false)
				this.showMask()
				this._reloadCall = this.filesClient.getFilteredFiles(
					{
						systemTagIds: this._systemTagIds,
					},
					{
						properties: this._getWebdavProperties(),
					}
				)
				if (this._detailsView) {
					// close sidebar
					this._updateDetailsView(null)
				}
				const callBack = this.reloadCallback.bind(this)
				return this._reloadCall.then(callBack, callBack)
			},

			reloadCallback: function(status, result) {
				if (result) {
					// prepend empty dir info because original handler
					result.unshift({})
				}

				return OCA.Files.FileList.prototype.reloadCallback.call(
					this,
					status,
					result
				)
			},
		}
	)

	OCA.SystemTags.FileList = FileList
})()
