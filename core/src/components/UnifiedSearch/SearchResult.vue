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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program.  If not, see <http://www.gnu.org/licenses/>.
  -
  -->
<template>
	<a :href="resourceUrl || '#'"
		class="unified-search__result"
		:class="{
			'unified-search__result--focused': focused,
		}"
		@click="reEmitEvent"
		@focus="reEmitEvent">

		<!-- Icon describing the result -->
		<div class="unified-search__result-icon"
			:class="{
				'unified-search__result-icon--rounded': rounded,
				'unified-search__result-icon--no-preview': !hasValidThumbnail && !loaded,
				'unified-search__result-icon--with-thumbnail': hasValidThumbnail && loaded,
				[icon]: !loaded && !isIconUrl,
			}"
			:style="{
				backgroundImage: isIconUrl ? `url(${icon})` : '',
			}"
			role="img">

			<img v-if="hasValidThumbnail"
				v-show="loaded"
				:src="thumbnailUrl"
				alt=""
				@error="onError"
				@load="onLoad">
		</div>

		<!-- Title and sub-title -->
		<span class="unified-search__result-content">
			<h3 class="unified-search__result-line-one" :title="title">
				<Highlight :text="title" :search="query" />
			</h3>
			<h4 v-if="subline" class="unified-search__result-line-two" :title="subline">{{ subline }}</h4>
		</span>
	</a>
</template>

<script>
import Highlight from '@nextcloud/vue/dist/Components/Highlight'

export default {
	name: 'SearchResult',

	components: {
		Highlight,
	},

	props: {
		thumbnailUrl: {
			type: String,
			default: null,
		},
		title: {
			type: String,
			required: true,
		},
		subline: {
			type: String,
			default: null,
		},
		resourceUrl: {
			type: String,
			default: null,
		},
		icon: {
			type: String,
			default: '',
		},
		rounded: {
			type: Boolean,
			default: false,
		},
		query: {
			type: String,
			default: '',
		},

		/**
		 * Only used for the first result as a visual feedback
		 * so we can keep the search input focused but pressing
		 * enter still opens the first result
		 */
		focused: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			hasValidThumbnail: this.thumbnailUrl && this.thumbnailUrl.trim() !== '',
			loaded: false,
		}
	},

	computed: {
		isIconUrl() {
			// If we're facing an absolute url
			if (this.icon.startsWith('/')) {
				return true
			}

			// Otherwise, let's check if this is a valid url
			try {
				// eslint-disable-next-line no-new
				new URL(this.icon)
			} catch {
				return false
			}
			return true
		},
	},

	watch: {
		// Make sure to reset state on change even when vue recycle the component
		thumbnailUrl() {
			this.hasValidThumbnail = this.thumbnailUrl && this.thumbnailUrl.trim() !== ''
			this.loaded = false
		},
	},

	methods: {
		reEmitEvent(e) {
			this.$emit(e.type, e)
		},

		/**
		 * If the image fails to load, fallback to iconClass
		 */
		onError() {
			this.hasValidThumbnail = false
		},

		onLoad() {
			this.loaded = true
		},
	},
}
</script>

<style lang="scss" scoped>
$clickable-area: 44px;
$margin: 10px;

.unified-search__result {
	display: flex;
	height: $clickable-area;
	padding: $margin;
	border-bottom: 1px solid var(--color-border);

	// Load more entry,
	&:last-child {
		border-bottom: none;
	}

	&--focused,
	&:active,
	&:hover,
	&:focus {
		background-color: var(--color-background-hover);
	}

	* {
		cursor: pointer;
	}

	&-icon {
		overflow: hidden;
		width: $clickable-area;
		height: $clickable-area;
		border-radius: var(--border-radius);
		background-repeat: no-repeat;
		background-position: center center;
		background-size: 32px;
		&--rounded {
			border-radius: $clickable-area / 2;
		}
		&--no-preview {
			background-size: 32px;
		}
		&--with-thumbnail {
			background-size: cover;
		}
		&--with-thumbnail:not(&--rounded) {
			// compensate for border
			max-width: $clickable-area - 2px;
			max-height: $clickable-area - 2px;
			border: 1px solid var(--color-border);
		}

		img {
			// Make sure to keep ratio
			width: 100%;
			height: 100%;

			object-fit: cover;
			object-position: center;
		}
	}

	&-icon,
	&-actions {
		flex: 0 0 $clickable-area;
	}

	&-content {
		display: flex;
		align-items: center;
		flex: 1 1 100%;
		flex-wrap: wrap;
		// Set to minimum and gro from it
		min-width: 0;
		padding-left: $margin;
	}

	&-line-one,
	&-line-two {
		overflow: hidden;
		flex: 1 1 100%;
		margin: 1px 0;
		white-space: nowrap;
		text-overflow: ellipsis;
		// Use the same color as the `a`
		color: inherit;
		font-size: inherit;
	}
	&-line-two {
		opacity: .7;
		font-size: var(--default-font-size);
	}
}

</style>
