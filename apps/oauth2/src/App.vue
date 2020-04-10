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
	<div id="oauth2" class="section">
		<h2>{{ t('oauth2', 'OAuth 2.0 clients') }}</h2>
		<p class="settings-hint">
			{{ t('oauth2', 'OAuth 2.0 allows external services to request access to {instanceName}.', { instanceName: OC.theme.name}) }}
		</p>
		<table v-if="clients.length > 0" class="grid">
			<thead>
				<tr>
					<th id="headerName" scope="col">
						{{ t('oauth2', 'Name') }}
					</th>
					<th id="headerRedirectUri" scope="col">
						{{ t('oauth2', 'Redirection URI') }}
					</th>
					<th id="headerClientIdentifier" scope="col">
						{{ t('oauth2', 'Client Identifier') }}
					</th>
					<th id="headerSecret" scope="col">
						{{ t('oauth2', 'Secret') }}
					</th>
					<th id="headerRemove">
&nbsp;
					</th>
				</tr>
			</thead>
			<tbody>
				<OAuthItem v-for="client in clients"
					:key="client.id"
					:client="client"
					@delete="deleteClient" />
			</tbody>
		</table>

		<br>
		<h3>{{ t('oauth2', 'Add client') }}</h3>
		<span v-if="newClient.error" class="msg error">{{ newClient.errorMsg }}</span>
		<form @submit.prevent="addClient">
			<input id="name"
				v-model="newClient.name"
				type="text"
				name="name"
				:placeholder="t('oauth2', 'Name')">
			<input id="redirectUri"
				v-model="newClient.redirectUri"
				type="url"
				name="redirectUri"
				:placeholder="t('oauth2', 'Redirection URI')">
			<input type="submit" class="button" :value="t('oauth2', 'Add')">
		</form>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import OAuthItem from './components/OAuthItem'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'App',
	components: {
		OAuthItem,
	},
	props: {
		clients: {
			type: Array,
			required: true,
		},
	},
	data: function() {
		return {
			newClient: {
				name: '',
				redirectUri: '',
				errorMsg: '',
				error: false,
			},
		}
	},
	methods: {
		deleteClient(id) {
			axios.delete(generateUrl('apps/oauth2/clients/{id}', { id: id }))
				.then((response) => {
					this.clients = this.clients.filter(client => client.id !== id)
				})
		},
		addClient() {
			this.newClient.error = false

			axios.post(
				generateUrl('apps/oauth2/clients'),
				{
					name: this.newClient.name,
					redirectUri: this.newClient.redirectUri,
				}
			).then(response => {
				this.clients.push(response.data)

				this.newClient.name = ''
				this.newClient.redirectUri = ''
			}).catch(reason => {
				this.newClient.error = true
				this.newClient.errorMsg = reason.response.data.message
			})
		},
	},
}
</script>
