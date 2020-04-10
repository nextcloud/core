<template>
	<div :class="{preview: true}">
		<div class="preview-image" :style="{backgroundImage: 'url(' + preview.img + ')'}" />
		<div class="preview-description">
			<h3>{{ preview.title }}</h3>
			<p>{{ preview.text }}</p>
			<input :id="'accessibility-' + preview.id"
				v-model="checked"
				type="checkbox"
				class="checkbox">
			<label :for="'accessibility-' + preview.id">{{ preview.enableLabel }}</label>
		</div>
	</div>
</template>

<script>
export default {
	name: 'ItemPreview',
	props: {
		preview: {
			type: Object,
			required: true,
		},
		selected: {
			type: String,
			default: null,
		},
	},
	computed: {
		checked: {
			get() {
				return this.selected === this.preview.id
			},
			set(checked) {
				this.$emit('select', checked ? this.preview.id : '')
			},
		},
	},
}
</script>
