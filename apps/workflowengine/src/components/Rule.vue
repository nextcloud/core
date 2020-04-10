<template>
	<div v-if="operation" class="section rule" :style="{ borderLeftColor: operation.color || '' }">
		<div class="trigger">
			<p>
				<span>{{ t('workflowengine', 'When') }}</span>
				<Event :rule="rule" @update="updateRule" />
			</p>
			<p v-for="(check, index) in rule.checks" :key="index">
				<span>{{ t('workflowengine', 'and') }}</span>
				<Check :check="check"
					:rule="rule"
					@update="updateRule"
					@validate="validate"
					@remove="removeCheck(check)" />
			</p>
			<p>
				<span />
				<input v-if="lastCheckComplete"
					type="button"
					class="check--add"
					value="Add a new filter"
					@click="rule.checks.push({class: null, operator: null, value: ''})">
			</p>
		</div>
		<div class="flow-icon icon-confirm" />
		<div class="action">
			<Operation :operation="operation" :colored="false">
				<component :is="operation.options"
					v-if="operation.options"
					v-model="rule.operation"
					@input="updateOperation" />
			</Operation>
			<div class="buttons">
				<button class="status-button icon"
					:class="ruleStatus.class"
					@click="saveRule">
					{{ ruleStatus.title }}
				</button>
				<button v-if="rule.id < -1 || dirty" @click="cancelRule">
					{{ t('workflowengine', 'Cancel') }}
				</button>
				<button v-else-if="!dirty" @click="deleteRule">
					{{ t('workflowengine', 'Delete') }}
				</button>
			</div>
			<p v-if="error" class="error-message">
				{{ error }}
			</p>
		</div>
	</div>
</template>

<script>
import { Tooltip } from '@nextcloud/vue/dist/Directives/Tooltip'
import { Actions } from '@nextcloud/vue/dist/Components/Actions'
import { ActionButton } from '@nextcloud/vue/dist/Components/ActionButton'
import Event from './Event'
import Check from './Check'
import Operation from './Operation'

export default {
	name: 'Rule',
	components: {
		Operation, Check, Event, Actions, ActionButton,
	},
	directives: {
		Tooltip,
	},
	props: {
		rule: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			editing: false,
			checks: [],
			error: null,
			dirty: this.rule.id < 0,
			originalRule: null,
		}
	},
	computed: {
		operation() {
			return this.$store.getters.getOperationForRule(this.rule)
		},
		ruleStatus() {
			if (this.error || !this.rule.valid || this.rule.checks.length === 0 || this.rule.checks.some((check) => check.invalid === true)) {
				return {
					title: t('workflowengine', 'The configuration is invalid'),
					class: 'icon-close-white invalid',
					tooltip: { placement: 'bottom', show: true, content: this.error },
				}
			}
			if (!this.dirty) {
				return { title: t('workflowengine', 'Active'), class: 'icon icon-checkmark' }
			}
			return { title: t('workflowengine', 'Save'), class: 'icon-confirm-white primary' }

		},
		lastCheckComplete() {
			const lastCheck = this.rule.checks[this.rule.checks.length - 1]
			return typeof lastCheck === 'undefined' || lastCheck.class !== null
		},
	},
	mounted() {
		this.originalRule = JSON.parse(JSON.stringify(this.rule))
	},
	methods: {
		async updateOperation(operation) {
			this.$set(this.rule, 'operation', operation)
			await this.updateRule()
		},
		validate(state) {
			this.error = null
			this.$store.dispatch('updateRule', this.rule)
		},
		updateRule() {
			if (!this.dirty) {
				this.dirty = true
			}

			this.error = null
			this.$store.dispatch('updateRule', this.rule)
		},
		async saveRule() {
			try {
				await this.$store.dispatch('pushUpdateRule', this.rule)
				this.dirty = false
				this.error = null
				this.originalRule = JSON.parse(JSON.stringify(this.rule))
			} catch (e) {
				console.error('Failed to save operation')
				this.error = e.response.data.ocs.meta.message
			}
		},
		async deleteRule() {
			try {
				await this.$store.dispatch('deleteRule', this.rule)
			} catch (e) {
				console.error('Failed to delete operation')
				this.error = e.response.data.ocs.meta.message
			}
		},
		cancelRule() {
			if (this.rule.id < 0) {
				this.$store.dispatch('removeRule', this.rule)
			} else {
				this.$store.dispatch('updateRule', this.originalRule)
				this.originalRule = JSON.parse(JSON.stringify(this.rule))
				this.dirty = false
			}
		},
		async removeCheck(check) {
			const index = this.rule.checks.findIndex(item => item === check)
			if (index > -1) {
				this.$delete(this.rule.checks, index)
			}
			this.$store.dispatch('updateRule', this.rule)
		},
	},
}
</script>

<style scoped lang="scss">
	button.icon {
		padding-left: 32px;
		background-position: 10px center;
	}

	.buttons {
		display: block;
		overflow: hidden;

		button {
			float: right;
			height: 34px;
		}
	}

	.error-message {
		float: right;
		margin-right: 10px;
	}

	.status-button {
		transition: 0.5s ease all;
		display: block;
		margin: 3px 10px 3px auto;
	}
	.status-button.primary {
		padding-left: 32px;
		background-position: 10px center;
	}
	.status-button:not(.primary) {
		background-color: var(--color-main-background);
	}
	.status-button.invalid {
		background-color: var(--color-warning);
		color: #fff;
		border: none;
	}
	.status-button.icon-checkmark {
		border: 1px solid var(--color-success);
	}

	.flow-icon {
		width: 44px;
	}

	.rule {
		display: flex;
		flex-wrap: wrap;
		border-left: 5px solid var(--color-primary-element);

		.trigger, .action {
			flex-grow: 1;
			min-height: 100px;
			max-width: 700px;
		}
		.action {
			max-width: 400px;
			position: relative;
		}
		.icon-confirm {
			background-position: right 27px;
			padding-right: 20px;
			margin-right: 20px;
		}
	}
	.trigger p, .action p {
		min-height: 34px;
		display: flex;
		align-items: center;

		& > span {
			min-width: 50px;
			text-align: right;
			color: var(--color-text-maxcontrast);
			padding-right: 10px;
			padding-top: 7px;
			margin-bottom: auto;
		}
		.multiselect {
			flex-grow: 1;
			max-width: 300px;
		}
	}

	.check--add {
		background-position: 7px center;
		background-color: transparent;
		padding-left: 6px;
		margin: 0;
		width: 180px;
		border-radius: var(--border-radius);
		color: var(--color-text-maxcontrast);
		font-weight: normal;
		text-align: left;
		font-size: 1em;
	}

	@media (max-width:1400px) {
		.rule {
			&, .trigger, .action {
				width: 100%;
				max-width: 100%;
			}
			.flow-icon {
				display: none;
			}
		}
	}

</style>
