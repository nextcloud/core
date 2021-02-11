<!--
 - @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
 -
 - @author John Molakvoæ <skjnldsv@protonmail.com>
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
	<div class="user-status-online-select">
		<input :id="id"
			:checked="checked"
			class="user-status-online-select__input"
			type="radio"
			name="user-status-online"
			@change="onChange">
		<label :for="id" :class="icon" class="user-status-online-select__label">
			{{ label }}
			<em class="user-status-online-select__subline">{{ subline }}</em>
		</label>
	</div>
</template>

<script>
export default {
	name: 'OnlineStatusSelect',

	props: {
		checked: {
			type: Boolean,
			default: false,
		},
		icon: {
			type: String,
			required: true,
		},
		type: {
			type: String,
			required: true,
		},
		label: {
			type: String,
			required: true,
		},
		subline: {
			type: String,
			default: null,
		},
	},

	computed: {
		id() {
			return `user-status-online-status-${this.type}`
		},
	},

	methods: {
		onChange() {
			this.$emit('select', this.type)
		},
	},
}
</script>

<style lang="scss" scoped>
$icon-size: 24px;
$label-padding: 8px;

.user-status-online-select {
	// Inputs are here for keyboard navigation, they are not visually visible
	&__input {
		position: absolute;
		top: auto;
		left: -10000px;
		overflow: hidden;
		width: 1px;
		height: 1px;
	}

	&__label {
		display: block;
		margin: $label-padding;
		padding: $label-padding;
		padding-left: $icon-size + $label-padding * 2;
		border: 2px solid var(--color-main-background);
		border-radius: var(--border-radius-large);
		background-color: var(--color-background-hover);
		background-position: $label-padding center;
		background-size: $icon-size;

		span,
		& {
			cursor: pointer;
		}
	}

	&__input:checked + &__label,
	&__input:focus + &__label,
	&__label:hover {
		border-color: var(--color-primary);
	}

	&__subline {
		display: block;
		color: var(--color-text-lighter);
	}
}

</style>
