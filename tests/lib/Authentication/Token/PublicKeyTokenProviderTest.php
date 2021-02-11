<?php
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\Authentication\Token;

use OC\Authentication\Exceptions\ExpiredTokenException;
use OC\Authentication\Exceptions\InvalidTokenException;
use OC\Authentication\Token\DefaultToken;
use OC\Authentication\Token\IToken;
use OC\Authentication\Token\PublicKeyToken;
use OC\Authentication\Token\PublicKeyTokenMapper;
use OC\Authentication\Token\PublicKeyTokenProvider;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class PublicKeyTokenProviderTest extends TestCase {

	/** @var PublicKeyTokenProvider|\PHPUnit\Framework\MockObject\MockObject */
	private $tokenProvider;
	/** @var PublicKeyTokenMapper|\PHPUnit\Framework\MockObject\MockObject */
	private $mapper;
	/** @var ICrypto */
	private $crypto;
	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	private $config;
	/** @var LoggerInterface|\PHPUnit\Framework\MockObject\MockObject */
	private $logger;
	/** @var ITimeFactory|\PHPUnit\Framework\MockObject\MockObject */
	private $timeFactory;
	/** @var int */
	private $time;

	protected function setUp(): void {
		parent::setUp();

		$this->mapper = $this->createMock(PublicKeyTokenMapper::class);
		$this->crypto = \OC::$server->getCrypto();
		$this->config = $this->createMock(IConfig::class);
		$this->config->method('getSystemValue')
			->willReturnMap([
				['session_lifetime', 60 * 60 * 24, 150],
				['remember_login_cookie_lifetime', 60 * 60 * 24 * 15, 300],
				['secret', '', '1f4h9s'],
				['openssl', [], []],
			]);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->time = 1313131;
		$this->timeFactory->method('getTime')
			->willReturn($this->time);

		$this->tokenProvider = new PublicKeyTokenProvider($this->mapper, $this->crypto, $this->config, $this->logger,
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

		$actual = $this->tokenProvider->generateToken($token, $uid, $user, $password, $name, $type, IToken::DO_NOT_REMEMBER);

		$this->assertInstanceOf(PublicKeyToken::class, $actual);
		$this->assertSame($uid, $actual->getUID());
		$this->assertSame($user, $actual->getLoginName());
		$this->assertSame($name, $actual->getName());
		$this->assertSame(IToken::DO_NOT_REMEMBER, $actual->getRemember());
		$this->assertSame($password, $this->tokenProvider->getPassword($actual, $token));
	}

	public function testUpdateToken() {
		$tk = new PublicKeyToken();
		$tk->setLastActivity($this->time - 200);
		$this->mapper->expects($this->once())
			->method('update')
			->with($tk);

		$this->tokenProvider->updateTokenActivity($tk);

		$this->assertEquals($this->time, $tk->getLastActivity());
	}

	public function testUpdateTokenDebounce() {
		$tk = new PublicKeyToken();

		$this->config->method('getSystemValueInt')
			->willReturnCallback(function ($value, $default) {
				return $default;
			});

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
		$token = 'token';
		$uid = 'user';
		$user = 'User';
		$password = 'passme';
		$name = 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12'
			. 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12'
			. 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12'
			. 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12';
		$type = IToken::PERMANENT_TOKEN;

		$actual = $this->tokenProvider->generateToken($token, $uid, $user, $password, $name, $type, IToken::DO_NOT_REMEMBER);

		$this->assertSame($password, $this->tokenProvider->getPassword($actual, $token));
	}


	public function testGetPasswordPasswordLessToken() {
		$this->expectException(\OC\Authentication\Exceptions\PasswordlessTokenException::class);

		$token = 'token1234';
		$tk = new PublicKeyToken();
		$tk->setPassword(null);

		$this->tokenProvider->getPassword($tk, $token);
	}


	public function testGetPasswordInvalidToken() {
		$this->expectException(\OC\Authentication\Exceptions\InvalidTokenException::class);

		$token = 'token';
		$uid = 'user';
		$user = 'User';
		$password = 'passme';
		$name = 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12'
			. 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12'
			. 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12'
			. 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12';
		$type = IToken::PERMANENT_TOKEN;

		$actual = $this->tokenProvider->generateToken($token, $uid, $user, $password, $name, $type, IToken::DO_NOT_REMEMBER);

		$this->tokenProvider->getPassword($actual, 'wrongtoken');
	}

	public function testSetPassword() {
		$token = 'token';
		$uid = 'user';
		$user = 'User';
		$password = 'passme';
		$name = 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12'
			. 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12'
			. 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12'
			. 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12';
		$type = IToken::PERMANENT_TOKEN;

		$actual = $this->tokenProvider->generateToken($token, $uid, $user, $password, $name, $type, IToken::DO_NOT_REMEMBER);

		$this->mapper->method('getTokenByUser')
			->with('user')
			->willReturn([$actual]);

		$newpass = 'newpass';
		$this->mapper->expects($this->once())
			->method('update')
			->with($this->callback(function ($token) use ($newpass) {
				return $newpass === $this->tokenProvider->getPassword($token, 'token');
			}));


		$this->tokenProvider->setPassword($actual, $token, $newpass);

		$this->assertSame($newpass, $this->tokenProvider->getPassword($actual, 'token'));
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
			->with(hash('sha512', 'token7'.'1f4h9s'));

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
		$token = 'oldId';
		$uid = 'user';
		$user = 'User';
		$password = null;
		$name = 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12'
			. 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12'
			. 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12'
			. 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12';
		$type = IToken::PERMANENT_TOKEN;

		$oldToken = $this->tokenProvider->generateToken($token, $uid, $user, $password, $name, $type, IToken::DO_NOT_REMEMBER);

		$this->mapper
			->expects($this->at(0))
			->method('getToken')
			->with(hash('sha512', 'oldId' . '1f4h9s'))
			->willReturn($oldToken);
		$this->mapper
			->expects($this->at(1))
			->method('insert')
			->with($this->callback(function (PublicKeyToken $token) use ($user, $uid, $name) {
				return $token->getUID() === $uid &&
					$token->getLoginName() === $user &&
					$token->getName() === $name &&
					$token->getType() === IToken::DO_NOT_REMEMBER &&
					$token->getLastActivity() === $this->time &&
					$token->getPassword() === null;
			}));
		$this->mapper
			->expects($this->at(2))
			->method('delete')
			->with($this->callback(function ($token) use ($oldToken) {
				return $token === $oldToken;
			}));

		$this->tokenProvider->renewSessionToken('oldId', 'newId');
	}

	public function testRenewSessionTokenWithPassword() {
		$token = 'oldId';
		$uid = 'user';
		$user = 'User';
		$password = 'password';
		$name = 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12'
			. 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12'
			. 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12'
			. 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12';
		$type = IToken::PERMANENT_TOKEN;

		$oldToken = $this->tokenProvider->generateToken($token, $uid, $user, $password, $name, $type, IToken::DO_NOT_REMEMBER);

		$this->mapper
			->expects($this->at(0))
			->method('getToken')
			->with(hash('sha512', 'oldId' . '1f4h9s'))
			->willReturn($oldToken);
		$this->mapper
			->expects($this->at(1))
			->method('insert')
			->with($this->callback(function (PublicKeyToken $token) use ($user, $uid, $name) {
				return $token->getUID() === $uid &&
					$token->getLoginName() === $user &&
					$token->getName() === $name &&
					$token->getType() === IToken::DO_NOT_REMEMBER &&
					$token->getLastActivity() === $this->time &&
					$token->getPassword() !== null &&
					$this->tokenProvider->getPassword($token, 'newId') === 'password';
			}));
		$this->mapper
			->expects($this->at(2))
			->method('delete')
			->with($this->callback(function ($token) use ($oldToken) {
				return $token === $oldToken;
			}));

		$this->tokenProvider->renewSessionToken('oldId', 'newId');
	}

	public function testGetToken() {
		$token = new PublicKeyToken();

		$this->config->method('getSystemValue')
			->with('secret')
			->willReturn('mysecret');

		$this->mapper->method('getToken')
			->with(
				$this->callback(function (string $token) {
					return hash('sha512', 'unhashedToken'.'1f4h9s') === $token;
				})
			)->willReturn($token);

		$this->assertSame($token, $this->tokenProvider->getToken('unhashedToken'));
	}

	public function testGetInvalidToken() {
		$this->expectException(InvalidTokenException::class);

		$this->mapper->method('getToken')
			->with(
				$this->callback(function (string $token) {
					return hash('sha512', 'unhashedToken'.'1f4h9s') === $token;
				})
			)->willThrowException(new DoesNotExistException('nope'));

		$this->tokenProvider->getToken('unhashedToken');
	}

	public function testGetExpiredToken() {
		$token = 'token';
		$uid = 'user';
		$user = 'User';
		$password = 'passme';
		$name = 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12'
			. 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12'
			. 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12'
			. 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12';
		$type = IToken::PERMANENT_TOKEN;

		$actual = $this->tokenProvider->generateToken($token, $uid, $user, $password, $name, $type, IToken::DO_NOT_REMEMBER);
		$actual->setExpires(42);

		$this->mapper->method('getToken')
			->with(
				$this->callback(function (string $token) {
					return hash('sha512', 'token'.'1f4h9s') === $token;
				})
			)->willReturn($actual);

		try {
			$this->tokenProvider->getToken('token');
			$this->fail();
		} catch (ExpiredTokenException $e) {
			$this->assertSame($actual, $e->getToken());
		}
	}

	public function testGetTokenById() {
		$token = $this->createMock(PublicKeyToken::class);

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
		$token = new PublicKeyToken();
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
		$token = 'oldtoken';
		$uid = 'user';
		$user = 'User';
		$password = 'password';
		$name = 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12'
			. 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12'
			. 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12'
			. 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12';
		$type = IToken::PERMANENT_TOKEN;

		$actual = $this->tokenProvider->generateToken($token, $uid, $user, $password, $name, $type, IToken::DO_NOT_REMEMBER);

		$new = $this->tokenProvider->rotate($actual, 'oldtoken', 'newtoken');

		$this->assertSame('password', $this->tokenProvider->getPassword($new, 'newtoken'));
	}

	public function testRotateNoPassword() {
		$token = 'oldtoken';
		$uid = 'user';
		$user = 'User';
		$password = null;
		$name = 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12'
			. 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12'
			. 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12'
			. 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12';
		$type = IToken::PERMANENT_TOKEN;

		$actual = $this->tokenProvider->generateToken($token, $uid, $user, $password, $name, $type, IToken::DO_NOT_REMEMBER);

		$oldPrivate = $actual->getPrivateKey();

		$new = $this->tokenProvider->rotate($actual, 'oldtoken', 'newtoken');

		$newPrivate = $new->getPrivateKey();

		$this->assertNotSame($newPrivate, $oldPrivate);
		$this->assertNull($new->getPassword());
	}

	public function testConvertToken() {
		$defaultToken = new DefaultToken();
		$defaultToken->setId(42);
		$defaultToken->setPassword('oldPass');
		$defaultToken->setExpires(1337);
		$defaultToken->setToken('oldToken');
		$defaultToken->setUid('uid');
		$defaultToken->setLoginName('loginName');
		$defaultToken->setLastActivity(999);
		$defaultToken->setName('name');
		$defaultToken->setRemember(IToken::REMEMBER);
		$defaultToken->setType(IToken::PERMANENT_TOKEN);

		$this->mapper->expects($this->once())
			->method('update')
			->willReturnArgument(0);

		$newToken = $this->tokenProvider->convertToken($defaultToken, 'newToken', 'newPassword');

		$this->assertSame(42, $newToken->getId());
		$this->assertSame('newPassword', $this->tokenProvider->getPassword($newToken, 'newToken'));
		$this->assertSame(1337, $newToken->getExpires());
		$this->assertSame('uid', $newToken->getUID());
		$this->assertSame('loginName', $newToken->getLoginName());
		$this->assertSame(1313131, $newToken->getLastActivity());
		$this->assertSame(1313131, $newToken->getLastCheck());
		$this->assertSame('name', $newToken->getName());
		$this->assertSame(IToken::REMEMBER, $newToken->getRemember());
		$this->assertSame(IToken::PERMANENT_TOKEN, $newToken->getType());
	}

	public function testMarkPasswordInvalidInvalidToken() {
		$token = $this->createMock(DefaultToken::class);

		$this->expectException(InvalidTokenException::class);

		$this->tokenProvider->markPasswordInvalid($token, 'tokenId');
	}

	public function testMarkPasswordInvalid() {
		$token = $this->createMock(PublicKeyToken::class);

		$token->expects($this->once())
			->method('setPasswordInvalid')
			->with(true);
		$this->mapper->expects($this->once())
			->method('update')
			->with($token);

		$this->tokenProvider->markPasswordInvalid($token, 'tokenId');
	}

	public function testUpdatePasswords() {
		$uid = 'myUID';
		$token1 = $this->tokenProvider->generateToken(
			'foo',
			$uid,
			$uid,
			'bar',
			'random1',
			IToken::PERMANENT_TOKEN,
			IToken::REMEMBER);
		$token2 = $this->tokenProvider->generateToken(
			'foobar',
			$uid,
			$uid,
			'bar',
			'random2',
			IToken::PERMANENT_TOKEN,
			IToken::REMEMBER);

		$this->mapper->method('hasExpiredTokens')
			->with($uid)
			->willReturn(true);
		$this->mapper->expects($this->once())
			->method('getTokenByUser')
			->with($uid)
			->willReturn([$token1, $token2]);
		$this->mapper->expects($this->exactly(2))
			->method('update')
			->with($this->callback(function (PublicKeyToken $t) use ($token1, $token2) {
				return $t === $token1 || $t === $token2;
			}));

		$this->tokenProvider->updatePasswords($uid, 'bar2');
	}
}
