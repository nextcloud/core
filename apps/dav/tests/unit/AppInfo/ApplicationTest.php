<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\DAV\Tests\unit\AppInfo;

use OCA\DAV\AppInfo\Application;
use OCA\DAV\CardDAV\CardDavBackend;
use OCA\DAV\CardDAV\ContactsManager;
use Test\TestCase;

/**
 * Class ApplicationTest
 *
 * @group DB
 *
 * @package OCA\DAV\Tests\Unit\AppInfo
 */
class ApplicationTest extends TestCase {
	public function test() {
		$app = new Application();
		$c = $app->getContainer();

		// assert service instances in the container are properly setup
		$s = $c->query(ContactsManager::class);
		$this->assertInstanceOf(ContactsManager::class, $s);
		$s = $c->query(CardDavBackend::class);
		$this->assertInstanceOf(CardDavBackend::class, $s);
	}
}
