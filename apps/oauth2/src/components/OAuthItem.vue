<!--
  - @copyright Copyright (c) 2018 Roeland Jago Douma <roeland@famdouma.nl>
  -
  - @author Roeland Jago Douma <roeland@famdouma.nl>
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
	<tr>
		<td>{{ name }}</td>
		<td>{{ redirectUri }}</td>
		<td><code>{{ clientId }}</code></td>
		<td><code>{{ renderedSecret }}</code><a class="icon-toggle has-tooltip" :title="t('oauth2', 'Show client secret')" @click="toggleSecret" /></td>
		<td class="action-column">
			<span><a class="icon-delete has-tooltip" :title="t('oauth2', 'Delete')" @click="$emit('delete', id)" /></span>
		</td>
	</tr>
</template>

<script>
export default {
	name: 'OAuthItem',
	props: {
		client: {
			type: Object,
			required: true,
		},
	},
	data: function() {
		return {
			id: this.client.id,
			name: this.client.name,
			redirectUri: this.client.redirectUri,
			clientId: this.client.clientId,
			clientSecret: this.client.clientSecret,
			renderSecret: false,
		}
	},
	computed: {
		renderedSecret: function() {
			if (this.renderSecret) {
				return this.clientSecret
			} else {
				return '****'
			}
		},
	},
	methods: {
		toggleSecret() {
			this.renderSecret = !this.renderSecret
		},
	},
}
</script>

<style scoped>
	.icon-toggle,
	.icon-delete {
		display: inline-block;
		width: 16px;
		height: 16px;
		padding: 10px;
		vertical-align: middle;
	}
	td code {
		display: inline-block;
		vertical-align: middle;
		padding: 3px;
	}
</style>
