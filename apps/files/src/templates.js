/**
 * @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { getLoggerBuilder } from '@nextcloud/logger'
import { loadState } from '@nextcloud/initial-state'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { getCurrentDirectory } from './utils/davUtils'
import axios from '@nextcloud/axios'
import Vue from 'vue'

import TemplatePickerView from './views/TemplatePicker'
import { getCurrentUser } from '@nextcloud/auth'
import { showError } from '@nextcloud/dialogs'

// Set up logger
const logger = getLoggerBuilder()
	.setApp('files')
	.detectUser()
	.build()

// Add translates functions
Vue.mixin({
	methods: {
		t,
		n,
	},
})

// Create document root
const TemplatePickerRoot = document.createElement('div')
TemplatePickerRoot.id = 'template-picker'
document.body.appendChild(TemplatePickerRoot)

// Retrieve and init templates
let templates = loadState('files', 'templates', [])
let templatesPath = loadState('files', 'templates_path', false)
logger.debug('Templates providers', templates)
logger.debug('Templates folder', { templatesPath })

// Init vue app
const View = Vue.extend(TemplatePickerView)
const TemplatePicker = new View({
	name: 'TemplatePicker',
	propsData: {
		logger,
	},
})
TemplatePicker.$mount('#template-picker')

// Init template engine after load to make sure it's the last injected entry
window.addEventListener('DOMContentLoaded', function() {
	if (!templatesPath) {
		logger.debug('Templates folder not initialized')
		const initTemplatesPlugin = {
			attach(menu) {
				// register the new menu entry
				menu.addMenuEntry({
					id: 'template-init',
					displayName: t('files', 'Set up templates folder'),
					templateName: t('files', 'Templates'),
					iconClass: 'icon-template-add',
					fileType: 'file',
					actionHandler(name) {
						initTemplatesFolder(name)
						menu.removeMenuEntry('template-init')
					},
				})
			},
		}
		OC.Plugins.register('OCA.Files.NewFileMenu', initTemplatesPlugin)
	}
})

// Init template files menu
templates.forEach((provider, index) => {
	const newTemplatePlugin = {
		attach(menu) {
			const fileList = menu.fileList

			// only attach to main file list, public view is not supported yet
			if (fileList.id !== 'files' && fileList.id !== 'files.public') {
				return
			}

			// register the new menu entry
			menu.addMenuEntry({
				id: `template-new-${provider.app}-${index}`,
				displayName: provider.label,
				templateName: provider.label + provider.extension,
				iconClass: provider.iconClass || 'icon-file',
				fileType: 'file',
				actionHandler(name) {
					TemplatePicker.open(name, provider)
				},
			})
		},
	}
	OC.Plugins.register('OCA.Files.NewFileMenu', newTemplatePlugin)
})

/**
 * Init the template directory
 *
 * @param {string} name the templates folder name
 */
const initTemplatesFolder = async function(name) {
	const templatePath = (getCurrentDirectory() + `/${name}`).replace('//', '/')
	try {
		logger.debug('Initializing the templates directory', { templatePath })
		const response = await axios.post(generateOcsUrl('apps/files/api/v1/templates', 2) + 'path', {
			templatePath,
			copySystemTemplates: true,
		})

		// Go to template directory
		OCA.Files.App.currentFileList.changeDirectory(templatePath, true, true)

		templates = response.data.ocs.data.templates
		templatesPath = response.data.ocs.data.template_path
	} catch (error) {
		logger.error('Unable to initialize the templates directory')
		showError(t('files', 'Unable to initialize the templates directory'))
	}
}
