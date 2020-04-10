<template>
	<div id="accessibility" class="section">
		<h2>{{ t('accessibility', 'Accessibility') }}</h2>
		<p v-html="description" />
		<p v-html="descriptionDetail" />

		<div class="preview-list">
			<ItemPreview :key="highcontrast.id"
				:preview="highcontrast"
				:selected="selected.highcontrast"
				@select="selectHighContrast" />
			<ItemPreview v-for="preview in themes"
				:key="preview.id"
				:preview="preview"
				:selected="selected.theme"
				@select="selectTheme" />
			<ItemPreview v-for="preview in fonts"
				:key="preview.id"
				:preview="preview"
				:selected="selected.font"
				@select="selectFont" />
		</div>
	</div>
</template>

<script>
import ItemPreview from './components/ItemPreview'
import axios from '@nextcloud/axios'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'

export default {
	name: 'Accessibility',
	components: { ItemPreview },
	props: {
		availableConfig: {
			type: Object,
			required: true,
		},
		userConfig: {
			type: Object,
			required: true,
		},
	},
	computed: {
		themes() {
			return this.availableConfig.themes
		},
		highcontrast() {
			return this.availableConfig.highcontrast
		},
		fonts() {
			return this.availableConfig.fonts
		},
		selected() {
			return {
				theme: this.userConfig.theme,
				highcontrast: this.userConfig.highcontrast,
				font: this.userConfig.font,
			}
		},
		description() {
			// using the `t` replace method escape html, we have to do it manually :/
			return t(
				'accessibility',
				'Universal access is very important to us. We follow web standards and check to make everything usable also without mouse, and assistive software such as screenreaders. We aim to be compliant with the {guidelines}Web Content Accessibility Guidelines{linkend} 2.1 on AA level, with the high contrast theme even on AAA level.'
			)
				.replace('{guidelines}', this.guidelinesLink)
				.replace('{linkend}', '</a>')
		},
		guidelinesLink() {
			return `<a target="_blank" href="https://www.w3.org/WAI/standards-guidelines/wcag/" rel="noreferrer nofollow">`
		},
		descriptionDetail() {
			return t(
				'accessibility',
				'If you find any issues, don’t hesitate to report them on {issuetracker}our issue tracker{linkend}. And if you want to get involved, come join {designteam}our design team{linkend}!'
			)
				.replace('{issuetracker}', this.issuetrackerLink)
				.replace('{designteam}', this.designteamLink)
				.replace(/\{linkend\}/g, '</a>')
		},
		issuetrackerLink() {
			return `<a target="_blank" href="https://github.com/nextcloud/server/issues/" rel="noreferrer nofollow">`
		},
		designteamLink() {
			return `<a target="_blank" href="https://nextcloud.com/design" rel="noreferrer nofollow">`
		},
	},
	methods: {
		// SELECT handlers
		selectHighContrast(id) {
			this.selectItem('highcontrast', id)
		},
		selectTheme(id) {
			const previous = this.selected.theme
			if (previous) {
				document.body.classList.remove(previous)
			}
			if (id) {
				document.body.classList.add(id)
			}

			this.selectItem('theme', id)
		},
		selectFont(id) {
			this.selectItem('font', id)
		},

		/**
		 * Commit a change and force reload css
		 * Fetching the file again will trigger the server update
		 *
		 * @param {string} type type of the change (font, highcontrast or theme)
		 * @param {string} id the data of the change
		 */
		async selectItem(type, id) {
			try {
				const isDelete = id === ''
				await axios({
					url: generateOcsUrl('apps/accessibility/api/v1/config', 2) + type,
					method: isDelete ? 'DELETE' : 'PUT',
					data: {
						value: id,
					},
				})

				this.userConfig[type] = id

				// Remove old link
				const link = document.querySelector('link[rel=stylesheet][href*=accessibility][href*=user-]')
				if (!link) {
					// insert new css
					const link = document.createElement('link')
					link.rel = 'stylesheet'
					link.href = generateUrl('/apps/accessibility/css/user-style.css') + '?v=' + new Date().getTime()
					document.head.appendChild(link)
				} else {
					// compare arrays
					if (
						JSON.stringify(Object.values(this.selected))
						=== JSON.stringify([false, false])
					) {
						// if nothing is selected, blindly remove the css
						link.remove()
					} else {
						// force update
						link.href
							= link.href.split('?')[0]
							+ '?v='
							+ new Date().getTime()
					}
				}
			} catch (err) {
				console.error(err, err.response)
				OC.Notification.showTemporary(t('accessibility', err.response.data.ocs.meta.message + '. Unable to apply the setting.'))
			}
		},
	},
}
</script>
