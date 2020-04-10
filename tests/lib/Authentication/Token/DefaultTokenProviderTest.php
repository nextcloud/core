<?php
/**
 * @author Christoph Wurst <christoph@owncloud.com>
 *
 * @copyright Copyright (c) 2016, Lukas Reschke <lukas@statuscode.ch>
 * @copyright Copyright (c) 2016, ownCloud, Inc.
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
 *
 */

namespace Test\Authentication\Token;

use OC\Authentication\Exceptions\ExpiredTokenException;
use OC\Authentication\Exceptions\InvalidTokenException;
use OC\Authentication\Token\DefaultToken;
use OC\Authentication\Token\DefaultTokenMapper;
use OC\Authentication\Token\DefaultTokenProvider;
use OC\Authentication\Token\IToken;
use OC\Authentication\Token\PublicKeyToken;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\ILogger;
use OCP\Security\ICrypto;
use Test\TestCase;

class DefaultTokenProviderTest extends TestCase {

	/** @var DefaultTokenProvider|\PHPUnit_Framework_MockObject_MockObject */
	private $tokenProvider;
	/** @var DefaultTokenMapper|\PHPUnit_Framework_MockObject_MockObject */
	private $mapper;
	/** @var ICrypto|\PHPUnit_Framework_MockObject_MockObject */
	private $crypto;
	/** @var IConfig|\PHPUnit_Framework_MockObject_MockObject */
	private $config;
	/** @var ILogger|\PHPUnit_Framework_MockObject_MockObject */
	private $logger;
	/** @var ITimeFactory|\PHPUnit_Framework_MockObject_MockObject */
	private $timeFactory;
	/** @var int */
	private $time;

	protected function setUp(): void {
		parent::setUp();

		$this->mapper = $this->createMock(DefaultTokenMapper::class);
		$this->crypto = $this->createMock(ICrypto::class);
		$this->config = $this->createMock(IConfig::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->time = 1313131;
		$this->timeFactory->expects($this->any())
			->method('getTime')
			->willReturn($this->time);

		$this->tokenProvider = new DefaultTokenProvider($this->mapper, $this->crypto, $this->config, $this->logger,
			$this->timeFactory);
	}

	public function testGenerateToken() {
		$token = 'token';
		$uid = 'user';
		$user = 'User';
		$password = 'passme';
		$name = 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12'
			. 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12'
			. 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12'
			. 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12';
		$type = IToken::PERMANENT_TOKEN;

		$toInsert = new DefaultToken();
		$toInsert->setUid($uid);
		$toInsert->setLoginName($user);
		$toInsert->setPassword('encryptedpassword');
		$toInsert->setName($name);
		$toInsert->setToken(hash('sha512', $token . '1f4h9s'));
		$toInsert->setType($type);
		$toInsert->setRemember(IToken::DO_NOT_REMEMBER);
		$toInsert->setLastActivity($this->time);
		$toInsert->setLastCheck($this->time);
		$toInsert->setVersion(DefaultToken::VERSION);

		$this->config->expects($this->any())
			->method('getSystemValue')
			->with('secret')
			->willReturn('1f4h9s');
		$this->crypto->expects($this->once())
			->method('encrypt')
			->with($password, $token . '1f4h9s')
			->willReturn('encryptedpassword');
		$this->mapper->expects($this->once())
			->method('insert')
			->with($this->equalTo($toInsert));

		$actual = $this->tokenProvider->generateToken($token, $uid, $user, $password, $name, $type, IToken::DO_NOT_REMEMBER);

		$this->assertEquals($toInsert, $actual);
	}

	public function testUpdateToken() {
		$tk = new DefaultToken();
		$tk->setLastActivity($this->time - 200);
		$this->mapper->expects($this->once())
			->method('update')
			->with($tk);

		$this->tokenProvider->updateTokenActivity($tk);

		$this->assertEquals($this->time, $tk->getLastActivity());
	}

	public function testUpdateTokenDebounce() {
		$tk = new DefaultToken();
		$tk->setLastActivity($this->time - 30);
		$this->mapper->expects($this->never())
			->method('update')
			->with($tk);

		$this->tokenProvider->updateTokenActivity($tk);
	}
	
	public function testGetTokenByUser() {
		$this->mapper->expects($this->once())
			->method('getTokenByUser')
			->with('uid')
			->willReturn(['token']);

		$this->assertEquals(['token'], $this->tokenProvider->getTokenByUser('uid'));
	}

	public function testGetPassword() {
		$token = 'token1234';
		$tk = new DefaultToken();
		$tk->setPassword('someencryptedvalue');
		$this->config->expects($this->once())
			->method('getSystemValue')
			->with('secret')
			->willReturn('1f4h9s');
		$this->crypto->expects($this->once())
			->method('decrypt')
			->with('someencryptedvalue', $token . '1f4h9s')
			->willReturn('passme');

		$actual = $this->tokenProvider->getPassword($tk, $token);

		$this->assertEquals('passme', $actual);
	}

	
	public function testGetPasswordPasswordLessToken() {
		$this->expectException(\OC\Authentication\Exceptions\PasswordlessTokenException::class);

		$token = 'token1234';
		$tk = new DefaultToken();
		$tk->setPassword(null);

		$this->tokenProvider->getPassword($tk, $token);
	}

	
	public function testGetPasswordDeletesInvalidToken() {
		$this->expectException(\OC\Authentication\Exceptions\InvalidTokenException::class);

		$token = 'token1234';
		$tk = new DefaultToken();
		$tk->setPassword('someencryptedvalue');
		/* @var $tokenProvider DefaultTokenProvider */
		$tokenProvider = $this->getMockBuilder('\OC\Authentication\Token\DefaultTokenProvider')
			->setMethods([
				'invalidateToken'
			])
			->setConstructorArgs([$this->mapper, $this->crypto, $this->config, $this->logger,
				$this->timeFactory])
			->getMock();
		$this->config->expects($this->once())
			->method('getSystemValue')
			->with('secret')
			->willReturn('1f4h9s');
		$this->crypto->expects($this->once())
			->method('decrypt')
			->with('someencryptedvalue', $token . '1f4h9s')
			->will($this->throwException(new \Exception('some crypto error occurred')));
		$tokenProvider->expects($this->once())
			->method('invalidateToken')
			->with($token);

		$tokenProvider->getPassword($tk, $token);
	}

	public function testSetPassword() {
		$token = new DefaultToken();
		$tokenId = 'token123';
		$password = '123456';

		$this->config->expects($this->once())
			->method('getSystemValue')
			->with('secret')
			->willReturn('ocsecret');
		$this->crypto->expects($this->once())
			->method('encrypt')
			->with($password, $tokenId . 'ocsecret')
			->willReturn('encryptedpassword');
		$this->mapper->expects($this->once())
			->method('update')
			->with($token);

		$this->tokenProvider->setPassword($token, $tokenId, $password);

		$this->assertEquals('encryptedpassword', $token->getPassword());
	}

	
	public function testSetPasswordInvalidToken() {
		$this->expectException(\OC\Authentication\Exceptions\InvalidTokenException::class);

		$token = $this->createMock(IToken::class);
		$tokenId = 'token123';
		$password = '123456';

		$this->tokenProvider->setPassword($token, $tokenId, $password);
	}

	public function testInvalidateToken() {
		$this->mapper->expects($this->once())
			->method('invalidate')
			->with(hash('sha512', 'token7'));

		$this->tokenProvider->invalidateToken('token7');
	}

	public function testInvaildateTokenById() {
		$id = 123;

		$this->mapper->expects($this->once())
			->method('deleteById')
			->with('uid', $id);

		$this->tokenProvider->invalidateTokenById('uid', $id);
	}

	public function testInvalidateOldTokens() {
		$defaultSessionLifetime = 60 * 60 * 24;
		$defaultRememberMeLifetime = 60 * 60 * 24 * 15;
		$this->config->expects($this->exactly(2))
			->method('getSystemValue')
			->willReturnMap([
				['session_lifetime', $defaultSessionLifetime, 150],
				['remember_login_cookie_lifetime', $defaultRememberMeLifetime, 300],
			]);
		$this->mapper->expects($this->at(0))
			->method('invalidateOld')
			->with($this->time - 150);
		$this->mapper->expects($this->at(1))
			->method('invalidateOld')
			->with($this->time - 300);

		$this->tokenProvider->invalidateOldTokens();
	}

	public function testRenewSessionTokenWithoutPassword() {
		$token = $this->getMockBuilder(DefaultToken::class)
			->disableOriginalConstructor()
			->setMethods(['getUID', 'getLoginName', 'getPassword', 'getName', 'getRemember'])
			->getMock();
		$token
			->expects($this->at(0))
			->method('getUID')
			->willReturn('UserUid');
		$token
			->expects($this->at(1))
			->method('getLoginName')
			->willReturn('UserLoginName');
		$token
			->expects($this->at(2))
			->method('getPassword')
			->willReturn(null);
		$token
			->expects($this->at(3))
			->method('getName')
			->willReturn('MyTokenName');
		$token
			->expects($this->at(4))
			->method('getRemember')
			->willReturn(IToken::DO_NOT_REMEMBER);
		$this->config
			->expects($this->exactly(2))
			->method('getSystemValue')
			->with('secret')
			->willReturn('MyInstanceSecret');
		$this->mapper
			->expects($this->at(0))
			->method('getToken')
			->with(hash('sha512', 'oldId' . 'MyInstanceSecret'))
			->willReturn($token);
		$newToken = new DefaultToken();
		$newToken->setUid('UserUid');
		$newToken->setLoginName('UserLoginName');
		$newToken->setName('MyTokenName');
		$newToken->setToken(hash('sha512', 'newId' . 'MyInstanceSecret'));
		$newToken->setType(IToken::TEMPORARY_TOKEN);
		$newToken->setRemember(IToken::DO_NOT_REMEMBER);
		$newToken->setLastActivity(1313131);
		$this->mapper
			->expects($this->at(1))
			->method('insert')
			->with($newToken);
		$this->mapper
			->expects($this->at(2))
			->method('delete')
			->with($token);

		$this->tokenProvider->renewSessionToken('oldId', 'newId');
	}

	public function testRenewSessionTokenWithPassword() {
		$token = $this->getMockBuilder(DefaultToken::class)
			->disableOriginalConstructor()
			->setMethods(['getUID', 'getLoginName', 'getPassword', 'getName', 'getRemember'])
			->getMock();
		$token
			->expects($this->at(0))
			->method('getUID')
			->willReturn('UserUid');
		$token
			->expects($this->at(1))
			->method('getLoginName')
			->willReturn('UserLoginName');
		$token
			->expects($this->at(2))
			->method('getPassword')
			->willReturn('EncryptedPassword');
		$token
			->expects($this->at(3))
			->method('getPassword')
			->willReturn('EncryptedPassword');
		$token
			->expects($this->at(4))
			->method('getName')
			->willReturn('MyTokenName');
		$token
			->expects($this->at(5))
			->method('getRemember')
			->willReturn(IToken::REMEMBER);
		$this->crypto
			->expects($this->any(0))
			->method('decrypt')
			->with('EncryptedPassword', 'oldIdMyInstanceSecret')
			->willReturn('ClearTextPassword');
		$this->crypto
			->expects($this->any(1))
			->method('encrypt')
			->with('ClearTextPassword', 'newIdMyInstanceSecret')
			->willReturn('EncryptedPassword');
		$this->config
			->expects($this->exactly(4))
			->method('getSystemValue')
			->with('secret')
			->willReturn('MyInstanceSecret');
		$this->mapper
			->expects($this->at(0))
			->method('getToken')
			->with(hash('sha512', 'oldId' . 'MyInstanceSecret'))
			->willReturn($token);
		$newToken = new DefaultToken();
		$newToken->setUid('UserUid');
		$newToken->setLoginName('UserLoginName');
		$newToken->setName('MyTokenName');
		$newToken->setToken(hash('sha512', 'newId' . 'MyInstanceSecret'));
		$newToken->setType(IToken::TEMPORARY_TOKEN);
		$newToken->setRemember(IToken::REMEMBER);
		$newToken->setLastActivity(1313131);
		$newToken->setPassword('EncryptedPassword');
		$this->mapper
			->expects($this->at(1))
			->method('insert')
			->with($this->equalTo($newToken));
		$this->mapper
			->expects($this->at(2))
			->method('delete')
			->with($token);

		$this->tokenProvider->renewSessionToken('oldId', 'newId');
	}

	public function testGetToken() {
		$token = new DefaultToken();

		$this->config->method('getSystemValue')
			->with('secret')
			->willReturn('mysecret');

		$this->mapper->method('getToken')
			->with(
				$this->callback(function (string $token) {
					return hash('sha512', 'unhashedTokenmysecret') === $token;
				})
			)->willReturn($token);

		$this->assertSame($token, $this->tokenProvider->getToken('unhashedToken'));
	}

	public function testGetInvalidToken() {
		$this->expectException(InvalidTokenException::class);

		$this->config->method('getSystemValue')
			->with('secret')
			->willReturn('mysecret');

		$this->mapper->method('getToken')
			->with(
				$this->callback(function (string $token) {
					return hash('sha512', 'unhashedTokenmysecret') === $token;
				})
			)->willThrowException(new InvalidTokenException());

		$this->tokenProvider->getToken('unhashedToken');
	}

	public function testGetExpiredToken() {
		$token = new DefaultToken();
		$token->setExpires(42);

		$this->config->method('getSystemValue')
			->with('secret')
			->willReturn('mysecret');

		$this->mapper->method('getToken')
			->with(
				$this->callback(function (string $token) {
					return hash('sha512', 'unhashedTokenmysecret') === $token;
				})
			)->willReturn($token);

		try {
			$this->tokenProvider->getToken('unhashedToken');
		} catch (ExpiredTokenException $e) {
			$this->assertSame($token, $e->getToken());
		}

	}

	public function testGetTokenById() {
		$token = $this->createMock(DefaultToken::class);

		$this->mapper->expects($this->once())
			->method('getTokenById')
			->with($this->equalTo(42))
			->willReturn($token);

		$this->assertSame($token, $this->tokenProvider->getTokenById(42));
	}

	public function testGetInvalidTokenById() {
		$this->expectException(InvalidTokenException::class);

		$this->mapper->expects($this->once())
			->method('getTokenById')
			->with($this->equalTo(42))
			->willThrowException(new DoesNotExistException('nope'));

		$this->tokenProvider->getTokenById(42);
	}

	public function testGetExpiredTokenById() {
		$token = new DefaultToken();
		$token->setExpires(42);

		$this->mapper->expects($this->once())
			->method('getTokenById')
			->with($this->equalTo(42))
			->willReturn($token);

		try {
			$this->tokenProvider->getTokenById(42);
			$this->fail();
		} catch (ExpiredTokenException $e) {
			$this->assertSame($token, $e->getToken());
		}
	}

	public function testRotate() {
		$token = new DefaultToken();
		$token->setPassword('oldencryptedpassword');

		$this->config->method('getSystemValue')
			->with('secret')
			->willReturn('mysecret');

		$this->crypto->method('decrypt')
			->with('oldencryptedpassword', 'oldtokenmysecret')
			->willReturn('mypassword');
		$this->crypto->method('encrypt')
			->with('mypassword', 'newtokenmysecret')
			->willReturn('newencryptedpassword');

		$this->mapper->expects($this->once())
			->method('update')
			->with($this->callback(function (DefaultToken $token) {
				return $token->getPassword() === 'newencryptedpassword' &&
					$token->getToken() === hash('sha512', 'newtokenmysecret');
			}));

		$this->tokenProvider->rotate($token, 'oldtoken', 'newtoken');
	}

	public function testRotateNoPassword() {
		$token = new DefaultToken();

		$this->config->method('getSystemValue')
			->with('secret')
			->willReturn('mysecret');

		$this->mapper->expects($this->once())
			->method('update')
			->with($this->callback(function (DefaultToken $token) {
				return $token->getPassword() === null &&
					$token->getToken() === hash('sha512', 'newtokenmysecret');
			}));

		$this->tokenProvider->rotate($token, 'oldtoken', 'newtoken');
	}

	public function testMarkPasswordInvalidInvalidToken() {
		$token = $this->createMock(PublicKeyToken::class);

		$this->expectException(InvalidTokenException::class);

		$this->tokenProvider->markPasswordInvalid($token, 'tokenId');
	}

	public function testMarkPasswordInvalid() {
		$token = $this->createMock(DefaultToken::class);

		$this->mapper->expects($this->once())
			->method('invalidate')
			->with('0c7db0098fe8ddba6032b22719ec18867c69a1820fa36d71c28bf96d52843bdc44a112bd24093b049be5bb54769bcb72d67190a4a9690e51aac263cba38186fb');

		$this->tokenProvider->markPasswordInvalid($token, 'tokenId');
	}
}
