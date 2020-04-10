<?php

declare(strict_types=1);

/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCA\Settings\Tests\Settings\Personal\Security;

use OCA\Settings\Settings\Personal\Security\Password;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class PasswordTest extends TestCase {

	/** @var IUserManager|MockObject */
	private $userManager;

	/** @var string */
	private $uid;

	/** @var Password */
	private $section;

	protected function setUp(): void {
		parent::setUp();

		$this->userManager = $this->createMock(IUserManager::class);
		$this->uid = 'test123';

		$this->section = new Password(
			$this->userManager,
			$this->uid
		);
	}

	public function testGetForm() {
		$user = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())
			->method('get')
			->with($this->uid)
			->willReturn($user);
		$user->expects($this->once())
			->method('canChangePassword')
			->willReturn(true);

		$form = $this->section->getForm();

		$expected = new TemplateResponse('settings', 'settings/personal/security/password', [
			'passwordChangeSupported' => true,
		]);
		$this->assertEquals($expected, $form);
	}

}
