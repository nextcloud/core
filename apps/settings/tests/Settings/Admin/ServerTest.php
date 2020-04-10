<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\Settings\Tests\Settings\Admin;

use OCA\Settings\Settings\Admin\Server;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

/**
 * @group DB
 */
class ServerTest extends TestCase {
	/** @var Server */
	private $admin;
	/** @var IDBConnection */
	private $connection;
	/** @var ITimeFactory|MockObject */
	private $timeFactory;
	/** @var IConfig|MockObject */
	private $config;

	protected function setUp(): void {
		parent::setUp();
		$this->connection = \OC::$server->getDatabaseConnection();
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->config = $this->createMock(IConfig::class);

		$this->admin = $this->getMockBuilder(Server::class)
			->onlyMethods(['cronMaxAge'])
			->setConstructorArgs([
				$this->connection,
				$this->timeFactory,
				$this->config,
			])
			->getMock();
	}

	public function testGetForm(): void {
		$this->admin->expects($this->once())
			->method('cronMaxAge')
			->willReturn(1337);
		$this->config
			->expects($this->at(0))
			->method('getAppValue')
			->with('core', 'backgroundjobs_mode', 'ajax')
			->willReturn('ajax');
		$this->config
			->expects($this->at(1))
			->method('getAppValue')
			->with('core', 'lastcron', false)
			->willReturn(false);
		$this->config
			->expects($this->at(2))
			->method('getAppValue')
			->with('core', 'cronErrors')
			->willReturn('');
		$expected = new TemplateResponse(
			'settings',
			'settings/admin/server',
			[
				'backgroundjobs_mode' => 'ajax',
				'lastcron'            => false,
				'cronErrors'          => '',
				'cronMaxAge'          => 1337,
				'cli_based_cron_possible' => true,
				'cli_based_cron_user' => function_exists('posix_getpwuid') ? posix_getpwuid(fileowner(\OC::$configDir . 'config.php'))['name'] : '', // to not explode here because of posix extension not being disabled - which is already checked in the line above
			],
			''
		);

		$this->assertEquals($expected, $this->admin->getForm());
	}

	public function testGetSection(): void {
		$this->assertSame('server', $this->admin->getSection());
	}

	public function testGetPriority(): void {
		$this->assertSame(0, $this->admin->getPriority());
	}
}
