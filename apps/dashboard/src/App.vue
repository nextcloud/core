<template>
	<div id="app-dashboard" :style="backgroundStyle">
		<h2>{{ greeting.text }}</h2>
		<ul class="statuses">
			<div v-for="status in sortedRegisteredStatus"
				:id="'status-' + status"
				:key="status">
				<div :ref="'status-' + status" />
			</div>
		</ul>

		<Draggable v-model="layout"
			class="panels"
			handle=".panel--header"
			@end="saveLayout">
			<div v-for="panelId in layout" :key="panels[panelId].id" class="panel">
				<div class="panel--header">
					<h2 :class="panels[panelId].iconClass">
						{{ panels[panelId].title }}
					</h2>
				</div>
				<div class="panel--content" :class="{ loading: !panels[panelId].mounted }">
					<div :ref="panels[panelId].id" :data-id="panels[panelId].id" />
				</div>
			</div>
		</Draggable>

		<div class="footer">
			<a class="edit-panels icon-rename"
				tabindex="0"
				@click="showModal"
				@keyup.enter="showModal"
				@keyup.space="showModal">{{ t('dashboard', 'Customize') }}</a>
		</div>

		<Modal v-if="modal" @close="closeModal">
			<div class="modal__content">
				<h3>{{ t('dashboard', 'Edit widgets') }}</h3>
				<ol class="panels">
					<li v-for="status in sortedAllStatuses" :key="status">
						<input :id="'status-checkbox-' + status"
							type="checkbox"
							class="checkbox"
							:checked="isStatusActive(status)"
							@input="updateStatusCheckbox(status, $event.target.checked)">
						<label :for="'status-checkbox-' + status" :class="statusInfo[status].icon">
							{{ statusInfo[status].text }}
						</label>
					</li>
				</ol>
				<Draggable v-model="layout"
					class="panels"
					tag="ol"
					handle=".draggable"
					@end="saveLayout">
					<li v-for="panel in sortedPanels" :key="panel.id">
						<input :id="'panel-checkbox-' + panel.id"
							type="checkbox"
							class="checkbox"
							:checked="isActive(panel)"
							@input="updateCheckbox(panel, $event.target.checked)">
						<label :for="'panel-checkbox-' + panel.id" :class="isActive(panel) ? 'draggable ' + panel.iconClass : panel.iconClass">
							{{ panel.title }}
						</label>
					</li>
				</Draggable>

				<a v-if="isAdmin" :href="appStoreUrl" class="button">{{ t('dashboard', 'Get more widgets from the app store') }}</a>

				<h3>{{ t('dashboard', 'Change background image') }}</h3>
				<BackgroundSettings :background="background"
					:theming-default-background="themingDefaultBackground"
					@update:background="updateBackground" />

				<h3>{{ t('dashboard', 'Weather service') }}</h3>
				<p>
					{{ t('dashboard', 'For your privacy, the weather data is requested by your Nextcloud server on your behalf so the weather service receives no personal information.') }}
				</p>
				<p class="credits--end">
					<a href="https://api.met.no/doc/TermsOfService" target="_blank" rel="noopener">{{ t('dashboard', 'Weather data from Met.no') }}</a>,
					<a href="https://wiki.osmfoundation.org/wiki/Privacy_Policy" target="_blank" rel="noopener">{{ t('dashboard', 'geocoding with Nominatim') }}</a>,
					<a href="https://www.opentopodata.org/#public-api" target="_blank" rel="noopener">{{ t('dashboard', 'elevation data from OpenTopoData') }}</a>.
				</p>
			</div>
		</Modal>
	</div>
</template>

<script>
import Vue from 'vue'
import { loadState } from '@nextcloud/initial-state'
import { getCurrentUser } from '@nextcloud/auth'
import Modal from '@nextcloud/vue/dist/Components/Modal'
import Draggable from 'vuedraggable'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import isMobile from './mixins/isMobile'
import BackgroundSettings from './components/BackgroundSettings'
import getBackgroundUrl from './helpers/getBackgroundUrl'

const panels = loadState('dashboard', 'panels')
const firstRun = loadState('dashboard', 'firstRun')
const background = loadState('dashboard', 'background')
const themingDefaultBackground = loadState('dashboard', 'themingDefaultBackground')
const version = loadState('dashboard', 'version')
const shippedBackgroundList = loadState('dashboard', 'shippedBackgrounds')
const statusInfo = {
	weather: {
		text: t('dashboard', 'Weather'),
		icon: 'icon-weather-status',
	},
	status: {
		text: t('dashboard', 'Status'),
		icon: 'icon-user-status-online',
	},
}

export default {
	name: 'App',
	components: {
		Modal,
		Draggable,
		BackgroundSettings,
	},
	mixins: [
		isMobile,
	],
	data() {
		return {
			isAdmin: getCurrentUser().isAdmin,
			timer: new Date(),
			registeredStatus: [],
			callbacks: {},
			callbacksStatus: {},
			allCallbacksStatus: {},
			statusInfo,
			enabledStatuses: loadState('dashboard', 'statuses'),
			panels,
			firstRun,
			displayName: getCurrentUser()?.displayName,
			uid: getCurrentUser()?.uid,
			layout: loadState('dashboard', 'layout').filter((panelId) => panels[panelId]),
			modal: false,
			appStoreUrl: generateUrl('/settings/apps/dashboard'),
			statuses: {},
			background,
			themingDefaultBackground,
			version,
		}
	},
	computed: {
		backgroundImage() {
			return getBackgroundUrl(this.background, this.version, this.themingDefaultBackground)
		},
		backgroundStyle() {
			if ((this.background === 'default' && this.themingDefaultBackground === 'backgroundColor')
				|| this.background.match(/#[0-9A-Fa-f]{6}/g)) {
				return null
			}
			return {
				backgroundImage: `url(${this.backgroundImage})`,
			}
		},
		greeting() {
			const time = this.timer.getHours()

			// Determine part of the day
			let partOfDay
			if (time >= 22 || time < 5) {
				partOfDay = 'night'
			} else if (time >= 18) {
				partOfDay = 'evening'
			} else if (time >= 12) {
				partOfDay = 'afternoon'
			} else {
				partOfDay = 'morning'
			}

			// Define the greetings
			const good = {
				morning: {
					generic: t('dashboard', 'Good morning'),
					withName: t('dashboard', 'Good morning, {name}', { name: this.displayName }),
				},
				afternoon: {
					generic: t('dashboard', 'Good afternoon'),
					withName: t('dashboard', 'Good afternoon, {name}', { name: this.displayName }),
				},
				evening: {
					generic: t('dashboard', 'Good evening'),
					withName: t('dashboard', 'Good evening, {name}', { name: this.displayName }),
				},
				night: {
					// Don't use "Good night" as it's not a greeting
					generic: t('dashboard', 'Hello'),
					withName: t('dashboard', 'Hello, {name}', { name: this.displayName }),
				},
			}

			// Figure out which greeting to show
			const shouldShowName = this.displayName && this.uid !== this.displayName
			return { text: shouldShowName ? good[partOfDay].withName : good[partOfDay].generic }
		},
		isActive() {
			return (panel) => this.layout.indexOf(panel.id) > -1
		},
		isStatusActive() {
			return (status) => !(status in this.enabledStatuses) || this.enabledStatuses[status]
		},
		sortedAllStatuses() {
			return Object.keys(this.allCallbacksStatus).slice().sort(this.sortStatuses)
		},
		sortedPanels() {
			return Object.values(this.panels).sort((a, b) => {
				const indexA = this.layout.indexOf(a.id)
				const indexB = this.layout.indexOf(b.id)
				if (indexA === -1 || indexB === -1) {
					return indexB - indexA || a.id - b.id
				}
				return indexA - indexB || a.id - b.id
			})
		},
		sortedRegisteredStatus() {
			return this.registeredStatus.slice().sort(this.sortStatuses)
		},
	},
	watch: {
		callbacks() {
			this.rerenderPanels()
		},
		callbacksStatus() {
			for (const app in this.callbacksStatus) {
				const element = this.$refs['status-' + app]
				if (this.statuses[app] && this.statuses[app].mounted) {
					continue
				}
				if (element) {
					this.callbacksStatus[app](element[0])
					Vue.set(this.statuses, app, { mounted: true })
				} else {
					console.error('Failed to register panel in the frontend as no backend data was provided for ' + app)
				}
			}
		},
	},
	mounted() {
		this.updateGlobalStyles()
		this.updateSkipLink()
		window.addEventListener('scroll', this.handleScroll)

		setInterval(() => {
			this.timer = new Date()
		}, 30000)

		if (this.firstRun) {
			window.addEventListener('scroll', this.disableFirstrunHint)
		}
	},
	destroyed() {
		window.removeEventListener('scroll', this.handleScroll)
	},
	methods: {
		/**
		 * Method to register panels that will be called by the integrating apps
		 *
		 * @param {string} app The unique app id for the widget
		 * @param {function} callback The callback function to register a panel which gets the DOM element passed as parameter
		 */
		register(app, callback) {
			Vue.set(this.callbacks, app, callback)
		},
		registerStatus(app, callback) {
			// always save callbacks in case user enables the status later
			Vue.set(this.allCallbacksStatus, app, callback)
			// register only if status is enabled or missing from config
			if (this.isStatusActive(app)) {
				this.registeredStatus.push(app)
				this.$nextTick(() => {
					Vue.set(this.callbacksStatus, app, callback)
				})
			}
		},
		rerenderPanels() {
			for (const app in this.callbacks) {
				const element = this.$refs[app]
				if (this.layout.indexOf(app) === -1) {
					continue
				}
				if (this.panels[app] && this.panels[app].mounted) {
					continue
				}
				if (element) {
					this.callbacks[app](element[0], {
						widget: this.panels[app],
					})
					Vue.set(this.panels[app], 'mounted', true)
				} else {
					console.error('Failed to register panel in the frontend as no backend data was provided for ' + app)
				}
			}
		},
		saveLayout() {
			axios.post(generateUrl('/apps/dashboard/layout'), {
				layout: this.layout.join(','),
			})
		},
		saveStatuses() {
			axios.post(generateUrl('/apps/dashboard/statuses'), {
				statuses: JSON.stringify(this.enabledStatuses),
			})
		},
		showModal() {
			this.modal = true
			this.firstRun = false
		},
		closeModal() {
			this.modal = false
		},
		updateCheckbox(panel, currentValue) {
			const index = this.layout.indexOf(panel.id)
			if (!currentValue && index > -1) {
				this.layout.splice(index, 1)

			} else {
				this.layout.push(panel.id)
			}
			Vue.set(this.panels[panel.id], 'mounted', false)
			this.saveLayout()
			this.$nextTick(() => this.rerenderPanels())
		},
		disableFirstrunHint() {
			window.removeEventListener('scroll', this.disableFirstrunHint)
			setTimeout(() => {
				this.firstRun = false
			}, 1000)
		},
		updateBackground(data) {
			this.background = data.type === 'custom' || data.type === 'default' ? data.type : data.value
			this.version = data.version
			this.updateGlobalStyles()
		},
		updateGlobalStyles() {
			document.body.setAttribute('data-dashboard-background', this.background)
			if (window.OCA.Theming.inverted) {
				document.body.classList.add('dashboard--inverted')
			}

			const shippedBackgroundTheme = shippedBackgroundList[this.background] ? shippedBackgroundList[this.background].theming : 'light'
			if (shippedBackgroundTheme === 'dark') {
				document.body.classList.add('dashboard--dark')
			} else {
				document.body.classList.remove('dashboard--dark')
			}
		},
		updateSkipLink() {
			// Make sure "Skip to main content" link points to the app content
			document.getElementsByClassName('skip-navigation')[0].setAttribute('href', '#app-dashboard')
		},
		updateStatusCheckbox(app, checked) {
			if (checked) {
				this.enableStatus(app)
			} else {
				this.disableStatus(app)
			}
		},
		enableStatus(app) {
			this.enabledStatuses[app] = true
			this.registerStatus(app, this.allCallbacksStatus[app])
			this.saveStatuses()
		},
		disableStatus(app) {
			this.enabledStatuses[app] = false
			const i = this.registeredStatus.findIndex((s) => s === app)
			if (i !== -1) {
				this.registeredStatus.splice(i, 1)
				Vue.set(this.statuses, app, { mounted: false })
				this.$nextTick(() => {
					Vue.delete(this.callbacksStatus, app)
				})
			}
			this.saveStatuses()
		},
		sortStatuses(a, b) {
			const al = a.toLowerCase()
			const bl = b.toLowerCase()
			return al > bl
				? 1
				: al < bl
					? -1
					: 0
		},
		handleScroll() {
			if (window.scrollY > 70) {
				document.body.classList.add('dashboard--scrolled')
			} else {
				document.body.classList.remove('dashboard--scrolled')
			}
		},
	},
}
</script>

<style lang="scss" scoped>
#app-dashboard {
	width: 100%;
	background-size: cover;
	background-position: center center;
	background-repeat: no-repeat;
	background-attachment: fixed;
	background-color: var(--color-primary);
	--color-background-translucent: rgba(255, 255, 255, 0.8);
	--background-blur: blur(10px);

	#body-user.theme--dark & {
		background-color: var(--color-main-background);
		--color-background-translucent: rgba(24, 24, 24, 0.8);
	}

	#body-user.theme--highcontrast & {
		background-color: var(--color-main-background);
		--color-background-translucent: var(--color-main-background);
	}

	> h2 {
		color: var(--color-primary-text);
		text-align: center;
		font-size: 32px;
		line-height: 130%;
		padding: 10vh 16px 0px;
	}
}

.panels {
	width: auto;
	margin: auto;
	max-width: 1500px;
	display: flex;
	justify-content: center;
	flex-direction: row;
	align-items: flex-start;
	flex-wrap: wrap;
}

.panel, .panels > div {
	width: 320px;
	max-width: 100%;
	margin: 16px;
	background-color: var(--color-background-translucent);
	-webkit-backdrop-filter: var(--background-blur);
	backdrop-filter: var(--background-blur);
	border-radius: var(--border-radius-large);

	#body-user.theme--highcontrast & {
		border: 2px solid var(--color-border);
	}

	&.sortable-ghost {
		 opacity: 0.1;
	}

	& > .panel--header {
		display: flex;
		z-index: 1;
		top: 50px;
		padding: 16px;
		cursor: grab;

		&, ::v-deep * {
			-webkit-touch-callout: none;
			-webkit-user-select: none;
			-khtml-user-select: none;
			-moz-user-select: none;
			-ms-user-select: none;
			user-select: none;
		}

		&:active {
			cursor: grabbing;
		}

		a {
			flex-grow: 1;
		}

		> h2 {
			display: block;
			flex-grow: 1;
			margin: 0;
			font-size: 20px;
			line-height: 24px;
			font-weight: bold;
			background-size: 32px;
			background-position: 14px 12px;
			padding: 16px 8px 16px 60px;
			height: 56px;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
			cursor: grab;
		}
	}

	& > .panel--content {
		margin: 0 16px 16px 16px;
		height: 420px;
		// We specifically do not want scrollbars inside widgets
		overflow: hidden;
	}

	// No need to extend height of widgets if only one column is shown
	@media only screen and (max-width: 709px) {
		& > .panel--content {
			height: auto;
		}
	}
}

.footer {
	text-align: center;
	transition: bottom var(--animation-slow) ease-in-out;
	bottom: 0;
	padding: 44px 0;
}

.edit-panels {
	display: inline-block;
	margin:auto;
	background-position: 16px center;
	padding: 12px 16px;
	padding-left: 36px;
	border-radius: var(--border-radius-pill);
	max-width: 200px;
	opacity: 1;
	text-align: center;
}

.edit-panels,
.statuses ::v-deep .action-item .action-item__menutoggle,
.statuses ::v-deep .action-item.action-item--open .action-item__menutoggle {
	background-color: var(--color-background-translucent);
	-webkit-backdrop-filter: var(--background-blur);
	backdrop-filter: var(--background-blur);

	&:hover,
	&:focus,
	&:active {
		background-color: var(--color-background-hover);
	}
}

.modal__content {
	padding: 32px 16px;
	max-height: 70vh;
	text-align: center;
	overflow: auto;

	ol {
		display: flex;
		flex-direction: row;
		justify-content: center;
		list-style-type: none;
		padding-bottom: 16px;
	}
	li {
		label {
			display: block;
			padding: 48px 8px 16px 8px;
			margin: 8px;
			width: 160px;
			background-color: var(--color-background-hover);
			border: 2px solid var(--color-main-background);
			border-radius: var(--border-radius-large);
			background-size: 24px;
			background-position: center 16px;
			text-align: center;

			&:hover {
				border-color: var(--color-primary);
			}
		}

		input:focus + label {
			border-color: var(--color-primary);
		}
	}

	h3 {
		font-weight: bold;

		&:not(:first-of-type) {
			margin-top: 64px;
		}
	}

	// Adjust design of 'Get more widgets' button
	.button {
		display: inline-block;
		padding: 10px 16px;
		margin: 0;
	}

	p {
		max-width: 650px;
		margin: 0 auto;

		a:hover,
		a:focus {
			border-bottom: 2px solid var(--color-border);
		}
	}

	.credits--end {
		padding-bottom: 32px;
		color: var(--color-text-maxcontrast);

		a {
			color: var(--color-text-maxcontrast);
		}
	}
}

.flip-list-move {
	transition: transform var(--animation-slow);
}

.statuses {
	display: flex;
	flex-direction: row;
	justify-content: center;
	flex-wrap: wrap;
	margin-bottom: 36px;

	& > div {
		margin: 8px;
	}
}
</style>
