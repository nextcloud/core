<!-- TODO: Move to vue components -->

<template>
	<span class="live-relative-timestamp" :data-timestamp="timestamp * 1000" :title="title">{{ formatted }}</span>
</template>

<script>
import moment from '@nextcloud/moment'

export default {
	name: 'Moment',
	props: {
		timestamp: {
			type: Number,
			required: true,
		},
		format: {
			type: String,
			default: 'LLL',
		},
	},
	computed: {
		title() {
			return moment.unix(this.timestamp).format(this.format)
		},
		formatted() {
			return moment.unix(this.timestamp).fromNow()
		},
	},
}
</script>
