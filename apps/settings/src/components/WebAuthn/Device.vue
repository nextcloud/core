<!--
  - @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
  -
  - @author 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
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
	<div class="webauthn-device">
		<span class="icon-webauthn-device" />
		{{ name || t('settings', 'Unnamed device') }}
		<Actions :force-menu="true">
			<ActionButton icon="icon-delete" @click="$emit('delete')">
				{{ t('settings', 'Delete') }}
			</ActionButton>
		</Actions>
	</div>
</template>

<script>
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'

export default {
	name: 'Device',
	components: {
		ActionButton,
		Actions,
	},
	props: {
		name: {
			type: String,
			required: true,
		},
	},
}
</script>

<style scoped>
	.webauthn-device {
		line-height: 300%;
		display: flex;
	}

	.icon-webauthn-device {
		display: inline-block;
		background-size: 100%;
		padding: 3px;
		margin: 3px;
	}
</style>
