<!--
  - @copyright Copyright (c) 2019 John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @author John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<AppSidebar
		v-if="file"
		ref="sidebar"
		v-bind="appSidebar"
		:force-menu="true"
		@close="close"
		@update:active="setActiveTab"
		@update:starred="toggleStarred"
		@[defaultActionListener].stop.prevent="onDefaultAction">
		<!-- TODO: create a standard to allow multiple elements here? -->
		<template v-if="fileInfo" #primary-actions>
			<LegacyView v-for="view in views"
				:key="view.cid"
				:component="view"
				:file-info="fileInfo" />
		</template>

		<!-- Actions menu -->
		<template v-if="fileInfo" #secondary-actions>
			<!-- TODO: create proper api for apps to register actions
			And inject themselves here. -->
			<ActionButton
				v-if="isSystemTagsEnabled"
				:close-after-click="true"
				icon="icon-tag"
				@click="toggleTags">
				{{ t('files', 'Tags') }}
			</ActionButton>
		</template>

		<!-- Error display -->
		<div v-if="error" class="emptycontent">
			<div class="icon-error" />
			<h2>{{ error }}</h2>
		</div>

		<!-- If fileInfo fetch is complete, display tabs -->
		<template v-for="tab in tabs" v-else-if="fileInfo">
			<component
				:is="tabComponent(tab).is"
				v-if="canDisplay(tab)"
				:id="tab.id"
				:key="tab.id"
				:component="tabComponent(tab).component"
				:name="tab.name"
				:dav-path="davPath"
				:file-info="fileInfo" />
		</template>
	</AppSidebar>
</template>
<script>
import $ from 'jquery'
import axios from '@nextcloud/axios'
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import FileInfo from '../services/FileInfo'
import LegacyTab from '../components/LegacyTab'
import LegacyView from '../components/LegacyView'
import { encodePath } from '@nextcloud/paths'

export default {
	name: 'Sidebar',

	components: {
		ActionButton,
		AppSidebar,
		LegacyView,
	},

	data() {
		return {
			// reactive state
			Sidebar: OCA.Files.Sidebar.state,
			error: null,
			fileInfo: null,
			starLoading: false,
		}
	},

	computed: {
		/**
		 * Current filename
		 * This is bound to the Sidebar service and
		 * is used to load a new file
		 * @returns {string}
		 */
		file() {
			return this.Sidebar.file
		},

		/**
		 * List of all the registered tabs
		 * @returns {Array}
		 */
		tabs() {
			return this.Sidebar.tabs
		},

		/**
		 * List of all the registered views
		 * @returns {Array}
		 */
		views() {
			return this.Sidebar.views
		},

		/**
		 * Current user dav root path
		 * @returns {string}
		 */
		davPath() {
			const user = OC.getCurrentUser().uid
			return OC.linkToRemote(`dav/files/${user}${encodePath(this.file)}`)
		},

		/**
		 * Current active tab handler
		 * @param {string} id the tab id to set as active
		 * @returns {string} the current active tab
		 */
		activeTab() {
			return this.Sidebar.activeTab
		},

		/**
		 * Sidebar subtitle
		 * @returns {string}
		 */
		subtitle() {
			return `${this.size}, ${this.time}`
		},

		/**
		 * File last modified formatted string
		 * @returns {string}
		 */
		time() {
			return OC.Util.relativeModifiedDate(this.fileInfo.mtime)
		},

		/**
		 * File size formatted string
		 * @returns {string}
		 */
		size() {
			return OC.Util.humanFileSize(this.fileInfo.size)
		},

		/**
		 * File background/figure to illustrate the sidebar header
		 * @returns {string}
		 */
		background() {
			return this.getPreviewIfAny(this.fileInfo)
		},

		/**
		 * App sidebar v-binding object
		 *
		 * @returns {Object}
		 */
		appSidebar() {
			if (this.fileInfo) {
				return {
					background: this.background,
					active: this.activeTab,
					class: { 'has-preview': this.fileInfo.hasPreview },
					compact: !this.fileInfo.hasPreview,
					'star-loading': this.starLoading,
					starred: this.fileInfo.isFavourited,
					subtitle: this.subtitle,
					title: this.fileInfo.name,
					'data-mimetype': this.fileInfo.mimetype,
				}
			} else if (this.error) {
				return {
					key: 'error', // force key to re-render
					subtitle: '',
					title: '',
				}
			} else {
				return {
					class: 'icon-loading',
					subtitle: '',
					title: '',
				}
			}
		},

		/**
		 * Default action object for the current file
		 *
		 * @returns {Object}
		 */
		defaultAction() {
			return this.fileInfo
				&& OCA.Files && OCA.Files.App && OCA.Files.App.fileList
				&& OCA.Files.App.fileList.fileActions
				&& OCA.Files.App.fileList.fileActions.getDefaultFileAction
				&& OCA.Files.App.fileList
					.fileActions.getDefaultFileAction(this.fileInfo.mimetype, this.fileInfo.type, OC.PERMISSION_READ)

		},

		/**
		 * Dynamic header click listener to ensure
		 * nothing is listening for a click if there
		 * is no default action
		 *
		 * @returns {string|null}
		 */
		defaultActionListener() {
			return this.defaultAction ? 'figure-click' : null
		},

		isSystemTagsEnabled() {
			return OCA && 'SystemTags' in OCA
		},
	},

	watch: {
		// update the sidebar data
		async file(curr, prev) {
			this.resetData()
			if (curr && curr.trim() !== '') {
				try {
					this.fileInfo = await FileInfo(this.davPath)
					// adding this as fallback because other apps expect it
					this.fileInfo.dir = this.file.split('/').slice(0, -1).join('/')

					// DEPRECATED legacy views
					// TODO: remove
					this.views.forEach(view => {
						view.setFileInfo(this.fileInfo)
					})

					this.$nextTick(() => {
						if (this.$refs.sidebar) {
							this.$refs.sidebar.updateTabs()
						}
					})
				} catch (error) {
					this.error = t('files', 'Error while loading the file data')
					console.error('Error while loading the file data', error)
				}
			}
		},
	},

	methods: {
		/**
		 * Can this tab be displayed ?
		 *
		 * @param {Object} tab a registered tab
		 * @returns {boolean}
		 */
		canDisplay(tab) {
			return tab.isEnabled(this.fileInfo)
		},
		resetData() {
			this.error = null
			this.fileInfo = null
			this.$nextTick(() => {
				if (this.$refs.sidebar) {
					this.$refs.sidebar.updateTabs()
				}
			})
		},

		getPreviewIfAny(fileInfo) {
			if (fileInfo.hasPreview) {
				return OC.generateUrl(`/core/preview?fileId=${fileInfo.id}&x=${screen.width}&y=${screen.height}&a=true`)
			}
			return this.getIconUrl(fileInfo)
		},

		/**
		 * Copied from https://github.com/nextcloud/server/blob/16e0887ec63591113ee3f476e0c5129e20180cde/apps/files/js/filelist.js#L1377
		 * TODO: We also need this as a standalone library
		 *
		 * @param {Object} fileInfo the fileinfo
		 * @returns {string} Url to the icon for mimeType
		 */
		getIconUrl(fileInfo) {
			const mimeType = fileInfo.mimetype || 'application/octet-stream'
			if (mimeType === 'httpd/unix-directory') {
				// use default folder icon
				if (fileInfo.mountType === 'shared' || fileInfo.mountType === 'shared-root') {
					return OC.MimeType.getIconUrl('dir-shared')
				} else if (fileInfo.mountType === 'external-root') {
					return OC.MimeType.getIconUrl('dir-external')
				} else if (fileInfo.mountType !== undefined && fileInfo.mountType !== '') {
					return OC.MimeType.getIconUrl('dir-' + fileInfo.mountType)
				} else if (fileInfo.shareTypes && (
					fileInfo.shareTypes.indexOf(OC.Share.SHARE_TYPE_LINK) > -1
					|| fileInfo.shareTypes.indexOf(OC.Share.SHARE_TYPE_EMAIL) > -1)
				) {
					return OC.MimeType.getIconUrl('dir-public')
				} else if (fileInfo.shareTypes && fileInfo.shareTypes.length > 0) {
					return OC.MimeType.getIconUrl('dir-shared')
				}
				return OC.MimeType.getIconUrl('dir')
			}
			return OC.MimeType.getIconUrl(mimeType)
		},

		tabComponent(tab) {
			if (tab.isLegacyTab) {
				return {
					is: LegacyTab,
					component: tab.component,
				}
			}
			return {
				is: tab.component,
			}
		},

		/**
		 * Set current active tab
		 *
		 * @param {string} id tab unique id
		 */
		setActiveTab(id) {
			OCA.Files.Sidebar.setActiveTab(id)
		},

		/**
		 * Toggle favourite state
		 * TODO: better implementation
		 *
		 * @param {Boolean} state favourited or not
		 */
		async toggleStarred(state) {
			try {
				this.starLoading = true
				await axios({
					method: 'PROPPATCH',
					url: this.davPath,
					data: `<?xml version="1.0"?>
						<d:propertyupdate xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">
						${state ? '<d:set>' : '<d:remove>'}
							<d:prop>
								<oc:favorite>1</oc:favorite>
							</d:prop>
						${state ? '</d:set>' : '</d:remove>'}
						</d:propertyupdate>`,
				})

				// TODO: Obliterate as soon as possible and use events with new files app
				// Terrible fallback for legacy files: toggle filelist as well
				if (OCA.Files && OCA.Files.App && OCA.Files.App.fileList && OCA.Files.App.fileList.fileActions) {
					OCA.Files.App.fileList.fileActions.triggerAction('Favorite', OCA.Files.App.fileList.getModelForFile(this.fileInfo.name), OCA.Files.App.fileList)
				}

			} catch (error) {
				OC.Notification.showTemporary(t('files', 'Unable to change the favourite state of the file'))
				console.error('Unable to change favourite state', error)
			}
			this.starLoading = false
		},

		onDefaultAction() {
			if (this.defaultAction) {
				// generate fake context
				this.defaultAction.action(this.fileInfo.name, {
					fileInfo: this.fileInfo,
					dir: this.fileInfo.dir,
					fileList: OCA.Files.App.fileList,
					$file: $('body'),
				})
			}
		},

		/**
		 * Toggle the tags selector
		 */
		toggleTags() {
			if (OCA.SystemTags && OCA.SystemTags.View) {
				OCA.SystemTags.View.toggle()
			}
		},

		/**
		 * Open the sidebar for the given file
		 *
		 * @param {string} path the file path to load
		 * @returns {Promise}
		 * @throws {Error} loading failure
		 */
		async open(path) {
			// update current opened file
			this.Sidebar.file = path

			// reset previous data
			this.resetData()
			if (path && path.trim() !== '') {
				try {
					this.fileInfo = await FileInfo(this.davPath)
					// adding this as fallback because other apps expect it
					this.fileInfo.dir = this.file.split('/').slice(0, -1).join('/')

					// DEPRECATED legacy views
					// TODO: remove
					this.views.forEach(view => {
						view.setFileInfo(this.fileInfo)
					})

					this.$nextTick(() => {
						if (this.$refs.sidebar) {
							this.$refs.sidebar.updateTabs()
						}
					})
				} catch (error) {
					this.error = t('files', 'Error while loading the file data')
					console.error('Error while loading the file data', error)

					throw new Error(error)
				}
			}
		},

		/**
		 * Close the sidebar
		 */
		close() {
			this.Sidebar.file = ''
			this.resetData()
		},
	},
}
</script>
<style lang="scss" scoped>
#app-sidebar {
	&.has-preview::v-deep {
		.app-sidebar-header__figure {
			background-size: cover;
		}

		&[data-mimetype="text/plain"],
		&[data-mimetype="text/markdown"] {
			.app-sidebar-header__figure {
				background-size: contain;
			}
		}
	}
}
</style>
