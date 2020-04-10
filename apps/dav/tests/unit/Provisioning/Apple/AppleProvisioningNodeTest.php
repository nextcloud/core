<?php
/**
 * @copyright Copyright (c) 2018 Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
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

namespace OCA\DAV\Tests\unit\Provisioning\Apple;

use OCA\DAV\Provisioning\Apple\AppleProvisioningNode;
use OCP\AppFramework\Utility\ITimeFactory;
use Sabre\DAV\PropPatch;
use Test\TestCase;

class AppleProvisioningNodeTest extends TestCase {

	/** @var ITimeFactory|\PHPUnit_Framework_MockObject_MockObject */
	private $timeFactory;

	/** @var AppleProvisioningNode */
	private $node;

	protected function setUp(): void {
		parent::setUp();

		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->node = new AppleProvisioningNode($this->timeFactory);
	}

	public function testGetName() {
		$this->assertEquals('apple-provisioning.mobileconfig', $this->node->getName());
	}

	
	public function testSetName() {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);
		$this->expectExceptionMessage('Renaming apple-provisioning.mobileconfig is forbidden');

		$this->node->setName('foo');
	}

	public function testGetLastModified() {
		$this->assertEquals(null, $this->node->getLastModified());
	}

	
	public function testDelete() {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);
		$this->expectExceptionMessage('apple-provisioning.mobileconfig may not be deleted');

		$this->node->delete();
	}

	public function testGetProperties() {
		$this->timeFactory->expects($this->at(0))
			->method('getDateTime')
			->willReturn(new \DateTime('2000-01-01'));

		$this->assertEquals([
			'{DAV:}getcontentlength' => 42,
			'{DAV:}getlastmodified' => 'Sat, 01 Jan 2000 00:00:00 +0000',
		], $this->node->getProperties([]));
	}

	
	public function testGetPropPatch() {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);
		$this->expectExceptionMessage('apple-provisioning.mobileconfig\'s properties may not be altered.');

		$propPatch = $this->createMock(PropPatch::class);

		$this->node->propPatch($propPatch);
	}
}
