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
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<Modal v-if="opened"
		:clear-view-delay="-1"
		class="templates-picker"
		size="large"
		@close="close">
		<form class="templates-picker__form"
			:style="style"
			@submit.prevent.stop="onSubmit">
			<h2>{{ t('files', 'Pick a template for {name}', { name: nameWithoutExt }) }}</h2>

			<!-- Templates list -->
			<ul class="templates-picker__list">
				<TemplatePreview
					v-bind="emptyTemplate"
					:checked="checked === emptyTemplate.fileid"
					@check="onCheck" />

				<TemplatePreview
					v-for="template in provider.templates"
					:key="template.fileid"
					v-bind="template"
					:checked="checked === template.fileid"
					:ratio="provider.ratio"
					@check="onCheck" />
			</ul>

			<!-- Cancel and submit -->
			<div class="templates-picker__buttons">
				<button @click="close">
					{{ t('files', 'Cancel') }}
				</button>
				<input type="submit"
					class="primary"
					:value="t('files', 'Create')"
					:aria-label="t('files', 'Create a new file with the selected template')">
			</div>
		</form>

		<EmptyContent v-if="loading" class="templates-picker__loading" icon="icon-loading">
			{{ t('files', 'Creating file') }}
		</EmptyContent>
	</Modal>
</template>

<script>
import { generateOcsUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import Modal from '@nextcloud/vue/dist/Components/Modal'

import { getCurrentDirectory } from '../utils/davUtils'
import { getTemplates } from '../services/Templates'
import TemplatePreview from '../components/TemplatePreview'

const border = 2
const margin = 8
const width = margin * 20

export default {
	name: 'TemplatePicker',

	components: {
		EmptyContent,
		Modal,
		TemplatePreview,
	},

	props: {
		logger: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			// Check empty template by default
			checked: -1,
			loading: false,
			name: null,
			opened: false,
			provider: null,
		}
	},

	computed: {
		/**
		 * Strip away extension from name
		 * @returns {string}
		 */
		nameWithoutExt() {
			return this.name.indexOf('.') > -1 ? this.name.split('.').slice(0, -1).join('.') : this.name
		},

		emptyTemplate() {
			return {
				basename: t('files', 'Blank'),
				fileid: -1,
				filename: this.t('files', 'Blank'),
				hasPreview: false,
				mime: this.provider?.mimetypes[0] || this.provider?.mimetypes,
			}
		},

		selectedTemplate() {
			return this.provider.templates.find(template => template.fileid === this.checked)
		},

		/**
		 * Style css vars bin,d
		 * @returns {Object}
		 */
		style() {
			return {
				'--margin': margin + 'px',
				'--width': width + 'px',
				'--border': border + 'px',
				'--fullwidth': width + 2 * margin + 2 * border + 'px',
				'--height': this.provider.ratio ? Math.round(width / this.provider.ratio) + 'px' : null,
			}
		},
	},

	methods: {
		/**
		 * Open the picker
		 * @param {string} name the file name to create
		 * @param {object} provider the template provider picked
		 */
		async open(name, provider) {

			this.checked = this.emptyTemplate.fileid
			this.name = name
			this.provider = provider

			const templates = await getTemplates()
			const fetchedProvider = templates.find((fetchedProvider) => fetchedProvider.app === provider.app && fetchedProvider.label === provider.label)
			if (fetchedProvider === null) {
				throw new Error('Failed to match provider in results')
			}
			this.provider = fetchedProvider

			// If there is no templates available, just create an empty file
			if (fetchedProvider.templates.length === 0) {
				this.onSubmit()
				return
			}

			// Else, open the picker
			this.opened = true
		},

		/**
		 * Close the picker and reset variables
		 */
		close() {
			this.checked = this.emptyTemplate.fileid
			this.loading = false
			this.name = null
			this.opened = false
			this.provider = null
		},

		/**
		 * Manages the radio template picker change
		 * @param {number} fileid the selected template file id
		 */
		onCheck(fileid) {
			this.checked = fileid
		},

		async onSubmit() {
			this.loading = true
			const currentDirectory = getCurrentDirectory()
			const fileList = OCA?.Files?.App?.currentFileList

			try {
				const response = await axios.post(generateOcsUrl('apps/files/api/v1/templates', 2) + 'create', {
					filePath: `${currentDirectory}/${this.name}`,
					templatePath: this.selectedTemplate?.filename,
					templateType: this.selectedTemplate?.templateType,
				})

				const fileInfo = response.data.ocs.data
				this.logger.debug('Created new file', fileInfo)

				// Run default action
				const fileAction = OCA.Files.fileActions.getDefaultFileAction(fileInfo.mime, 'file', OC.PERMISSION_ALL)
				fileAction.action(fileInfo.basename, {
					$file: null,
					dir: currentDirectory,
					fileList,
					fileActions: fileList?.fileActions,
				})

				// Reload files list
				fileList?.reload?.() || window.location.reload()

				this.close()
			} catch (error) {
				this.logger.error('Error while creating the new file from template')
				console.error(error)
				showError(this.t('files', 'Unable to create new file from template'))
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.templates-picker {
	&__form {
		padding: calc(var(--margin) * 2);
		// Will be handled by the buttons
		padding-bottom: 0;

		h2 {
			text-align: center;
			font-weight: bold;
			margin: var(--margin) 0 calc(var(--margin) * 2);
		}
	}

	&__list {
		display: grid;
		grid-gap: calc(var(--margin) * 2);
		grid-auto-columns: 1fr;
		// We want maximum 5 columns. Putting 6 as we don't count the grid gap. So it will always be lower than 6
		max-width: calc(var(--fullwidth) * 6);
		grid-template-columns: repeat(auto-fit, var(--fullwidth));
		// Make sure all rows are the same height
		grid-auto-rows: 1fr;
		// Center the columns set
		justify-content: center;
	}

	&__buttons {
		display: flex;
		justify-content: space-between;
		padding: calc(var(--margin) * 2) var(--margin);
		position: sticky;
		bottom: 0;
		background-image: linear-gradient(0, var(--gradient-main-background));

		button, input[type='submit'] {
			height: 44px;
		}
	}

	// Make sure we're relative for the loading emptycontent on top
	/deep/ .modal-container {
		position: relative;
		overflow-y: auto !important;
	}

	&__loading {
		position: absolute;
		top: 0;
		left: 0;
		justify-content: center;
		width: 100%;
		height: 100%;
		margin: 0;
		background-color: var(--color-main-background-translucent);
	}
}

</style>
