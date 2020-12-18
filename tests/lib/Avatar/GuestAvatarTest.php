<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018, Michael Weimann <mail@michael-weimann.eu>
 *
 * @author Michael Weimann <mail@michael-weimann.eu>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 */

namespace Test\Avatar;

use OC\Avatar\GuestAvatar;
use OCP\Files\SimpleFS\InMemoryFile;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

/**
 * This class provides test cases for the GuestAvatar class.
 *
 * @package Test\Avatar
 */
class GuestAvatarTest extends TestCase {
	/**
	 * @var GuestAvatar
	 */
	private $guestAvatar;

	/**
	 * Setups a guest avatar instance for tests.
	 *
	 * @before
	 * @return void
	 */
	public function setupGuestAvatar() {
		/* @var MockObject|LoggerInterface $logger */
		$logger = $this->getMockBuilder(LoggerInterface::class)->getMock();
		$this->guestAvatar = new GuestAvatar('einstein', $logger);
	}

	/**
	 * Asserts that testGet() returns the expected avatar.
	 *
	 * For the test a static name "einstein" is used and
	 * the generated image is compared with an expected one.
	 *
	 * @return void
	 */
	public function testGet() {
		$avatar = $this->guestAvatar->getFile(32);
		self::assertInstanceOf(InMemoryFile::class, $avatar);
		$expectedFile = file_get_contents(
			__DIR__ . '/../../data/guest_avatar_einstein_32.png'
		);
		self::assertEquals(trim($expectedFile), trim($avatar->getContent()));
	}

	/**
	 * Asserts that "testIsCustomAvatar" returns false for guests.
	 *
	 * @return void
	 */
	public function testIsCustomAvatar() {
		self::assertFalse($this->guestAvatar->isCustomAvatar());
	}
}
