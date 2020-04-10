// eslint-disable-next-line camelcase
__webpack_public_path__ = OC.linkTo('files_sharing', 'js/dist/')
// eslint-disable-next-line camelcase
__webpack_nonce__ = btoa(OC.requestToken)

window.OCP.Collaboration.registerType('file', {
	action: () => {
		return new Promise((resolve, reject) => {
			OC.dialogs.filepicker(t('files_sharing', 'Link to a file'), function(f) {
				const client = OC.Files.getClient()
				client.getFileInfo(f).then((status, fileInfo) => {
					resolve(fileInfo.id)
				}).fail(() => {
					reject(new Error('Cannot get fileinfo'))
				})
			}, false, null, false, OC.dialogs.FILEPICKER_TYPE_CHOOSE, '', { allowDirectoryChooser: true })
		})
	},
	typeString: t('files_sharing', 'Link to a file'),
	typeIconClass: 'icon-files-dark',
})
