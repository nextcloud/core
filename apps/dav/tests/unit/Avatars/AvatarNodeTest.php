<?php
/**
 * @copyright Copyright (c) 2017, ownCloud GmbH
 *
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

namespace OCA\DAV\Tests\Unit\Avatars;

use OCA\DAV\Avatars\AvatarNode;
use OCP\IAvatar;
use Test\TestCase;

class AvatarNodeTest extends TestCase {
	public function testGetName() {
		/** @var IAvatar | \PHPUnit\Framework\MockObject\MockObject $a */
		$a = $this->createMock(IAvatar::class);
		$n = new AvatarNode(1024, 'png', $a);
		$this->assertEquals('1024.png', $n->getName());
	}

	public function testGetContentType() {
		/** @var IAvatar | \PHPUnit\Framework\MockObject\MockObject $a */
		$a = $this->createMock(IAvatar::class);
		$n = new AvatarNode(1024, 'png', $a);
		$this->assertEquals('image/png', $n->getContentType());

		$n = new AvatarNode(1024, 'jpeg', $a);
		$this->assertEquals('image/jpeg', $n->getContentType());
	}
}
