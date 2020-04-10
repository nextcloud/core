<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
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

namespace OC\AppFramework\Routing;

use OC\AppFramework\App;
use OC\AppFramework\DependencyInjection\DIContainer;

class RouteActionHandler {
	private $controllerName;
	private $actionName;
	private $container;

	/**
	 * @param string $controllerName
	 * @param string $actionName
	 */
	public function __construct(DIContainer $container, $controllerName, $actionName) {
		$this->controllerName = $controllerName;
		$this->actionName = $actionName;
		$this->container = $container;
	}

	public function __invoke($params) {
		App::main($this->controllerName, $this->actionName, $this->container, $params);
	}
}
