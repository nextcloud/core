/*
 * Copyright (c) 2014 Vincent Petry <pvince81@owncloud.com>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

/* global Handlebars */

(function (OCA) {

	_.extend(OC.Files.Client, {
		PROPERTY_TAGS: '{' + OC.Files.Client.NS_OWNCLOUD + '}tags',
		PROPERTY_FAVORITE: '{' + OC.Files.Client.NS_OWNCLOUD + '}favorite'
	});

	/**
	 * Returns the icon class for the matching state
	 *
	 * @param {boolean} state true if starred, false otherwise
	 * @return {string} icon class for star image
	 */
	function getStarIconClass (state) {
		return state ? 'icon-starred' : 'icon-star';
	}

	/**
	 * Render the star icon with the given state
	 *
	 * @param {boolean} state true if starred, false otherwise
	 * @return {Object} jQuery object
	 */
	function renderStar (state) {
		return OCA.Files.Templates['favorite_mark']({
			isFavorite: state,
			altText: state ? t('files', 'Favorited') : t('files', 'Not favorited'),
			iconClass: getStarIconClass(state)
		});
	}

	/**
	 * Toggle star icon on favorite mark element
	 *
	 * @param {Object} $favoriteMarkEl favorite mark element
	 * @param {boolean} state true if starred, false otherwise
	 */
	function toggleStar ($favoriteMarkEl, state) {
		$favoriteMarkEl.removeClass('icon-star icon-starred').addClass(getStarIconClass(state));
		$favoriteMarkEl.toggleClass('permanent', state);
	}

	/**
	 * Remove Item from Quickaccesslist
	 *
	 * @param {String} appfolder folder to be removed
	 */
	function removeFavoriteFromList (appfolder) {
		var quickAccessList = 'sublist-favorites';
		var listULElements = document.getElementById(quickAccessList);
		if (!listULElements) {
			return;
		}

		var apppath=appfolder;
		if(appfolder.startsWith("//")){
			apppath=appfolder.substring(1, appfolder.length);
		}

		$(listULElements).find('[data-dir="' + _.escape(apppath) + '"]').remove();

		if (listULElements.childElementCount === 0) {
			var collapsibleButton = $(listULElements).parent().find('button.collapse');
			collapsibleButton.hide();
			$("#button-collapse-parent-favorites").removeClass('collapsible');
		}
	}

	/**
	 * Add Item to Quickaccesslist
	 *
	 * @param {String} appfolder folder to be added
	 */
	function addFavoriteToList (appfolder) {
		var quickAccessList = 'sublist-favorites';
		var listULElements = document.getElementById(quickAccessList);
		if (!listULElements) {
			return;
		}
		var listLIElements = listULElements.getElementsByTagName('li');

		var appName = appfolder.substring(appfolder.lastIndexOf("/") + 1, appfolder.length);
		var apppath = appfolder;

		if(appfolder.startsWith("//")){
			apppath = appfolder.substring(1, appfolder.length);
		}
		var url = OC.generateUrl('/apps/files/?dir=' + apppath + '&view=files');

		var innerTagA = document.createElement('A');
		innerTagA.setAttribute("href", url);
		innerTagA.setAttribute("class", "nav-icon-files svg");
		innerTagA.innerHTML = _.escape(appName);

		var length = listLIElements.length + 1;
		var innerTagLI = document.createElement('li');
		innerTagLI.setAttribute("data-id", apppath.replace('/', '-'));
		innerTagLI.setAttribute("data-dir", apppath);
		innerTagLI.setAttribute("data-view", 'files');
		innerTagLI.setAttribute("class", "nav-" + appName);
		innerTagLI.setAttribute("folderpos", length.toString());
		innerTagLI.appendChild(innerTagA);

		$.get(OC.generateUrl("/apps/files/api/v1/quickaccess/get/NodeType"),{folderpath: apppath}, function (data, status) {
				if (data === "dir") {
					if (listULElements.childElementCount <= 0) {
						listULElements.appendChild(innerTagLI);
						var collapsibleButton = $(listULElements).parent().find('button.collapse');
						collapsibleButton.show();
						$(listULElements).parent().addClass('collapsible');
					} else {
						listLIElements[listLIElements.length - 1].after(innerTagLI);
					}
				}
			}
		);
	}

	OCA.Files = OCA.Files || {};

	/**
	 * Extends the file actions and file list to include a favorite mark icon
	 * and a favorite action in the file actions menu; it also adds "data-tags"
	 * and "data-favorite" attributes to file elements.
	 *
	 * @namespace OCA.Files.TagsPlugin
	 */
	OCA.Files.TagsPlugin = {
		name: 'Tags',

		allowedLists: [
			'files',
			'favorites',
			'systemtags',
			'shares.self',
			'shares.others',
			'shares.link'
		],

		_extendFileActions: function (fileActions) {
			var self = this;

			fileActions.registerAction({
				name: 'Favorite',
				displayName: function (context) {
					var $file = context.$file;
					var isFavorite = $file.data('favorite') === true;

					if (isFavorite) {
						return t('files', 'Remove from favorites');
					}

					// As it is currently not possible to provide a context for
					// the i18n strings "Add to favorites" was used instead of
					// "Favorite" to remove the ambiguity between verb and noun
					// when it is translated.
					return t('files', 'Add to favorites');
				},
				mime: 'all',
				order: -100,
				permissions: OC.PERMISSION_NONE,
				iconClass: function (fileName, context) {
					var $file = context.$file;
					var isFavorite = $file.data('favorite') === true;

					if (isFavorite) {
						return 'icon-favorite';
					}

					return 'icon-starred';
				},
				actionHandler: function (fileName, context) {
					var $favoriteMarkEl = context.$file.find('.favorite-mark');
					var $file = context.$file;
					var fileInfo = context.fileList.files[$file.index()];
					var dir = context.dir || context.fileList.getCurrentDirectory();
					var tags = $file.attr('data-tags');

					if (_.isUndefined(tags)) {
						tags = '';
					}
					tags = tags.split('|');
					tags = _.without(tags, '');
					var isFavorite = tags.indexOf(OC.TAG_FAVORITE) >= 0;
					if (isFavorite) {
						// remove tag from list
						tags = _.without(tags, OC.TAG_FAVORITE);
						removeFavoriteFromList(dir + '/' + fileName);
					} else {
						tags.push(OC.TAG_FAVORITE);
						addFavoriteToList(dir + '/' + fileName);
					}

					// pre-toggle the star
					toggleStar($favoriteMarkEl, !isFavorite);

					context.fileInfoModel.trigger('busy', context.fileInfoModel, true);

					self.applyFileTags(
						dir + '/' + fileName,
						tags,
						$favoriteMarkEl,
						isFavorite
					).then(function (result) {
						context.fileInfoModel.trigger('busy', context.fileInfoModel, false);
						// response from server should contain updated tags
						var newTags = result.tags;
						if (_.isUndefined(newTags)) {
							newTags = tags;
						}
						context.fileInfoModel.set({
							'tags': newTags,
							'favorite': !isFavorite
						});
					});
				}
			});
		},

		_extendFileList: function (fileList) {
			// extend row prototype
			var oldCreateRow = fileList._createRow;
			fileList._createRow = function (fileData) {
				var $tr = oldCreateRow.apply(this, arguments);
				var isFavorite = false;
				if (fileData.tags) {
					$tr.attr('data-tags', fileData.tags.join('|'));
					if (fileData.tags.indexOf(OC.TAG_FAVORITE) >= 0) {
						$tr.attr('data-favorite', true);
						isFavorite = true;
					}
				}
				var $icon = $(renderStar(isFavorite));
				$tr.find('td.filename .thumbnail').append($icon);
				return $tr;
			};
			var oldElementToFile = fileList.elementToFile;
			fileList.elementToFile = function ($el) {
				var fileInfo = oldElementToFile.apply(this, arguments);
				var tags = $el.attr('data-tags');
				if (_.isUndefined(tags)) {
					tags = '';
				}
				tags = tags.split('|');
				tags = _.without(tags, '');
				fileInfo.tags = tags;
				return fileInfo;
			};

			var oldGetWebdavProperties = fileList._getWebdavProperties;
			fileList._getWebdavProperties = function () {
				var props = oldGetWebdavProperties.apply(this, arguments);
				props.push(OC.Files.Client.PROPERTY_TAGS);
				props.push(OC.Files.Client.PROPERTY_FAVORITE);
				return props;
			};

			fileList.filesClient.addFileInfoParser(function (response) {
				var data = {};
				var props = response.propStat[0].properties;
				var tags = props[OC.Files.Client.PROPERTY_TAGS];
				var favorite = props[OC.Files.Client.PROPERTY_FAVORITE];
				if (tags && tags.length) {
					tags = _.chain(tags).filter(function (xmlvalue) {
						return (xmlvalue.namespaceURI === OC.Files.Client.NS_OWNCLOUD && xmlvalue.nodeName.split(':')[1] === 'tag');
					}).map(function (xmlvalue) {
						return xmlvalue.textContent || xmlvalue.text;
					}).value();
				}
				if (tags) {
					data.tags = tags;
				}
				if (favorite && parseInt(favorite, 10) !== 0) {
					data.tags = data.tags || [];
					data.tags.push(OC.TAG_FAVORITE);
				}
				return data;
			});
		},

		attach: function (fileList) {
			if (this.allowedLists.indexOf(fileList.id) < 0) {
				return;
			}
			this._extendFileActions(fileList.fileActions);
			this._extendFileList(fileList);
		},

		/**
		 * Replaces the given files' tags with the specified ones.
		 *
		 * @param {String} fileName path to the file or folder to tag
		 * @param {Array.<String>} tagNames array of tag names
		 * @param {Object} $favoriteMarkEl favorite mark element
		 * @param {boolean} isFavorite Was the item favorited before
		 */
		applyFileTags: function (fileName, tagNames, $favoriteMarkEl, isFavorite) {
			var encodedPath = OC.encodePath(fileName);
			while (encodedPath[0] === '/') {
				encodedPath = encodedPath.substr(1);
			}
			return $.ajax({
				url: OC.generateUrl('/apps/files/api/v1/files/') + encodedPath,
				contentType: 'application/json',
				data: JSON.stringify({
					tags: tagNames || []
				}),
				dataType: 'json',
				type: 'POST'
			}).fail(function (response) {
				var message = '';
				// show message if it is available
				if (response.responseJSON && response.responseJSON.message) {
					message = ': ' + response.responseJSON.message;
				}
				OC.Notification.show(t('files', 'An error occurred while trying to update the tags' + message), {type: 'error'});
				toggleStar($favoriteMarkEl, isFavorite);
			});
		}
	};
})
(OCA);

OC.Plugins.register('OCA.Files.FileList', OCA.Files.TagsPlugin);
