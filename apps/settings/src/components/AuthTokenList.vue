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
	<table id="app-tokens-table">
		<thead v-if="tokens.length">
			<tr>
				<th />
				<th>{{ t('settings', 'Device') }}</th>
				<th>{{ t('settings', 'Last activity') }}</th>
				<th />
			</tr>
		</thead>
		<tbody class="token-list">
			<AuthToken v-for="token in sortedTokens"
				:key="token.id"
				:token="token"
				@toggleScope="toggleScope"
				@rename="rename"
				@delete="onDelete"
				@wipe="onWipe" />
		</tbody>
	</table>
</template>

<script>
import AuthToken from './AuthToken'

export default {
	name: 'AuthTokenList',
	components: {
		AuthToken,
	},
	props: {
		tokens: {
			type: Array,
			required: true,
		},
	},
	computed: {
		sortedTokens() {
			return this.tokens.slice().sort((t1, t2) => {
				const ts1 = parseInt(t1.lastActivity, 10)
				const ts2 = parseInt(t2.lastActivity, 10)
				return ts2 - ts1
			})
		},
	},
	methods: {
		toggleScope(token, scope, value) {
			// Just pass it on
			this.$emit('toggleScope', token, scope, value)
		},
		rename(token, newName) {
			// Just pass it on
			this.$emit('rename', token, newName)
		},
		onDelete(token) {
			// Just pass it on
			this.$emit('delete', token)
		},
		onWipe(token) {
			// Just pass it on
			this.$emit('wipe', token)
		},
	},
}
</script>

<style lang="scss" scoped>
	table {
		width: 100%;
		min-height: 50px;
		padding-top: 5px;
		max-width: 580px;

		th {
			opacity: .5;
			padding: 10px 10px 10px 0;
		}
	}

	.token-list {
		td > a.icon-more {
			transition: opacity var(--animation-quick);
		}

		a.icon-more {
			padding: 14px;
			display: block;
			width: 44px;
			height: 44px;
			opacity: .5;
		}

		tr {
			&:hover td > a.icon,
			td > a.icon:focus,
			&.active td > a.icon {
				opacity: 1;
			}
		}
	}
</style>

<!-- some styles are not scoped to make them work on subcomponents -->
<style lang="scss">
	#app-tokens-table {
		tr > *:nth-child(2) {
			padding-left: 6px;
		}

		tr > *:nth-child(3) {
			text-align: right;
		}
	}
</style>
