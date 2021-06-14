<template>
	<div class="section">
		<h2>{{ $t('dav', 'Availability') }}</h2>
		<p>
			{{ $t('dav', 'If you configure your working hours, other users will see when you are out of office when they book a meeting.') }}
		</p>
		<table>
			<tr v-for="day in daysOfTheWeek" :key="day.id">
				<td class="availability-day">
					<input :id="`toggle-day-${day.id}`"
						type="checkbox"
						class="checkbox">
					<label :for="`toggle-day-${day.id}`">
						{{ day.displayName }}
					</label>
				</td>
				<td class="availability-slots">
					<DatetimePicker type="time" format="H:mm" />
					{{ $t('dav', 'to') }}
					<DatetimePicker type="time" format="H:mm" />
					<span class="add-another">{{ $t('dav', 'Add slot') }}</span>
				</td>
			</tr>
		</table>
	</div>
</template>

<script>
import DatetimePicker from '@nextcloud/vue/dist/Components/DatetimePicker'

export default {
	name: 'Availability',
	components: {
		DatetimePicker,
	},
	data() {
		// TODO: reorder if Sunday is the first day of the week
		return {
			daysOfTheWeek: [
				{
					id: 'mo',
					displayName: this.$t('dav', 'Monday'),
				},
				{
					id: 'tu',
					displayName: this.$t('dav', 'Tuesday'),
				},
				{
					id: 'we',
					displayName: this.$t('dav', 'Wednesday'),
				},
				{
					id: 'th',
					displayName: this.$t('dav', 'Thursday'),
				},
				{
					id: 'fr',
					displayName: this.$t('dav', 'Friday'),
				},
				{
					id: 'sa',
					displayName: this.$t('dav', 'Saturday'),
				},
				{
					id: 'su',
					displayName: this.$t('dav', 'Sunday'),
				},
			],
		}
	},
}
</script>

<style lang="scss" scoped>
.availability-day {
	padding: 10px;
}
.availability-slots {
	padding: 10px 10px 20px 10px;
}
.add-another {
	text-decoration: underline;
}
</style>
