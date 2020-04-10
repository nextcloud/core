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

namespace Tests\Contacts\ContactsMenu\Actions;

use OC\Contacts\ContactsMenu\Actions\LinkAction;
use Test\TestCase;

class LinkActionTest extends TestCase {

	private $action;

	protected function setUp(): void {
		parent::setUp();

		$this->action = new LinkAction();
	}

	public function testSetIcon() {
		$icon = 'icon-test';

		$this->action->setIcon($icon);
		$json = $this->action->jsonSerialize();

		$this->assertArrayHasKey('icon', $json);
		$this->assertEquals($json['icon'], $icon);
	}

	public function testGetSetName() {
		$name = 'Jane Doe';

		$this->assertNull($this->action->getName());
		$this->action->setName($name);
		$this->assertEquals($name, $this->action->getName());
	}

	public function testGetSetPriority() {
		$prio = 50;

		$this->assertEquals(10, $this->action->getPriority());
		$this->action->setPriority($prio);
		$this->assertEquals($prio, $this->action->getPriority());
	}

	public function testSetHref() {
		$this->action->setHref('/some/url');

		$json = $this->action->jsonSerialize();
		$this->assertArrayHasKey('hyperlink', $json);
		$this->assertEquals($json['hyperlink'], '/some/url');
	}

	public function testJsonSerialize() {
		$this->action->setIcon('icon-contacts');
		$this->action->setName('Nickie Works');
		$this->action->setPriority(33);
		$this->action->setHref('example.com');
		$expected = [
			'title' => 'Nickie Works',
			'icon' => 'icon-contacts',
			'hyperlink' => 'example.com',
		];

		$json = $this->action->jsonSerialize();

		$this->assertEquals($expected, $json);
	}

}
