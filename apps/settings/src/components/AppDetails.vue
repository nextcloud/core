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
	<div id="app-details-view" style="padding: 20px;">
		<h2>
			<div v-if="!app.preview" class="icon-settings-dark" />
			<svg v-if="app.previewAsIcon && app.preview"
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
			{{ app.name }}
		</h2>
		<img v-if="app.screenshot" :src="app.screenshot" width="100%">
		<div v-if="app.level === 300 || app.level === 200 || hasRating" class="app-level">
			<span v-if="app.level === 300"
				v-tooltip.auto="t('settings', 'This app is supported via your current Nextcloud subscription.')"
				class="supported icon-checkmark-color">
				{{ t('settings', 'Supported') }}</span>
			<span v-if="app.level === 200"
				v-tooltip.auto="t('settings', 'Featured apps are developed by and within the community. They offer central functionality and are ready for production use.')"
				class="official icon-checkmark">
				{{ t('settings', 'Featured') }}</span>
			<AppScore v-if="hasRating" :score="app.appstoreData.ratingOverall" />
		</div>

		<div v-if="author" class="app-author">
			{{ t('settings', 'by') }}
			<span v-for="(a, index) in author" :key="index">
				<a v-if="a['@attributes'] && a['@attributes']['homepage']" :href="a['@attributes']['homepage']">{{ a['@value'] }}</a><span v-else-if="a['@value']">{{ a['@value'] }}</span><span v-else>{{ a }}</span><span v-if="index+1 < author.length">, </span>
			</span>
		</div>
		<div v-if="licence" class="app-licence">
			{{ licence }}
		</div>
		<div class="actions">
			<div class="actions-buttons">
				<input v-if="app.update"
					class="update primary"
					type="button"
					:value="t('settings', 'Update to {version}', {version: app.update})"
					:disabled="installing || loading(app.id)"
					@click="update(app.id)">
				<input v-if="app.canUnInstall"
					class="uninstall"
					type="button"
					:value="t('settings', 'Remove')"
					:disabled="installing || loading(app.id)"
					@click="remove(app.id)">
				<input v-if="app.active"
					class="enable"
					type="button"
					:value="t('settings','Disable')"
					:disabled="installing || loading(app.id)"
					@click="disable(app.id)">
				<input v-if="!app.active && (app.canInstall || app.isCompatible)"
					v-tooltip.auto="enableButtonTooltip"
					class="enable primary"
					type="button"
					:value="enableButtonText"
					:disabled="!app.canInstall || installing || loading(app.id)"
					@click="enable(app.id)">
				<input v-else-if="!app.active"
					v-tooltip.auto="forceEnableButtonTooltip"
					class="enable force"
					type="button"
					:value="forceEnableButtonText"
					:disabled="installing || loading(app.id)"
					@click="forceEnable(app.id)">
			</div>
			<div class="app-groups">
				<div v-if="app.active && canLimitToGroups(app)" class="groups-enable">
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
			</div>
		</div>

		<ul class="app-dependencies">
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

		<p class="documentation">
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

		<div class="app-description" v-html="renderMarkdown" />
	</div>
</template>

<script>
import { Multiselect } from '@nextcloud/vue'
import marked from 'marked'
import dompurify from 'dompurify'

import AppScore from './AppList/AppScore'
import AppManagement from './AppManagement'
import PrefixMixin from './PrefixMixin'
import SvgFilterMixin from './SvgFilterMixin'

export default {
	name: 'AppDetails',
	components: {
		Multiselect,
		AppScore,
	},
	mixins: [AppManagement, PrefixMixin, SvgFilterMixin],
	props: ['category', 'app'],
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
		hasRating() {
			return this.app.appstoreData && this.app.appstoreData.ratingNumOverall > 5
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
		renderMarkdown() {
			const renderer = new marked.Renderer()
			renderer.link = function(href, title, text) {
				let prot
				try {
					prot = decodeURIComponent(unescape(href))
						.replace(/[^\w:]/g, '')
						.toLowerCase()
				} catch (e) {
					return ''
				}

				if (prot.indexOf('http:') !== 0 && prot.indexOf('https:') !== 0) {
					return ''
				}

				let out = '<a href="' + href + '" rel="noreferrer noopener"'
				if (title) {
					out += ' title="' + title + '"'
				}
				out += '>' + text + '</a>'
				return out
			}
			renderer.image = function(href, title, text) {
				if (text) {
					return text
				}
				return title
			}
			renderer.blockquote = function(quote) {
				return quote
			}
			return dompurify.sanitize(
				marked(this.app.description.trim(), {
					renderer: renderer,
					gfm: false,
					highlight: false,
					tables: false,
					breaks: false,
					pedantic: false,
					sanitize: true,
					smartLists: true,
					smartypants: false,
				}),
				{
					SAFE_FOR_JQUERY: true,
					ALLOWED_TAGS: [
						'strong',
						'p',
						'a',
						'ul',
						'ol',
						'li',
						'em',
						'del',
						'blockquote',
					],
				}
			)
		},
	},
	mounted() {
		if (this.app.groups.length > 0) {
			this.groupCheckedAppsData = true
		}
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
