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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<Multiselect v-model="inputValObjects"
		:options="tags"
		:options-limit="5"
		:placeholder="label"
		track-by="id"
		:custom-label="tagLabel"
		class="multiselect-vue"
		:multiple="multiple"
		:close-on-select="false"
		:tag-width="60"
		:disabled="disabled"
		@input="update">
		<span slot="noResult">{{ t('core', 'No results') }}</span>
		<template #option="scope">
			{{ tagLabel(scope.option) }}
		</template>
	</multiselect>
</template>

<script>
import { Multiselect } from '@nextcloud/vue/dist/Components/Multiselect'
import { searchTags } from './api'

let uuid = 0
export default {
	name: 'MultiselectTag',
	components: {
		Multiselect,
	},
	props: {
		label: {
			type: String,
			required: true,
		},
		value: {
			type: [String, Array],
			default: null,
		},
		disabled: {
			type: Boolean,
			default: false,
		},
		multiple: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			inputValObjects: [],
			tags: [],
		}
	},
	computed: {
		id() {
			return 'settings-input-text-' + this.uuid
		},
	},
	watch: {
		value(newVal) {
			this.inputValObjects = this.getValueObject()
		},
	},
	beforeCreate: function() {
		this.uuid = uuid.toString()
		uuid += 1
		searchTags().then((result) => {
			this.tags = result
			this.inputValObjects = this.getValueObject()
		}).catch(console.error.bind(this))
	},
	methods: {
		getValueObject() {
			if (this.tags.length === 0) {
				return []
			}
			if (this.multiple) {
				return this.value.filter((tag) => tag !== '').map(
					(id) => this.tags.find((tag2) => tag2.id === id)
				)
			} else {
				return this.tags.find((tag) => tag.id === this.value)
			}
		},
		update() {
			if (this.multiple) {
				this.$emit('input', this.inputValObjects.map((element) => element.id))
			} else {
				if (this.inputValObjects === null) {
					this.$emit('input', '')
				} else {
					this.$emit('input', this.inputValObjects.id)
				}
			}
		},
		tagLabel({ displayName, userVisible, userAssignable }) {
			if (userVisible === false) {
				return t('systemtags', '%s (invisible)').replace('%s', displayName)
			}
			if (userAssignable === false) {
				return t('systemtags', '%s (restricted)').replace('%s', displayName)
			}
			return displayName
		},
	},
}
</script>
