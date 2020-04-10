/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */
(function() {

	OCA.Sharing = OCA.Sharing || {}

	/**
	 * Shows "add external share" dialog.
	 *
	 * @param {Object} share the share
	 * @param {String} share.remote remote server URL
	 * @param {String} share.owner owner name
	 * @param {String} share.name name of the shared folder
	 * @param {String} share.token authentication token
	 * @param {bool} passwordProtected true if the share is password protected
	 * @param {Function} callback the callback
	 */
	OCA.Sharing.showAddExternalDialog = function(share, passwordProtected, callback) {
		var remote = share.remote;
		var owner = share.ownerDisplayName || share.owner;
		var name = share.name;
		var remoteClean = (remote.substr(0, 8) === 'https://') ? remote.substr(8) : remote.substr(7);

		if (!passwordProtected) {
			OC.dialogs.confirm(
				t(
					'files_sharing',
					'Do you want to add the remote share {name} from {owner}@{remote}?',
					{ name: name, owner: owner, remote: remoteClean }
				),
				t('files_sharing', 'Remote share'),
				function(result) {
					callback(result, share);
				},
				true
			).then(this._adjustDialog);
		} else {
			OC.dialogs.prompt(
				t(
					'files_sharing',
					'Do you want to add the remote share {name} from {owner}@{remote}?',
					{ name: name, owner: owner, remote: remoteClean }
				),
				t('files_sharing', 'Remote share'),
				function(result, password) {
					share.password = password;
					callback(result, share);
				},
				true,
				t('files_sharing', 'Remote share password'),
				true
			).then(this._adjustDialog);
		}
	};

	OCA.Sharing._adjustDialog = function() {
		var $dialog = $('.oc-dialog:visible');
		var $buttons = $dialog.find('button');
		// hack the buttons
		$dialog.find('.ui-icon').remove();
		$buttons.eq(0).text(t('core', 'Cancel'));
		$buttons.eq(1).text(t('files_sharing', 'Add remote share'));
	};

	OCA.Sharing.ExternalShareDialogPlugin = {

		filesApp: null,

		attach: function(filesApp) {
			var self = this;
			this.filesApp = filesApp;
			this.processIncomingShareFromUrl();

			if (!$('#header').find('div.notifications').length) {
				// No notification app, display the modal
				this.processSharesToConfirm();
			}

			$('body').on('OCA.Notification.Action', function(e) {
				if (e.notification.app === 'files_sharing' && e.notification.object_type === 'remote_share' && e.action.type === 'POST') {
					// User accepted a remote share reload
					self.filesApp.fileList.reload();
				}
			});
		},

		/**
		 * Process incoming remote share that might have been passed
		 * through the URL
		 */
		processIncomingShareFromUrl: function() {
			var fileList = this.filesApp.fileList;
			var params = OC.Util.History.parseUrlQuery();
			// manually add server-to-server share
			if (params.remote && params.token && params.owner && params.name) {

				var callbackAddShare = function(result, share) {
					var password = share.password || '';
					if (result) {
						$.post(
							OC.generateUrl('apps/federatedfilesharing/askForFederatedShare'),
							{
								remote: share.remote,
								token: share.token,
								owner: share.owner,
								ownerDisplayName: share.ownerDisplayName || share.owner,
								name: share.name,
								password: password
							}
						).done(function(data) {
							if (data.hasOwnProperty('legacyMount')) {
								fileList.reload();
							} else {
								OC.Notification.showTemporary(data.message);
							}
						}).fail(function(data) {
							OC.Notification.showTemporary(JSON.parse(data.responseText).message);
						});
					}
				};

				// clear hash, it is unlikely that it contain any extra parameters
				location.hash = '';
				params.passwordProtected = parseInt(params.protected, 10) === 1;
				OCA.Sharing.showAddExternalDialog(
					params,
					params.passwordProtected,
					callbackAddShare
				);
			}
		},

		/**
		 * Retrieve a list of remote shares that need to be approved
		 */
		processSharesToConfirm: function() {
			var fileList = this.filesApp.fileList;
			// check for new server-to-server shares which need to be approved
			$.get(OC.generateUrl('/apps/files_sharing/api/externalShares'), {}, function(shares) {
				var index;
				for (index = 0; index < shares.length; ++index) {
					OCA.Sharing.showAddExternalDialog(
						shares[index],
						false,
						function(result, share) {
							if (result) {
								// Accept
								$.post(OC.generateUrl('/apps/files_sharing/api/externalShares'), {id: share.id})
									.then(function() {
										fileList.reload();
									});
							} else {
								// Delete
								$.ajax({
									url: OC.generateUrl('/apps/files_sharing/api/externalShares/'+share.id),
									type: 'DELETE'
								});
							}
						}
					);
				}});
		}
	};
})(OC, OCA);

OC.Plugins.register('OCA.Files.App', OCA.Sharing.ExternalShareDialogPlugin);
