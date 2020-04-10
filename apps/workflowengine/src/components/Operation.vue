<template>
	<div class="actions__item" :class="{'colored': colored}" :style="{ backgroundColor: colored ? operation.color : 'transparent' }">
		<div class="icon" :class="operation.iconClass" :style="{ backgroundImage: operation.iconClass ? '' : `url(${operation.icon})` }" />
		<div class="actions__item__description">
			<h3>{{ operation.name }}</h3>
			<small>{{ operation.description }}</small>
			<div>
				<button v-if="colored">
					{{ t('workflowengine', 'Add new flow') }}
				</button>
			</div>
		</div>
		<div class="actions__item_options">
			<slot />
		</div>
	</div>
</template>

<script>
export default {
	name: 'Operation',
	props: {
		operation: {
			type: Object,
			required: true,
		},
		colored: {
			type: Boolean,
			default: true,
		},
	},
}
</script>

<style scoped lang="scss">
	@import "./../styles/operation";
</style>
