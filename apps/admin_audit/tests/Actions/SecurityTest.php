<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018 Roeland Jago Douma <roeland@famdouma.nl>
 *
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

namespace OCA\AdminAudit\Tests\Actions;

use OCA\AdminAudit\Actions\Security;
use OCP\ILogger;
use OCP\IUser;
use Test\TestCase;

class SecurityTest extends TestCase {
	/** @var ILogger|\PHPUnit_Framework_MockObject_MockObject */
	private $logger;

	/** @var Security */
	private $security;

	/** @var IUser|\PHPUnit_Framework_MockObject_MockObject */
	private $user;

	protected function setUp(): void {
		parent::setUp();

		$this->logger = $this->createMock(ILogger::class);
		$this->security = new Security($this->logger);

		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn('myuid');
		$this->user->method('getDisplayName')->willReturn('mydisplayname');
	}

	public function testTwofactorFailed() {
		$this->logger->expects($this->once())
			->method('info')
			->with(
				$this->equalTo('Failed two factor attempt by user mydisplayname (myuid) with provider myprovider'),
				['app' => 'admin_audit']
			);

		$this->security->twofactorFailed($this->user, ['provider' => 'myprovider']);
	}

	public function testTwofactorSuccess() {
		$this->logger->expects($this->once())
			->method('info')
			->with(
				$this->equalTo('Successful two factor attempt by user mydisplayname (myuid) with provider myprovider'),
				['app' => 'admin_audit']
			);

		$this->security->twofactorSuccess($this->user, ['provider' => 'myprovider']);
	}

}
