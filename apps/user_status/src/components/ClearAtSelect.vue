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
	<div class="clear-at-select">
		<span
			class="clear-at-select__label">
			{{ $t('user_status', 'Clear status message after') }}
		</span>
		<Multiselect
			label="label"
			:value="option"
			:options="options"
			open-direction="top"
			@select="select" />
	</div>
</template>

<script>
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import { getAllClearAtOptions } from '../services/clearAtOptionsService'
import { clearAtFilter } from '../filters/clearAtFilter'

export default {
	name: 'ClearAtSelect',
	components: {
		Multiselect,
	},
	props: {
		clearAt: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			options: getAllClearAtOptions(),
		}
	},
	computed: {
		/**
		 * Returns an object of the currently selected option
		 *
		 * @returns {Object}
		 */
		option() {
			return {
				clearAt: this.clearAt,
				label: clearAtFilter(this.clearAt),
			}
		},
	},
	methods: {
		/**
		 * Triggered when the user selects a new option.
		 *
		 * @param {Object=} option The new selected option
		 */
		select(option) {
			if (!option) {
				return
			}

			this.$emit('selectClearAt', option.clearAt)
		},
	},
}
</script>

<style lang="scss" scoped>
.clear-at-select {
	display: flex;
	margin-bottom: 10px;
	align-items: center;

	&__label {
		margin-right: 10px;
	}

	.multiselect {
		flex-grow: 1;
	}
}
</style>
