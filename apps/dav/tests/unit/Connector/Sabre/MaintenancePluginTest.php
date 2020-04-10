<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\DAV\Tests\unit\Connector\Sabre;

use OCA\DAV\Connector\Sabre\MaintenancePlugin;
use OCP\IConfig;
use Test\TestCase;

/**
 * Class MaintenancePluginTest
 *
 * @package OCA\DAV\Tests\unit\Connector\Sabre
 */
class MaintenancePluginTest extends TestCase {
	/** @var IConfig */
	private $config;
	/** @var MaintenancePlugin */
	private $maintenancePlugin;

	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->getMockBuilder(IConfig::class)->getMock();
		$this->maintenancePlugin = new MaintenancePlugin($this->config);
	}

	
	public function testMaintenanceMode() {
		$this->expectException(\Sabre\DAV\Exception\ServiceUnavailable::class);
		$this->expectExceptionMessage('System in maintenance mode.');

		$this->config
			->expects($this->exactly(1))
			->method('getSystemValueBool')
			->with('maintenance')
			->willReturn(true);

		$this->maintenancePlugin->checkMaintenanceMode();
	}

}
