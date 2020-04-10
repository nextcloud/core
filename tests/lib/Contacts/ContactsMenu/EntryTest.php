<?php

/**
 * @copyright 2017 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2017 Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace Tests\Contacts\ContactsMenu;

use OC\Contacts\ContactsMenu\Actions\LinkAction;
use OC\Contacts\ContactsMenu\Entry;
use Test\TestCase;

class EntryTest extends TestCase {

	/** @var Entry */
	private $entry;

	protected function setUp(): void {
		parent::setUp();

		$this->entry = new Entry();
	}

	public function testSetId() {
		$this->entry->setId(123);
		$this->addToAssertionCount(1);
	}

	public function testSetGetFullName() {
		$fn = 'Danette Chaille';
		$this->assertEquals('', $this->entry->getFullName());
		$this->entry->setFullName($fn);
		$this->assertEquals($fn, $this->entry->getFullName());
	}

	public function testAddGetEMailAddresses() {
		$this->assertEmpty($this->entry->getEMailAddresses());
		$this->entry->addEMailAddress('user@example.com');
		$this->assertEquals(['user@example.com'], $this->entry->getEMailAddresses());
	}

	public function testAddAndSortAction() {
		// Three actions, two with equal priority
		$action1 = new LinkAction();
		$action2 = new LinkAction();
		$action3 = new LinkAction();
		$action1->setPriority(10);
		$action1->setName('Bravo');

		$action2->setPriority(0);
		$action2->setName('Batman');

		$action3->setPriority(10);
		$action3->setName('Alfa');

		$this->entry->addAction($action1);
		$this->entry->addAction($action2);
		$this->entry->addAction($action3);
		$sorted = $this->entry->getActions();

		$this->assertSame($action3, $sorted[0]);
		$this->assertSame($action1, $sorted[1]);
		$this->assertSame($action2, $sorted[2]);
	}

	public function testSetGetProperties() {
		$props = [
			'prop1' => 123,
			'prop2' => 'string',
		];

		$this->entry->setProperties($props);

		$this->assertNull($this->entry->getProperty('doesntexist'));
		$this->assertEquals(123, $this->entry->getProperty('prop1'));
		$this->assertEquals('string', $this->entry->getProperty('prop2'));
	}

	public function testJsonSerialize() {
		$expectedJson = [
			'id' => 123,
			'fullName' => 'Guadalupe Frisbey',
			'topAction' => null,
			'actions' => [],
			'lastMessage' => '',
			'avatar' => null,
		];

		$this->entry->setId(123);
		$this->entry->setFullName('Guadalupe Frisbey');
		$json = $this->entry->jsonSerialize();

		$this->assertEquals($expectedJson, $json);
	}

}
