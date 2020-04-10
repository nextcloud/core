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

namespace Tests\Contacts\ContactsMenu\Providers;

use OC\Contacts\ContactsMenu\Providers\EMailProvider;
use OCP\Contacts\ContactsMenu\IActionFactory;
use OCP\Contacts\ContactsMenu\IEntry;
use OCP\Contacts\ContactsMenu\ILinkAction;
use OCP\IURLGenerator;
use PHPUnit_Framework_MockObject_MockObject;
use Test\TestCase;

class EMailproviderTest extends TestCase {

	/** @var IActionFactory|PHPUnit_Framework_MockObject_MockObject */
	private $actionFactory;

	/** @var IURLGenerator|PHPUnit_Framework_MockObject_MockObject */
	private $urlGenerator;

	/** @var EMailProvider */
	private $provider;

	protected function setUp(): void {
		parent::setUp();

		$this->actionFactory = $this->createMock(IActionFactory::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);

		$this->provider = new EMailProvider($this->actionFactory, $this->urlGenerator);
	}

	public function testProcess() {
		$entry = $this->createMock(IEntry::class);
		$action = $this->createMock(ILinkAction::class);
		$iconUrl = 'https://example.com/img/actions/icon.svg';
		$this->urlGenerator->expects($this->once())
			->method('imagePath')
			->willReturn('img/actions/icon.svg');
		$this->urlGenerator->expects($this->once())
			->method('getAbsoluteURL')
			->with('img/actions/icon.svg')
			->willReturn($iconUrl);
		$entry->expects($this->once())
			->method('getEMailAddresses')
			->willReturn([
				'user@example.com',
			]);
		$this->actionFactory->expects($this->once())
			->method('newEMailAction')
			->with($this->equalTo($iconUrl), $this->equalTo('user@example.com'), $this->equalTo('user@example.com'))
			->willReturn($action);
		$entry->expects($this->once())
			->method('addAction')
			->with($action);

		$this->provider->process($entry);
	}

	public function testProcessEmptyAddress() {
		$entry = $this->createMock(IEntry::class);
		$action = $this->createMock(ILinkAction::class);
		$iconUrl = 'https://example.com/img/actions/icon.svg';
		$this->urlGenerator->expects($this->once())
			->method('imagePath')
			->willReturn('img/actions/icon.svg');
		$this->urlGenerator->expects($this->once())
			->method('getAbsoluteURL')
			->with('img/actions/icon.svg')
			->willReturn($iconUrl);
		$entry->expects($this->once())
			->method('getEMailAddresses')
			->willReturn([
				'',
			]);
		$this->actionFactory->expects($this->never())
			->method('newEMailAction');
		$entry->expects($this->never())
			->method('addAction');

		$this->provider->process($entry);
	}

}
