<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

use OC\Log;
use OC\SystemConfig;
use OCA\DAV\Connector\Sabre\Exception\InvalidPath;
use OCA\DAV\Connector\Sabre\ExceptionLoggerPlugin as PluginToTest;
use PHPUnit_Framework_MockObject_MockObject;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\Exception\ServiceUnavailable;
use Sabre\DAV\Server;
use Test\TestCase;

class TestLogger extends Log {
	public $message;
	public $level;

	public function writeLog(string $app, $entry, int $level) {
		$this->level = $level;
		$this->message = $entry;
	}
}

class ExceptionLoggerPluginTest extends TestCase {

	/** @var Server */
	private $server;

	/** @var PluginToTest */
	private $plugin;

	/** @var TestLogger | PHPUnit_Framework_MockObject_MockObject */
	private $logger;

	private function init() {
		$config = $this->createMock(SystemConfig::class);
		$config->expects($this->any())
			->method('getValue')
			->willReturnCallback(function ($key, $default) {
				switch ($key) {
					case 'loglevel':
						return 0;
					default:
						return $default;
				}
			});

		$this->server = new Server();
		$this->logger = new TestLogger(new Log\File(\OC::$SERVERROOT.'/data/nextcloud.log', '', $config), $config);
		$this->plugin = new PluginToTest('unit-test', $this->logger);
		$this->plugin->initialize($this->server);
	}

	/**
	 * @dataProvider providesExceptions
	 */
	public function testLogging($expectedLogLevel, $expectedMessage, $exception) {
		$this->init();
		$this->plugin->logException($exception);

		$this->assertEquals($expectedLogLevel, $this->logger->level);
		$this->assertEquals(get_class($exception), $this->logger->message['Exception']);
		$this->assertEquals($expectedMessage, $this->logger->message['Message']);
	}

	public function providesExceptions() {
		return [
			[0, '', new NotFound()],
			[0, 'System in maintenance mode.', new ServiceUnavailable('System in maintenance mode.')],
			[4, 'Upgrade needed', new ServiceUnavailable('Upgrade needed')],
			[4, 'This path leads to nowhere', new InvalidPath('This path leads to nowhere')]
		];
	}

}
