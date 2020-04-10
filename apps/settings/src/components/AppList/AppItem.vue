<!--
  - @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
  -
  - @author Julius Härtl <jus@bitgrid.net>
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
	<div class="section" :class="{ selected: isSelected }" @click="showAppDetails">
		<div class="app-image app-image-icon" @click="showAppDetails">
			<div v-if="(listView && !app.preview) || (!listView && !app.screenshot)" class="icon-settings-dark" />

			<svg v-if="listView && app.preview"
				width="32"
				height="32"
				viewBox="0 0 32 32">
				<defs><filter :id="filterId"><feColorMatrix in="SourceGraphic" type="matrix" values="-1 0 0 0 1 0 -1 0 0 1 0 0 -1 0 1 0 0 0 1 0" /></filter></defs>
				<image x="0"
					y="0"
					width="32"
					height="32"
					preserveAspectRatio="xMinYMin meet"
					:filter="filterUrl"
					:xlink:href="app.preview"
					class="app-icon" />
			</svg>

			<img v-if="!listView && app.screenshot" :src="app.screenshot" width="100%">
		</div>
		<div class="app-name" @click="showAppDetails">
			{{ app.name }}
		</div>
		<div v-if="!listView" class="app-summary">
			{{ app.summary }}
		</div>
		<div v-if="listView" class="app-version">
			<span v-if="app.version">{{ app.version }}</span>
			<span v-else-if="app.appstoreData.releases[0].version">{{ app.appstoreData.releases[0].version }}</span>
		</div>

		<div class="app-level">
			<span v-if="app.level === 300"
				v-tooltip.auto="t('settings', 'This app is supported via your current Nextcloud subscription.')"
				class="supported icon-checkmark-color">
				{{ t('settings', 'Supported') }}</span>
			<span v-if="app.level === 200"
				v-tooltip.auto="t('settings', 'Featured apps are developed by and within the community. They offer central functionality and are ready for production use.')"
				class="official icon-checkmark">
				{{ t('settings', 'Featured') }}</span>
			<AppScore v-if="hasRating && !listView" :score="app.score" />
		</div>

		<div class="actions">
			<div v-if="app.error" class="warning">
				{{ app.error }}
			</div>
			<div v-if="loading(app.id)" class="icon icon-loading-small" />
			<input v-if="app.update"
				class="update primary"
				type="button"
				:value="t('settings', 'Update to {update}', {update:app.update})"
				:disabled="installing || loading(app.id)"
				@click.stop="update(app.id)">
			<input v-if="app.canUnInstall"
				class="uninstall"
				type="button"
				:value="t('settings', 'Remove')"
				:disabled="installing || loading(app.id)"
				@click.stop="remove(app.id)">
			<input v-if="app.active"
				class="enable"
				type="button"
				:value="t('settings','Disable')"
				:disabled="installing || loading(app.id)"
				@click.stop="disable(app.id)">
			<input v-if="!app.active && (app.canInstall || app.isCompatible)"
				v-tooltip.auto="enableButtonTooltip"
				class="enable"
				type="button"
				:value="enableButtonText"
				:disabled="!app.canInstall || installing || loading(app.id)"
				@click.stop="enable(app.id)">
			<input v-else-if="!app.active"
				v-tooltip.auto="forceEnableButtonTooltip"
				class="enable force"
				type="button"
				:value="forceEnableButtonText"
				:disabled="installing || loading(app.id)"
				@click.stop="forceEnable(app.id)">
		</div>
	</div>
</template>

<script>
import AppScore from './AppScore'
import AppManagement from '../AppManagement'
import SvgFilterMixin from '../SvgFilterMixin'

export default {
	name: 'AppItem',
	components: {
		AppScore,
	},
	mixins: [AppManagement, SvgFilterMixin],
	props: {
		app: {},
		category: {},
		listView: {
			type: Boolean,
			default: true,
		},
	},
	data() {
		return {
			isSelected: false,
			scrolled: false,
		}
	},
	computed: {
		hasRating() {
			return this.app.appstoreData && this.app.appstoreData.ratingNumOverall > 5
		},
	},
	watch: {
		'$route.params.id': function(id) {
			this.isSelected = (this.app.id === id)
		},
	},
	mounted() {
		this.isSelected = (this.app.id === this.$route.params.id)
	},
	watchers: {

	},
	methods: {
		async showAppDetails(event) {
			if (event.currentTarget.tagName === 'INPUT' || event.currentTarget.tagName === 'A') {
				return
			}
			try {
				await this.$router.push({
					name: 'apps-details',
					params: { category: this.category, id: this.app.id },
				})
			} catch (e) {
				// we already view this app
			}
		},
		prefix(prefix, content) {
			return prefix + '_' + content
		},
	},
}
</script>

<style scoped>
	.force {
		background: var(--color-main-background);
		border-color: var(--color-error);
		color: var(--color-error);
	}
	.force:hover,
	.force:active {
		background: var(--color-error);
		border-color: var(--color-error) !important;
		color: var(--color-main-background);
	}
</style>
