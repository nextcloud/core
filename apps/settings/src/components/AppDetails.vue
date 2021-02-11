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
	<div class="app-details">
		<div class="app-details__actions">
			<div v-if="app.active && canLimitToGroups(app)" class="app-details__actions-groups">
				<input :id="prefix('groups_enable', app.id)"
					v-model="groupCheckedAppsData"
					type="checkbox"
					:value="app.id"
					class="groups-enable__checkbox checkbox"
					@change="setGroupLimit">
				<label :for="prefix('groups_enable', app.id)">{{ t('settings', 'Limit to groups') }}</label>
				<input type="hidden"
					class="group_select"
					:title="t('settings', 'All')"
					value="">
				<Multiselect v-if="isLimitedToGroups(app)"
					:options="groups"
					:value="appGroups"
					:options-limit="5"
					:placeholder="t('settings', 'Limit app usage to groups')"
					label="name"
					track-by="id"
					class="multiselect-vue"
					:multiple="true"
					:close-on-select="false"
					:tag-width="60"
					@select="addGroupLimitation"
					@remove="removeGroupLimitation"
					@search-change="asyncFindGroup">
					<span slot="noResult">{{ t('settings', 'No results') }}</span>
				</Multiselect>
			</div>
			<div class="app-details__actions-manage">
				<input v-if="app.update"
					class="update primary"
					type="button"
					:value="t('settings', 'Update to {version}', { version: app.update })"
					:disabled="installing || isLoading"
					@click="update(app.id)">
				<input v-if="app.canUnInstall"
					class="uninstall"
					type="button"
					:value="t('settings', 'Remove')"
					:disabled="installing || isLoading"
					@click="remove(app.id)">
				<input v-if="app.active"
					class="enable"
					type="button"
					:value="t('settings','Disable')"
					:disabled="installing || isLoading"
					@click="disable(app.id)">
				<input v-if="!app.active && (app.canInstall || app.isCompatible)"
					v-tooltip.auto="enableButtonTooltip"
					class="enable primary"
					type="button"
					:value="enableButtonText"
					:disabled="!app.canInstall || installing || isLoading"
					@click="enable(app.id)">
				<input v-else-if="!app.active && !app.canInstall"
					v-tooltip.auto="forceEnableButtonTooltip"
					class="enable force"
					type="button"
					:value="forceEnableButtonText"
					:disabled="installing || isLoading"
					@click="forceEnable(app.id)">
			</div>
		</div>

		<ul class="app-details__dependencies">
			<li v-if="app.missingMinOwnCloudVersion">
				{{ t('settings', 'This app has no minimum Nextcloud version assigned. This will be an error in the future.') }}
			</li>
			<li v-if="app.missingMaxOwnCloudVersion">
				{{ t('settings', 'This app has no maximum Nextcloud version assigned. This will be an error in the future.') }}
			</li>
			<li v-if="!app.canInstall">
				{{ t('settings', 'This app cannot be installed because the following dependencies are not fulfilled:') }}
				<ul class="missing-dependencies">
					<li v-for="(dep, index) in app.missingDependencies" :key="index">
						{{ dep }}
					</li>
				</ul>
			</li>
		</ul>

		<p class="app-details__documentation">
			<a v-if="!app.internal"
				class="appslink"
				:href="appstoreUrl"
				target="_blank"
				rel="noreferrer noopener">{{ t('settings', 'View in store') }} ↗</a>

			<a v-if="app.website"
				class="appslink"
				:href="app.website"
				target="_blank"
				rel="noreferrer noopener">{{ t('settings', 'Visit website') }} ↗</a>
			<a v-if="app.bugs"
				class="appslink"
				:href="app.bugs"
				target="_blank"
				rel="noreferrer noopener">{{ t('settings', 'Report a bug') }} ↗</a>

			<a v-if="app.documentation && app.documentation.user"
				class="appslink"
				:href="app.documentation.user"
				target="_blank"
				rel="noreferrer noopener">{{ t('settings', 'User documentation') }} ↗</a>
			<a v-if="app.documentation && app.documentation.admin"
				class="appslink"
				:href="app.documentation.admin"
				target="_blank"
				rel="noreferrer noopener">{{ t('settings', 'Admin documentation') }} ↗</a>
			<a v-if="app.documentation && app.documentation.developer"
				class="appslink"
				:href="app.documentation.developer"
				target="_blank"
				rel="noreferrer noopener">{{ t('settings', 'Developer documentation') }} ↗</a>
		</p>
		<Markdown class="app-details__description" :text="app.description" />
	</div>
</template>

<script>
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'

import AppManagement from '../mixins/AppManagement'
import PrefixMixin from './PrefixMixin'
import Markdown from './Markdown'

export default {
	name: 'AppDetails',

	components: {
		Multiselect,
		Markdown,
	},
	mixins: [AppManagement, PrefixMixin],

	props: {
		app: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			groupCheckedAppsData: false,
		}
	},

	computed: {
		appstoreUrl() {
			return `https://apps.nextcloud.com/apps/${this.app.id}`
		},
		licence() {
			if (this.app.licence) {
				return t('settings', '{license}-licensed', { license: ('' + this.app.licence).toUpperCase() })
			}
			return null
		},
		author() {
			if (typeof this.app.author === 'string') {
				return [
					{
						'@value': this.app.author,
					},
				]
			}
			if (this.app.author['@value']) {
				return [this.app.author]
			}
			return this.app.author
		},
		appGroups() {
			return this.app.groups.map(group => { return { id: group, name: group } })
		},
		groups() {
			return this.$store.getters.getGroups
				.filter(group => group.id !== 'disabled')
				.sort((a, b) => a.name.localeCompare(b.name))
		},
	},
	mounted() {
		if (this.app.groups.length > 0) {
			this.groupCheckedAppsData = true
		}
	},
}
</script>

<style scoped lang="scss">
.app-details {
	padding: 20px;

	&__actions {
		// app management
		&-manage {
			// if too many, shrink them and ellipsis
			display: flex;
			input {
				flex: 0 1 auto;
				min-width: 0;
				text-overflow: ellipsis;
				white-space: nowrap;
				overflow: hidden;
			}
		}
	}
	&__dependencies {
		opacity: .7;
	}
	&__documentation {
		padding-top: 20px;
	}
	&__description {
		padding-top: 20px;
	}
}

.force {
	color: var(--color-error);
	border-color: var(--color-error);
	background: var(--color-main-background);
}
.force:hover,
.force:active {
	color: var(--color-main-background);
	border-color: var(--color-error) !important;
	background: var(--color-error);
}

</style>
