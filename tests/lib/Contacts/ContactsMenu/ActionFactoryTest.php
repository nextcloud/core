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

use OC\Contacts\ContactsMenu\ActionFactory;
use OCP\Contacts\ContactsMenu\IAction;
use Test\TestCase;

class ActionFactoryTest extends TestCase {

	/** @var ActionFactory */
	private $actionFactory;

	protected function setUp(): void {
		parent::setUp();

		$this->actionFactory = new ActionFactory();
	}

	public function testNewLinkAction() {
		$icon = 'icon-test';
		$name = 'Test';
		$href = 'some/url';

		$action = $this->actionFactory->newLinkAction($icon, $name, $href);

		$this->assertInstanceOf(IAction::class, $action);
		$this->assertEquals($name, $action->getName());
		$this->assertEquals(10, $action->getPriority());
	}

	public function testNewEMailAction() {
		$icon = 'icon-test';
		$name = 'Test';
		$href = 'user@example.com';

		$action = $this->actionFactory->newEMailAction($icon, $name, $href);

		$this->assertInstanceOf(IAction::class, $action);
		$this->assertEquals($name, $action->getName());
		$this->assertEquals(10, $action->getPriority());
		$this->assertEquals('mailto:user@example.com', $action->getHref());
	}

}
