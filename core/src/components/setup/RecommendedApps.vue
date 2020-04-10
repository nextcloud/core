<!--
  - @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
  -
  - @author 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
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
  - along with this program.  If not, see <http://www.gnu.org/licenses/>.
  -->

<template>
	<div class="body-login-container">
		<h2>{{ t('core', 'Recommended apps') }}</h2>
		<p v-if="loadingApps" class="loading text-center">
			{{ t('core', 'Loading apps …') }}
		</p>
		<p v-else-if="loadingAppsError" class="loading-error text-center">
			{{ t('core', 'Could not fetch list of apps from the app store.') }}
		</p>
		<p v-else class="text-center">
			{{ t('core', 'Installing apps …') }}
		</p>
		<div v-for="app in recommendedApps" :key="app.id" class="app">
			<img :src="customIcon(app.id)" :alt="t('core', 'Nextcloud {app}', { app: app.name })">
			<div class="info">
				<h3>
					{{ app.name }}
					<span v-if="app.loading" class="icon icon-loading-small" />
					<span v-else-if="app.active" class="icon icon-checkmark-white" />
				</h3>
				<p v-html="customDescription(app.id)" />
				<p v-if="app.installationError">
					<strong>{{ t('core', 'App download or installation failed') }}</strong>
				</p>
				<p v-else-if="!app.isCompatible">
					<strong>{{ t('core', 'Can\'t install this app because it is not compatible') }}</strong>
				</p>
				<p v-else-if="!app.canInstall">
					<strong>{{ t('core', 'Can\'t install this app') }}</strong>
				</p>
			</div>
		</div>
		<p class="text-center">
			<a :href="defaultPageUrl">{{ t('core', 'Cancel') }}</a>
		</p>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl, imagePath } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import pLimit from 'p-limit'
import { translate as t } from '@nextcloud/l10n'

import logger from '../../logger'

const recommended = {
	calendar: {
		description: t('core', 'Schedule work & meetings, synced with all your devices.'),
		icon: imagePath('core', 'places/calendar.svg'),
	},
	contacts: {
		description: t('core', 'Keep your colleagues and friends in one place without leaking their private info.'),
		icon: imagePath('core', 'places/contacts.svg'),
	},
	mail: {
		description: t('core', 'Simple email app nicely integrated with Files, Contacts and Calendar.'),
		icon: imagePath('core', 'actions/mail.svg'),
	},
	spreed: {
		description: t('core', 'Chatting, video calls, screensharing, online meetings and web conferencing – in your browser and with mobile apps.'),
	},
	onlyoffice: {
		description: t('core', 'Collaboratively edit office documents.'),
	},
	documentserver_community: {
		description: t('core', 'Local document editing back-end used by the OnlyOffice app.'),
	},
}
const recommendedIds = Object.keys(recommended)
const defaultPageUrl = loadState('core', 'defaultPageUrl')

export default {
	name: 'RecommendedApps',
	data() {
		return {
			loadingApps: true,
			loadingAppsError: false,
			apps: [],
			defaultPageUrl,
		}
	},
	computed: {
		recommendedApps() {
			return this.apps.filter(app => recommendedIds.includes(app.id))
		},
	},
	mounted() {
		return axios.get(generateUrl('settings/apps/list'))
			.then(resp => resp.data)
			.then(data => {
				logger.info(`${data.apps.length} apps fetched`)

				this.apps = data.apps.map(app => Object.assign(app, { loading: false, installationError: false }))
				logger.debug(`${this.recommendedApps.length} recommended apps found`, { apps: this.recommendedApps })

				this.installApps()
			})
			.catch(error => {
				logger.error('could not fetch app list', { error })

				this.loadingAppsError = true
			})
			.then(() => {
				this.loadingApps = false
			})
	},
	methods: {
		installApps() {
			const limit = pLimit(1)
			const installing = this.recommendedApps
				.filter(app => !app.active && app.isCompatible && app.canInstall)
				.map(app => limit(() => {
					logger.info(`installing ${app.id}`)
					app.loading = true
					return axios.post(generateUrl(`settings/apps/enable`), { appIds: [app.id], groups: [] })
						.catch(error => {
							logger.error(`could not install ${app.id}`, { error })
							app.installationError = true
						})
						.then(() => {
							logger.info(`installed ${app.id}`)
							app.loading = false
						})
				}))
			logger.debug(`installing ${installing.length} recommended apps`)
			Promise.all(installing)
				.then(() => {
					logger.info('all recommended apps installed, redirecting …')

					window.location = defaultPageUrl
				})
				.catch(error => logger.error('could not install recommended apps', { error }))
		},
		customIcon(appId) {
			if (!(appId in recommended) || !recommended[appId].icon) {
				logger.warn(`no app icon for recommended app ${appId}`)
				return imagePath('core', 'places/default-app-icon.svg')
			}
			return recommended[appId].icon
		},
		customDescription(appId) {
			if (!(appId in recommended)) {
				logger.warn(`no app description for recommended app ${appId}`)
				return ''
			}
			return recommended[appId].description
		},
	},
}
</script>

<style lang="scss" scoped>
.body-login-container {

}

p.loading, p.loading-error {
	height: 100px;
}

.text-center {
	text-align: center;
}

.app {
	display: flex;
	flex-direction: row;

	img {
		height: 50px;
		width: 50px;
		filter: invert(1);
	}

	img, .info {
		padding: 12px;
	}

	.info {
		h3, p {
			text-align: left;
		}

		h3 {
			color: #fff;
			margin-top: 0;
		}

		h3 > span.icon {
			display: inline-block;
		}
	}
}
</style>
