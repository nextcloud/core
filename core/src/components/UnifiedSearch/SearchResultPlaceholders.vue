<template>
	<ul>
		<!-- Placeholder animation -->
		<svg class="unified-search__result-placeholder-gradient">
			<defs>
				<linearGradient id="unified-search__result-placeholder-gradient">
					<stop offset="0%" :stop-color="light">
						<animate attributeName="stop-color"
							:values="`${light}; ${light}; ${dark}; ${dark}; ${light}`"
							dur="2s"
							repeatCount="indefinite" />
					</stop>
					<stop offset="100%" :stop-color="dark">
						<animate attributeName="stop-color"
							:values="`${dark}; ${light}; ${light}; ${dark}; ${dark}`"
							dur="2s"
							repeatCount="indefinite" />
					</stop>
				</linearGradient>
			</defs>
		</svg>

		<!-- Placeholders -->
		<li v-for="placeholder in [1, 2, 3]" :key="placeholder">
			<svg
				class="unified-search__result-placeholder"
				xmlns="http://www.w3.org/2000/svg"
				fill="url(#unified-search__result-placeholder-gradient)">
				<rect class="unified-search__result-placeholder-icon" />
				<rect class="unified-search__result-placeholder-line-one" />
				<rect class="unified-search__result-placeholder-line-two" :style="{width: `calc(${randWidth()}%)`}" />
			</svg>
		</li>
	</ul>
</template>

<script>
export default {
	name: 'SearchResultPlaceholders',

	data() {
		return {
			light: null,
			dark: null,
		}
	},
	mounted() {
		const styles = getComputedStyle(document.documentElement)
		this.dark = styles.getPropertyValue('--color-placeholder-dark')
		this.light = styles.getPropertyValue('--color-placeholder-light')
	},

	methods: {
		randWidth() {
			return Math.floor(Math.random() * 20) + 30
		},
	},
}
</script>

<style lang="scss" scoped>
$clickable-area: 44px;
$margin: 10px;

.unified-search__result-placeholder-gradient {
	position: fixed;
	height: 0;
	width: 0;
	z-index: -1;
}

.unified-search__result-placeholder {
	width: calc(100% - 2 * #{$margin});
	height: $clickable-area;
	margin: $margin;

	&-icon {
		width: $clickable-area;
		height: $clickable-area;
		rx: var(--border-radius);
		ry: var(--border-radius);
	}

	&-line-one,
	&-line-two {
		width: calc(100% - #{$margin + $clickable-area});
		height: 1em;
		x: $margin + $clickable-area;
	}

	&-line-one {
		y: 5px;
	}

	&-line-two {
		y: 25px;
	}
}

</style>
