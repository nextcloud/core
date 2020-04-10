/*
 * @copyright 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
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

import $ from 'jquery'

$.widget('oc.ocdialog', {
	options: {
		width: 'auto',
		height: 'auto',
		closeButton: true,
		closeOnEscape: true,
		modal: false,
	},
	_create: function() {
		const self = this

		this.originalCss = {
			display: this.element[0].style.display,
			width: this.element[0].style.width,
			height: this.element[0].style.height,
		}

		this.originalTitle = this.element.attr('title')
		this.options.title = this.options.title || this.originalTitle

		this.$dialog = $('<div class="oc-dialog" />')
			.attr({
				// Setting tabIndex makes the div focusable
				tabIndex: -1,
				role: 'dialog',
			})
			.insertBefore(this.element)
		this.$dialog.append(this.element.detach())
		this.element.removeAttr('title').addClass('oc-dialog-content').appendTo(this.$dialog)

		this.$dialog.css({
			display: 'inline-block',
			position: 'fixed',
		})

		this.enterCallback = null

		$(document).on('keydown keyup', function(event) {
			if (
				event.target !== self.$dialog.get(0)
				&& self.$dialog.find($(event.target)).length === 0
			) {
				return
			}
			// Escape
			if (
				event.keyCode === 27
				&& event.type === 'keydown'
				&& self.options.closeOnEscape
			) {
				event.stopImmediatePropagation()
				self.close()
				return false
			}
			// Enter
			if (event.keyCode === 13) {
				event.stopImmediatePropagation()
				if (self.enterCallback !== null) {
					self.enterCallback()
					event.preventDefault()
					return false
				}
				if (event.type === 'keyup') {
					event.preventDefault()
					return false
				}
				// If no button is selected we trigger the primary
				if (
					self.$buttonrow
					&& self.$buttonrow.find($(event.target)).length === 0
				) {
					const $button = self.$buttonrow.find('button.primary')
					if ($button && !$button.prop('disabled')) {
						$button.trigger('click')
					}
				} else if (self.$buttonrow) {
					$(event.target).trigger('click')
				}
				return false
			}
		})

		this._setOptions(this.options)
		this._createOverlay()
	},
	_init: function() {
		this.$dialog.focus()
		this._trigger('open')
	},
	_setOption: function(key, value) {
		const self = this
		switch (key) {
		case 'title':
			if (this.$title) {
				this.$title.text(value)
			} else {
				const $title = $('<h2 class="oc-dialog-title">'
						+ value
						+ '</h2>')
				this.$title = $title.prependTo(this.$dialog)
			}
			this._setSizes()
			break
		case 'buttons':
			if (this.$buttonrow) {
				this.$buttonrow.empty()
			} else {
				const $buttonrow = $('<div class="oc-dialog-buttonrow" />')
				this.$buttonrow = $buttonrow.appendTo(this.$dialog)
			}
			if (value.length === 1) {
				this.$buttonrow.addClass('onebutton')
			} else if (value.length === 2) {
				this.$buttonrow.addClass('twobuttons')
			} else if (value.length === 3) {
				this.$buttonrow.addClass('threebuttons')
			}
			$.each(value, function(idx, val) {
				const $button = $('<button>').text(val.text)
				if (val.classes) {
					$button.addClass(val.classes)
				}
				if (val.defaultButton) {
					$button.addClass('primary')
					self.$defaultButton = $button
				}
				self.$buttonrow.append($button)
				$button.click(function() {
					val.click.apply(self.element[0], arguments)
				})
			})
			this.$buttonrow.find('button')
				.on('focus', function(event) {
					self.$buttonrow.find('button').removeClass('primary')
					$(this).addClass('primary')
				})
			this._setSizes()
			break
		case 'style':
			if (value.buttons !== undefined) {
				this.$buttonrow.addClass(value.buttons)
			}
			break
		case 'closeButton':
			if (value) {
				const $closeButton = $('<a class="oc-dialog-close"></a>')
				this.$dialog.prepend($closeButton)
				$closeButton.on('click', function() {
					self.close()
				})
			} else {
				this.$dialog.find('.oc-dialog-close').remove()
			}
			break
		case 'width':
			this.$dialog.css('width', value)
			break
		case 'height':
			this.$dialog.css('height', value)
			break
		case 'close':
			this.closeCB = value
			break
		}
		// this._super(key, value);
		$.Widget.prototype._setOption.apply(this, arguments)
	},
	_setOptions: function(options) {
		// this._super(options);
		$.Widget.prototype._setOptions.apply(this, arguments)
	},
	_setSizes: function() {
		let lessHeight = 0
		if (this.$title) {
			lessHeight += this.$title.outerHeight(true)
		}
		if (this.$buttonrow) {
			lessHeight += this.$buttonrow.outerHeight(true)
		}
		this.element.css({
			'height': 'calc(100% - ' + lessHeight + 'px)',
		})
	},
	_createOverlay: function() {
		if (!this.options.modal) {
			return
		}

		const self = this
		this.overlay = $('<div>')
			.addClass('oc-dialog-dim')
			.appendTo($('#content'))
		this.overlay.on('click keydown keyup', function(event) {
			if (event.target !== self.$dialog.get(0) && self.$dialog.find($(event.target)).length === 0) {
				event.preventDefault()
				event.stopPropagation()

			}
		})
	},
	_destroyOverlay: function() {
		if (!this.options.modal) {
			return
		}

		if (this.overlay) {
			this.overlay.off('click keydown keyup')
			this.overlay.remove()
			this.overlay = null
		}
	},
	widget: function() {
		return this.$dialog
	},
	setEnterCallback: function(callback) {
		this.enterCallback = callback
	},
	unsetEnterCallback: function() {
		this.enterCallback = null
	},
	close: function() {
		this._destroyOverlay()
		const self = this
		// Ugly hack to catch remaining keyup events.
		setTimeout(function() {
			self._trigger('close', self)
		}, 200)

		self.$dialog.remove()
		this.destroy()
	},
	destroy: function() {
		if (this.$title) {
			this.$title.remove()
		}
		if (this.$buttonrow) {
			this.$buttonrow.remove()
		}

		if (this.originalTitle) {
			this.element.attr('title', this.originalTitle)
		}
		this.element.removeClass('oc-dialog-content')
			.css(this.originalCss).detach().insertBefore(this.$dialog)
		this.$dialog.remove()
	},
})
