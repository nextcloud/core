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
	<div>
		<Multiselect
			:value="currentValue"
			:placeholder="t('workflowengine', 'Select a request URL')"
			label="label"
			track-by="pattern"
			group-values="children"
			group-label="label"
			:options="options"
			:multiple="false"
			:tagging="false"
			@input="setValue">
			<template slot="singleLabel" slot-scope="props">
				<span class="option__icon" :class="props.option.icon" />
				<span class="option__title option__title_single">{{ props.option.label }}</span>
			</template>
			<template slot="option" slot-scope="props">
				<span class="option__icon" :class="props.option.icon" />
				<span class="option__title">{{ props.option.label }} {{ props.option.$groupLabel }}</span>
			</template>
		</Multiselect>
		<input v-if="!isPredefined"
			type="text"
			:value="currentValue.pattern"
			:placeholder="placeholder"
			@input="updateCustom">
	</div>
</template>

<script>
import { Multiselect } from '@nextcloud/vue/dist/Components/Multiselect'
import valueMixin from '../../mixins/valueMixin'

export default {
	name: 'RequestURL',
	components: {
		Multiselect,
	},
	mixins: [
		valueMixin,
	],
	data() {
		return {
			newValue: '',
			predefinedTypes: [
				{
					label: t('workflowengine', 'Predefined URLs'),
					children: [
						{ pattern: 'webdav', label: t('workflowengine', 'Files WebDAV') },
					],
				},
			],
		}
	},
	computed: {
		options() {
			return [...this.predefinedTypes, this.customValue]
		},
		placeholder() {
			if (this.check.operator === 'matches' || this.check.operator === '!matches') {
				return '/^https\\:\\/\\/localhost\\/index\\.php$/i'
			}
			return 'https://localhost/index.php'
		},
		matchingPredefined() {
			return this.predefinedTypes
				.map(groups => groups.children)
				.flat()
				.find((type) => this.newValue === type.pattern)
		},
		isPredefined() {
			return !!this.matchingPredefined
		},
		customValue() {
			return {
				label: t('workflowengine', 'Others'),
				children: [
					{
						icon: 'icon-settings-dark',
						label: t('workflowengine', 'Custom URL'),
						pattern: '',
					},
				],
			}
		},
		currentValue() {
			if (this.matchingPredefined) {
				return this.matchingPredefined
			}
			return {
				icon: 'icon-settings-dark',
				label: t('workflowengine', 'Custom URL'),
				pattern: this.newValue,
			}
		},
	},
	methods: {
		validateRegex(string) {
			const regexRegex = /^\/(.*)\/([gui]{0,3})$/
			const result = regexRegex.exec(string)
			return result !== null
		},
		setValue(value) {
			// TODO: check if value requires a regex and set the check operator according to that
			if (value !== null) {
				this.newValue = value.pattern
				this.$emit('input', this.newValue)
			}
		},
		updateCustom(event) {
			this.newValue = event.target.value
			this.$emit('input', this.newValue)
		},
	},
}
</script>
<style scoped>
	.multiselect, input[type='text'] {
		width: 100%;
	}
</style>
