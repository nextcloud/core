<template>
	<div class="timeslot">
		<input v-model="newValue.startTime"
			type="text"
			class="timeslot--start"
			placeholder="e.g. 08:00"
			@input="update">
		<input v-model="newValue.endTime"
			type="text"
			placeholder="e.g. 18:00"
			@input="update">
		<p v-if="!valid" class="invalid-hint">
			{{ t('workflowengine', 'Please enter a valid time span') }}
		</p>
		<Multiselect v-show="valid"
			v-model="newValue.timezone"
			:options="timezones"
			@input="update" />
	</div>
</template>

<script>
import { Multiselect } from '@nextcloud/vue/dist/Components/Multiselect'
import moment from 'moment-timezone'
import valueMixin from '../../mixins/valueMixin'

const zones = moment.tz.names()
export default {
	name: 'RequestTime',
	components: {
		Multiselect,
	},
	mixins: [
		valueMixin,
	],
	props: {
		value: {
			type: String,
			default: '',
		},
	},
	data() {
		return {
			timezones: zones,
			valid: false,
			newValue: {
				startTime: null,
				endTime: null,
				timezone: moment.tz.guess(),
			},
		}
	},
	mounted() {
		this.validate()
	},
	methods: {
		updateInternalValue(value) {
			try {
				const data = JSON.parse(value)
				if (data.length === 2) {
					this.newValue = {
						startTime: data[0].split(' ', 2)[0],
						endTime: data[1].split(' ', 2)[0],
						timezone: data[0].split(' ', 2)[1],
					}
				}
			} catch (e) {
				// ignore invalid values
			}
		},
		validate() {
			this.valid = this.newValue.startTime && this.newValue.startTime.match(/^(0[0-9]|1[0-9]|2[0-3]|[0-9]):[0-5][0-9]$/i) !== null
				&& this.newValue.endTime && this.newValue.endTime.match(/^(0[0-9]|1[0-9]|2[0-3]|[0-9]):[0-5][0-9]$/i) !== null
				&& moment.tz.zone(this.newValue.timezone) !== null
			if (this.valid) {
				this.$emit('valid')
			} else {
				this.$emit('invalid')
			}
			return this.valid
		},
		update() {
			if (this.newValue.timezone === null) {
				this.newValue.timezone = moment.tz.guess()
			}
			if (this.validate()) {
				const output = `["${this.newValue.startTime} ${this.newValue.timezone}","${this.newValue.endTime} ${this.newValue.timezone}"]`
				this.$emit('input', output)
			}
		},
	},
}
</script>

<style scoped lang="scss">
	.timeslot {
		display: flex;
		flex-grow: 1;
		flex-wrap: wrap;
		max-width: 180px;

		.multiselect {
			width: 100%;
			margin-bottom: 5px;
		}

		.multiselect::v-deep .multiselect__tags:not(:hover):not(:focus):not(:active) {
			border: 1px solid transparent;
		}

		input[type=text] {
			width: 50%;
			margin: 0;
			margin-bottom: 5px;

			&.timeslot--start {
				margin-right: 5px;
				width: calc(50% - 5px);
			}
		}

		.invalid-hint {
			color: var(--color-text-maxcontrast);
		}
	}
</style>
