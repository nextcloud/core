<!--
  - @copyright Copyright (c) 2020 Julius Härtl <jus@bitgrid.net>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div class="settings-markdown" v-html="renderMarkdown" />
</template>

<script>
import marked from 'marked'
import dompurify from 'dompurify'

export default {
	name: 'Markdown',
	props: {
		text: {
			type: String,
			default: '',
		},
	},
	computed: {
		renderMarkdown() {
			const renderer = new marked.Renderer()
			renderer.link = function(href, title, text) {
				let prot
				try {
					prot = decodeURIComponent(unescape(href))
						.replace(/[^\w:]/g, '')
						.toLowerCase()
				} catch (e) {
					return ''
				}

				if (prot.indexOf('http:') !== 0 && prot.indexOf('https:') !== 0) {
					return ''
				}

				let out = '<a href="' + href + '" rel="noreferrer noopener"'
				if (title) {
					out += ' title="' + title + '"'
				}
				out += '>' + text + '</a>'
				return out
			}
			renderer.image = function(href, title, text) {
				if (text) {
					return text
				}
				return title
			}
			renderer.blockquote = function(quote) {
				return quote
			}
			return dompurify.sanitize(
				marked(this.text.trim(), {
					renderer,
					gfm: false,
					highlight: false,
					tables: false,
					breaks: false,
					pedantic: false,
					sanitize: true,
					smartLists: true,
					smartypants: false,
				}),
				{
					SAFE_FOR_JQUERY: true,
					ALLOWED_TAGS: [
						'h1',
						'h2',
						'h3',
						'h4',
						'h5',
						'h6',
						'strong',
						'p',
						'a',
						'ul',
						'ol',
						'li',
						'em',
						'del',
						'blockquote',
					],
				}
			)
		},
	},
}
</script>

<style scoped lang="scss">
	.settings-markdown::v-deep {

		h1,
		h2,
		h3,
		h4,
		h5,
		h6 {
			font-weight: 600;
			line-height: 120%;
			margin-top: 24px;
			margin-bottom: 12px;
			color: var(--color-main-text);
		}

		h1 {
			font-size: 36px;
			margin-top: 48px;
		}

		h2 {
			font-size: 28px;
			margin-top: 48px;
		}

		h3 {
			font-size: 24px;
		}

		h4 {
			font-size: 21px;
		}

		h5 {
			font-size: 17px;
		}

		h6 {
			font-size: var(--default-font-size);
		}

		pre {
			white-space: pre;
			overflow-x: auto;
			background-color: var(--color-background-dark);
			border-radius: var(--border-radius);
			padding: 1em 1.3em;
			margin-bottom: 1em;
		}

		p code {
			background-color: var(--color-background-dark);
			border-radius: var(--border-radius);
			padding: .1em .3em;
		}

		li {
			position: relative;
		}

		ul, ol {
			padding-left: 10px;
			margin-left: 10px;
		}

		ul li {
			list-style-type: disc;
		}

		ul > li > ul > li {
			list-style-type: circle;
		}

		ul > li > ul > li ul li {
			list-style-type: square;
		}

		blockquote {
			padding-left: 1em;
			border-left: 4px solid var(--color-primary-element);
			color: var(--color-text-maxcontrast);
			margin-left: 0;
			margin-right: 0;
		}

	}
</style>
