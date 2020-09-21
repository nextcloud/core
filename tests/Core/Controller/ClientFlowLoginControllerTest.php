<?php
/**
 * @copyright Copyright (c) 2017 Lukas Reschke <lukas@statuscode.ch>
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

namespace Tests\Core\Controller;

use OC\Authentication\Exceptions\InvalidTokenException;
use OC\Authentication\Exceptions\PasswordlessTokenException;
use OC\Authentication\Token\IProvider;
use OC\Authentication\Token\IToken;
use OC\Core\Controller\ClientFlowLoginController;
use OCA\OAuth2\Db\AccessTokenMapper;
use OCA\OAuth2\Db\Client;
use OCA\OAuth2\Db\ClientMapper;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\StandaloneTemplateResponse;
use OCP\Defaults;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;
use OCP\Session\Exceptions\SessionNotAvailableException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Test\TestCase;

class ClientFlowLoginControllerTest extends TestCase {
	/** @var IRequest|\PHPUnit\Framework\MockObject\MockObject */
	private $request;
	/** @var IUserSession|\PHPUnit\Framework\MockObject\MockObject */
	private $userSession;
	/** @var IL10N|\PHPUnit\Framework\MockObject\MockObject */
	private $l10n;
	/** @var Defaults|\PHPUnit\Framework\MockObject\MockObject */
	private $defaults;
	/** @var ISession|\PHPUnit\Framework\MockObject\MockObject */
	private $session;
	/** @var IProvider|\PHPUnit\Framework\MockObject\MockObject */
	private $tokenProvider;
	/** @var ISecureRandom|\PHPUnit\Framework\MockObject\MockObject */
	private $random;
	/** @var IURLGenerator|\PHPUnit\Framework\MockObject\MockObject */
	private $urlGenerator;
	/** @var ClientMapper|\PHPUnit\Framework\MockObject\MockObject */
	private $clientMapper;
	/** @var AccessTokenMapper|\PHPUnit\Framework\MockObject\MockObject */
	private $accessTokenMapper;
	/** @var ICrypto|\PHPUnit\Framework\MockObject\MockObject */
	private $crypto;
	/** @var EventDispatcherInterface|\PHPUnit\Framework\MockObject\MockObject */
	private $eventDispatcher;


	/** @var ClientFlowLoginController */
	private $clientFlowLoginController;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->expects($this->any())
			->method('t')
			->willReturnCallback(function ($text, $parameters = []) {
				return vsprintf($text, $parameters);
			});
		$this->defaults = $this->createMock(Defaults::class);
		$this->session = $this->createMock(ISession::class);
		$this->tokenProvider = $this->createMock(IProvider::class);
		$this->random = $this->createMock(ISecureRandom::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->clientMapper = $this->createMock(ClientMapper::class);
		$this->accessTokenMapper = $this->createMock(AccessTokenMapper::class);
		$this->crypto = $this->createMock(ICrypto::class);
		$this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

		$this->clientFlowLoginController = new ClientFlowLoginController(
			'core',
			$this->request,
			$this->userSession,
			$this->l10n,
			$this->defaults,
			$this->session,
			$this->tokenProvider,
			$this->random,
			$this->urlGenerator,
			$this->clientMapper,
			$this->accessTokenMapper,
			$this->crypto,
			$this->eventDispatcher
		);
	}

	public function testGenerateAppPasswordWithInvalidToken() {
		$this->session
			->expects($this->once())
			->method('get')
			->with('client.flow.state.token')
			->willReturn('OtherToken');
		$this->session
			->expects($this->once())
			->method('remove')
			->with('client.flow.state.token');

		$expected = new StandaloneTemplateResponse(
			'core',
			'403',
			[
				'message' => 'State token does not match',
			],
			'guest'
		);
		$expected->setStatus(Http::STATUS_FORBIDDEN);
		$this->assertEquals($expected, $this->clientFlowLoginController->generateAppPassword('MyStateToken'));
	}

	public function testGenerateAppPasswordWithSessionNotAvailableException() {
		$this->session
			->expects($this->once())
			->method('get')
			->with('client.flow.state.token')
			->willReturn('MyStateToken');
		$this->session
			->expects($this->once())
			->method('remove')
			->with('client.flow.state.token');
		$this->session
			->expects($this->once())
			->method('getId')
			->willThrowException(new SessionNotAvailableException());

		$expected = new Http\Response();
		$expected->setStatus(Http::STATUS_FORBIDDEN);
		$this->assertEquals($expected, $this->clientFlowLoginController->generateAppPassword('MyStateToken'));
	}

	public function testGenerateAppPasswordWithInvalidTokenException() {
		$this->session
			->expects($this->once())
			->method('get')
			->with('client.flow.state.token')
			->willReturn('MyStateToken');
		$this->session
			->expects($this->once())
			->method('remove')
			->with('client.flow.state.token');
		$this->session
			->expects($this->once())
			->method('getId')
			->willReturn('SessionId');
		$this->tokenProvider
			->expects($this->once())
			->method('getToken')
			->with('SessionId')
			->willThrowException(new InvalidTokenException());

		$expected = new Http\Response();
		$expected->setStatus(Http::STATUS_FORBIDDEN);
		$this->assertEquals($expected, $this->clientFlowLoginController->generateAppPassword('MyStateToken'));
	}

	public function testGeneratePasswordWithPassword() {
		$this->session
			->expects($this->once())
			->method('get')
			->with('client.flow.state.token')
			->willReturn('MyStateToken');
		$this->session
			->expects($this->once())
			->method('remove')
			->with('client.flow.state.token');
		$this->session
			->expects($this->once())
			->method('getId')
			->willReturn('SessionId');
		$myToken = $this->createMock(IToken::class);
		$myToken
			->expects($this->once())
			->method('getLoginName')
			->willReturn('MyLoginName');
		$this->tokenProvider
			->expects($this->once())
			->method('getToken')
			->with('SessionId')
			->willReturn($myToken);
		$this->tokenProvider
			->expects($this->once())
			->method('getPassword')
			->with($myToken, 'SessionId')
			->willReturn('MyPassword');
		$this->random
			->expects($this->once())
			->method('generate')
			->with(72)
			->willReturn('MyGeneratedToken');
		$user = $this->createMock(IUser::class);
		$user
			->expects($this->once())
			->method('getUID')
			->willReturn('MyUid');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($user);
		$this->tokenProvider
			->expects($this->once())
			->method('generateToken')
			->with(
				'MyGeneratedToken',
				'MyUid',
				'MyLoginName',
				'MyPassword',
				'unknown',
				IToken::PERMANENT_TOKEN,
				IToken::DO_NOT_REMEMBER
			);
		$this->request
			->expects($this->once())
			->method('getServerProtocol')
			->willReturn('http');
		$this->request
			->expects($this->once())
			->method('getServerHost')
			->willReturn('example.com');
		$this->request
			->expects($this->any())
			->method('getHeader')
			->willReturn('');

		$this->eventDispatcher->expects($this->once())
			->method('dispatch');

		$expected = new Http\RedirectResponse('nc://login/server:http://example.com&user:MyLoginName&password:MyGeneratedToken');
		$this->assertEquals($expected, $this->clientFlowLoginController->generateAppPassword('MyStateToken'));
	}

	/**
	 * @param string $redirectUri
	 * @param string $redirectUrl
	 *
	 * @testWith
	 * ["https://example.com/redirect.php", "https://example.com/redirect.php?state=MyOauthState&code=MyAccessCode"]
	 * ["https://example.com/redirect.php?hello=world", "https://example.com/redirect.php?hello=world&state=MyOauthState&code=MyAccessCode"]
	 *
	 */
	public function testGeneratePasswordWithPasswordForOauthClient($redirectUri, $redirectUrl) {
		$this->session
			->expects($this->at(0))
			->method('get')
			->with('client.flow.state.token')
			->willReturn('MyStateToken');
		$this->session
			->expects($this->at(1))
			->method('remove')
			->with('client.flow.state.token');
		$this->session
			->expects($this->at(3))
			->method('get')
			->with('oauth.state')
			->willReturn('MyOauthState');
		$this->session
			->expects($this->at(4))
			->method('remove')
			->with('oauth.state');
		$this->session
			->expects($this->once())
			->method('getId')
			->willReturn('SessionId');
		$myToken = $this->createMock(IToken::class);
		$myToken
			->expects($this->once())
			->method('getLoginName')
			->willReturn('MyLoginName');
		$this->tokenProvider
			->expects($this->once())
			->method('getToken')
			->with('SessionId')
			->willReturn($myToken);
		$this->tokenProvider
			->expects($this->once())
			->method('getPassword')
			->with($myToken, 'SessionId')
			->willReturn('MyPassword');
		$this->random
			->expects($this->at(0))
			->method('generate')
			->with(72)
			->willReturn('MyGeneratedToken');
		$this->random
			->expects($this->at(1))
			->method('generate')
			->with(128)
			->willReturn('MyAccessCode');
		$user = $this->createMock(IUser::class);
		$user
			->expects($this->once())
			->method('getUID')
			->willReturn('MyUid');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($user);
		$token = $this->createMock(IToken::class);
		$this->tokenProvider
			->expects($this->once())
			->method('generateToken')
			->with(
				'MyGeneratedToken',
				'MyUid',
				'MyLoginName',
				'MyPassword',
				'My OAuth client',
				IToken::PERMANENT_TOKEN,
				IToken::DO_NOT_REMEMBER
			)
			->willReturn($token);
		$client = new Client();
		$client->setName('My OAuth client');
		$client->setRedirectUri($redirectUri);
		$this->clientMapper
			->expects($this->once())
			->method('getByIdentifier')
			->with('MyClientIdentifier')
			->willReturn($client);

		$this->eventDispatcher->expects($this->once())
			->method('dispatch');

		$expected = new Http\RedirectResponse($redirectUrl);
		$this->assertEquals($expected, $this->clientFlowLoginController->generateAppPassword('MyStateToken', 'MyClientIdentifier'));
	}

	public function testGeneratePasswordWithoutPassword() {
		$this->session
			->expects($this->once())
			->method('get')
			->with('client.flow.state.token')
			->willReturn('MyStateToken');
		$this->session
			->expects($this->once())
			->method('remove')
			->with('client.flow.state.token');
		$this->session
			->expects($this->once())
			->method('getId')
			->willReturn('SessionId');
		$myToken = $this->createMock(IToken::class);
		$myToken
			->expects($this->once())
			->method('getLoginName')
			->willReturn('MyLoginName');
		$this->tokenProvider
			->expects($this->once())
			->method('getToken')
			->with('SessionId')
			->willReturn($myToken);
		$this->tokenProvider
			->expects($this->once())
			->method('getPassword')
			->with($myToken, 'SessionId')
			->willThrowException(new PasswordlessTokenException());
		$this->random
			->expects($this->once())
			->method('generate')
			->with(72)
			->willReturn('MyGeneratedToken');
		$user = $this->createMock(IUser::class);
		$user
			->expects($this->once())
			->method('getUID')
			->willReturn('MyUid');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($user);
		$this->tokenProvider
			->expects($this->once())
			->method('generateToken')
			->with(
				'MyGeneratedToken',
				'MyUid',
				'MyLoginName',
				null,
				'unknown',
				IToken::PERMANENT_TOKEN,
				IToken::DO_NOT_REMEMBER
			);
		$this->request
			->expects($this->once())
			->method('getServerProtocol')
			->willReturn('http');
		$this->request
			->expects($this->once())
			->method('getServerHost')
			->willReturn('example.com');
		$this->request
			->expects($this->any())
			->method('getHeader')
			->willReturn('');

		$this->eventDispatcher->expects($this->once())
			->method('dispatch');

		$expected = new Http\RedirectResponse('nc://login/server:http://example.com&user:MyLoginName&password:MyGeneratedToken');
		$this->assertEquals($expected, $this->clientFlowLoginController->generateAppPassword('MyStateToken'));
	}

	public function dataGeneratePasswordWithHttpsProxy() {
		return [
			[
				[
					['X-Forwarded-Proto', 'http'],
					['X-Forwarded-Ssl', 'off'],
					['USER_AGENT', ''],
				],
				'http',
				'http',
			],
			[
				[
					['X-Forwarded-Proto', 'http'],
					['X-Forwarded-Ssl', 'off'],
					['USER_AGENT', ''],
				],
				'https',
				'https',
			],
			[
				[
					['X-Forwarded-Proto', 'https'],
					['X-Forwarded-Ssl', 'off'],
					['USER_AGENT', ''],
				],
				'http',
				'https',
			],
			[
				[
					['X-Forwarded-Proto', 'https'],
					['X-Forwarded-Ssl', 'on'],
					['USER_AGENT', ''],
				],
				'http',
				'https',
			],
			[
				[
					['X-Forwarded-Proto', 'http'],
					['X-Forwarded-Ssl', 'on'],
					['USER_AGENT', ''],
				],
				'http',
				'https',
			],
		];
	}

	/**
	 * @dataProvider dataGeneratePasswordWithHttpsProxy
	 * @param array $headers
	 * @param string $protocol
	 * @param string $expected
	 */
	public function testGeneratePasswordWithHttpsProxy(array $headers, $protocol, $expected) {
		$this->session
			->expects($this->once())
			->method('get')
			->with('client.flow.state.token')
			->willReturn('MyStateToken');
		$this->session
			->expects($this->once())
			->method('remove')
			->with('client.flow.state.token');
		$this->session
			->expects($this->once())
			->method('getId')
			->willReturn('SessionId');
		$myToken = $this->createMock(IToken::class);
		$myToken
			->expects($this->once())
			->method('getLoginName')
			->willReturn('MyLoginName');
		$this->tokenProvider
			->expects($this->once())
			->method('getToken')
			->with('SessionId')
			->willReturn($myToken);
		$this->tokenProvider
			->expects($this->once())
			->method('getPassword')
			->with($myToken, 'SessionId')
			->willReturn('MyPassword');
		$this->random
			->expects($this->once())
			->method('generate')
			->with(72)
			->willReturn('MyGeneratedToken');
		$user = $this->createMock(IUser::class);
		$user
			->expects($this->once())
			->method('getUID')
			->willReturn('MyUid');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($user);
		$this->tokenProvider
			->expects($this->once())
			->method('generateToken')
			->with(
				'MyGeneratedToken',
				'MyUid',
				'MyLoginName',
				'MyPassword',
				'unknown',
				IToken::PERMANENT_TOKEN,
				IToken::DO_NOT_REMEMBER
			);
		$this->request
			->expects($this->once())
			->method('getServerProtocol')
			->willReturn($protocol);
		$this->request
			->expects($this->once())
			->method('getServerHost')
			->willReturn('example.com');
		$this->request
			->expects($this->atLeastOnce())
			->method('getHeader')
			->willReturnMap($headers);

		$this->eventDispatcher->expects($this->once())
			->method('dispatch');

		$expected = new Http\RedirectResponse('nc://login/server:' . $expected . '://example.com&user:MyLoginName&password:MyGeneratedToken');
		$this->assertEquals($expected, $this->clientFlowLoginController->generateAppPassword('MyStateToken'));
	}
}
