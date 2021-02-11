/* eslint-disable */
/*
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 * @copyright Copyright (c) 2019 Gary Kim <gary@garykim.dev>
 *
 * @author 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Gary Kim <gary@garykim.dev>
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
 */

import _ from 'underscore'
import $ from 'jquery'

import OC from './index'
import OCA from '../OCA/index'

/**
 * this class to ease the usage of jquery dialogs
 */
const Dialogs = {
	// dialog button types
	YES_NO_BUTTONS: 70,
	OK_BUTTONS: 71,

	FILEPICKER_TYPE_CHOOSE: 1,
	FILEPICKER_TYPE_MOVE: 2,
	FILEPICKER_TYPE_COPY: 3,
	FILEPICKER_TYPE_COPY_MOVE: 4,
	FILEPICKER_TYPE_CUSTOM: 5,

	// used to name each dialog
	dialogsCounter: 0,

	/**
	 * displays alert dialog
	 * @param {string} text content of dialog
	 * @param {string} title dialog title
	 * @param {function} callback which will be triggered when user presses OK
	 * @param {boolean} [modal] make the dialog modal
	 */
	alert: function(text, title, callback, modal) {
		this.message(
			text,
			title,
			'alert',
			Dialogs.OK_BUTTON,
			callback,
			modal
		)
	},
	/**
	 * displays info dialog
	 * @param {string} text content of dialog
	 * @param {string} title dialog title
	 * @param {function} callback which will be triggered when user presses OK
	 * @param {boolean} [modal] make the dialog modal
	 */
	info: function(text, title, callback, modal) {
		this.message(text, title, 'info', Dialogs.OK_BUTTON, callback, modal)
	},

	/**
	 * displays confirmation dialog
	 * @param {string} text content of dialog
	 * @param {string} title dialog title
	 * @param {function} callback which will be triggered when user presses OK (true or false would be passed to callback respectively)
	 * @param {boolean} [modal] make the dialog modal
	 * @returns {Promise}
	 */
	confirm: function(text, title, callback, modal) {
		return this.message(
			text,
			title,
			'notice',
			Dialogs.YES_NO_BUTTONS,
			callback,
			modal
		)
	},
	/**
	 * displays confirmation dialog
	 * @param {string} text content of dialog
	 * @param {string} title dialog title
	 * @param {{type: Int, confirm: String, cancel: String, confirmClasses: String}} buttons text content of buttons
	 * @param {function} callback which will be triggered when user presses OK (true or false would be passed to callback respectively)
	 * @param {boolean} [modal] make the dialog modal
	 * @returns {Promise}
	 */
	confirmDestructive: function(text, title, buttons, callback, modal) {
		return this.message(
			text,
			title,
			'none',
			buttons,
			callback,
			modal === undefined ? true : modal
		)
	},
	/**
	 * displays confirmation dialog
	 * @param {string} text content of dialog
	 * @param {string} title dialog title
	 * @param {function} callback which will be triggered when user presses OK (true or false would be passed to callback respectively)
	 * @param {boolean} [modal] make the dialog modal
	 * @returns {Promise}
	 */
	confirmHtml: function(text, title, callback, modal) {
		return this.message(
			text,
			title,
			'notice',
			Dialogs.YES_NO_BUTTONS,
			callback,
			modal,
			true
		)
	},
	/**
	 * displays prompt dialog
	 * @param {string} text content of dialog
	 * @param {string} title dialog title
	 * @param {function} callback which will be triggered when user presses OK (true or false would be passed to callback respectively)
	 * @param {boolean} [modal] make the dialog modal
	 * @param {string} name name of the input field
	 * @param {boolean} password whether the input should be a password input
	 * @returns {Promise}
	 */
	prompt: function(text, title, callback, modal, name, password) {
		return $.when(this._getMessageTemplate()).then(function($tmpl) {
			var dialogName = 'oc-dialog-' + Dialogs.dialogsCounter + '-content'
			var dialogId = '#' + dialogName
			var $dlg = $tmpl.octemplate({
				dialog_name: dialogName,
				title: title,
				message: text,
				type: 'notice'
			})
			var input = $('<input/>')
			input.attr('type', password ? 'password' : 'text').attr('id', dialogName + '-input').attr('placeholder', name)
			var label = $('<label/>').attr('for', dialogName + '-input').text(name + ': ')
			$dlg.append(label)
			$dlg.append(input)
			if (modal === undefined) {
				modal = false
			}
			$('body').append($dlg)

			// wrap callback in _.once():
			// only call callback once and not twice (button handler and close
			// event) but call it for the close event, if ESC or the x is hit
			if (callback !== undefined) {
				callback = _.once(callback)
			}

			var buttonlist = [{
				text: t('core', 'No'),
				click: function() {
					if (callback !== undefined) {
						// eslint-disable-next-line standard/no-callback-literal
						callback(false, input.val())
					}
					$(dialogId).ocdialog('close')
				}
			}, {
				text: t('core', 'Yes'),
				click: function() {
					if (callback !== undefined) {
						// eslint-disable-next-line standard/no-callback-literal
						callback(true, input.val())
					}
					$(dialogId).ocdialog('close')
				},
				defaultButton: true
			}]

			$(dialogId).ocdialog({
				closeOnEscape: true,
				modal: modal,
				buttons: buttonlist,
				close: function() {
					// callback is already fired if Yes/No is clicked directly
					if (callback !== undefined) {
						// eslint-disable-next-line standard/no-callback-literal
						callback(false, input.val())
					}
				}
			})
			input.focus()
			Dialogs.dialogsCounter++
		})
	},
	/**
	 * show a file picker to pick a file from
	 *
	 * In order to pick several types of mime types they need to be passed as an
	 * array of strings.
	 *
	 * When no mime type filter is given only files can be selected. In order to
	 * be able to select both files and folders "['*', 'httpd/unix-directory']"
	 * should be used instead.
	 *
	 * @param {string} title dialog title
	 * @param {function} callback which will be triggered when user presses Choose
	 * @param {boolean} [multiselect] whether it should be possible to select multiple files
	 * @param {string[]} [mimetypeFilter] mimetype to filter by - directories will always be included
	 * @param {boolean} [modal] make the dialog modal
	 * @param {string} [type] Type of file picker : Choose, copy, move, copy and move
	 * @param {string} [path] path to the folder that the the file can be picket from
	 * @param {Object} [options] additonal options that need to be set
	 */
	filepicker: function(title, callback, multiselect, mimetypeFilter, modal, type, path, options) {
		var self = this

		this.filepicker.sortField = 'name'
		this.filepicker.sortOrder = 'asc'
		// avoid opening the picker twice
		if (this.filepicker.loading) {
			return
		}

		if (type === undefined) {
			type = this.FILEPICKER_TYPE_CHOOSE
		}

		var emptyText = t('core', 'No files in here')
		var newText = t('files', 'New folder')
		if (type === this.FILEPICKER_TYPE_COPY || type === this.FILEPICKER_TYPE_MOVE || type === this.FILEPICKER_TYPE_COPY_MOVE) {
			emptyText = t('core', 'No more subfolders in here')
		}

		this.filepicker.loading = true
		this.filepicker.filesClient = (OCA.Sharing && OCA.Sharing.PublicApp && OCA.Sharing.PublicApp.fileList) ? OCA.Sharing.PublicApp.fileList.filesClient : OC.Files.getClient()

		this.filelist = null
		path = path || ''
		options = Object.assign({
			allowDirectoryChooser: false
		}, options)

		$.when(this._getFilePickerTemplate()).then(function($tmpl) {
			self.filepicker.loading = false
			var dialogName = 'oc-dialog-filepicker-content'
			if (self.$filePicker) {
				self.$filePicker.ocdialog('close')
			}

			if (mimetypeFilter === undefined || mimetypeFilter === null) {
				mimetypeFilter = []
			}
			if (typeof (mimetypeFilter) === 'string') {
				mimetypeFilter = [mimetypeFilter]
			}

			self.$filePicker = $tmpl.octemplate({
				dialog_name: dialogName,
				title: title,
				emptytext: emptyText,
				newtext: newText,
				nameCol: t('core', 'Name'),
				sizeCol: t('core', 'Size'),
				modifiedCol: t('core', 'Modified')
			}).data('path', path).data('multiselect', multiselect).data('mimetype', mimetypeFilter).data('allowDirectoryChooser', options.allowDirectoryChooser)

			if (modal === undefined) {
				modal = false
			}
			if (multiselect === undefined) {
				multiselect = false
			}

			// No grid for IE!
			if (OC.Util.isIE()) {
				self.$filePicker.find('#picker-view-toggle').remove()
				self.$filePicker.find('#picker-filestable').removeClass('view-grid')
			}

			$('body').append(self.$filePicker)

			self.$showGridView = $('input#picker-showgridview')
			self.$showGridView.on('change', _.bind(self._onGridviewChange, self))

			if (!OC.Util.isIE()) {
				self._getGridSettings()
			}

			var newButton = self.$filePicker.find('.actions.creatable .button-add')
			if (type === self.FILEPICKER_TYPE_CHOOSE && !options.allowDirectoryChooser) {
				newButton.hide()
			}
			newButton.on('focus', function() {
				self.$filePicker.ocdialog('setEnterCallback', function() {
					event.stopImmediatePropagation()
					event.preventDefault()
					newButton.click()
				})
			})
			newButton.on('blur', function() {
				self.$filePicker.ocdialog('unsetEnterCallback')
			})

			OC.registerMenu(newButton, self.$filePicker.find('.menu'), function() {
				$input.focus()
				self.$filePicker.ocdialog('setEnterCallback', function() {
					event.stopImmediatePropagation()
					event.preventDefault()
					self.$filePicker.submit()
				})
				var newName = $input.val()
				var lastPos = newName.lastIndexOf('.')
				if (lastPos === -1) {
					lastPos = newName.length
				}
				$input.selectRange(0, lastPos)
			})
			var $form = self.$filePicker.find('.filenameform')
			var $input = $form.find('input[type=\'text\']')
			var $submit = $form.find('input[type=\'submit\']')
			$submit.on('click', function(event) {
				event.stopImmediatePropagation()
				event.preventDefault()
				$form.submit()
			})


			/**
			 * Checks whether the given file name is valid.
			 *
			 * @param name file name to check
			 * @return true if the file name is valid.
			 * @throws a string exception with an error message if
			 * the file name is not valid
			 *
			 * NOTE: This function is duplicated in the files app:
			 * https://github.com/nextcloud/server/blob/b9bc2417e7a8dc81feb0abe20359bedaf864f790/apps/files/js/files.js#L127-L148
			 */
			var isFileNameValid = function (name) {
				var trimmedName = name.trim();
				if (trimmedName === '.' || trimmedName === '..')
				{
					throw t('files', '"{name}" is an invalid file name.', {name: name})
				} else if (trimmedName.length === 0) {
					throw t('files', 'File name cannot be empty.')
				} else if (trimmedName.indexOf('/') !== -1) {
					throw t('files', '"/" is not allowed inside a file name.')
				} else if (!!(trimmedName.match(OC.config.blacklist_files_regex))) {
					throw t('files', '"{name}" is not an allowed filetype', {name: name})
				}

				return true
			}

			var checkInput = function() {
				var filename = $input.val()
				try {
					if (!isFileNameValid(filename)) {
						// isFileNameValid(filename) throws an exception itself
					} else if (self.filelist.find(function(file) {
						return file.name === this
					}, filename)) {
						throw t('files', '{newName} already exists', { newName: filename }, undefined, {
							escape: false
						})
					} else {
						return true
					}
				} catch (error) {
					$input.attr('title', error)
					$input.tooltip({
						placement: 'right',
						trigger: 'manual',
						'container': '.newFolderMenu'
					})
					$input.tooltip('fixTitle')
					$input.tooltip('show')
					$input.addClass('error')
				}
				return false
			}

			$form.on('submit', function(event) {
				event.stopPropagation()
				event.preventDefault()

				if (checkInput()) {
					var newname = $input.val()
					self.filepicker.filesClient.createDirectory(self.$filePicker.data('path') + "/" + newname).always(function (status) {
						self._fillFilePicker(self.$filePicker.data('path') + "/" + newname)
					})
					OC.hideMenus()
					self.$filePicker.ocdialog('unsetEnterCallback')
					self.$filePicker.click()
					$input.val(newText)
				}
			})
			$input.keypress(function(event) {
				if (event.keyCode === 13 || event.which === 13) {
					event.stopImmediatePropagation()
					event.preventDefault()
					$form.submit()
				}
			})

			self.$filePicker.ready(function() {
				self.$fileListHeader = self.$filePicker.find('.filelist thead tr')
				self.$filelist = self.$filePicker.find('.filelist tbody')
				self.$filelistContainer = self.$filePicker.find('.filelist-container')
				self.$dirTree = self.$filePicker.find('.dirtree')
				self.$dirTree.on('click', 'div:not(:last-child)', self, function(event) {
					self._handleTreeListSelect(event, type)
				})
				self.$filelist.on('click', 'tr', function(event) {
					self._handlePickerClick(event, $(this), type)
				})
				self.$fileListHeader.on('click', 'a', function(event) {
					var dir = self.$filePicker.data('path')
					self.filepicker.sortField = $(event.currentTarget).data('sort')
					self.filepicker.sortOrder = self.filepicker.sortOrder === 'asc' ? 'desc' : 'asc'
					self._fillFilePicker(dir)
				})
				self._fillFilePicker(path)
			})

			// build buttons
			var functionToCall = function(returnType) {
				if (callback !== undefined) {
					var datapath
					if (multiselect === true) {
						datapath = []
						self.$filelist.find('tr.filepicker_element_selected').each(function(index, element) {
							datapath.push(self.$filePicker.data('path') + '/' + $(element).data('entryname'))
						})
					} else {
						datapath = self.$filePicker.data('path')
						var selectedName = self.$filelist.find('tr.filepicker_element_selected').data('entryname')
						if (selectedName) {
							datapath += '/' + selectedName
						}
					}
					callback(datapath, returnType)
					self.$filePicker.ocdialog('close')
				}
			}

			var chooseCallback = function() {
				functionToCall(Dialogs.FILEPICKER_TYPE_CHOOSE)
			}

			var copyCallback = function() {
				functionToCall(Dialogs.FILEPICKER_TYPE_COPY)
			}

			var moveCallback = function() {
				functionToCall(Dialogs.FILEPICKER_TYPE_MOVE)
			}

			var buttonlist = []
			if (type === Dialogs.FILEPICKER_TYPE_CHOOSE) {
				buttonlist.push({
					text: t('core', 'Choose'),
					click: chooseCallback,
					defaultButton: true
				})
			} else if (type === Dialogs.FILEPICKER_TYPE_CUSTOM) {
				options.buttons.forEach(function(button) {
					buttonlist.push({
						text: button.text,
						click: function() {
							functionToCall(button.type)
						},
						defaultButton: button.defaultButton
					})
				})
			} else {
				if (type === Dialogs.FILEPICKER_TYPE_COPY || type === Dialogs.FILEPICKER_TYPE_COPY_MOVE) {
					buttonlist.push({
						text: t('core', 'Copy'),
						click: copyCallback,
						defaultButton: false
					})
				}
				if (type === Dialogs.FILEPICKER_TYPE_MOVE || type === Dialogs.FILEPICKER_TYPE_COPY_MOVE) {
					buttonlist.push({
						text: t('core', 'Move'),
						click: moveCallback,
						defaultButton: true
					})
				}
			}

			self.$filePicker.ocdialog({
				closeOnEscape: true,
				// max-width of 600
				width: 600,
				height: 500,
				modal: modal,
				buttons: buttonlist,
				style: {
					buttons: 'aside'
				},
				close: function() {
					try {
						$(this).ocdialog('destroy').remove()
					} catch (e) {
					}
					self.$filePicker = null
				}
			})

			// We can access primary class only from oc-dialog.
			// Hence this is one of the approach to get the choose button.
			var getOcDialog = self.$filePicker.closest('.oc-dialog')
			var buttonEnableDisable = getOcDialog.find('.primary')
			if (self.$filePicker.data('mimetype').indexOf('httpd/unix-directory') !== -1 || self.$filePicker.data('allowDirectoryChooser')) {
				buttonEnableDisable.prop('disabled', false)
			} else {
				buttonEnableDisable.prop('disabled', true)
			}
		})
			.fail(function(status, error) {
				// If the method is called while navigating away
				// from the page, it is probably not needed ;)
				self.filepicker.loading = false
				if (status !== 0) {
					alert(t('core', 'Error loading file picker template: {error}', { error: error }))
				}
			})
	},
	/**
	 * Displays raw dialog
	 * You better use a wrapper instead ...
	 */
	message: function(content, title, dialogType, buttons, callback, modal, allowHtml) {
		return $.when(this._getMessageTemplate()).then(function($tmpl) {
			var dialogName = 'oc-dialog-' + Dialogs.dialogsCounter + '-content'
			var dialogId = '#' + dialogName
			var $dlg = $tmpl.octemplate({
				dialog_name: dialogName,
				title: title,
				message: content,
				type: dialogType
			}, allowHtml ? { escapeFunction: '' } : {})
			if (modal === undefined) {
				modal = false
			}
			$('body').append($dlg)
			var buttonlist = []
			switch (buttons) {
			case Dialogs.YES_NO_BUTTONS:
				buttonlist = [{
					text: t('core', 'No'),
					click: function() {
						if (callback !== undefined) {
							callback(false)
						}
						$(dialogId).ocdialog('close')
					}
				},
				{
					text: t('core', 'Yes'),
					click: function() {
						if (callback !== undefined) {
							callback(true)
						}
						$(dialogId).ocdialog('close')
					},
					defaultButton: true
				}]
				break
			case Dialogs.OK_BUTTON:
				var functionToCall = function() {
					$(dialogId).ocdialog('close')
					if (callback !== undefined) {
						callback()
					}
				}
				buttonlist[0] = {
					text: t('core', 'OK'),
					click: functionToCall,
					defaultButton: true
				}
				break
			default:
				if (typeof(buttons) === 'object') {
					switch (buttons.type) {
						case Dialogs.YES_NO_BUTTONS:
							buttonlist = [{
								text: buttons.cancel || t('core', 'No'),
								click: function() {
									if (callback !== undefined) {
										callback(false)
									}
									$(dialogId).ocdialog('close')
								}
							},
								{
									text: buttons.confirm || t('core', 'Yes'),
									click: function() {
										if (callback !== undefined) {
											callback(true)
										}
										$(dialogId).ocdialog('close')
									},
									defaultButton: true,
									classes: buttons.confirmClasses
								}]
							break
					}
				}
				break
			}

			$(dialogId).ocdialog({
				closeOnEscape: true,
				closeCallback: () => { callback && callback(false) },
				modal: modal,
				buttons: buttonlist
			})
			Dialogs.dialogsCounter++
		})
			.fail(function(status, error) {
				// If the method is called while navigating away from
				// the page, we still want to deliver the message.
				if (status === 0) {
					alert(title + ': ' + content)
				} else {
					alert(t('core', 'Error loading message template: {error}', { error: error }))
				}
			})
	},
	_fileexistsshown: false,
	/**
	 * Displays file exists dialog
	 * @param {object} data upload object
	 * @param {object} original file with name, size and mtime
	 * @param {object} replacement file with name, size and mtime
	 * @param {object} controller with onCancel, onSkip, onReplace and onRename methods
	 * @returns {Promise} jquery promise that resolves after the dialog template was loaded
	 */
	fileexists: function(data, original, replacement, controller) {
		var self = this
		var dialogDeferred = new $.Deferred()

		var getCroppedPreview = function(file) {
			var deferred = new $.Deferred()
			// Only process image files.
			var type = file.type && file.type.split('/').shift()
			if (window.FileReader && type === 'image') {
				var reader = new FileReader()
				reader.onload = function(e) {
					var blob = new Blob([e.target.result])
					window.URL = window.URL || window.webkitURL
					var originalUrl = window.URL.createObjectURL(blob)
					var image = new Image()
					image.src = originalUrl
					image.onload = function() {
						var url = crop(image)
						deferred.resolve(url)
					}
				}
				reader.readAsArrayBuffer(file)
			} else {
				deferred.reject()
			}
			return deferred
		}

		var crop = function(img) {
			var canvas = document.createElement('canvas')
			var targetSize = 96
			var width = img.width
			var height = img.height
			var x; var y; var size

			// Calculate the width and height, constraining the proportions
			if (width > height) {
				y = 0
				x = (width - height) / 2
			} else {
				y = (height - width) / 2
				x = 0
			}
			size = Math.min(width, height)

			// Set canvas size to the cropped area
			canvas.width = size
			canvas.height = size
			var ctx = canvas.getContext('2d')
			ctx.drawImage(img, x, y, size, size, 0, 0, size, size)

			// Resize the canvas to match the destination (right size uses 96px)
			resampleHermite(canvas, size, size, targetSize, targetSize)

			return canvas.toDataURL('image/png', 0.7)
		}

		/**
		 * Fast image resize/resample using Hermite filter with JavaScript.
		 *
		 * @author: ViliusL
		 *
		 * @param {*} canvas
		 * @param {number} W
		 * @param {number} H
		 * @param {number} W2
		 * @param {number} H2
		 */
		var resampleHermite = function(canvas, W, H, W2, H2) {
			W2 = Math.round(W2)
			H2 = Math.round(H2)
			var img = canvas.getContext('2d').getImageData(0, 0, W, H)
			var img2 = canvas.getContext('2d').getImageData(0, 0, W2, H2)
			var data = img.data
			var data2 = img2.data
			var ratio_w = W / W2
			var ratio_h = H / H2
			var ratio_w_half = Math.ceil(ratio_w / 2)
			var ratio_h_half = Math.ceil(ratio_h / 2)

			for (var j = 0; j < H2; j++) {
				for (var i = 0; i < W2; i++) {
					var x2 = (i + j * W2) * 4
					var weight = 0
					var weights = 0
					var weights_alpha = 0
					var gx_r = 0
					var gx_g = 0
					var gx_b = 0
					var gx_a = 0
					var center_y = (j + 0.5) * ratio_h
					for (var yy = Math.floor(j * ratio_h); yy < (j + 1) * ratio_h; yy++) {
						var dy = Math.abs(center_y - (yy + 0.5)) / ratio_h_half
						var center_x = (i + 0.5) * ratio_w
						var w0 = dy * dy // pre-calc part of w
						for (var xx = Math.floor(i * ratio_w); xx < (i + 1) * ratio_w; xx++) {
							var dx = Math.abs(center_x - (xx + 0.5)) / ratio_w_half
							var w = Math.sqrt(w0 + dx * dx)
							if (w >= -1 && w <= 1) {
								// hermite filter
								weight = 2 * w * w * w - 3 * w * w + 1
								if (weight > 0) {
									dx = 4 * (xx + yy * W)
									// alpha
									gx_a += weight * data[dx + 3]
									weights_alpha += weight
									// colors
									if (data[dx + 3] < 255) { weight = weight * data[dx + 3] / 250 }
									gx_r += weight * data[dx]
									gx_g += weight * data[dx + 1]
									gx_b += weight * data[dx + 2]
									weights += weight
								}
							}
						}
					}
					data2[x2] = gx_r / weights
					data2[x2 + 1] = gx_g / weights
					data2[x2 + 2] = gx_b / weights
					data2[x2 + 3] = gx_a / weights_alpha
				}
			}
			canvas.getContext('2d').clearRect(0, 0, Math.max(W, W2), Math.max(H, H2))
			canvas.width = W2
			canvas.height = H2
			canvas.getContext('2d').putImageData(img2, 0, 0)
		}

		var addConflict = function($conflicts, original, replacement) {

			var $conflict = $conflicts.find('.template').clone().removeClass('template').addClass('conflict')
			var $originalDiv = $conflict.find('.original')
			var $replacementDiv = $conflict.find('.replacement')

			$conflict.data('data', data)

			$conflict.find('.filename').text(original.name)
			$originalDiv.find('.size').text(OC.Util.humanFileSize(original.size))
			$originalDiv.find('.mtime').text(OC.Util.formatDate(original.mtime))
			// ie sucks
			if (replacement.size && replacement.lastModified) {
				$replacementDiv.find('.size').text(OC.Util.humanFileSize(replacement.size))
				$replacementDiv.find('.mtime').text(OC.Util.formatDate(replacement.lastModified))
			}
			var path = original.directory + '/' + original.name
			var urlSpec = {
				file: path,
				x: 96,
				y: 96,
				c: original.etag,
				forceIcon: 0
			}
			var previewpath = Files.generatePreviewUrl(urlSpec)
			// Escaping single quotes
			previewpath = previewpath.replace(/'/g, '%27')
			$originalDiv.find('.icon').css({ 'background-image': "url('" + previewpath + "')" })
			getCroppedPreview(replacement).then(
				function(path) {
					$replacementDiv.find('.icon').css('background-image', 'url(' + path + ')')
				}, function() {
					path = OC.MimeType.getIconUrl(replacement.type)
					$replacementDiv.find('.icon').css('background-image', 'url(' + path + ')')
				}
			)
			// connect checkboxes with labels
			var checkboxId = $conflicts.find('.conflict').length
			$originalDiv.find('input:checkbox').attr('id', 'checkbox_original_' + checkboxId)
			$replacementDiv.find('input:checkbox').attr('id', 'checkbox_replacement_' + checkboxId)

			$conflicts.append($conflict)

			// set more recent mtime bold
			// ie sucks
			if (replacement.lastModified > original.mtime) {
				$replacementDiv.find('.mtime').css('font-weight', 'bold')
			} else if (replacement.lastModified < original.mtime) {
				$originalDiv.find('.mtime').css('font-weight', 'bold')
			} else {
				// TODO add to same mtime collection?
			}

			// set bigger size bold
			if (replacement.size && replacement.size > original.size) {
				$replacementDiv.find('.size').css('font-weight', 'bold')
			} else if (replacement.size && replacement.size < original.size) {
				$originalDiv.find('.size').css('font-weight', 'bold')
			} else {
				// TODO add to same size collection?
			}

			// TODO show skip action for files with same size and mtime in bottom row

			// always keep readonly files

			if (original.status === 'readonly') {
				$originalDiv
					.addClass('readonly')
					.find('input[type="checkbox"]')
					.prop('checked', true)
					.prop('disabled', true)
				$originalDiv.find('.message')
					.text(t('core', 'read-only'))
			}
		}
		// var selection = controller.getSelection(data.originalFiles);
		// if (selection.defaultAction) {
		//	controller[selection.defaultAction](data);
		// } else {
		var dialogName = 'oc-dialog-fileexists-content'
		var dialogId = '#' + dialogName
		if (this._fileexistsshown) {
			// add conflict

			var $conflicts = $(dialogId + ' .conflicts')
			addConflict($conflicts, original, replacement)

			var count = $(dialogId + ' .conflict').length
			var title = n('core',
				'{count} file conflict',
				'{count} file conflicts',
				count,
				{ count: count }
			)
			$(dialogId).parent().children('.oc-dialog-title').text(title)

			// recalculate dimensions
			$(window).trigger('resize')
			dialogDeferred.resolve()
		} else {
			// create dialog
			this._fileexistsshown = true
			$.when(this._getFileExistsTemplate()).then(function($tmpl) {
				var title = t('core', 'One file conflict')
				var $dlg = $tmpl.octemplate({
					dialog_name: dialogName,
					title: title,
					type: 'fileexists',

					allnewfiles: t('core', 'New Files'),
					allexistingfiles: t('core', 'Already existing files'),

					why: t('core', 'Which files do you want to keep?'),
					what: t('core', 'If you select both versions, the copied file will have a number added to its name.')
				})
				$('body').append($dlg)

				if (original && replacement) {
					var $conflicts = $dlg.find('.conflicts')
					addConflict($conflicts, original, replacement)
				}

				var buttonlist = [{
					text: t('core', 'Cancel'),
					classes: 'cancel',
					click: function() {
						if (typeof controller.onCancel !== 'undefined') {
							controller.onCancel(data)
						}
						$(dialogId).ocdialog('close')
					}
				},
				{
					text: t('core', 'Continue'),
					classes: 'continue',
					click: function() {
						if (typeof controller.onContinue !== 'undefined') {
							controller.onContinue($(dialogId + ' .conflict'))
						}
						$(dialogId).ocdialog('close')
					}
				}]

				$(dialogId).ocdialog({
					width: 500,
					closeOnEscape: true,
					modal: true,
					buttons: buttonlist,
					closeButton: null,
					close: function() {
						self._fileexistsshown = false
						try {
							$(this).ocdialog('destroy').remove()
						} catch (e) {
							// ignore
						}
					}
				})

				$(dialogId).css('height', 'auto')

				var $primaryButton = $dlg.closest('.oc-dialog').find('button.continue')
				$primaryButton.prop('disabled', true)

				function updatePrimaryButton() {
					var checkedCount = $dlg.find('.conflicts .checkbox:checked').length
					$primaryButton.prop('disabled', checkedCount === 0)
				}

				// add checkbox toggling actions
				$(dialogId).find('.allnewfiles').on('click', function() {
					var $checkboxes = $(dialogId).find('.conflict .replacement input[type="checkbox"]')
					$checkboxes.prop('checked', $(this).prop('checked'))
				})
				$(dialogId).find('.allexistingfiles').on('click', function() {
					var $checkboxes = $(dialogId).find('.conflict .original:not(.readonly) input[type="checkbox"]')
					$checkboxes.prop('checked', $(this).prop('checked'))
				})
				$(dialogId).find('.conflicts').on('click', '.replacement,.original:not(.readonly)', function() {
					var $checkbox = $(this).find('input[type="checkbox"]')
					$checkbox.prop('checked', !$checkbox.prop('checked'))
				})
				$(dialogId).find('.conflicts').on('click', '.replacement input[type="checkbox"],.original:not(.readonly) input[type="checkbox"]', function() {
					var $checkbox = $(this)
					$checkbox.prop('checked', !$checkbox.prop('checked'))
				})

				// update counters
				$(dialogId).on('click', '.replacement,.allnewfiles', function() {
					var count = $(dialogId).find('.conflict .replacement input[type="checkbox"]:checked').length
					if (count === $(dialogId + ' .conflict').length) {
						$(dialogId).find('.allnewfiles').prop('checked', true)
						$(dialogId).find('.allnewfiles + .count').text(t('core', '(all selected)'))
					} else if (count > 0) {
						$(dialogId).find('.allnewfiles').prop('checked', false)
						$(dialogId).find('.allnewfiles + .count').text(t('core', '({count} selected)', { count: count }))
					} else {
						$(dialogId).find('.allnewfiles').prop('checked', false)
						$(dialogId).find('.allnewfiles + .count').text('')
					}
					updatePrimaryButton()
				})
				$(dialogId).on('click', '.original,.allexistingfiles', function() {
					var count = $(dialogId).find('.conflict .original input[type="checkbox"]:checked').length
					if (count === $(dialogId + ' .conflict').length) {
						$(dialogId).find('.allexistingfiles').prop('checked', true)
						$(dialogId).find('.allexistingfiles + .count').text(t('core', '(all selected)'))
					} else if (count > 0) {
						$(dialogId).find('.allexistingfiles').prop('checked', false)
						$(dialogId).find('.allexistingfiles + .count')
							.text(t('core', '({count} selected)', { count: count }))
					} else {
						$(dialogId).find('.allexistingfiles').prop('checked', false)
						$(dialogId).find('.allexistingfiles + .count').text('')
					}
					updatePrimaryButton()
				})

				dialogDeferred.resolve()
			})
				.fail(function() {
					dialogDeferred.reject()
					alert(t('core', 'Error loading file exists template'))
				})
		}
		// }
		return dialogDeferred.promise()
	},
	// get the gridview setting and set the input accordingly
	_getGridSettings: function() {
		var self = this
		$.get(OC.generateUrl('/apps/files/api/v1/showgridview'), function(response) {
			self.$showGridView.get(0).checked = response.gridview
			self.$showGridView.next('#picker-view-toggle')
				.removeClass('icon-toggle-filelist icon-toggle-pictures')
				.addClass(response.gridview ? 'icon-toggle-filelist' : 'icon-toggle-pictures')
			$('.list-container').toggleClass('view-grid', response.gridview)
		})
	},
	_onGridviewChange: function() {
		var show = this.$showGridView.is(':checked')
		// only save state if user is logged in
		if (OC.currentUser) {
			$.post(OC.generateUrl('/apps/files/api/v1/showgridview'), {
				show: show
			})
		}
		this.$showGridView.next('#picker-view-toggle')
			.removeClass('icon-toggle-filelist icon-toggle-pictures')
			.addClass(show ? 'icon-toggle-filelist' : 'icon-toggle-pictures')
		$('.list-container').toggleClass('view-grid', show)
	},
	_getFilePickerTemplate: function() {
		var defer = $.Deferred()
		if (!this.$filePickerTemplate) {
			var self = this
			$.get(OC.filePath('core', 'templates', 'filepicker.html'), function(tmpl) {
				self.$filePickerTemplate = $(tmpl)
				self.$listTmpl = self.$filePickerTemplate.find('.filelist tbody tr:first-child').detach()
				defer.resolve(self.$filePickerTemplate)
			})
				.fail(function(jqXHR, textStatus, errorThrown) {
					defer.reject(jqXHR.status, errorThrown)
				})
		} else {
			defer.resolve(this.$filePickerTemplate)
		}
		return defer.promise()
	},
	_getMessageTemplate: function() {
		var defer = $.Deferred()
		if (!this.$messageTemplate) {
			var self = this
			$.get(OC.filePath('core', 'templates', 'message.html'), function(tmpl) {
				self.$messageTemplate = $(tmpl)
				defer.resolve(self.$messageTemplate)
			})
				.fail(function(jqXHR, textStatus, errorThrown) {
					defer.reject(jqXHR.status, errorThrown)
				})
		} else {
			defer.resolve(this.$messageTemplate)
		}
		return defer.promise()
	},
	_getFileExistsTemplate: function() {
		var defer = $.Deferred()
		if (!this.$fileexistsTemplate) {
			var self = this
			$.get(OC.filePath('files', 'templates', 'fileexists.html'), function(tmpl) {
				self.$fileexistsTemplate = $(tmpl)
				defer.resolve(self.$fileexistsTemplate)
			})
				.fail(function() {
					defer.reject()
				})
		} else {
			defer.resolve(this.$fileexistsTemplate)
		}
		return defer.promise()
	},
	_getFileList: function(dir, mimeType) { // this is only used by the spreedme app atm
		if (typeof (mimeType) === 'string') {
			mimeType = [mimeType]
		}

		return $.getJSON(
			OC.filePath('files', 'ajax', 'list.php'),
			{
				dir: dir,
				mimetypes: JSON.stringify(mimeType)
			}
		)
	},

	/**
	 * fills the filepicker with files
	 */
	_fillFilePicker: function(dir) {
		var self = this
		this.$filelist.empty()
		this.$filePicker.find('.emptycontent').hide()
		this.$filelistContainer.addClass('icon-loading')
		this.$filePicker.data('path', dir)
		var filter = this.$filePicker.data('mimetype')
		if (typeof (filter) === 'string') {
			filter = [filter]
		}
		self.$fileListHeader.find('.sort-indicator').addClass('hidden').removeClass('icon-triangle-n').removeClass('icon-triangle-s')
		self.$fileListHeader.find('[data-sort=' + self.filepicker.sortField + '] .sort-indicator').removeClass('hidden')
		if (self.filepicker.sortOrder === 'asc') {
			self.$fileListHeader.find('[data-sort=' + self.filepicker.sortField + '] .sort-indicator').addClass('icon-triangle-n')
		} else {
			self.$fileListHeader.find('[data-sort=' + self.filepicker.sortField + '] .sort-indicator').addClass('icon-triangle-s')
		}
		self.filepicker.filesClient.getFolderContents(dir).then(function(status, files) {
			self.filelist = files
			if (filter && filter.length > 0 && filter.indexOf('*') === -1) {
				files = files.filter(function(file) {
					return file.type === 'dir' || filter.indexOf(file.mimetype) !== -1
				})
			}

			var Comparators = {
				name: function(fileInfo1, fileInfo2) {
					if (fileInfo1.type === 'dir' && fileInfo2.type !== 'dir') {
						return -1
					}
					if (fileInfo1.type !== 'dir' && fileInfo2.type === 'dir') {
						return 1
					}
					return OC.Util.naturalSortCompare(fileInfo1.name, fileInfo2.name)
				},
				size: function(fileInfo1, fileInfo2) {
					return fileInfo1.size - fileInfo2.size
				},
				mtime: function(fileInfo1, fileInfo2) {
					return fileInfo1.mtime - fileInfo2.mtime
				}
			}
			var comparator = Comparators[self.filepicker.sortField] || Comparators.name
			files = files.sort(function(file1, file2) {
				var isFavorite = function(fileInfo) {
					return fileInfo.tags && fileInfo.tags.indexOf(OC.TAG_FAVORITE) >= 0
				}

				if (isFavorite(file1) && !isFavorite(file2)) {
					return -1
				} else if (!isFavorite(file1) && isFavorite(file2)) {
					return 1
				}

				return self.filepicker.sortOrder === 'asc' ? comparator(file1, file2) : -comparator(file1, file2)
			})

			self._fillSlug()

			if (files.length === 0) {
				self.$filePicker.find('.emptycontent').show()
				self.$fileListHeader.hide()
			} else {
				self.$filePicker.find('.emptycontent').hide()
				self.$fileListHeader.show()
			}

			self.$filelist.empty();

			$.each(files, function(idx, entry) {
				entry.icon = OC.MimeType.getIconUrl(entry.mimetype)
				var simpleSize, sizeColor
				if (typeof (entry.size) !== 'undefined' && entry.size >= 0) {
					simpleSize = OC.Util.humanFileSize(parseInt(entry.size, 10), true)
					sizeColor = Math.round(160 - Math.pow((entry.size / (1024 * 1024)), 2))
				} else {
					simpleSize = t('files', 'Pending')
					sizeColor = 80
				}

				// split the filename in half if the size is bigger than 20 char
				// for ellipsis
				if (entry.name.length >= 10) {
					// leave maximum 10 letters
					var split = Math.min(Math.floor(entry.name.length / 2), 10)
					var filename1 = entry.name.substr(0, entry.name.length - split)
					var filename2 = entry.name.substr(entry.name.length - split)
				} else {
					var filename1 = entry.name
					var filename2 = ''
				}

				var $row = self.$listTmpl.octemplate({
					type: entry.type,
					dir: dir,
					filename: entry.name,
					filename1: filename1,
					filename2: filename2,
					date: OC.Util.relativeModifiedDate(entry.mtime),
					size: simpleSize,
					sizeColor: sizeColor,
					icon: entry.icon
				})
				if (entry.type === 'file') {
					var urlSpec = {
						file: dir + '/' + entry.name,
						x: 100,
						y: 100
					}
					var img = new Image()
					var previewUrl = OC.generateUrl('/core/preview.png?') + $.param(urlSpec)
					img.onload = function() {
						if (img.width > 5) {
							$row.find('td.filename').attr('style', 'background-image:url(' + previewUrl + ')')
						}
					}
					img.src = previewUrl
				}
				self.$filelist.append($row)
			})

			self.$filelistContainer.removeClass('icon-loading')
		})
	},
	/**
	 * fills the tree list with directories
	 */
	_fillSlug: function() {
		var addButton = this.$dirTree.find('.actions.creatable').detach()
		this.$dirTree.empty()
		var self = this

		self.$dirTree.append(addButton)

		var dir
		var path = this.$filePicker.data('path')
		var $template = $('<div data-dir="{dir}"><a>{name}</a></div>').addClass('crumb')
		if (path) {
			var paths = path.split('/')
			$.each(paths, function(index, dir) {
				dir = paths.pop()
				if (dir === '') {
					return false
				}
				self.$dirTree.prepend($template.octemplate({
					dir: paths.join('/') + '/' + dir,
					name: dir
				}))
			})
		}

		$template.octemplate({
			dir: '',
			name: '' // Ugly but works ;)
		}, { escapeFunction: null }).prependTo(this.$dirTree)

	},
	/**
	 * handle selection made in the tree list
	 */
	_handleTreeListSelect: function(event, type) {
		var self = event.data
		var dir = $(event.target).closest('.crumb').data('dir')
		self._fillFilePicker(dir)
		var getOcDialog = (event.target).closest('.oc-dialog')
		var buttonEnableDisable = $('.primary', getOcDialog)
		this._changeButtonsText(type, dir.split(/[/]+/).pop())
		if (this.$filePicker.data('mimetype').indexOf('httpd/unix-directory') !== -1 || this.$filePicker.data('allowDirectoryChooser')) {
			buttonEnableDisable.prop('disabled', false)
		} else {
			buttonEnableDisable.prop('disabled', true)
		}
	},
	/**
	 * handle clicks made in the filepicker
	 */
	_handlePickerClick: function(event, $element, type) {
		var getOcDialog = this.$filePicker.closest('.oc-dialog')
		var buttonEnableDisable = getOcDialog.find('.primary')
		if ($element.data('type') === 'file') {
			if (this.$filePicker.data('multiselect') !== true || !event.ctrlKey) {
				this.$filelist.find('.filepicker_element_selected').removeClass('filepicker_element_selected')
			}
			$element.toggleClass('filepicker_element_selected')
			buttonEnableDisable.prop('disabled', false)
		} else if ($element.data('type') === 'dir') {
			this._fillFilePicker(this.$filePicker.data('path') + '/' + $element.data('entryname'))
			this._changeButtonsText(type, $element.data('entryname'))
			if (this.$filePicker.data('mimetype').indexOf('httpd/unix-directory') !== -1 || this.$filePicker.data('allowDirectoryChooser')) {
				buttonEnableDisable.prop('disabled', false)
			} else {
				buttonEnableDisable.prop('disabled', true)
			}
		}
	},

	/**
	 * Handle
	 * @param type of action
	 * @param dir on which to change buttons text
	 * @private
	 */
	_changeButtonsText: function(type, dir) {
		var copyText = dir === '' ? t('core', 'Copy') : t('core', 'Copy to {folder}', { folder: dir })
		var moveText = dir === '' ? t('core', 'Move') : t('core', 'Move to {folder}', { folder: dir })
		var buttons = $('.oc-dialog-buttonrow button')
		switch (type) {
			case this.FILEPICKER_TYPE_CHOOSE:
				break
			case this.FILEPICKER_TYPE_CUSTOM:
				break
			case this.FILEPICKER_TYPE_COPY:
				buttons.text(copyText)
				break
			case this.FILEPICKER_TYPE_MOVE:
				buttons.text(moveText)
				break
			case this.FILEPICKER_TYPE_COPY_MOVE:
				buttons.eq(0).text(copyText)
				buttons.eq(1).text(moveText)
				break
		}
	}
}

export default Dialogs
