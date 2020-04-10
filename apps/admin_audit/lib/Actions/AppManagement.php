<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\AdminAudit\Actions;

class AppManagement extends Action {

	/**
	 * @param string $appName
	 */
	public function enableApp(string $appName) {
		$this->log('App "%s" enabled',
			['app' => $appName],
			['app']
		);
	}

	/**
	 * @param string $appName
	 * @param string[] $groups
	 */
	public function enableAppForGroups(string $appName, array $groups) {
		$this->log('App "%1$s" enabled for groups: %2$s',
			['app' => $appName, 'groups' => implode(', ', $groups)],
			['app', 'groups']
		);
	}

	/**
	 * @param string $appName
	 */
	public function disableApp(string $appName) {
		$this->log('App "%s" disabled',
			['app' => $appName],
			['app']
		);
	}
}
