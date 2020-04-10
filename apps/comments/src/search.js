/* eslint-disable */
/*
 * Copyright (c) 2014
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */
(function(OC, OCA, $) {
	'use strict'

	/**
	 * Construct a new FileActions instance
	 * @constructs Files
	 */
	var Comment = function() {
		this.initialize()
	}

	Comment.prototype = {

		fileList: null,

		/**
		 * Initialize the file search
		 */
		initialize: function() {

			var self = this

			this.fileAppLoaded = function() {
				return !!OCA.Files && !!OCA.Files.App
			}
			function inFileList($row, result) {
				return false

				if (!self.fileAppLoaded()) {
					return false
				}
				var dir = self.fileList.getCurrentDirectory().replace(/\/+$/, '')
				var resultDir = OC.dirname(result.path)
				return dir === resultDir && self.fileList.inList(result.name)
			}
			function hideNoFilterResults() {
				var $nofilterresults = $('.nofilterresults')
				if (!$nofilterresults.hasClass('hidden')) {
					$nofilterresults.addClass('hidden')
				}
			}

			/**
			 *
			 * @param {jQuery} $row
			 * @param {Object} result
			 * @param {int} result.id
			 * @param {string} result.comment
			 * @param {string} result.authorId
			 * @param {string} result.authorName
			 * @param {string} result.link
			 * @param {string} result.fileName
			 * @param {string} result.path
			 * @returns {*}
			 */
			this.renderCommentResult = function($row, result) {
				if (inFileList($row, result)) {
					return null
				}
				hideNoFilterResults()
				/* render preview icon, show path beneath filename,
				 show size and last modified date on the right */
				this.updateLegacyMimetype(result)

				var $pathDiv = $('<div>').addClass('path').text(result.path)

				var $avatar = $('<div>')
				$avatar.addClass('avatar')
					.css('display', 'inline-block')
					.css('vertical-align', 'middle')
					.css('margin', '0 5px 2px 3px')

				if (result.authorName) {
					$avatar.avatar(result.authorId, 21, undefined, false, undefined, result.authorName)
				} else {
					$avatar.avatar(result.authorId, 21)
				}

				$row.find('td.info div.name').after($pathDiv).text(result.comment).prepend($('<span>').addClass('path').css('margin-right', '5px').text(result.authorName)).prepend($avatar)
				$row.find('td.result a').attr('href', result.link)

				$row.find('td.icon')
					.css('background-image', 'url(' + OC.imagePath('core', 'actions/comment') + ')')
					.css('opacity', '.4')
				var dir = OC.dirname(result.path)
				// "result.path" does not include a leading "/", so "OC.dirname"
				// returns the path itself for files or folders in the root.
				if (dir === result.path) {
					dir = '/'
				}
				$row.find('td.info a').attr('href',
					OC.generateUrl('/apps/files/?dir={dir}&scrollto={scrollto}', { dir: dir, scrollto: result.fileName })
				)

				return $row
			}

			this.handleCommentClick = function($row, result, event) {
				if (self.fileAppLoaded() && self.fileList.id === 'files') {
					self.fileList.changeDirectory(OC.dirname(result.path))
					self.fileList.scrollTo(result.name)
					return false
				} else {
					return true
				}
			}

			this.updateLegacyMimetype = function(result) {
				// backward compatibility:
				if (!result.mime && result.mime_type) {
					result.mime = result.mime_type
				}
			}
			this.setFileList = function(fileList) {
				this.fileList = fileList
			}

			OC.Plugins.register('OCA.Search.Core', this)
		},
		attach: function(search) {
			search.setRenderer('comment', this.renderCommentResult.bind(this))
			search.setHandler('comment', this.handleCommentClick.bind(this))
		}
	}

	OCA.Search.comment = new Comment()
})(OC, OCA, $)
