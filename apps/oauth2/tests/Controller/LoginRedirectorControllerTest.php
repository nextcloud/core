<?php
/**
 * @copyright Copyright (c) 2017 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OCA\OAuth2\Tests\Controller;

use OCA\OAuth2\Controller\LoginRedirectorController;
use OCA\OAuth2\Db\Client;
use OCA\OAuth2\Db\ClientMapper;
use OCA\OAuth2\Exceptions\ClientNotFoundException;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use Test\TestCase;

/**
 * @group DB
 */
class LoginRedirectorControllerTest extends TestCase {
	/** @var IRequest|\PHPUnit_Framework_MockObject_MockObject */
	private $request;
	/** @var IURLGenerator|\PHPUnit_Framework_MockObject_MockObject */
	private $urlGenerator;
	/** @var ClientMapper|\PHPUnit_Framework_MockObject_MockObject */
	private $clientMapper;
	/** @var ISession|\PHPUnit_Framework_MockObject_MockObject */
	private $session;
	/** @var LoginRedirectorController */
	private $loginRedirectorController;
	/** @var IL10N */
	private $l;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->clientMapper = $this->createMock(ClientMapper::class);
		$this->session = $this->createMock(ISession::class);
		$this->l = $this->createMock(IL10N::class);

		$this->loginRedirectorController = new LoginRedirectorController(
			'oauth2',
			$this->request,
			$this->urlGenerator,
			$this->clientMapper,
			$this->session,
			$this->l
		);
	}

	public function testAuthorize() {
		$client = new Client();
		$client->setClientIdentifier('MyClientIdentifier');
		$this->clientMapper
			->expects($this->once())
			->method('getByIdentifier')
			->with('MyClientId')
			->willReturn($client);
		$this->session
			->expects($this->once())
			->method('set')
			->with('oauth.state', 'MyState');
		$this->urlGenerator
			->expects($this->once())
			->method('linkToRouteAbsolute')
			->with(
				'core.ClientFlowLogin.showAuthPickerPage',
				[
					'clientIdentifier' => 'MyClientIdentifier',
				]
			)
			->willReturn('https://example.com/?clientIdentifier=foo');

		$expected = new RedirectResponse('https://example.com/?clientIdentifier=foo');
		$this->assertEquals($expected, $this->loginRedirectorController->authorize('MyClientId', 'MyState', 'code'));
	}

	public function testAuthorizeWrongResponseType() {
		$client = new Client();
		$client->setClientIdentifier('MyClientIdentifier');
		$client->setRedirectUri('http://foo.bar');
		$this->clientMapper
			->expects($this->once())
			->method('getByIdentifier')
			->with('MyClientId')
			->willReturn($client);
		$this->session
			->expects($this->never())
			->method('set');


		$expected = new RedirectResponse('http://foo.bar?error=unsupported_response_type&state=MyState');
		$this->assertEquals($expected, $this->loginRedirectorController->authorize('MyClientId', 'MyState', 'wrongcode'));
	}

	public function testClientNotFound() {
		$clientNotFound = new ClientNotFoundException('could not find client test123', 0);
		$this->clientMapper
			->expects($this->once())
			->method('getByIdentifier')
			->willThrowException($clientNotFound);
		$this->session
			->expects($this->never())
			->method('set');

		$response = $this->loginRedirectorController->authorize('MyClientId', 'MyState', 'wrongcode');
		$this->assertInstanceOf(TemplateResponse::class, $response);

		/** @var TemplateResponse $response */
		$this->assertEquals('404', $response->getTemplateName());
		$this->assertEquals('guest', $response->getRenderAs());
	}
}
