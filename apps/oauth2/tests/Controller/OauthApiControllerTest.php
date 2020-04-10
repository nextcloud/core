<?php
/**
 * @copyright Copyright (c) 2017 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

use OC\Authentication\Exceptions\ExpiredTokenException;
use OC\Authentication\Exceptions\InvalidTokenException;
use OC\Authentication\Token\DefaultToken;
use OC\Authentication\Token\IProvider as TokenProvider;
use OC\Security\Bruteforce\Throttler;
use OCA\OAuth2\Controller\OauthApiController;
use OCA\OAuth2\Db\AccessToken;
use OCA\OAuth2\Db\AccessTokenMapper;
use OCA\OAuth2\Db\Client;
use OCA\OAuth2\Db\ClientMapper;
use OCA\OAuth2\Exceptions\AccessTokenNotFoundException;
use OCA\OAuth2\Exceptions\ClientNotFoundException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IRequest;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;
use Test\TestCase;

class OauthApiControllerTest extends TestCase {
	/** @var IRequest|\PHPUnit_Framework_MockObject_MockObject */
	private $request;
	/** @var ICrypto|\PHPUnit_Framework_MockObject_MockObject */
	private $crypto;
	/** @var AccessTokenMapper|\PHPUnit_Framework_MockObject_MockObject */
	private $accessTokenMapper;
	/** @var ClientMapper|\PHPUnit_Framework_MockObject_MockObject */
	private $clientMapper;
	/** @var TokenProvider|\PHPUnit_Framework_MockObject_MockObject */
	private $tokenProvider;
	/** @var ISecureRandom|\PHPUnit_Framework_MockObject_MockObject */
	private $secureRandom;
	/** @var ITimeFactory|\PHPUnit_Framework_MockObject_MockObject */
	private $time;
	/** @var Throttler|\PHPUnit_Framework_MockObject_MockObject */
	private $throttler;
	/** @var OauthApiController */
	private $oauthApiController;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->crypto = $this->createMock(ICrypto::class);
		$this->accessTokenMapper = $this->createMock(AccessTokenMapper::class);
		$this->clientMapper = $this->createMock(ClientMapper::class);
		$this->tokenProvider = $this->createMock(TokenProvider::class);
		$this->secureRandom = $this->createMock(ISecureRandom::class);
		$this->time = $this->createMock(ITimeFactory::class);
		$this->throttler = $this->createMock(Throttler::class);

		$this->oauthApiController = new OauthApiController(
			'oauth2',
			$this->request,
			$this->crypto,
			$this->accessTokenMapper,
			$this->clientMapper,
			$this->tokenProvider,
			$this->secureRandom,
			$this->time,
			$this->throttler
		);
	}

	public function testGetTokenInvalidGrantType() {
		$expected = new JSONResponse([
			'error' => 'invalid_grant',
		], Http::STATUS_BAD_REQUEST);

		$this->assertEquals($expected, $this->oauthApiController->getToken('foo', null, null, null, null));
	}

	public function testGetTokenInvalidCode() {
		$expected = new JSONResponse([
			'error' => 'invalid_request',
		], Http::STATUS_BAD_REQUEST);

		$this->accessTokenMapper->method('getByCode')
			->with('invalidcode')
			->willThrowException(new AccessTokenNotFoundException());

		$this->assertEquals($expected, $this->oauthApiController->getToken('authorization_code', 'invalidcode', null, null, null));
	}

	public function testGetTokenInvalidRefreshToken() {
		$expected = new JSONResponse([
			'error' => 'invalid_request',
		], Http::STATUS_BAD_REQUEST);

		$this->accessTokenMapper->method('getByCode')
			->with('invalidrefresh')
			->willThrowException(new AccessTokenNotFoundException());

		$this->assertEquals($expected, $this->oauthApiController->getToken('refresh_token', null, 'invalidrefresh', null, null));
	}

	public function testGetTokenClientDoesNotExist() {
		$expected = new JSONResponse([
			'error' => 'invalid_request',
		], Http::STATUS_BAD_REQUEST);

		$accessToken = new AccessToken();
		$accessToken->setClientId(42);

		$this->accessTokenMapper->method('getByCode')
			->with('validrefresh')
			->willReturn($accessToken);

		$this->clientMapper->method('getByUid')
			->with(42)
			->willThrowException(new ClientNotFoundException());

		$this->assertEquals($expected, $this->oauthApiController->getToken('refresh_token', null, 'validrefresh', null, null));
	}

	public function invalidClientProvider() {
		return [
			['invalidClientId', 'invalidClientSecret'],
			['clientId', 'invalidClientSecret'],
			['invalidClientId', 'clientSecret'],
		];
	}

	/**
	 * @dataProvider invalidClientProvider
	 *
	 * @param string $clientId
	 * @param string $clientSecret
	 */
	public function testGetTokenInvalidClient($clientId, $clientSecret) {
		$expected = new JSONResponse([
			'error' => 'invalid_client',
		], Http::STATUS_BAD_REQUEST);

		$accessToken = new AccessToken();
		$accessToken->setClientId(42);

		$this->accessTokenMapper->method('getByCode')
			->with('validrefresh')
			->willReturn($accessToken);

		$client = new Client();
		$client->setClientIdentifier('clientId');
		$client->setSecret('clientSecret');
		$this->clientMapper->method('getByUid')
			->with(42)
			->willReturn($client);

		$this->assertEquals($expected, $this->oauthApiController->getToken('refresh_token', null, 'validrefresh', $clientId, $clientSecret));
	}

	public function testGetTokenInvalidAppToken() {
		$expected = new JSONResponse([
			'error' => 'invalid_request',
		], Http::STATUS_BAD_REQUEST);

		$accessToken = new AccessToken();
		$accessToken->setClientId(42);
		$accessToken->setTokenId(1337);
		$accessToken->setEncryptedToken('encryptedToken');

		$this->accessTokenMapper->method('getByCode')
			->with('validrefresh')
			->willReturn($accessToken);

		$client = new Client();
		$client->setClientIdentifier('clientId');
		$client->setSecret('clientSecret');
		$this->clientMapper->method('getByUid')
			->with(42)
			->willReturn($client);

		$this->crypto->method('decrypt')
			->with(
				'encryptedToken',
				'validrefresh'
			)->willReturn('decryptedToken');

		$this->tokenProvider->method('getTokenById')
			->with(1337)
			->willThrowException(new InvalidTokenException());

		$this->accessTokenMapper->expects($this->once())
			->method('delete')
			->with($accessToken);

		$this->assertEquals($expected, $this->oauthApiController->getToken('refresh_token', null, 'validrefresh', 'clientId', 'clientSecret'));
	}

	public function testGetTokenValidAppToken() {
		$accessToken = new AccessToken();
		$accessToken->setClientId(42);
		$accessToken->setTokenId(1337);
		$accessToken->setEncryptedToken('encryptedToken');

		$this->accessTokenMapper->method('getByCode')
			->with('validrefresh')
			->willReturn($accessToken);

		$client = new Client();
		$client->setClientIdentifier('clientId');
		$client->setSecret('clientSecret');
		$this->clientMapper->method('getByUid')
			->with(42)
			->willReturn($client);

		$this->crypto->method('decrypt')
			->with(
				'encryptedToken',
				'validrefresh'
			)->willReturn('decryptedToken');

		$appToken = new DefaultToken();
		$appToken->setUid('userId');
		$this->tokenProvider->method('getTokenById')
			->with(1337)
			->willThrowException(new ExpiredTokenException($appToken));

		$this->accessTokenMapper->expects($this->never())
			->method('delete')
			->with($accessToken);

		$this->secureRandom->method('generate')
			->willReturnCallback(function ($len) {
				return 'random'.$len;
			});

		$this->tokenProvider->expects($this->once())
			->method('rotate')
			->with(
				$appToken,
				'decryptedToken',
				'random72'
			)->willReturn($appToken);

		$this->time->method('getTime')
			->willReturn(1000);

		$this->tokenProvider->expects($this->once())
			->method('updateToken')
			->with(
				$this->callback(function (DefaultToken $token) {
					return $token->getExpires() === 4600;
				})
			);

		$this->crypto->method('encrypt')
			->with('random72', 'random128')
			->willReturn('newEncryptedToken');

		$this->accessTokenMapper->expects($this->once())
			->method('update')
			->with(
				$this->callback(function (AccessToken $token) {
					return $token->getHashedCode() === hash('sha512', 'random128') &&
						$token->getEncryptedToken() === 'newEncryptedToken';
				})
			);

		$expected = new JSONResponse([
			'access_token' => 'random72',
			'token_type' => 'Bearer',
			'expires_in' => 3600,
			'refresh_token' => 'random128',
			'user_id' => 'userId',
		]);

		$this->request->method('getRemoteAddress')
			->willReturn('1.2.3.4');

		$this->throttler->expects($this->once())
			->method('resetDelay')
			->with(
				'1.2.3.4',
				'login',
				['user' => 'userId']
			);

		$this->assertEquals($expected, $this->oauthApiController->getToken('refresh_token', null, 'validrefresh', 'clientId', 'clientSecret'));
	}

	public function testGetTokenValidAppTokenBasicAuth() {
		$accessToken = new AccessToken();
		$accessToken->setClientId(42);
		$accessToken->setTokenId(1337);
		$accessToken->setEncryptedToken('encryptedToken');

		$this->accessTokenMapper->method('getByCode')
			->with('validrefresh')
			->willReturn($accessToken);

		$client = new Client();
		$client->setClientIdentifier('clientId');
		$client->setSecret('clientSecret');
		$this->clientMapper->method('getByUid')
			->with(42)
			->willReturn($client);

		$this->crypto->method('decrypt')
			->with(
				'encryptedToken',
				'validrefresh'
			)->willReturn('decryptedToken');

		$appToken = new DefaultToken();
		$appToken->setUid('userId');
		$this->tokenProvider->method('getTokenById')
			->with(1337)
			->willThrowException(new ExpiredTokenException($appToken));

		$this->accessTokenMapper->expects($this->never())
			->method('delete')
			->with($accessToken);

		$this->secureRandom->method('generate')
			->willReturnCallback(function ($len) {
				return 'random'.$len;
			});

		$this->tokenProvider->expects($this->once())
			->method('rotate')
			->with(
				$appToken,
				'decryptedToken',
				'random72'
			)->willReturn($appToken);

		$this->time->method('getTime')
			->willReturn(1000);

		$this->tokenProvider->expects($this->once())
			->method('updateToken')
			->with(
				$this->callback(function (DefaultToken $token) {
					return $token->getExpires() === 4600;
				})
			);

		$this->crypto->method('encrypt')
			->with('random72', 'random128')
			->willReturn('newEncryptedToken');

		$this->accessTokenMapper->expects($this->once())
			->method('update')
			->with(
				$this->callback(function (AccessToken $token) {
					return $token->getHashedCode() === hash('sha512', 'random128') &&
						$token->getEncryptedToken() === 'newEncryptedToken';
				})
			);

		$expected = new JSONResponse([
			'access_token' => 'random72',
			'token_type' => 'Bearer',
			'expires_in' => 3600,
			'refresh_token' => 'random128',
			'user_id' => 'userId',
		]);

		$this->request->server['PHP_AUTH_USER'] = 'clientId';
		$this->request->server['PHP_AUTH_PW'] = 'clientSecret';

		$this->request->method('getRemoteAddress')
			->willReturn('1.2.3.4');

		$this->throttler->expects($this->once())
			->method('resetDelay')
			->with(
				'1.2.3.4',
				'login',
				['user' => 'userId']
			);

		$this->assertEquals($expected, $this->oauthApiController->getToken('refresh_token', null, 'validrefresh', null, null));
	}

	public function testGetTokenExpiredAppToken() {
		$accessToken = new AccessToken();
		$accessToken->setClientId(42);
		$accessToken->setTokenId(1337);
		$accessToken->setEncryptedToken('encryptedToken');

		$this->accessTokenMapper->method('getByCode')
			->with('validrefresh')
			->willReturn($accessToken);

		$client = new Client();
		$client->setClientIdentifier('clientId');
		$client->setSecret('clientSecret');
		$this->clientMapper->method('getByUid')
			->with(42)
			->willReturn($client);

		$this->crypto->method('decrypt')
			->with(
				'encryptedToken',
				'validrefresh'
			)->willReturn('decryptedToken');

		$appToken = new DefaultToken();
		$appToken->setUid('userId');
		$this->tokenProvider->method('getTokenById')
			->with(1337)
			->willReturn($appToken);

		$this->accessTokenMapper->expects($this->never())
			->method('delete')
			->with($accessToken);

		$this->secureRandom->method('generate')
			->willReturnCallback(function ($len) {
				return 'random'.$len;
			});

		$this->tokenProvider->expects($this->once())
			->method('rotate')
			->with(
				$appToken,
				'decryptedToken',
				'random72'
			)->willReturn($appToken);

		$this->time->method('getTime')
			->willReturn(1000);

		$this->tokenProvider->expects($this->once())
			->method('updateToken')
			->with(
				$this->callback(function (DefaultToken $token) {
					return $token->getExpires() === 4600;
				})
			);

		$this->crypto->method('encrypt')
			->with('random72', 'random128')
			->willReturn('newEncryptedToken');

		$this->accessTokenMapper->expects($this->once())
			->method('update')
			->with(
				$this->callback(function (AccessToken $token) {
					return $token->getHashedCode() === hash('sha512', 'random128') &&
						$token->getEncryptedToken() === 'newEncryptedToken';
				})
			);

		$expected = new JSONResponse([
			'access_token' => 'random72',
			'token_type' => 'Bearer',
			'expires_in' => 3600,
			'refresh_token' => 'random128',
			'user_id' => 'userId',
		]);

		$this->request->method('getRemoteAddress')
			->willReturn('1.2.3.4');

		$this->throttler->expects($this->once())
			->method('resetDelay')
			->with(
				'1.2.3.4',
				'login',
				['user' => 'userId']
			);

		$this->assertEquals($expected, $this->oauthApiController->getToken('refresh_token', null, 'validrefresh', 'clientId', 'clientSecret'));
	}
}
