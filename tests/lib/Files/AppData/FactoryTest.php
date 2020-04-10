<?php
/**
 * @copyright 2016 Roeland Jago Douma <roeland@famdouma.nl>
 *
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\Files\AppData;

use OC\Files\AppData\Factory;
use OC\SystemConfig;
use OCP\Files\IRootFolder;

class FactoryTest extends \Test\TestCase {
	/** @var IRootFolder|\PHPUnit_Framework_MockObject_MockObject */
	private $rootFolder;

	/** @var SystemConfig|\PHPUnit_Framework_MockObject_MockObject */
	private $systemConfig;

	/** @var Factory */
	private $factory;

	protected function setUp(): void {
		parent::setUp();

		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->systemConfig = $this->createMock(SystemConfig::class);
		$this->factory = new Factory($this->rootFolder, $this->systemConfig);
	}

	public function testGet() {
		$this->rootFolder->expects($this->never())
			->method($this->anything());
		$this->systemConfig->expects($this->never())
			->method($this->anything());

		$this->factory->get('foo');
	}
}
