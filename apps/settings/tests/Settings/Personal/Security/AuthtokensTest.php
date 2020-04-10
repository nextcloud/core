<?php

declare(strict_types=1);

/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCA\Settings\Tests\Settings\Personal\Security;

use OC\Authentication\Token\DefaultToken;
use OC\Authentication\Token\IProvider as IAuthTokenProvider;
use OCA\Settings\Settings\Personal\Security\Authtokens;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IInitialStateService;
use OCP\ISession;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class AuthtokensTest extends TestCase {

	/** @var IAuthTokenProvider|MockObject */
	private $authTokenProvider;

	/** @var ISession|MockObject */
	private $session;

	/** @var IUserSession|MockObject */
	private $userSession;

	/** @var IInitialStateService|MockObject */
	private $initialStateService;

	/** @var string */
	private $uid;

	/** @var Authtokens */
	private $section;

	protected function setUp(): void {
		parent::setUp();

		$this->authTokenProvider = $this->createMock(IAuthTokenProvider::class);
		$this->session = $this->createMock(ISession::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->initialStateService = $this->createMock(IInitialStateService::class);
		$this->uid = 'test123';

		$this->section = new Authtokens(
			$this->authTokenProvider,
			$this->session,
			$this->userSession,
			$this->initialStateService,
			$this->uid
		);
	}

	public function testGetForm() {
		$token1 = new DefaultToken();
		$token1->setId(100);
		$token2 = new DefaultToken();
		$token2->setId(200);
		$tokens = [
			$token1,
			$token2,
		];
		$sessionToken = new DefaultToken();
		$sessionToken->setId(100);

		$this->authTokenProvider->expects($this->once())
			->method('getTokenByUser')
			->with($this->uid)
			->willReturn($tokens);
		$this->session->expects($this->once())
			->method('getId')
			->willReturn('session123');
		$this->authTokenProvider->expects($this->once())
			->method('getToken')
			->with('session123')
			->willReturn($sessionToken);
		$this->initialStateService->expects($this->at(0))
			->method('provideInitialState')
			->with('settings', 'app_tokens', [
				[
					'id' => 100,
					'name' => null,
					'lastActivity' => 0,
					'type' => 0,
					'canDelete' => false,
					'current' => true,
					'scope' => ['filesystem' => true],
					'canRename' => false,
				],
				[
					'id' => 200,
					'name' => null,
					'lastActivity' => 0,
					'type' => 0,
					'canDelete' => true,
					'scope' => ['filesystem' => true],
					'canRename' => true,
				],
			]);

		$this->initialStateService->expects($this->at(1))
			->method('provideInitialState')
			->with('settings', 'can_create_app_token', true);

		$form = $this->section->getForm();

		$expected = new TemplateResponse('settings', 'settings/personal/security/authtokens');
		$this->assertEquals($expected, $form);
	}

}
