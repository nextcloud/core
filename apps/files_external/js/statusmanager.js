/**
 * ownCloud
 *
 * @author Juan Pablo Villafañez Ramos <jvillafanez@owncloud.com>
 * @author Jesus Macias Portela <jesus@owncloud.com>
 * @copyright (C) 2014 ownCloud, Inc.
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

/** @global Handlebars */

if (!OCA.Files_External) {
	OCA.Files_External = {};
}

if (!OCA.Files_External.StatusManager) {
	OCA.Files_External.StatusManager = {};
}

OCA.Files_External.StatusManager = {

	mountStatus: null,
	mountPointList: null,

	/**
	 * Function
	 * @param {callback} afterCallback
	 */

	getMountStatus: function (afterCallback) {
		var self = this;
		if (typeof afterCallback !== 'function' || self.isGetMountStatusRunning) {
			return;
		}

		if (self.mountStatus) {
			afterCallback(self.mountStatus);
		}
	},

	/**
	 * Function Check mount point status from cache
	 * @param {string} mount_point
	 */

	getMountPointListElement: function (mount_point) {
		var element;
		$.each(this.mountPointList, function (key, value) {
			if (value.mount_point === mount_point) {
				element = value;
				return false;
			}
		});
		return element;
	},

	/**
	 * Function Check mount point status from cache
	 * @param {string} mount_point
	 * @param {string} mount_point
	 */

	getMountStatusForMount: function (mountData, afterCallback) {
		var self = this;
		if (typeof afterCallback !== 'function' || self.isGetMountStatusRunning) {
			return $.Deferred().resolve();
		}

		var defObj;
		if (self.mountStatus[mountData.mount_point]) {
			defObj = $.Deferred();
			afterCallback(mountData, self.mountStatus[mountData.mount_point]);
			defObj.resolve();  // not really useful, but it'll keep the same behaviour
		} else {
			defObj = $.ajax({
				type: 'GET',
				url: OC.getRootPath() + '/index.php/apps/files_external/' + ((mountData.type === 'personal') ? 'userstorages' : 'userglobalstorages') + '/' + mountData.id,
				data: {'testOnly' : false},
				success: function (response) {
					if (response && response.status === 0) {
						self.mountStatus[mountData.mount_point] = response;
					} else {
						var statusCode = response.status ? response.status : 1;
						var statusMessage = response.statusMessage ? response.statusMessage : t('files_external', 'Empty response from the server')
						// failure response with error message
						self.mountStatus[mountData.mount_point] = {
							type: mountData.type,
							status: statusCode,
							id: mountData.id,
							error: statusMessage,
							userProvided: response.userProvided,
							authMechanism: response.authMechanism
						};
					}
					afterCallback(mountData, self.mountStatus[mountData.mount_point]);
				},
				error: function (jqxhr, state, error) {
					var message;
					if (mountData.location === 3) {
						// In this case the error is because  mount point use Login credentials and don't exist in the session
						message = t('files_external', 'Couldn\'t access. Please log out and in again to activate this mount point');
					} else {
						message = t('files_external', 'Couldn\'t get the information from the remote server: {code} {type}', {
							code: jqxhr.status,
							type: error
						});
					}
					self.mountStatus[mountData.mount_point] = {
						type: mountData.type,
						status: 1,
						location: mountData.location,
						error: message
					};
					afterCallback(mountData, self.mountStatus[mountData.mount_point]);
				}
			});
		}
		return defObj;
	},

	/**
	 * Function to get external mount point list from the files_external API
	 * @param {Function} afterCallback function to be executed
	 */

	getMountPointList: function (afterCallback) {
		var self = this;
		if (typeof afterCallback !== 'function' || self.isGetMountPointListRunning) {
			return;
		}

		if (self.mountPointList) {
			afterCallback(self.mountPointList);
		} else {
			self.isGetMountPointListRunning = true;
			$.ajax({
				type: 'GET',
				url: OC.linkToOCS('apps/files_external/api/v1') + 'mounts?format=json',
				success: function (response) {
					self.mountPointList = [];
					_.each(response.ocs.data, function (mount) {
						var element = {};
						element.mount_point = mount.name;
						element.type = mount.scope;
						element.location = "";
						element.id = mount.id;
						element.backendText = mount.backend;
						element.backend = mount.class;

						self.mountPointList.push(element);
					});
					afterCallback(self.mountPointList);
				},
				error: function (jqxhr, state, error) {
					self.mountPointList = [];
					OC.Notification.show(t('files_external', 'Couldn\'t get the list of external mount points: {type}', 
						{type: error}), {type: 'error'}
					);
				},
				complete: function () {
					self.isGetMountPointListRunning = false;
				}
			});
		}
	},

	/**
	 * Function to manage action when a mountpoint status = 1 (Errored). Show a dialog to be redirected to settings page.
	 * @param {string} name MountPoint Name
	 */

	manageMountPointError: function (name) {
		this.getMountStatus($.proxy(function (allMountStatus) {
			if (allMountStatus.hasOwnProperty(name) && allMountStatus[name].status > 0 && allMountStatus[name].status < 7) {
				var mountData = allMountStatus[name];
				if (mountData.type === "system") {
					if (mountData.userProvided || mountData.authMechanism === 'password::global::user') {
						// personal mount whit credentials problems
						this.showCredentialsDialog(name, mountData);
					} else {
						OC.dialogs.confirm(t('files_external', 'There was an error with message: ') + mountData.error + '. Do you want to review mount point config in admin settings page?', t('files_external', 'External mount error'), function (e) {
							if (e === true) {
								OC.redirect(OC.generateUrl('/settings/admin/externalstorages'));
							}
						});
					}
				} else {
					OC.dialogs.confirm(t('files_external', 'There was an error with message: ') + mountData.error + '. Do you want to review mount point config in personal settings page?', t('files_external', 'External mount error'), function (e) {
						if (e === true) {
							OC.redirect(OC.generateUrl('/settings/personal#' + t('files_external', 'external-storage')));
						}
					});
				}
			}
		}, this));
	},

	/**
	 * Function to process a mount point in relation with their status, Called from Async Queue.
	 * @param {object} mountData
	 * @param {object} mountStatus
	 */

	processMountStatusIndividual: function (mountData, mountStatus) {

		var mountPoint = mountData.mount_point;
		if (mountStatus.status > 0) {
			var trElement = FileList.findFileEl(OCA.Files_External.StatusManager.Utils.jqSelEscape(mountPoint));

			var route = OCA.Files_External.StatusManager.Utils.getIconRoute(trElement) + '-error';

			if (OCA.Files_External.StatusManager.Utils.isCorrectViewAndRootFolder()) {
				OCA.Files_External.StatusManager.Utils.showIconError(mountPoint, $.proxy(OCA.Files_External.StatusManager.manageMountPointError, OCA.Files_External.StatusManager), route);
			}
			return false;
		} else {
			if (OCA.Files_External.StatusManager.Utils.isCorrectViewAndRootFolder()) {
				OCA.Files_External.StatusManager.Utils.restoreFolder(mountPoint);
				OCA.Files_External.StatusManager.Utils.toggleLink(mountPoint, true, true);
			}
			return true;
		}
	},

	/**
	 * Function to process a mount point in relation with their status
	 * @param {object} mountData
	 * @param {object} mountStatus
	 */

	processMountList: function (mountList) {
		var elementList = null;
		$.each(mountList, function (name, value) {
			var trElement = $('#fileList tr[data-file=\"' + OCA.Files_External.StatusManager.Utils.jqSelEscape(value.mount_point) + '\"]'); //FileList.findFileEl(OCA.Files_External.StatusManager.Utils.jqSelEscape(value.mount_point));
			trElement.attr('data-external-backend', value.backend);
			if (elementList) {
				elementList = elementList.add(trElement);
			} else {
				elementList = trElement;
			}
		});

		if (elementList instanceof $) {
			if (OCA.Files_External.StatusManager.Utils.isCorrectViewAndRootFolder()) {
				// Put their custom icon
				OCA.Files_External.StatusManager.Utils.changeFolderIcon(elementList);
				// Save default view
				OCA.Files_External.StatusManager.Utils.storeDefaultFolderIconAndBgcolor(elementList);
				// Disable row until check status
				elementList.addClass('externalDisabledRow');
				OCA.Files_External.StatusManager.Utils.toggleLink(elementList.find('a.name'), false, false);
			}
		}
	},

	/**
	 * Function to process the whole mount point list in relation with their status (Async queue)
	 */

	launchFullConnectivityCheckOneByOne: function () {
		var self = this;
		this.getMountPointList(function (list) {
			// check if we have a list first
			if (list === undefined && !self.emptyWarningShown) {
				self.emptyWarningShown = true;
				OC.Notification.show(t('files_external', 'Couldn\'t fetch list of Windows network drive mount points: Empty response from server'), 
					{type: 'error'}
				);
				return;
			}
			if (list && list.length > 0) {
				self.processMountList(list);

				if (!self.mountStatus) {
					self.mountStatus = {};
				}

				var ajaxQueue = [];
				$.each(list, function (key, value) {
					var queueElement = {
						funcName: $.proxy(self.getMountStatusForMount, self),
						funcArgs: [value,
							$.proxy(self.processMountStatusIndividual, self)]
					};
					ajaxQueue.push(queueElement);
				});

				var rolQueue = new OCA.Files_External.StatusManager.RollingQueue(ajaxQueue, 4, function () {
					if (!self.notificationHasShown) {
						$.each(self.mountStatus, function (key, value) {
							if (value.status === 1) {
								self.notificationHasShown = true;
							}
						});
					}
				});
				rolQueue.runQueue();
			}
		});
	},


	/**
	 * Function to process a mount point list in relation with their status (Async queue)
	 * @param {object} mountListData
	 * @param {boolean} recheck delete cached info and force api call to check mount point status
	 */

	launchPartialConnectivityCheck: function (mountListData, recheck) {
		if (mountListData.length === 0) {
			return;
		}

		var self = this;
		var ajaxQueue = [];
		$.each(mountListData, function (key, value) {
			if (recheck && value.mount_point in self.mountStatus) {
				delete self.mountStatus[value.mount_point];
			}
			var queueElement = {
				funcName: $.proxy(self.getMountStatusForMount, self),
				funcArgs: [value,
					$.proxy(self.processMountStatusIndividual, self)]
			};
			ajaxQueue.push(queueElement);
		});
		new OCA.Files_External.StatusManager.RollingQueue(ajaxQueue, 4).runQueue();
	},


	/**
	 * Function to relaunch some mount point status check
	 * @param {string} mountListNames
	 * @param {boolean} recheck delete cached info and force api call to check mount point status
	 */

	recheckConnectivityForMount: function (mountListNames, recheck) {
		if (mountListNames.length === 0) {
			return;
		}

		var self = this;
		var mountListData = [];

		if (!self.mountStatus) {
			self.mountStatus = {};
		}

		$.each(mountListNames, function (key, value) {
			var mountData = self.getMountPointListElement(value);
			if (mountData) {
				mountListData.push(mountData);
			}
		});

		// for all mounts in the list, delete the cached status values
		if (recheck) {
			$.each(mountListData, function (key, value) {
				if (value.mount_point in self.mountStatus) {
					delete self.mountStatus[value.mount_point];
				}
			});
		}

		self.processMountList(mountListData);
		self.launchPartialConnectivityCheck(mountListData, recheck);
	},

	credentialsDialogTemplate:
		'<div id="files_external_div_form"><div>' +
		'<div>{{credentials_text}}</div>' +
		'<form>' +
		'<input type="text" name="username" placeholder="{{placeholder_username}}"/>' +
		'<input type="password" name="password" placeholder="{{placeholder_password}}"/>' +
		'</form>' +
		'</div></div>',

	/**
	 * Function to display custom dialog to enter credentials
	 * @param mountPoint
	 * @param mountData
	 */
	showCredentialsDialog: function (mountPoint, mountData) {
		var dialog = $(OCA.Files_External.Templates.credentialsDialog({
			credentials_text: t('files_external', 'Please enter the credentials for the {mount} mount', {
				'mount': mountPoint
			}),
			placeholder_username: t('files_external', 'Username'),
			placeholder_password: t('files_external', 'Password')
		}));

		$('body').append(dialog);

		var apply = function () {
			var username = dialog.find('[name=username]').val();
			var password = dialog.find('[name=password]').val();
			var endpoint = OC.generateUrl('apps/files_external/userglobalstorages/{id}', {
				id: mountData.id
			});
			$('.oc-dialog-close').hide();
			$.ajax({
				type: 'PUT',
				url: endpoint,
				data: {
					backendOptions: {
						user: username,
						password: password
					}
				},
				success: function (data) {
					OC.Notification.show(t('files_external', 'Credentials saved'), {type: 'success'});
					dialog.ocdialog('close');
					/* Trigger status check again */
					OCA.Files_External.StatusManager.recheckConnectivityForMount([OC.basename(data.mountPoint)], true);
				},
				error: function () {
					$('.oc-dialog-close').show();
					OC.Notification.show(t('files_external', 'Credentials saving failed'), {type: 'error'});
				}
			});
			return false;
		};

		var ocdialogParams = {
			modal: true,
			title: t('files_external', 'Credentials required'),
			buttons: [{
				text: t('files_external', 'Save'),
				click: apply,
				closeOnEscape: true
			}],
			closeOnExcape: true
		};

		dialog.ocdialog(ocdialogParams)
			.bind('ocdialogclose', function () {
				dialog.ocdialog('destroy').remove();
			});

		dialog.find('form').on('submit', apply);
		dialog.find('form input:first').focus();
		dialog.find('form input').keyup(function (e) {
			if ((e.which && e.which === 13) || (e.keyCode && e.keyCode === 13)) {
				$(e.target).closest('form').submit();
				return false;
			} else {
				return true;
			}
		});
	}
};

OCA.Files_External.StatusManager.Utils = {

	showIconError: function (folder, clickAction, errorImageUrl) {
		var imageUrl = "url(" + errorImageUrl + ")";
		var trFolder = $('#fileList tr[data-file=\"' + OCA.Files_External.StatusManager.Utils.jqSelEscape(folder) + '\"]'); //FileList.findFileEl(OCA.Files_External.StatusManager.Utils.jqSelEscape(folder));
		this.changeFolderIcon(folder, imageUrl);
		this.toggleLink(folder, false, clickAction);
		trFolder.addClass('externalErroredRow');
	},

	/**
	 * @param folder string with the folder or jQuery element pointing to the tr element
	 */
	storeDefaultFolderIconAndBgcolor: function (folder) {
		var trFolder;
		if (folder instanceof $) {
			trFolder = folder;
		} else {
			trFolder = $('#fileList tr[data-file=\"' + OCA.Files_External.StatusManager.Utils.jqSelEscape(folder) + '\"]'); //FileList.findFileEl(OCA.Files_External.StatusManager.Utils.jqSelEscape(folder)); //$('#fileList tr[data-file=\"' + OCA.Files_External.StatusManager.Utils.jqSelEscape(folder) + '\"]');
		}
		trFolder.each(function () {
			var thisElement = $(this);
			if (thisElement.data('oldbgcolor') === undefined) {
				thisElement.data('oldbgcolor', thisElement.css('background-color'));
			}
		});

		var icon = trFolder.find('td.filename div.thumbnail');
		icon.each(function () {
			var thisElement = $(this);
			if (thisElement.data('oldImage') === undefined) {
				thisElement.data('oldImage', thisElement.css('background-image'));
			}
		});
	},

	/**
	 * @param folder string with the folder or jQuery element pointing to the tr element
	 */
	restoreFolder: function (folder) {
		var trFolder;
		if (folder instanceof $) {
			trFolder = folder;
		} else {
			// can't use here FileList.findFileEl(OCA.Files_External.StatusManager.Utils.jqSelEscape(folder)); return incorrect instance of filelist
			trFolder = $('#fileList tr[data-file=\"' + OCA.Files_External.StatusManager.Utils.jqSelEscape(folder) + '\"]');
		}
		trFolder.removeClass('externalErroredRow').removeClass('externalDisabledRow');
		var tdChilds = trFolder.find("td.filename div.thumbnail");
		tdChilds.each(function () {
			var thisElement = $(this);
			thisElement.css('background-image', thisElement.data('oldImage'));
		});
	},

	/**
	 * @param folder string with the folder or jQuery element pointing to the first td element
	 * of the tr matching the folder name
	 */
	changeFolderIcon: function (filename) {
		var file;
		var route;
		if (filename instanceof $) {
			//trElementList
			$.each(filename, function (index) {
				route = OCA.Files_External.StatusManager.Utils.getIconRoute($(this));
				$(this).attr("data-icon", route);
				$(this).find('td.filename div.thumbnail').css('background-image', "url(" + route + ")").css('display', 'none').css('display', 'inline');
			});
		} else {
			file = $("#fileList tr[data-file=\"" + this.jqSelEscape(filename) + "\"] > td.filename div.thumbnail");
			var parentTr = file.parents('tr:first');
			route = OCA.Files_External.StatusManager.Utils.getIconRoute(parentTr);
			parentTr.attr("data-icon", route);
			file.css('background-image', "url(" + route + ")").css('display', 'none').css('display', 'inline');
		}
	},

	/**
	 * @param backend string with the name of the external storage backend
	 * of the tr matching the folder name
	 */
	getIconRoute: function (tr) {
		if (OCA.Theming) {
			var icon = OC.generateUrl('/apps/theming/img/core/filetypes/folder-external.svg?v=' + OCA.Theming.cacheBuster);
		} else {
			var icon = OC.imagePath('core', 'filetypes/folder-external');
		}
		var backend = null;

		if (tr instanceof $) {
			backend = tr.attr('data-external-backend');
		}

		switch (backend) {
			case 'windows_network_drive':
				icon = OC.imagePath('windows_network_drive', 'folder-windows');
				break;
		}

		return icon;
	},

	toggleLink: function (filename, active, action) {
		var link;
		if (filename instanceof $) {
			link = filename;
		} else {
			link = $("#fileList tr[data-file=\"" + this.jqSelEscape(filename) + "\"] > td.filename a.name");
		}
		if (active) {
			link.off('click.connectivity');
			OCA.Files.App.fileList.fileActions.display(link.parent(), true, OCA.Files.App.fileList);
		} else {
			link.find('.fileactions, .nametext .action').remove();  // from files/js/fileactions (display)
			link.off('click.connectivity');
			link.on('click.connectivity', function (e) {
				if (action && $.isFunction(action)) {
					action(filename);
				}
				e.preventDefault();
				return false;
			});
		}
	},

	isCorrectViewAndRootFolder: function () {
		// correct views = files & extstoragemounts
		if (OCA.Files.App.getActiveView() === 'files' || OCA.Files.App.getActiveView() === 'extstoragemounts') {
			return OCA.Files.App.getCurrentAppContainer().find('#dir').val() === '/';
		}
		return false;
	},

	/* escape a selector expression for jQuery */
	jqSelEscape: function (expression) {
		if (expression) {
			return expression.replace(/[!"#$%&'()*+,.\/:;<=>?@\[\\\]^`{|}~]/g, '\\$&');
		}
		return null;
	},

	/* Copied from http://stackoverflow.com/questions/2631001/javascript-test-for-existence-of-nested-object-key */
	checkNested: function (cobj /*, level1, level2, ... levelN*/) {
		var args = Array.prototype.slice.call(arguments),
			obj = args.shift();

		for (var i = 0; i < args.length; i++) {
			if (!obj || !obj.hasOwnProperty(args[i])) {
				return false;
			}
			obj = obj[args[i]];
		}
		return true;
	}
};
