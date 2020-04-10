<?php
/**
 * @copyright Copyright (c) 2017 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Tests\Core\Command\Maintenance;

use OC\Core\Command\Maintenance\UpdateTheme;
use OC\Files\Type\Detection;
use OCP\Files\IMimeTypeDetector;
use OCP\ICache;
use OCP\ICacheFactory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class UpdateThemeTest extends TestCase {
	/** @var IMimeTypeDetector */
	protected $detector;
	/** @var ICacheFactory */
	protected $cacheFactory;


	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $consoleInput;
	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $consoleOutput;

	/** @var \Symfony\Component\Console\Command\Command */
	protected $command;

	protected function setUp(): void {
		parent::setUp();

		$this->detector = $this->createMock(Detection::class);
		$this->cacheFactory = $this->createMock(ICacheFactory::class);

		$this->consoleInput = $this->getMockBuilder(InputInterface::class)->getMock();
		$this->consoleOutput = $this->getMockBuilder(OutputInterface::class)->getMock();

		$this->command = new UpdateTheme($this->detector, $this->cacheFactory);
	}

	public function testThemeUpdate() {
		$this->consoleInput->method('getOption')
			->with('maintenance:theme:update')
			->willReturn(true);
		$this->detector->expects($this->once())
			->method('getAllAliases')
			->willReturn([]);
		$cache = $this->createMock(ICache::class);
		$cache->expects($this->once())
			->method('clear')
			->with('');
		$this->cacheFactory->expects($this->once())
			->method('createDistributed')
			->with('imagePath')
			->willReturn($cache);
		self::invokePrivate($this->command, 'execute', [$this->consoleInput, $this->consoleOutput]);
	}
}
