<!--
  - @copyright Copyright (c) 2020 Georg Ehrke <oc.list@georgehrke.com>
  - @author Georg Ehrke <oc.list@georgehrke.com>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<li>
		<div class="user-status-menu-item">
			<!-- Username display -->
			<span
				v-if="!inline"
				class="user-status-menu-item__header"
				:title="displayName">
				{{ displayName }}
			</span>

			<!-- Status modal toggle -->
			<toggle :is="inline ? 'button' : 'a'"
				:class="{'user-status-menu-item__toggle--inline': inline}"
				class="user-status-menu-item__toggle"
				href="#"
				@click.prevent.stop="openModal">
				<span :class="statusIcon" class="user-status-menu-item__toggle-icon" />
				{{ visibleMessage }}
			</toggle>
		</div>

		<!-- Status management modal -->
		<SetStatusModal
			v-if="isModalOpen"
			@close="closeModal" />
	</li>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import debounce from 'debounce'

import { sendHeartbeat } from './services/heartbeatService'
import OnlineStatusMixin from './mixins/OnlineStatusMixin'

export default {
	name: 'UserStatus',

	components: {
		SetStatusModal: () => import(/* webpackChunkName: 'user-status-modal' */'./components/SetStatusModal'),
	},
	mixins: [OnlineStatusMixin],

	props: {
		inline: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			isModalOpen: false,
			heartbeatInterval: null,
			setAwayTimeout: null,
			mouseMoveListener: null,
			isAway: false,
		}
	},
	computed: {
		/**
		 * The display-name of the current user
		 *
		 * @returns {String}
		 */
		displayName() {
			return getCurrentUser().displayName
		},
	},

	/**
	 * Loads the current user's status from initial state
	 * and stores it in Vuex
	 */
	mounted() {
		this.$store.dispatch('loadStatusFromInitialState')

		if (OC.config.session_keepalive) {
			// Send the latest status to the server every 5 minutes
			this.heartbeatInterval = setInterval(this._backgroundHeartbeat.bind(this), 1000 * 60 * 5)
			this.setAwayTimeout = () => {
				this.isAway = true
			}
			// Catch mouse movements, but debounce to once every 30 seconds
			this.mouseMoveListener = debounce(() => {
				const wasAway = this.isAway
				this.isAway = false
				// Reset the two minute counter
				clearTimeout(this.setAwayTimeout)
				// If the user did not move the mouse within two minutes,
				// mark them as away
				setTimeout(this.setAwayTimeout, 1000 * 60 * 2)

				if (wasAway) {
					this._backgroundHeartbeat()
				}
			}, 1000 * 2, true)
			window.addEventListener('mousemove', this.mouseMoveListener, {
				capture: true,
				passive: true,
			})

			this._backgroundHeartbeat()
		}
	},

	/**
	 * Some housekeeping before destroying the component
	 */
	beforeDestroy() {
		window.removeEventListener('mouseMove', this.mouseMoveListener)
		clearInterval(this.heartbeatInterval)
	},

	methods: {
		/**
		 * Opens the modal to set a custom status
		 */
		openModal() {
			this.isModalOpen = true
		},
		/**
		 * Closes the modal
		 */
		closeModal() {
			this.isModalOpen = false
		},

		/**
		 * Sends the status heartbeat to the server
		 *
		 * @returns {Promise<void>}
		 * @private
		 */
		async _backgroundHeartbeat() {
			try {
				await sendHeartbeat(this.isAway)
			} catch (error) {
				console.debug('Failed sending heartbeat, got: ' + error.response.status)
				return
			}
			await this.$store.dispatch('reFetchStatusFromServer')
		},
	},
}
</script>

<style lang="scss" scoped>
$max-width-user-status: 200px;

.user-status-menu-item {
	&__header {
		display: block;
		overflow: hidden;
		box-sizing: border-box;
		max-width: $max-width-user-status;
		padding: 10px 12px 5px 38px;
		text-align: left;
		white-space: nowrap;
		text-overflow: ellipsis;
		opacity: 1;
		color: var(--color-text-maxcontrast);
	}

	&__toggle {
		&-icon {
			width: 16px;
			height: 16px;
			margin-right: 10px;
			opacity: 1 !important;
			background-size: 16px;
		}

		// In dashboard
		&--inline {
			width: auto;
			min-width: 44px;
			height: 44px;
			margin: 0;
			border: 0;
			border-radius: var(--border-radius-pill);
			background-color: var(--color-background-translucent);
			font-size: inherit;
			font-weight: normal;

			-webkit-backdrop-filter: var(--background-blur);
			backdrop-filter: var(--background-blur);

			&:active,
			&:hover,
			&:focus {
				background-color: var(--color-background-hover);
			}
		}
	}
}

li {
	list-style-type: none;
}

</style>
