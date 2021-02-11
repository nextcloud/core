<?php

/**
 * @copyright Copyright (c) 2014 Thomas Müller <deepdiver@owncloud.com>
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
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

namespace Test\Activity;

use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\RichObjectStrings\IValidator;
use Test\TestCase;

class ManagerTest extends TestCase {

	/** @var \OC\Activity\Manager */
	private $activityManager;

	/** @var IRequest|\PHPUnit\Framework\MockObject\MockObject */
	protected $request;
	/** @var IUserSession|\PHPUnit\Framework\MockObject\MockObject */
	protected $session;
	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	protected $config;
	/** @var IValidator|\PHPUnit\Framework\MockObject\MockObject */
	protected $validator;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->session = $this->createMock(IUserSession::class);
		$this->config = $this->createMock(IConfig::class);
		$this->validator = $this->createMock(IValidator::class);

		$this->activityManager = new \OC\Activity\Manager(
			$this->request,
			$this->session,
			$this->config,
			$this->validator,
			$this->createMock(IL10N::class)
		);

		$this->assertSame([], self::invokePrivate($this->activityManager, 'getConsumers'));

		$this->activityManager->registerConsumer(function () {
			return new NoOpConsumer();
		});

		$this->assertNotEmpty(self::invokePrivate($this->activityManager, 'getConsumers'));
		$this->assertNotEmpty(self::invokePrivate($this->activityManager, 'getConsumers'));
	}

	public function testGetConsumers() {
		$consumers = self::invokePrivate($this->activityManager, 'getConsumers');

		$this->assertNotEmpty($consumers);
	}


	public function testGetConsumersInvalidConsumer() {
		$this->expectException(\InvalidArgumentException::class);

		$this->activityManager->registerConsumer(function () {
			return new \stdClass();
		});

		self::invokePrivate($this->activityManager, 'getConsumers');
	}

	public function getUserFromTokenThrowInvalidTokenData() {
		return [
			[null, []],
			['', []],
			['12345678901234567890123456789', []],
			['1234567890123456789012345678901', []],
			['123456789012345678901234567890', []],
			['123456789012345678901234567890', ['user1', 'user2']],
		];
	}

	/**
	 * @dataProvider getUserFromTokenThrowInvalidTokenData
	 *
	 * @param string $token
	 * @param array $users
	 */
	public function testGetUserFromTokenThrowInvalidToken($token, $users) {
		$this->expectException(\UnexpectedValueException::class);

		$this->mockRSSToken($token, $token, $users);
		self::invokePrivate($this->activityManager, 'getUserFromToken');
	}

	public function getUserFromTokenData() {
		return [
			[null, '123456789012345678901234567890', 'user1'],
			['user2', null, 'user2'],
			['user2', '123456789012345678901234567890', 'user2'],
		];
	}

	/**
	 * @dataProvider getUserFromTokenData
	 *
	 * @param string $userLoggedIn
	 * @param string $token
	 * @param string $expected
	 */
	public function testGetUserFromToken($userLoggedIn, $token, $expected) {
		if ($userLoggedIn !== null) {
			$this->mockUserSession($userLoggedIn);
		}
		$this->mockRSSToken($token, '123456789012345678901234567890', ['user1']);

		$this->assertEquals($expected, $this->activityManager->getCurrentUserId());
	}

	protected function mockRSSToken($requestToken, $userToken, $users) {
		if ($requestToken !== null) {
			$this->request->expects($this->any())
				->method('getParam')
				->with('token', '')
				->willReturn($requestToken);
		}

		$this->config->expects($this->any())
			->method('getUsersForUserValue')
			->with('activity', 'rsstoken', $userToken)
			->willReturn($users);
	}

	protected function mockUserSession($user) {
		$mockUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$mockUser->expects($this->any())
			->method('getUID')
			->willReturn($user);

		$this->session->expects($this->any())
			->method('isLoggedIn')
			->willReturn(true);
		$this->session->expects($this->any())
			->method('getUser')
			->willReturn($mockUser);
	}


	public function testPublishExceptionNoApp() {
		$this->expectException(\BadMethodCallException::class);

		$event = $this->activityManager->generateEvent();
		$this->activityManager->publish($event);
	}


	public function testPublishExceptionNoType() {
		$this->expectException(\BadMethodCallException::class);

		$event = $this->activityManager->generateEvent();
		$event->setApp('test');
		$this->activityManager->publish($event);
	}


	public function testPublishExceptionNoAffectedUser() {
		$this->expectException(\BadMethodCallException::class);

		$event = $this->activityManager->generateEvent();
		$event->setApp('test')
			->setType('test_type');
		$this->activityManager->publish($event);
	}


	public function testPublishExceptionNoSubject() {
		$this->expectException(\BadMethodCallException::class);

		$event = $this->activityManager->generateEvent();
		$event->setApp('test')
			->setType('test_type')
			->setAffectedUser('test_affected');
		$this->activityManager->publish($event);
	}

	public function dataPublish() {
		return [
			[null, ''],
			['test_author', 'test_author'],
		];
	}

	/**
	 * @dataProvider dataPublish
	 * @param string|null $author
	 * @param string $expected
	 */
	public function testPublish($author, $expected) {
		if ($author !== null) {
			$authorObject = $this->getMockBuilder(IUser::class)
				->disableOriginalConstructor()
				->getMock();
			$authorObject->expects($this->once())
				->method('getUID')
				->willReturn($author);
			$this->session->expects($this->atLeastOnce())
				->method('getUser')
				->willReturn($authorObject);
		}

		$event = $this->activityManager->generateEvent();
		$event->setApp('test')
			->setType('test_type')
			->setSubject('test_subject', [])
			->setAffectedUser('test_affected')
			->setObject('file', 123);

		$consumer = $this->getMockBuilder('OCP\Activity\IConsumer')
			->disableOriginalConstructor()
			->getMock();
		$consumer->expects($this->once())
			->method('receive')
			->with($event)
			->willReturnCallback(function (\OCP\Activity\IEvent $event) use ($expected) {
				$this->assertLessThanOrEqual(time() + 2, $event->getTimestamp(), 'Timestamp not set correctly');
				$this->assertGreaterThanOrEqual(time() - 2, $event->getTimestamp(), 'Timestamp not set correctly');
				$this->assertSame($expected, $event->getAuthor(), 'Author name not set correctly');
			});
		$this->activityManager->registerConsumer(function () use ($consumer) {
			return $consumer;
		});

		$this->activityManager->publish($event);
	}

	public function testPublishAllManually() {
		$event = $this->activityManager->generateEvent();
		$event->setApp('test_app')
			->setType('test_type')
			->setAffectedUser('test_affected')
			->setAuthor('test_author')
			->setTimestamp(1337)
			->setSubject('test_subject', ['test_subject_param'])
			->setMessage('test_message', ['test_message_param'])
			->setObject('test_object_type', 42, 'test_object_name')
			->setLink('test_link')
		;

		$consumer = $this->getMockBuilder('OCP\Activity\IConsumer')
			->disableOriginalConstructor()
			->getMock();
		$consumer->expects($this->once())
			->method('receive')
			->willReturnCallback(function (\OCP\Activity\IEvent $event) {
				$this->assertSame('test_app', $event->getApp(), 'App not set correctly');
				$this->assertSame('test_type', $event->getType(), 'Type not set correctly');
				$this->assertSame('test_affected', $event->getAffectedUser(), 'Affected user not set correctly');
				$this->assertSame('test_author', $event->getAuthor(), 'Author not set correctly');
				$this->assertSame(1337, $event->getTimestamp(), 'Timestamp not set correctly');
				$this->assertSame('test_subject', $event->getSubject(), 'Subject not set correctly');
				$this->assertSame(['test_subject_param'], $event->getSubjectParameters(), 'Subject parameter not set correctly');
				$this->assertSame('test_message', $event->getMessage(), 'Message not set correctly');
				$this->assertSame(['test_message_param'], $event->getMessageParameters(), 'Message parameter not set correctly');
				$this->assertSame('test_object_type', $event->getObjectType(), 'Object type not set correctly');
				$this->assertSame(42, $event->getObjectId(), 'Object ID not set correctly');
				$this->assertSame('test_object_name', $event->getObjectName(), 'Object name not set correctly');
				$this->assertSame('test_link', $event->getLink(), 'Link not set correctly');
			});
		$this->activityManager->registerConsumer(function () use ($consumer) {
			return $consumer;
		});

		$this->activityManager->publish($event);
	}
}

class NoOpConsumer implements \OCP\Activity\IConsumer {
	public function receive(\OCP\Activity\IEvent $event) {
	}
}
