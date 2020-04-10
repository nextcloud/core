<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCP\AppFramework;

use OCP\IContainer;

/**
 * Class IAppContainer
 * @package OCP\AppFramework
 *
 * This container interface provides short cuts for app developers to access predefined app service.
 * @since 6.0.0
 */
interface IAppContainer extends IContainer {

	/**
	 * used to return the appname of the set application
	 * @return string the name of your application
	 * @since 6.0.0
	 */
	public function getAppName();

	/**
	 * @return \OCP\IServerContainer
	 * @since 6.0.0
	 */
	public function getServer();

	/**
	 * @param string $middleWare
	 * @return boolean
	 * @since 6.0.0
	 */
	public function registerMiddleWare($middleWare);

	/**
	 * Register a capability
	 *
	 * @param string $serviceName e.g. 'OCA\Files\Capabilities'
	 * @since 8.2.0
	 */
	 public function registerCapability($serviceName);
}
