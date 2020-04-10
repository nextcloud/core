<?php

declare (strict_types = 1);
/**
 * @author Joas Schilling <nickvergessen@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
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

namespace Test\Notification;

use OC\Notification\Notification;
use OCP\Notification\IAction;
use OCP\Notification\INotification;
use OCP\RichObjectStrings\IValidator;
use Test\TestCase;

class NotificationTest extends TestCase {
	/** @var INotification */
	protected $notification;
	/** @var IValidator|\PHPUnit_Framework_MockObject_MockObject */
	protected $validator;

	protected function setUp(): void {
		parent::setUp();
		$this->validator = $this->createMock(IValidator::class);
		$this->notification = new Notification($this->validator);
	}

	protected function dataValidString($maxLength) {
		$dataSets = [
			['test1'],
			['1564'],
			[str_repeat('a', 1)],
		];
		if ($maxLength !== false) {
			$dataSets[] = [str_repeat('a', $maxLength)];
		}
		return $dataSets;
	}

	protected function dataInvalidString($maxLength) {
		$dataSets = [
			['']
		];
		if ($maxLength !== false) {
			$dataSets[] = [str_repeat('a', $maxLength + 1)];
		}
		return $dataSets;
	}

	public function dataSetApp() {
		return $this->dataValidString(32);
	}

	/**
	 * @dataProvider dataSetApp
	 * @param string $app
	 */
	public function testSetApp($app) {
		$this->assertSame('', $this->notification->getApp());
		$this->assertSame($this->notification, $this->notification->setApp($app));
		$this->assertSame($app, $this->notification->getApp());
	}

	public function dataSetAppInvalid() {
		return $this->dataInvalidString(32);
	}

	/**
	 * @dataProvider dataSetAppInvalid
	 * @param mixed $app
	 *
	 */
	public function testSetAppInvalid($app) {
		$this->expectException(\InvalidArgumentException::class);

		$this->notification->setApp($app);
	}


	public function dataSetUser() {
		return $this->dataValidString(64);
	}

	/**
	 * @dataProvider dataSetUser
	 * @param string $user
	 */
	public function testSetUser($user) {
		$this->assertSame('', $this->notification->getUser());
		$this->assertSame($this->notification, $this->notification->setUser($user));
		$this->assertSame($user, $this->notification->getUser());
	}

	public function dataSetUserInvalid() {
		return $this->dataInvalidString(64);
	}

	/**
	 * @dataProvider dataSetUserInvalid
	 * @param mixed $user
	 *
	 */
	public function testSetUserInvalid($user) {
		$this->expectException(\InvalidArgumentException::class);

		$this->notification->setUser($user);
	}

	public function dataSetDateTime() {
		$past = new \DateTime();
		$past->sub(new \DateInterval('P1Y'));
		$current = new \DateTime();
		$future = new \DateTime();
		$future->add(new \DateInterval('P1Y'));

		return [
			[$past],
			[$current],
			[$future],
		];
	}

	/**
	 * @dataProvider dataSetDateTime
	 * @param \DateTime $dateTime
	 */
	public function testSetDateTime(\DateTime $dateTime) {
		$this->assertSame(0, $this->notification->getDateTime()->getTimestamp());
		$this->assertSame($this->notification, $this->notification->setDateTime($dateTime));
		$this->assertSame($dateTime, $this->notification->getDateTime());
	}

	public function dataSetDateTimeZero() {
		$nineTeenSeventy = new \DateTime();
		$nineTeenSeventy->setTimestamp(0);
		return [
			[$nineTeenSeventy],
		];
	}

	/**
	 * @dataProvider dataSetDateTimeZero
	 * @param \DateTime $dateTime
	 *
	 * @expectedMessage 'The given date time is invalid'
	 */
	public function testSetDateTimeZero($dateTime) {
		$this->expectException(\InvalidArgumentException::class);

		$this->notification->setDateTime($dateTime);
	}

	public function dataSetObject() {
		return [
			['a', '21'],
			[str_repeat('a', 64), '42'],
		];
	}

	/**
	 * @dataProvider dataSetObject
	 * @param string $type
	 * @param string $id
	 */
	public function testSetObject($type, $id) {
		$this->assertSame('', $this->notification->getObjectType());
		$this->assertSame('', $this->notification->getObjectId());
		$this->assertSame($this->notification, $this->notification->setObject($type, $id));
		$this->assertSame($type, $this->notification->getObjectType());
		$this->assertSame($id, $this->notification->getObjectId());
	}

	public function dataSetObjectTypeInvalid() {
		return $this->dataInvalidString(64);
	}

	public function dataSetObjectIdInvalid() {
		return [
			[''],
			[str_repeat('a', 64 + 1)],
		];
	}

	/**
	 * @dataProvider dataSetObjectIdInvalid
	 * @param mixed $id
	 *
	 * @expectedMessage 'The given object id is invalid'
	 */
	public function testSetObjectIdInvalid($id) {
		$this->expectException(\InvalidArgumentException::class);

		$this->notification->setObject('object', $id);
	}

	public function dataSetSubject() {
		return [
			['a', []],
			[str_repeat('a', 64), [str_repeat('a', 160)]],
			[str_repeat('a', 64), array_fill(0, 160, 'a')],
		];
	}

	/**
	 * @dataProvider dataSetSubject
	 * @param string $subject
	 * @param array $parameters
	 */
	public function testSetSubject($subject, $parameters) {
		$this->assertSame('', $this->notification->getSubject());
		$this->assertSame([], $this->notification->getSubjectParameters());
		$this->assertSame($this->notification, $this->notification->setSubject($subject, $parameters));
		$this->assertSame($subject, $this->notification->getSubject());
		$this->assertSame($parameters, $this->notification->getSubjectParameters());
	}

	public function dataSetSubjectInvalidSubject() {
		return $this->dataInvalidString(64);
	}

	/**
	 * @dataProvider dataSetSubjectInvalidSubject
	 * @param mixed $subject
	 *
	 */
	public function testSetSubjectInvalidSubject($subject) {
		$this->expectException(\InvalidArgumentException::class);

		$this->notification->setSubject($subject, []);
	}

	public function dataSetParsedSubject() {
		return $this->dataValidString(false);
	}

	/**
	 * @dataProvider dataSetParsedSubject
	 * @param string $subject
	 */
	public function testSetParsedSubject($subject) {
		$this->assertSame('', $this->notification->getParsedSubject());
		$this->assertSame($this->notification, $this->notification->setParsedSubject($subject));
		$this->assertSame($subject, $this->notification->getParsedSubject());
	}

	public function dataSetParsedSubjectInvalid() {
		return $this->dataInvalidString(false);
	}

	/**
	 * @dataProvider dataSetParsedSubjectInvalid
	 * @param mixed $subject
	 *
	 */
	public function testSetParsedSubjectInvalid($subject) {
		$this->expectException(\InvalidArgumentException::class);

		$this->notification->setParsedSubject($subject);
	}

	public function dataSetMessage() {
		return [
			['a', []],
			[str_repeat('a', 64), [str_repeat('a', 160)]],
			[str_repeat('a', 64), array_fill(0, 160, 'a')],
		];
	}

	/**
	 * @dataProvider dataSetMessage
	 * @param string $message
	 * @param array $parameters
	 */
	public function testSetMessage($message, $parameters) {
		$this->assertSame('', $this->notification->getMessage());
		$this->assertSame([], $this->notification->getMessageParameters());
		$this->assertSame($this->notification, $this->notification->setMessage($message, $parameters));
		$this->assertSame($message, $this->notification->getMessage());
		$this->assertSame($parameters, $this->notification->getMessageParameters());
	}

	public function dataSetMessageInvalidMessage() {
		return $this->dataInvalidString(64);
	}

	/**
	 * @dataProvider dataSetMessageInvalidMessage
	 * @param mixed $message
	 *
	 */
	public function testSetMessageInvalidMessage($message) {
		$this->expectException(\InvalidArgumentException::class);

		$this->notification->setMessage($message, []);
	}

	public function dataSetParsedMessage() {
		return $this->dataValidString(false);
	}

	/**
	 * @dataProvider dataSetParsedMessage
	 * @param string $message
	 */
	public function testSetParsedMessage($message) {
		$this->assertSame('', $this->notification->getParsedMessage());
		$this->assertSame($this->notification, $this->notification->setParsedMessage($message));
		$this->assertSame($message, $this->notification->getParsedMessage());
	}

	public function dataSetParsedMessageInvalid() {
		return $this->dataInvalidString(false);
	}

	/**
	 * @dataProvider dataSetParsedMessageInvalid
	 * @param mixed $message
	 *
	 */
	public function testSetParsedMessageInvalid($message) {
		$this->expectException(\InvalidArgumentException::class);

		$this->notification->setParsedMessage($message);
	}

	public function dataSetLink() {
		return $this->dataValidString(4000);
	}

	/**
	 * @dataProvider dataSetLink
	 * @param string $link
	 */
	public function testSetLink($link) {
		$this->assertSame('', $this->notification->getLink());
		$this->assertSame($this->notification, $this->notification->setLink($link));
		$this->assertSame($link, $this->notification->getLink());
	}

	public function dataSetLinkInvalid() {
		return $this->dataInvalidString(4000);
	}

	/**
	 * @dataProvider dataSetLinkInvalid
	 * @param mixed $link
	 *
	 */
	public function testSetLinkInvalid($link) {
		$this->expectException(\InvalidArgumentException::class);

		$this->notification->setLink($link);
	}

	public function dataSetIcon() {
		return $this->dataValidString(4000);
	}

	/**
	 * @dataProvider dataSetIcon
	 * @param string $icon
	 */
	public function testSetIcon($icon) {
		$this->assertSame('', $this->notification->getIcon());
		$this->assertSame($this->notification, $this->notification->setIcon($icon));
		$this->assertSame($icon, $this->notification->getIcon());
	}

	public function dataSetIconInvalid() {
		return $this->dataInvalidString(4000);
	}

	/**
	 * @dataProvider dataSetIconInvalid
	 * @param mixed $icon
	 *
	 */
	public function testSetIconInvalid($icon) {
		$this->expectException(\InvalidArgumentException::class);

		$this->notification->setIcon($icon);
	}

	public function testCreateAction() {
		$action = $this->notification->createAction();
		$this->assertInstanceOf(IAction::class, $action);
	}

	public function testAddAction() {
		/** @var \OCP\Notification\IAction|\PHPUnit_Framework_MockObject_MockObject $action */
		$action = $this->createMock(IAction::class);
		$action->expects($this->once())
			->method('isValid')
			->willReturn(true);
		$action->expects($this->never())
			->method('isValidParsed');

		$this->assertSame($this->notification, $this->notification->addAction($action));

		$this->assertEquals([$action], $this->notification->getActions());
		$this->assertEquals([], $this->notification->getParsedActions());
	}

	
	public function testAddActionInvalid() {
		$this->expectException(\InvalidArgumentException::class);

		/** @var \OCP\Notification\IAction|\PHPUnit_Framework_MockObject_MockObject $action */
		$action = $this->createMock(IAction::class);
		$action->expects($this->once())
			->method('isValid')
			->willReturn(false);
		$action->expects($this->never())
			->method('isValidParsed');

		$this->notification->addAction($action);
	}

	public function testAddActionSecondPrimary() {
		/** @var \OCP\Notification\IAction|\PHPUnit_Framework_MockObject_MockObject $action */
		$action = $this->createMock(IAction::class);
		$action->expects($this->exactly(2))
			->method('isValid')
			->willReturn(true);
		$action->expects($this->exactly(2))
			->method('isPrimary')
			->willReturn(true);

		$this->assertSame($this->notification, $this->notification->addAction($action));

		$this->expectException(\InvalidArgumentException::class);
		$this->notification->addAction($action);
	}

	public function testAddParsedAction() {
		/** @var \OCP\Notification\IAction|\PHPUnit_Framework_MockObject_MockObject $action */
		$action = $this->createMock(IAction::class);
		$action->expects($this->once())
			->method('isValidParsed')
			->willReturn(true);
		$action->expects($this->never())
			->method('isValid');

		$this->assertSame($this->notification, $this->notification->addParsedAction($action));

		$this->assertEquals([$action], $this->notification->getParsedActions());
		$this->assertEquals([], $this->notification->getActions());
	}

	
	public function testAddParsedActionInvalid() {
		$this->expectException(\InvalidArgumentException::class);

		/** @var \OCP\Notification\IAction|\PHPUnit_Framework_MockObject_MockObject $action */
		$action = $this->createMock(IAction::class);
		$action->expects($this->once())
			->method('isValidParsed')
			->willReturn(false);
		$action->expects($this->never())
			->method('isValid');

		$this->notification->addParsedAction($action);
	}

	public function testAddActionSecondParsedPrimary() {
		/** @var \OCP\Notification\IAction|\PHPUnit_Framework_MockObject_MockObject $action */
		$action = $this->createMock(IAction::class);
		$action->expects($this->exactly(2))
			->method('isValidParsed')
			->willReturn(true);
		$action->expects($this->exactly(2))
			->method('isPrimary')
			->willReturn(true);

		$this->assertSame($this->notification, $this->notification->addParsedAction($action));

		$this->expectException(\InvalidArgumentException::class);
		$this->notification->addParsedAction($action);
	}

	public function testAddActionParsedPrimaryEnd() {
		/** @var \OCP\Notification\IAction|\PHPUnit_Framework_MockObject_MockObject $action */
		$action1 = $this->createMock(IAction::class);
		$action1->expects($this->exactly(2))
			->method('isValidParsed')
			->willReturn(true);
		$action1->expects($this->exactly(2))
			->method('isPrimary')
			->willReturn(false);
		/** @var \OCP\Notification\IAction|\PHPUnit_Framework_MockObject_MockObject $action */
		$action2 = $this->createMock(IAction::class);
		$action2->expects($this->once())
			->method('isValidParsed')
			->willReturn(true);
		$action2->expects($this->once())
			->method('isPrimary')
			->willReturn(true);

		$this->assertSame($this->notification, $this->notification->addParsedAction($action1));
		$this->assertSame($this->notification, $this->notification->addParsedAction($action2));
		$this->assertSame($this->notification, $this->notification->addParsedAction($action1));

		$this->assertEquals([$action2, $action1, $action1], $this->notification->getParsedActions());
	}

	public function dataIsValid() {
		return [
			[false, '', false],
			[true, '', false],
			[false, 'a', false],
			[true, 'a', true],
		];
	}

	/**
	 * @dataProvider dataIsValid
	 *
	 * @param bool $isValidCommon
	 * @param string $subject
	 * @param bool $expected
	 */
	public function testIsValid($isValidCommon, $subject, $expected) {
		/** @var \OCP\Notification\INotification|\PHPUnit_Framework_MockObject_MockObject $notification */
		$notification = $this->getMockBuilder(Notification::class)
			->setMethods([
				'isValidCommon',
				'getSubject',
				'getParsedSubject',
			])
			->setConstructorArgs([$this->validator])
			->getMock();

		$notification->expects($this->once())
			->method('isValidCommon')
			->willReturn($isValidCommon);

		$notification->expects(!$isValidCommon ? $this->never() : $this->once())
			->method('getSubject')
			->willReturn($subject);

		$notification->expects($this->never())
			->method('getParsedSubject')
			->willReturn($subject);

		$this->assertEquals($expected, $notification->isValid());
	}

	/**
	 * @dataProvider dataIsValid
	 *
	 * @param bool $isValidCommon
	 * @param string $subject
	 * @param bool $expected
	 */
	public function testIsParsedValid($isValidCommon, $subject, $expected) {
		/** @var \OCP\Notification\INotification|\PHPUnit_Framework_MockObject_MockObject $notification */
		$notification = $this->getMockBuilder(Notification::class)
			->setMethods([
				'isValidCommon',
				'getParsedSubject',
				'getSubject',
			])
			->setConstructorArgs([$this->validator])
			->getMock();

		$notification->expects($this->once())
			->method('isValidCommon')
			->willReturn($isValidCommon);

		$notification->expects(!$isValidCommon ? $this->never() : $this->once())
			->method('getParsedSubject')
			->willReturn($subject);

		$notification->expects($this->never())
			->method('getSubject')
			->willReturn($subject);

		$this->assertEquals($expected, $notification->isValidParsed());
	}

	public function dataIsValidCommon() {
		return [
			['', '', 0, '', '', false],
			['app', '', 0, '', '', false],
			['app', 'user', 0, '', '', false],
			['app', 'user', time(), '', '', false],
			['app', 'user', time(), 'type', '', false],
			['app', 'user', time(), 'type', '42', true],
		];
	}

	/**
	 * @dataProvider dataIsValidCommon
	 *
	 * @param string $app
	 * @param string $user
	 * @param int $timestamp
	 * @param string $objectType
	 * @param string $objectId
	 * @param bool $expected
	 */
	public function testIsValidCommon($app, $user, $timestamp, $objectType, $objectId, $expected) {
		/** @var \OCP\Notification\INotification|\PHPUnit_Framework_MockObject_MockObject $notification */
		$notification = $this->getMockBuilder(Notification::class)
			->setMethods([
				'getApp',
				'getUser',
				'getDateTime',
				'getObjectType',
				'getObjectId',
			])
			->setConstructorArgs([$this->validator])
			->getMock();

		$notification->expects($this->any())
			->method('getApp')
			->willReturn($app);

		$notification->expects($this->any())
			->method('getUser')
			->willReturn($user);

		$dateTime = new \DateTime();
		$dateTime->setTimestamp($timestamp);

		$notification->expects($this->any())
			->method('getDateTime')
			->willReturn($dateTime);

		$notification->expects($this->any())
			->method('getObjectType')
			->willReturn($objectType);

		$notification->expects($this->any())
			->method('getObjectId')
			->willReturn($objectId);

		$this->assertEquals($expected, $this->invokePrivate($notification, 'isValidCommon'));
	}
}
