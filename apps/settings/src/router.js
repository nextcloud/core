/**
 * @copyright Copyright (c) 2018 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import Vue from 'vue'
import Router from 'vue-router'
import { generateUrl } from '@nextcloud/router'

// Dynamic loading
const Users = () => import(/* webpackChunkName: 'settings-users' */'./views/Users')
const Apps = () => import(/* webpackChunkName: 'settings-apps' */'./views/Apps')

Vue.use(Router)

/*
 * This is the list of routes where the vuejs app will
 * take over php to provide data
 * You need to forward the php routing (routes.php) to
 * the settings-vue template, where the vue-router will
 * ensure the proper route.
 * ⚠️ Routes needs to match the php routes.
 */

export default new Router({
	mode: 'history',
	// if index.php is in the url AND we got this far, then it's working:
	// let's keep using index.php in the url
	base: generateUrl(''),
	linkActiveClass: 'active',
	routes: [
		{
			path: '/:index(index.php/)?settings/users',
			component: Users,
			props: true,
			name: 'users',
			children: [
				{
					path: ':selectedGroup',
					name: 'group',
					component: Users,
				},
			],
		},
		{
			path: '/:index(index.php/)?settings/apps',
			component: Apps,
			props: true,
			name: 'apps',
			children: [
				{
					path: ':category',
					name: 'apps-category',
					component: Apps,
					children: [
						{
							path: ':id',
							name: 'apps-details',
							component: Apps,
						},
					],
				},
			],
		},
	],
})
