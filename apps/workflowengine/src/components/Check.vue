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
	<!-- eslint-disable vue/no-mutating-props -->
	<div v-click-outside="hideDelete" class="check" @click="showDelete">
		<Multiselect ref="checkSelector"
			v-model="currentOption"
			:options="options"
			label="name"
			track-by="class"
			:allow-empty="false"
			:placeholder="t('workflowengine', 'Select a filter')"
			@input="updateCheck" />
		<Multiselect v-model="currentOperator"
			:disabled="!currentOption"
			:options="operators"
			class="comparator"
			label="name"
			track-by="operator"
			:allow-empty="false"
			:placeholder="t('workflowengine', 'Select a comparator')"
			@input="updateCheck" />
		<component :is="currentOption.component"
			v-if="currentOperator && currentComponent"
			v-model="check.value"
			:disabled="!currentOption"
			:check="check"
			class="option"
			@input="updateCheck"
			@valid="(valid=true) && validate()"
			@invalid="!(valid=false) && validate()" />
		<input v-else
			v-model="check.value"
			type="text"
			:class="{ invalid: !valid }"
			:disabled="!currentOption"
			:placeholder="valuePlaceholder"
			class="option"
			@input="updateCheck">
		<Actions v-if="deleteVisible || !currentOption">
			<ActionButton icon="icon-close" @click="$emit('remove')" />
		</Actions>
	</div>
</template>

<script>
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ClickOutside from 'vue-click-outside'

export default {
	name: 'Check',
	components: {
		ActionButton,
		Actions,
		Multiselect,
	},
	directives: {
		ClickOutside,
	},
	props: {
		check: {
			type: Object,
			required: true,
		},
		rule: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			deleteVisible: false,
			currentOption: null,
			currentOperator: null,
			options: [],
			valid: false,
		}
	},
	computed: {
		checks() {
			return this.$store.getters.getChecksForEntity(this.rule.entity)
		},
		operators() {
			if (!this.currentOption) { return [] }
			const operators = this.checks[this.currentOption.class].operators
			if (typeof operators === 'function') {
				return operators(this.check)
			}
			return operators
		},
		currentComponent() {
			if (!this.currentOption) { return [] }
			return this.checks[this.currentOption.class].component
		},
		valuePlaceholder() {
			if (this.currentOption && this.currentOption.placeholder) {
				return this.currentOption.placeholder(this.check)
			}
			return ''
		},
	},
	watch: {
		'check.operator'() {
			this.validate()
		},
	},
	mounted() {
		this.options = Object.values(this.checks)
		this.currentOption = this.checks[this.check.class]
		this.currentOperator = this.operators.find((operator) => operator.operator === this.check.operator)

		if (this.check.class === null) {
			this.$nextTick(() => this.$refs.checkSelector.$el.focus())
		}
		this.validate()
	},
	methods: {
		showDelete() {
			this.deleteVisible = true
		},
		hideDelete() {
			this.deleteVisible = false
		},
		validate() {
			this.valid = true
			if (this.currentOption && this.currentOption.validate) {
				this.valid = !!this.currentOption.validate(this.check)
			}
			// eslint-disable-next-line vue/no-mutating-props
			this.check.invalid = !this.valid
			this.$emit('validate', this.valid)
		},
		updateCheck() {
			const matchingOperator = this.operators.findIndex((operator) => this.check.operator === operator.operator)
			if (this.check.class !== this.currentOption.class || matchingOperator === -1) {
				this.currentOperator = this.operators[0]
			}
			// eslint-disable-next-line vue/no-mutating-props
			this.check.class = this.currentOption.class
			// eslint-disable-next-line vue/no-mutating-props
			this.check.operator = this.currentOperator.operator

			this.validate()

			this.$emit('update', this.check)
		},
	},
}
</script>

<style scoped lang="scss">
	.check {
		display: flex;
		flex-wrap: wrap;
		width: 100%;
		padding-right: 20px;
		& > *:not(.close) {
			width: 180px;
		}
		& > .comparator {
			min-width: 130px;
			width: 130px;
		}
		& > .option {
			min-width: 230px;
			width: 230px;
		}
		& > .multiselect,
		& > input[type=text] {
			margin-right: 5px;
			margin-bottom: 5px;
		}

		.multiselect::v-deep .multiselect__content-wrapper li>span,
		.multiselect::v-deep .multiselect__single {
			display: block;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}
	}

	input[type=text] {
		margin: 0;
	}

	::placeholder {
		font-size: 10px;
	}

	button.action-item.action-item--single.icon-close {
		height: 44px;
		width: 44px;
		margin-top: -5px;
		margin-bottom: -5px;
	}

	.invalid {
		border: 1px solid var(--color-error) !important;
	}
</style>
