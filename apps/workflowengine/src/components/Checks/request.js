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

import RequestUserAgent from './RequestUserAgent'
import RequestTime from './RequestTime'
import RequestURL from './RequestURL'
import RequestUserGroup from './RequestUserGroup'

const RequestChecks = [
	{
		class: 'OCA\\WorkflowEngine\\Check\\RequestURL',
		name: t('workflowengine', 'Request URL'),
		operators: [
			{ operator: 'is', name: t('workflowengine', 'is') },
			{ operator: '!is', name: t('workflowengine', 'is not') },
			{ operator: 'matches', name: t('workflowengine', 'matches') },
			{ operator: '!matches', name: t('workflowengine', 'does not match') },
		],
		component: RequestURL,
	},
	{
		class: 'OCA\\WorkflowEngine\\Check\\RequestTime',
		name: t('workflowengine', 'Request time'),
		operators: [
			{ operator: 'in', name: t('workflowengine', 'between') },
			{ operator: '!in', name: t('workflowengine', 'not between') },
		],
		component: RequestTime,
	},
	{
		class: 'OCA\\WorkflowEngine\\Check\\RequestUserAgent',
		name: t('workflowengine', 'Request user agent'),
		operators: [
			{ operator: 'is', name: t('workflowengine', 'is') },
			{ operator: '!is', name: t('workflowengine', 'is not') },
			{ operator: 'matches', name: t('workflowengine', 'matches') },
			{ operator: '!matches', name: t('workflowengine', 'does not match') },
		],
		component: RequestUserAgent,
	},
	{
		class: 'OCA\\WorkflowEngine\\Check\\UserGroupMembership',
		name: t('workflowengine', 'User group membership'),
		operators: [
			{ operator: 'is', name: t('workflowengine', 'is member of') },
			{ operator: '!is', name: t('workflowengine', 'is not member of') },
		],
		component: RequestUserGroup,
	},
]

export default RequestChecks
