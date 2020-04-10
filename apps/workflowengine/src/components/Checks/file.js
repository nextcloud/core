/**
 * @copyright Copyright (c) 2019 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

import { stringValidator, validateIPv4, validateIPv6 } from './../../helpers/validators'
import FileMimeType from './FileMimeType'
import FileSystemTag from './FileSystemTag'

const stringOrRegexOperators = (check) => {
	if (check.value.startsWith('/')) {
		return [
			{ operator: 'matches', name: t('workflowengine', 'matches') },
			{ operator: '!matches', name: t('workflowengine', 'does not match') },
		]
	}
	return [
		{ operator: 'is', name: t('workflowengine', 'is') },
		{ operator: '!is', name: t('workflowengine', 'is not') },
	]
}

const FileChecks = [
	{
		class: 'OCA\\WorkflowEngine\\Check\\FileName',
		name: t('workflowengine', 'File name'),
		operators: stringOrRegexOperators,
		placeholder: (check) => {
			if (check.operator === 'matches' || check.operator === '!matches') {
				return '/^dummy-.+$/i'
			}
			return 'filename.txt'
		},
		validate: stringValidator,
	},

	{
		class: 'OCA\\WorkflowEngine\\Check\\FileMimeType',
		name: t('workflowengine', 'File MIME type'),
		operators: stringOrRegexOperators,
		component: FileMimeType,
	},

	{
		class: 'OCA\\WorkflowEngine\\Check\\FileSize',
		name: t('workflowengine', 'File size (upload)'),
		operators: [
			{ operator: 'less', name: t('workflowengine', 'less') },
			{ operator: '!greater', name: t('workflowengine', 'less or equals') },
			{ operator: '!less', name: t('workflowengine', 'greater or equals') },
			{ operator: 'greater', name: t('workflowengine', 'greater') },
		],
		placeholder: (check) => '5 MB',
		validate: (check) => check.value ? check.value.match(/^[0-9]+[ ]?[kmgt]?b$/i) !== null : false,
	},

	{
		class: 'OCA\\WorkflowEngine\\Check\\RequestRemoteAddress',
		name: t('workflowengine', 'Request remote address'),
		operators: [
			{ operator: 'matchesIPv4', name: t('workflowengine', 'matches IPv4') },
			{ operator: '!matchesIPv4', name: t('workflowengine', 'does not match IPv4') },
			{ operator: 'matchesIPv6', name: t('workflowengine', 'matches IPv6') },
			{ operator: '!matchesIPv6', name: t('workflowengine', 'does not match IPv6') },
		],
		placeholder: (check) => {
			if (check.operator === 'matchesIPv6' || check.operator === '!matchesIPv6') {
				return '::1/128'
			}
			return '127.0.0.1/32'
		},
		validate: (check) => {
			if (check.operator === 'matchesIPv6' || check.operator === '!matchesIPv6') {
				return validateIPv6(check.value)
			}
			return validateIPv4(check.value)
		},
	},

	{
		class: 'OCA\\WorkflowEngine\\Check\\FileSystemTags',
		name: t('workflowengine', 'File system tag'),
		operators: [
			{ operator: 'is', name: t('workflowengine', 'is tagged with') },
			{ operator: '!is', name: t('workflowengine', 'is not tagged with') },
		],
		component: FileSystemTag,
	},
]

export default FileChecks
