<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\DAV\Tests\unit\CardDAV;

use OCA\DAV\CardDAV\CardDavBackend;
use OCA\DAV\CardDAV\ContactsManager;
use OCP\Contacts\IManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use Test\TestCase;

class ContactsManagerTest extends TestCase {
	public function test() {
		/** @var IManager | \PHPUnit_Framework_MockObject_MockObject $cm */
		$cm = $this->getMockBuilder(IManager::class)->disableOriginalConstructor()->getMock();
		$cm->expects($this->exactly(2))->method('registerAddressBook');
		$urlGenerator = $this->getMockBuilder(IURLGenerator::class)->disableOriginalConstructor()->getMock();
		/** @var CardDavBackend | \PHPUnit_Framework_MockObject_MockObject $backEnd */
		$backEnd = $this->getMockBuilder(CardDavBackend::class)->disableOriginalConstructor()->getMock();
		$backEnd->method('getAddressBooksForUser')->willReturn([
			['{DAV:}displayname' => 'Test address book', 'uri' => 'default'],
		]);

		$l = $this->createMock(IL10N::class);
		$app = new ContactsManager($backEnd, $l);
		$app->setupContactsProvider($cm, 'user01', $urlGenerator);
	}
}
