<?php
/**
 * @copyright Copyright (c) 2017 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OCA\Group_LDAP\Tests\Migration;

use OCA\User_LDAP\Group_Proxy;
use OCA\User_LDAP\Mapping\GroupMapping;
use OCA\User_LDAP\Migration\UUIDFixGroup;
use OCA\User_LDAP\Tests\Migration\AbstractUUIDFixTest;

/**
 * Class UUIDFixGroupTest
 *
 * @package OCA\Group_LDAP\Tests\Migration
 * @group DB
 */
class UUIDFixGroupTest extends AbstractUUIDFixTest {
	protected function setUp(): void {
		$this->isUser = false;
		parent::setUp();

		$this->isUser = false;

		$this->mapper = $this->createMock(GroupMapping::class);

		$this->mockProxy(Group_Proxy::class);
		$this->instantiateJob(UUIDFixGroup::class);
	}

}
