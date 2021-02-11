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
	<form
		class="custom-input__form"
		@submit.prevent>
		<input
			ref="input"
			maxlength="80"
			:disabled="disabled"
			:placeholder="$t('user_status', 'What\'s your status?')"
			type="text"
			:value="message"
			@change="change"
			@keyup="change"
			@paste="change"
			@keyup.enter="submit">
	</form>
</template>

<script>
export default {
	name: 'CustomMessageInput',
	props: {
		message: {
			type: String,
			required: true,
			default: () => '',
		},
		disabled: {
			type: Boolean,
			default: false,
		},
	},
	methods: {
		focus() {
			this.$refs.input.focus()
		},

		/**
		 * Notifies the parent component about a changed input
		 *
		 * @param {Event} event The Change Event
		 */
		change(event) {
			this.$emit('change', event.target.value)
		},

		submit(event) {
			this.$emit('submit', event.target.value)
		},
	},
}
</script>

<style lang="scss" scoped>
.custom-input__form {
	flex-grow: 1;

	input {
		width: 100%;
		border-radius: 0 var(--border-radius) var(--border-radius) 0;
	}
}
</style>
