<!--
  - @copyright Copyright (c) 2019 Julius Härtl <jus@bitgrid.net>
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
	<CollectionList v-if="fileId"
		:id="fileId"
		type="file"
		:name="filename" />
</template>

<script>
import { CollectionList } from 'nextcloud-vue-collections'

export default {
	name: 'CollaborationView',
	components: {
		CollectionList
	},
	computed: {
		fileId() {
			if (this.$root.model && this.$root.model.id) {
				return '' + this.$root.model.id
			}
			return null
		},
		filename() {
			if (this.$root.model && this.$root.model.name) {
				return '' + this.$root.model.name
			}
			return ''
		}
	}
}
</script>
