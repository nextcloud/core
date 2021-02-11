<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Morris Jobke <hey@morrisjobke.de>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\Route;

use OC\Route\Router;
use OCP\ILogger;
use Test\TestCase;

/**
 * Class RouterTest
 *
 * @package Test\Route
 */
class RouterTest extends TestCase {
	public function testGenerateConsecutively(): void {
		/** @var ILogger $logger */
		$logger = $this->createMock(ILogger::class);
		$router = new Router($logger);

		$this->assertEquals('/index.php/apps/files/', $router->generate('files.view.index'));

		// the OCS route is the prefixed one for the AppFramework - see /ocs/v1.php for routing details
		$this->assertEquals('/index.php/ocsapp/apps/dav/api/v1/direct', $router->generate('ocs.dav.direct.getUrl'));

		// special route name - should load all apps and then find the route
		$this->assertEquals('/index.php/apps/files/ajax/list.php', $router->generate('files_ajax_list'));

		// test caching
		$this->assertEquals('/index.php/apps/files/', $router->generate('files.view.index'));
	}
}
