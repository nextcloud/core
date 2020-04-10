<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCP\Capabilities;

/**
 * Minimal interface that has to be implemented for a class to be considered
 * a capability.
 *
 * In an application use:
 *   $this->getContainer()->registerCapability('OCA\MY_APP\Capabilities');
 * To register capabilities.
 *
 * The class 'OCA\MY_APP\Capabilities' must then implement ICapability
 *
 * @since 8.2.0
 */
interface ICapability {

	/**
	 * Function an app uses to return the capabilities
	 *
	 * @return array Array containing the apps capabilities
	 * @since 8.2.0
	 */
	public function getCapabilities();
}
