<?php
/**
 * @author Robin McCorkell <rmccorkell@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace Tests\Core\Command\Maintenance\Mimetype;

use OC\Core\Command\Maintenance\Mimetype\UpdateDB;
use OC\Files\Type\Detection;
use OC\Files\Type\Loader;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IMimeTypeLoader;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class UpdateDBTest extends TestCase {
	/** @var IMimeTypeDetector */
	protected $detector;
	/** @var IMimeTypeLoader */
	protected $loader;

	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $consoleInput;
	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $consoleOutput;

	/** @var \Symfony\Component\Console\Command\Command */
	protected $command;

	protected function setUp(): void {
		parent::setUp();

		$this->detector = $this->getMockBuilder(Detection::class)
			->disableOriginalConstructor()
			->getMock();
		$this->loader = $this->getMockBuilder(Loader::class)
			->disableOriginalConstructor()
			->getMock();

		$this->consoleInput = $this->getMockBuilder(InputInterface::class)->getMock();
		$this->consoleOutput = $this->getMockBuilder(OutputInterface::class)->getMock();

		$this->command = new UpdateDB($this->detector, $this->loader);
	}

	public function testNoop() {
		$this->consoleInput->method('getOption')
			->with('repair-filecache')
			->willReturn(false);

		$this->detector->expects($this->once())
			->method('getAllMappings')
			->willReturn([
				'ext' => ['testing/existingmimetype']
			]);
		$this->loader->expects($this->once())
			->method('exists')
			->with('testing/existingmimetype')
			->willReturn(true);

		$this->loader->expects($this->never())
			->method('updateFilecache');

		$this->consoleOutput->expects($this->at(0))
			->method('writeln')
			->with('Added 0 new mimetypes');
		$this->consoleOutput->expects($this->at(1))
			->method('writeln')
			->with('Updated 0 filecache rows');

		self::invokePrivate($this->command, 'execute', [$this->consoleInput, $this->consoleOutput]);
	}

	public function testAddMimetype() {
		$this->consoleInput->method('getOption')
			->with('repair-filecache')
			->willReturn(false);

		$this->detector->expects($this->once())
			->method('getAllMappings')
			->willReturn([
				'ext' => ['testing/existingmimetype'],
				'new' => ['testing/newmimetype']
			]);
		$this->loader->expects($this->exactly(2))
			->method('exists')
			->willReturnMap([
				['testing/existingmimetype', true],
				['testing/newmimetype', false],
			]);
		$this->loader->expects($this->exactly(2))
			->method('getId')
			->willReturnMap([
				['testing/existingmimetype', 1],
				['testing/newmimetype', 2],
			]);

		$this->loader->expects($this->once())
			->method('updateFilecache')
			->with('new', 2)
			->willReturn(3);

		$this->consoleOutput->expects($this->at(0))
			->method('writeln')
			->with('Added mimetype "testing/newmimetype" to database');
		$this->consoleOutput->expects($this->at(1))
			->method('writeln')
			->with('Updated 3 filecache rows for mimetype "testing/newmimetype"');

		$this->consoleOutput->expects($this->at(2))
			->method('writeln')
			->with('Added 1 new mimetypes');
		$this->consoleOutput->expects($this->at(3))
			->method('writeln')
			->with('Updated 3 filecache rows');

		self::invokePrivate($this->command, 'execute', [$this->consoleInput, $this->consoleOutput]);
	}

	public function testSkipComments() {
		$this->detector->expects($this->once())
			->method('getAllMappings')
			->willReturn([
				'_comment' => 'some comment in the JSON'
			]);
		$this->loader->expects($this->never())
			->method('exists');

		self::invokePrivate($this->command, 'execute', [$this->consoleInput, $this->consoleOutput]);
	}

	public function testRepairFilecache() {
		$this->consoleInput->method('getOption')
			->with('repair-filecache')
			->willReturn(true);

		$this->detector->expects($this->once())
			->method('getAllMappings')
			->willReturn([
				'ext' => ['testing/existingmimetype'],
			]);
		$this->loader->expects($this->exactly(1))
			->method('exists')
			->willReturnMap([
				['testing/existingmimetype', true],
			]);
		$this->loader->expects($this->exactly(1))
			->method('getId')
			->willReturnMap([
				['testing/existingmimetype', 1],
			]);

		$this->loader->expects($this->once())
			->method('updateFilecache')
			->with('ext', 1)
			->willReturn(3);

		$this->consoleOutput->expects($this->at(0))
			->method('writeln')
			->with('Updated 3 filecache rows for mimetype "testing/existingmimetype"');

		$this->consoleOutput->expects($this->at(1))
			->method('writeln')
			->with('Added 0 new mimetypes');
		$this->consoleOutput->expects($this->at(2))
			->method('writeln')
			->with('Updated 3 filecache rows');

		self::invokePrivate($this->command, 'execute', [$this->consoleInput, $this->consoleOutput]);
	}
}
