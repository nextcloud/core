<?php
/**
 * @copyright Copyright (c) 2018 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Johannes Ernst <jernst@indiecomputing.com>
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

namespace Test\Log;

use OC\Log\Errorlog;
use OC\Log\File;
use OC\Log\LogFactory;
use OC\Log\Syslog;
use OC\Log\Systemdlog;
use OC\SystemConfig;
use OCP\IServerContainer;
use Test\TestCase;

/**
 * Class LogFactoryTest
 *
 * @package Test\Log
 */
class LogFactoryTest extends TestCase {
	/** @var IServerContainer|\PHPUnit_Framework_MockObject_MockObject */
	protected $c;

	/** @var LogFactory */
	protected $factory;

	/** @var SystemConfig|\PHPUnit_Framework_MockObject_MockObject */
	protected $systemConfig;

	protected function setUp(): void {
		parent::setUp();

		$this->c = $this->createMock(IServerContainer::class);
		$this->systemConfig = $this->createMock(SystemConfig::class);

		$this->factory = new LogFactory($this->c, $this->systemConfig);
	}

	public function fileTypeProvider(): array {
		return [
			[
				'file'
			],
			[
				'nextcloud'
			],
			[
				'owncloud'
			],
			[
				'krzxkyr_default'
			]
		];
	}

	/**
	 * @param string $type
	 * @dataProvider fileTypeProvider
	 * @throws \OCP\AppFramework\QueryException
	 */
	public function testFile(string $type) {
		$datadir = \OC::$SERVERROOT.'/data';
		$defaultLog = $datadir . '/nextcloud.log';

		$this->systemConfig->expects($this->exactly(3))
			->method('getValue')
			->withConsecutive(['datadirectory', $datadir], ['logfile', $defaultLog], ['logfilemode', 0640])
			->willReturnOnConsecutiveCalls($datadir, $defaultLog, 0640);

		$log = $this->factory->get($type);
		$this->assertInstanceOf(File::class, $log);
	}

	public function logFilePathProvider():array {
		return [
			[
				'/dev/null',
				'/dev/null'
			],
			[
				'/xdev/youshallfallback',
				\OC::$SERVERROOT.'/data/nextcloud.log'
			]
		];
	}

	/**
	 * @dataProvider logFilePathProvider
	 * @throws \OCP\AppFramework\QueryException
	 */
	public function testFileCustomPath($path, $expected) {
		$datadir = \OC::$SERVERROOT.'/data';
		$defaultLog = $datadir . '/nextcloud.log';

		$this->systemConfig->expects($this->exactly(3))
			->method('getValue')
			->withConsecutive(['datadirectory', $datadir], ['logfile', $defaultLog], ['logfilemode', 0640])
			->willReturnOnConsecutiveCalls($datadir, $path, 0640);

		$log = $this->factory->get('file');
		$this->assertInstanceOf(File::class, $log);
		$this->assertSame($expected, $log->getLogFilePath());
	}

	/**
	 * @throws \OCP\AppFramework\QueryException
	 */
	public function testErrorLog() {
		$log = $this->factory->get('errorlog');
		$this->assertInstanceOf(Errorlog::class, $log);
	}

	/**
	 * @throws \OCP\AppFramework\QueryException
	 */
	public function testSystemLog() {
		$this->c->expects($this->once())
			->method('resolve')
			->with(Syslog::class)
			->willReturn($this->createMock(Syslog::class));

		$log = $this->factory->get('syslog');
		$this->assertInstanceOf(Syslog::class, $log);
	}

	/**
	 * @throws \OCP\AppFramework\QueryException
	 */
	public function testSystemdLog() {
		$this->c->expects($this->once())
			->method('resolve')
			->with(Systemdlog::class)
			->willReturn($this->createMock(Systemdlog::class));

		$log = $this->factory->get('systemd');
		$this->assertInstanceOf(Systemdlog::class, $log);
	}
}
