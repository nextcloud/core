<template>
	<div id="updatenotification" class="followupsection">
		<div class="update">
			<template v-if="isNewVersionAvailable">
				<p v-if="versionIsEol">
					<span class="warning">
						<span class="icon icon-error-white" />
						{{ t('updatenotification', 'The version you are running is not maintained anymore. Please make sure to update to a supported version as soon as possible.') }}
					</span>
				</p>

				<p>
					<span v-html="newVersionAvailableString" /><br>
					<span v-if="!isListFetched" class="icon icon-loading-small" />
					<span v-html="statusText" />
				</p>

				<template v-if="missingAppUpdates.length">
					<h3 @click="toggleHideMissingUpdates">
						{{ t('updatenotification', 'Apps missing compatible version') }}
						<span v-if="!hideMissingUpdates" class="icon icon-triangle-n" />
						<span v-if="hideMissingUpdates" class="icon icon-triangle-s" />
					</h3>
					<ul v-if="!hideMissingUpdates" class="applist">
						<li v-for="(app, index) in missingAppUpdates" :key="index">
							<a :href="'https://apps.nextcloud.com/apps/' + app.appId" :title="t('settings', 'View in store')">{{ app.appName }} ↗</a>
						</li>
					</ul>
				</template>

				<template v-if="availableAppUpdates.length">
					<h3 @click="toggleHideAvailableUpdates">
						{{ t('updatenotification', 'Apps with compatible version') }}
						<span v-if="!hideAvailableUpdates" class="icon icon-triangle-n" />
						<span v-if="hideAvailableUpdates" class="icon icon-triangle-s" />
					</h3>
					<ul v-if="!hideAvailableUpdates" class="applist">
						<li v-for="(app, index) in availableAppUpdates" :key="index">
							<a :href="'https://apps.nextcloud.com/apps/' + app.appId" :title="t('settings', 'View in store')">{{ app.appName }} ↗</a>
						</li>
					</ul>
				</template>

				<div>
					<a v-if="updaterEnabled && webUpdaterEnabled"
						href="#"
						class="button primary"
						@click="clickUpdaterButton">{{ t('updatenotification', 'Open updater') }}</a>
					<a v-if="downloadLink"
						:href="downloadLink"
						class="button"
						:class="{ hidden: !updaterEnabled }">{{ t('updatenotification', 'Download now') }}</a>
					<span v-if="updaterEnabled && !webUpdaterEnabled">
						{{ t('updatenotification', 'Please use the command line updater to update.') }}
					</span>
					<div v-if="whatsNew" class="whatsNew">
						<div class="toggleWhatsNew">
							<a v-click-outside="hideMenu" class="button" @click="toggleMenu">{{ t('updatenotification', 'What\'s new?') }}</a>
							<div class="popovermenu" :class="{ 'menu-center': true, open: openedWhatsNew }">
								<PopoverMenu :menu="whatsNew" />
							</div>
						</div>
					</div>
				</div>
			</template>
			<template v-else-if="!isUpdateChecked">
				{{ t('updatenotification', 'The update check is not yet finished. Please refresh the page.') }}
			</template>
			<template v-else>
				{{ t('updatenotification', 'Your version is up to date.') }}
				<span v-tooltip.auto="lastCheckedOnString" class="icon-info svg" />
			</template>

			<template v-if="!isDefaultUpdateServerURL">
				<p class="topMargin">
					<em>{{ t('updatenotification', 'A non-default update server is in use to be checked for updates:') }} <code>{{ updateServerURL }}</code></em>
				</p>
			</template>
		</div>

		<h3 class="update-channel-selector">
			{{ t('updatenotification', 'Update channel:') }}
			<div v-click-outside="closeUpdateChannelMenu" class="update-menu">
				<span class="icon-update-menu" @click="toggleUpdateChannelMenu">
					{{ localizedChannelName }}
					<span class="icon-triangle-s" />
				</span>
				<div class="popovermenu menu menu-center" :class="{ 'show-menu': openedUpdateChannelMenu}">
					<PopoverMenu :menu="channelList" />
				</div>
			</div>
		</h3>
		<span id="channel_save_msg" class="msg" /><br>
		<p>
			<em>{{ t('updatenotification', 'You can always update to a newer version. But you can never downgrade to a more stable version.') }}</em><br>
			<em v-html="noteDelayedStableString" />
		</p>

		<p id="oca_updatenotification_groups">
			{{ t('updatenotification', 'Notify members of the following groups about available updates:') }}
			<Multiselect v-model="notifyGroups"
				:options="availableGroups"
				:multiple="true"
				label="label"
				track-by="value"
				:tag-width="75" /><br>
			<em v-if="currentChannel === 'daily' || currentChannel === 'git'">{{ t('updatenotification', 'Only notifications for app updates are available.') }}</em>
			<em v-if="currentChannel === 'daily'">{{ t('updatenotification', 'The selected update channel makes dedicated notifications for the server obsolete.') }}</em>
			<em v-if="currentChannel === 'git'">{{ t('updatenotification', 'The selected update channel does not support updates of the server.') }}</em>
		</p>
	</div>
</template>

<script>
import { generateUrl, getRootUrl, generateOcsUrl } from '@nextcloud/router'
import PopoverMenu from '@nextcloud/vue/dist/Components/PopoverMenu'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import { VTooltip } from 'v-tooltip'
import ClickOutside from 'vue-click-outside'

VTooltip.options.defaultHtml = false

export default {
	name: 'UpdateNotification',
	components: {
		Multiselect,
		PopoverMenu,
	},
	directives: {
		ClickOutside,
		tooltip: VTooltip,
	},
	data() {
		return {
			newVersionString: '',
			lastCheckedDate: '',
			isUpdateChecked: false,
			webUpdaterEnabled: true,
			updaterEnabled: true,
			versionIsEol: false,
			downloadLink: '',
			isNewVersionAvailable: false,
			hasValidSubscription: false,
			updateServerURL: '',
			changelogURL: '',
			whatsNewData: [],
			currentChannel: '',
			channels: [],
			notifyGroups: '',
			availableGroups: [],
			isDefaultUpdateServerURL: true,
			enableChangeWatcher: false,

			availableAppUpdates: [],
			missingAppUpdates: [],
			appStoreFailed: false,
			appStoreDisabled: false,
			isListFetched: false,
			hideMissingUpdates: false,
			hideAvailableUpdates: true,
			openedWhatsNew: false,
			openedUpdateChannelMenu: false,
		}
	},

	_$el: null,
	_$notifyGroups: null,

	computed: {
		newVersionAvailableString() {
			return t('updatenotification', 'A new version is available: <strong>{newVersionString}</strong>', {
				newVersionString: this.newVersionString,
			})
		},

		noteDelayedStableString() {
			return t('updatenotification', 'Note that after a new release the update only shows up after the first minor release or later. We roll out new versions spread out over time to our users and sometimes skip a version when issues are found. Learn more about updates and release channels at {link}')
				.replace('{link}', '<a href="https://nextcloud.com/release-channels/">https://nextcloud.com/release-channels/</a>')
		},

		lastCheckedOnString() {
			return t('updatenotification', 'Checked on {lastCheckedDate}', {
				lastCheckedDate: this.lastCheckedDate,
			})
		},

		statusText() {
			if (!this.isListFetched) {
				return t('updatenotification', 'Checking apps for compatible versions')
			}

			if (this.appStoreDisabled) {
				return t('updatenotification', 'Please make sure your config.php does not set <samp>appstoreenabled</samp> to false.')
			}

			if (this.appStoreFailed) {
				return t('updatenotification', 'Could not connect to the appstore or the appstore returned no updates at all. Search manually for updates or make sure your server has access to the internet and can connect to the appstore.')
			}

			return this.missingAppUpdates.length === 0
				? t('updatenotification', '<strong>All</strong> apps have a compatible version for this Nextcloud version available', this)
				: n('updatenotification', '<strong>%n</strong> app has no compatible version for this Nextcloud version available', '<strong>%n</strong> apps have no compatible version for this Nextcloud version available', this.missingAppUpdates.length)
		},

		whatsNew() {
			if (this.whatsNewData.length === 0) {
				return null
			}
			const whatsNew = []
			for (const i in this.whatsNewData) {
				whatsNew[i] = { icon: 'icon-checkmark', longtext: this.whatsNewData[i] }
			}
			if (this.changelogURL) {
				whatsNew.push({
					href: this.changelogURL,
					text: t('updatenotification', 'View changelog'),
					icon: 'icon-link',
					target: '_blank',
					action: '',
				})
			}
			return whatsNew
		},

		channelList() {
			const channelList = []

			channelList.push({
				text: t('updatenotification', 'Enterprise'),
				longtext: t('updatenotification', 'For enterprise use. Provides always the latest patch level, but will not update to the next major release immediately. That update happens once Nextcloud GmbH has done additional hardening and testing for large-scale and mission-critical deployments. This channel is only available to customers and provides the Nextcloud Enterprise package.'),
				icon: 'icon-star',
				active: this.currentChannel === 'enterprise',
				disabled: !this.hasValidSubscription,
				action: this.changeReleaseChannelToEnterprise,
			})

			channelList.push({
				text: t('updatenotification', 'Stable'),
				longtext: t('updatenotification', 'The most recent stable version. It is suited for regular use and will always update to the latest major version.'),
				icon: 'icon-checkmark',
				active: this.currentChannel === 'stable',
				action: this.changeReleaseChannelToStable,
			})

			channelList.push({
				text: t('updatenotification', 'Beta'),
				longtext: t('updatenotification', 'A pre-release version only for testing new features, not for production environments.'),
				icon: 'icon-category-customization',
				active: this.currentChannel === 'beta',
				action: this.changeReleaseChannelToBeta,
			})

			if (this.isNonDefaultChannel) {
				channelList.push({
					text: this.currentChannel,
					icon: 'icon-rename',
					active: true,
				})
			}

			return channelList
		},

		isNonDefaultChannel() {
			return this.currentChannel !== 'enterprise' && this.currentChannel !== 'stable' && this.currentChannel !== 'beta'
		},

		localizedChannelName() {
			switch (this.currentChannel) {
			case 'enterprise':
				return t('updatenotification', 'Enterprise')
			case 'stable':
				return t('updatenotification', 'Stable')
			case 'beta':
				return t('updatenotification', 'Beta')
			default:
				return this.currentChannel
			}
		},
	},

	watch: {
		notifyGroups(selectedOptions) {
			if (!this.enableChangeWatcher) {
				return
			}

			const selectedGroups = []
			_.each(selectedOptions, function(group) {
				selectedGroups.push(group.value)
			})

			OCP.AppConfig.setValue('updatenotification', 'notify_groups', JSON.stringify(selectedGroups))
		},
		isNewVersionAvailable() {
			if (!this.isNewVersionAvailable) {
				return
			}

			$.ajax({
				url: generateOcsUrl('apps/updatenotification/api/v1/applist', 2) + this.newVersion,
				type: 'GET',
				beforeSend(request) {
					request.setRequestHeader('Accept', 'application/json')
				},
				success: function(response) {
					this.availableAppUpdates = response.ocs.data.available
					this.missingAppUpdates = response.ocs.data.missing
					this.isListFetched = true
					this.appStoreFailed = false
				}.bind(this),
				error: function(xhr) {
					this.availableAppUpdates = []
					this.missingAppUpdates = []
					this.appStoreDisabled = xhr.responseJSON.ocs.data.appstore_disabled
					this.isListFetched = true
					this.appStoreFailed = true
				}.bind(this),
			})
		},
	},
	beforeMount() {
		// Parse server data
		const data = JSON.parse($('#updatenotification').attr('data-json'))

		this.newVersion = data.newVersion
		this.newVersionString = data.newVersionString
		this.lastCheckedDate = data.lastChecked
		this.isUpdateChecked = data.isUpdateChecked
		this.webUpdaterEnabled = data.webUpdaterEnabled
		this.updaterEnabled = data.updaterEnabled
		this.downloadLink = data.downloadLink
		this.isNewVersionAvailable = data.isNewVersionAvailable
		this.updateServerURL = data.updateServerURL
		this.currentChannel = data.currentChannel
		this.channels = data.channels
		this.notifyGroups = data.notifyGroups
		this.isDefaultUpdateServerURL = data.isDefaultUpdateServerURL
		this.versionIsEol = data.versionIsEol
		this.hasValidSubscription = data.hasValidSubscription
		if (data.changes && data.changes.changelogURL) {
			this.changelogURL = data.changes.changelogURL
		}
		if (data.changes && data.changes.whatsNew) {
			if (data.changes.whatsNew.admin) {
				this.whatsNewData = this.whatsNewData.concat(data.changes.whatsNew.admin)
			}
			this.whatsNewData = this.whatsNewData.concat(data.changes.whatsNew.regular)
		}
	},
	mounted() {
		this._$el = $(this.$el)
		this._$notifyGroups = this._$el.find('#oca_updatenotification_groups_list')
		this._$notifyGroups.on('change', function() {
			this.$emit('input')
		}.bind(this))

		$.ajax({
			url: generateOcsUrl('cloud', 2) + '/groups',
			dataType: 'json',
			success: function(data) {
				const results = []
				$.each(data.ocs.data.groups, function(i, group) {
					results.push({ value: group, label: group })
				})

				this.availableGroups = results
				this.enableChangeWatcher = true
			}.bind(this),
		})
	},

	methods: {
		/**
			 * Creates a new authentication token and loads the updater URL
			 */
		clickUpdaterButton() {
			$.ajax({
				url: generateUrl('/apps/updatenotification/credentials'),
			}).success(function(token) {
				// create a form to send a proper post request to the updater
				const form = document.createElement('form')
				form.setAttribute('method', 'post')
				form.setAttribute('action', getRootUrl() + '/updater/')

				const hiddenField = document.createElement('input')
				hiddenField.setAttribute('type', 'hidden')
				hiddenField.setAttribute('name', 'updater-secret-input')
				hiddenField.setAttribute('value', token)

				form.appendChild(hiddenField)

				document.body.appendChild(form)
				form.submit()
			})
		},
		changeReleaseChannelToEnterprise() {
			this.changeReleaseChannel('enterprise')
		},
		changeReleaseChannelToStable() {
			this.changeReleaseChannel('stable')
		},
		changeReleaseChannelToBeta() {
			this.changeReleaseChannel('beta')
		},
		changeReleaseChannel(channel) {
			this.currentChannel = channel

			$.ajax({
				url: generateUrl('/apps/updatenotification/channel'),
				type: 'POST',
				data: {
					channel: this.currentChannel,
				},
				success(data) {
					OC.msg.finishedAction('#channel_save_msg', data)
				},
			})

			this.openedUpdateChannelMenu = false
		},
		toggleUpdateChannelMenu() {
			this.openedUpdateChannelMenu = !this.openedUpdateChannelMenu
		},
		toggleHideMissingUpdates() {
			this.hideMissingUpdates = !this.hideMissingUpdates
		},
		toggleHideAvailableUpdates() {
			this.hideAvailableUpdates = !this.hideAvailableUpdates
		},
		toggleMenu() {
			this.openedWhatsNew = !this.openedWhatsNew
		},
		closeUpdateChannelMenu() {
			this.openedUpdateChannelMenu = false
		},
		hideMenu() {
			this.openedWhatsNew = false
		},
	},
}
</script>

<style lang="scss" scoped>
	#updatenotification {
		margin-top: -25px;
		margin-bottom: 200px;
		div.update,
		p:not(.inlineblock) {
			margin-bottom: 25px;
		}
		h2.inlineblock {
			margin-top: 25px;
		}
		h3 {
			cursor: pointer;
			.icon {
				cursor: pointer;
			}
			&:first-of-type {
				margin-top: 0;
			}
			&.update-channel-selector {
				display: inline-block;
				cursor: inherit;
			}
		}
		.icon {
			display: inline-block;
			margin-bottom: -3px;
		}
		.icon-triangle-s, .icon-triangle-n {
			opacity: 0.5;
		}
		.whatsNew {
			display: inline-block;
		}
		.toggleWhatsNew {
			position: relative;
		}
		.popovermenu {
			p {
				margin-bottom: 0;
				width: 100%;
			}
			margin-top: 5px;
			width: 300px;
		}
		.applist {
			margin-bottom: 25px;
		}

		.update-menu {
			position: relative;
			cursor: pointer;
			margin-left: 3px;
			display: inline-block;
			.icon-update-menu {
				cursor: inherit;
				.icon-triangle-s {
					display: inline-block;
					vertical-align: middle;
					cursor: inherit;
					opacity: 1;
				}
			}
			.popovermenu {
				display: none;
				top: 28px;
				&.show-menu {
					display: block;
				}
			}
		}
	}
</style>
<style lang="scss">
	/* override needed to make menu wider */
	#updatenotification .popovermenu {
		p {
			margin-top: 5px;
			width: 100%;
		}
		margin-top: 5px;
		width: 300px;
	}
	/* override needed to replace yellow hover state with a dark one */
	#updatenotification .update-menu .icon-star:hover,
	#updatenotification .update-menu .icon-star:focus {
		background-image: var(--icon-star-000);
	}

	#updatenotification .topMargin {
		margin-top: 15px;
	}
</style>
