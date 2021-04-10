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
	<div class="event">
		<div v-if="operation.isComplex && operation.fixedEntity !== ''" class="isComplex">
			<img class="option__icon" :src="entity.icon">
			<span class="option__title option__title_single">{{ operation.triggerHint }}</span>
		</div>
		<Multiselect v-else
			:value="currentEvent"
			:options="allEvents"
			track-by="id"
			:multiple="true"
			:auto-limit="false"
			:disabled="allEvents.length <= 1"
			@input="updateEvent">
			<template slot="selection" slot-scope="{ values, isOpen }">
				<div v-if="values.length && !isOpen" class="eventlist">
					<img class="option__icon" :src="values[0].entity.icon">
					<span v-for="(value, index) in values" :key="value.id" class="text option__title option__title_single">{{ value.displayName }} <span v-if="index+1 < values.length">, </span></span>
				</div>
			</template>
			<template slot="option" slot-scope="props">
				<img class="option__icon" :src="props.option.entity.icon">
				<span class="option__title">{{ props.option.displayName }}</span>
			</template>
		</Multiselect>
	</div>
</template>

<script>
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import { showWarning } from '@nextcloud/dialogs'

export default {
	name: 'Event',
	components: {
		Multiselect,
	},
	props: {
		rule: {
			type: Object,
			required: true,
		},
	},
	computed: {
		entity() {
			return this.$store.getters.getEntityForOperation(this.operation)
		},
		operation() {
			return this.$store.getters.getOperationForRule(this.rule)
		},
		allEvents() {
			return this.$store.getters.getEventsForOperation(this.operation)
		},
		currentEvent() {
			return this.allEvents.filter(event => event.entity.id === this.rule.entity && this.rule.events.indexOf(event.eventName) !== -1)
		},
	},
	methods: {
		updateEvent(events) {
			if (events.length === 0) {
				showWarning(t('workflowengine', 'At least one event must be selected'))
				return
			}
			const existingEntity = this.rule.entity
			const newEntities = events.map(event => event.entity.id).filter((value, index, self) => self.indexOf(value) === index)
			let newEntity = null
			if (newEntities.length > 1) {
				newEntity = newEntities.filter(entity => entity !== existingEntity)[0]
			} else {
				newEntity = newEntities[0]
			}

			this.$set(this.rule, 'entity', newEntity)
			this.$set(this.rule, 'events', events.filter(event => event.entity.id === newEntity).map(event => event.eventName))
			this.$emit('update', this.rule)
		},
	},
}
</script>

<style scoped lang="scss">
	.event {
		margin-bottom: 5px;
	}

	.isComplex {
		img {
			vertical-align: text-top;
		}
		span {
			padding-top: 2px;
			display: inline-block;
		}
	}

	.multiselect {
		width: 100%;
		max-width: 550px;
		margin-top: 4px;
	}

	.multiselect::v-deep .multiselect__single {
		display: flex;
	}

	.multiselect:not(.multiselect--active)::v-deep .multiselect__tags {
		background-color: var(--color-main-background) !important;
		border: 1px solid transparent;
	}

	.multiselect::v-deep .multiselect__tags {
		background-color: var(--color-main-background) !important;
		height: auto;
		min-height: 34px;
	}

	.multiselect:not(.multiselect--disabled)::v-deep .multiselect__tags .multiselect__single {
		background-image: var(--icon-triangle-s-000);
		background-repeat: no-repeat;
		background-position: right center;
	}

	input {
		border: 1px solid transparent;
	}

	.option__title {
		margin-left: 5px;
		color: var(--color-main-text);
	}

	.option__title_single {
		font-weight: 900;
	}

	.option__icon {
		width: 16px;
		height: 16px;
	}

	.eventlist img,
	.eventlist .text {
		vertical-align: middle;
	}
</style>
