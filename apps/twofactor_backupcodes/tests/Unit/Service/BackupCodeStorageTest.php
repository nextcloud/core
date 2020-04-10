<?php
/**
 *
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

namespace OCA\TwoFactorBackupCodes\Tests\Unit\Service;

use OCA\TwoFactorBackupCodes\Db\BackupCode;
use OCA\TwoFactorBackupCodes\Db\BackupCodeMapper;
use OCA\TwoFactorBackupCodes\Event\CodesGenerated;
use OCA\TwoFactorBackupCodes\Service\BackupCodeStorage;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IUser;
use OCP\Security\IHasher;
use OCP\Security\ISecureRandom;
use PHPUnit_Framework_MockObject_MockObject;
use Test\TestCase;

class BackupCodeStorageTest extends TestCase {

	/** @var BackupCodeMapper|PHPUnit_Framework_MockObject_MockObject */
	private $mapper;

	/** @var ISecureRandom|PHPUnit_Framework_MockObject_MockObject */
	private $random;

	/** @var IHasher|PHPUnit_Framework_MockObject_MockObject */
	private $hasher;

	/** @var IEventDispatcher|PHPUnit_Framework_MockObject_MockObject */
	private $eventDispatcher;

	/** @var BackupCodeStorage */
	private $storage;

	protected function setUp(): void {
		parent::setUp();

		$this->mapper = $this->createMock(BackupCodeMapper::class);
		$this->random = $this->createMock(ISecureRandom::class);
		$this->hasher = $this->createMock(IHasher::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);

		$this->storage = new BackupCodeStorage($this->mapper, $this->random, $this->hasher, $this->eventDispatcher);
	}

	public function testCreateCodes() {
		$user = $this->createMock(IUser::class);
		$number = 5;
		$user->method('getUID')->willReturn('fritz');
		$this->random->expects($this->exactly($number))
			->method('generate')
			->with(16, ISecureRandom::CHAR_HUMAN_READABLE)
			->willReturn('CODEABCDEF');
		$this->hasher->expects($this->exactly($number))
			->method('hash')
			->with('CODEABCDEF')
			->willReturn('HASHEDCODE');
		$row = new BackupCode();
		$row->setUserId('fritz');
		$row->setCode('HASHEDCODE');
		$row->setUsed(0);
		$this->mapper->expects($this->exactly($number))
			->method('insert')
			->with($this->equalTo($row));
		$this->eventDispatcher->expects($this->once())
			->method('dispatch')
			->with(
				$this->equalTo(CodesGenerated::class),
				$this->equalTo(new CodesGenerated($user))
			);

		$codes = $this->storage->createCodes($user, $number);
		$this->assertCount($number, $codes);
		foreach ($codes as $code) {
			$this->assertEquals('CODEABCDEF', $code);
		}
	}

	public function testHasBackupCodes() {
		$user = $this->createMock(IUser::class);
		$codes = [
			new BackupCode(),
			new BackupCode(),
		];

		$this->mapper->expects($this->once())
			->method('getBackupCodes')
			->with($user)
			->willReturn($codes);

		$this->assertTrue($this->storage->hasBackupCodes($user));
	}

	public function testHasBackupCodesNoCodes() {
		$user = $this->createMock(IUser::class);
		$codes = [];

		$this->mapper->expects($this->once())
			->method('getBackupCodes')
			->with($user)
			->willReturn($codes);

		$this->assertFalse($this->storage->hasBackupCodes($user));
	}

	public function testGetBackupCodeState() {
		$user = $this->createMock(IUser::class);

		$code1 = new BackupCode();
		$code1->setUsed(1);
		$code2 = new BackupCode();
		$code2->setUsed('0');
		$codes = [
			$code1,
			$code2,
		];

		$this->mapper->expects($this->once())
			->method('getBackupCodes')
			->with($user)
			->willReturn($codes);

		$expected = [
			'enabled' => true,
			'total' => 2,
			'used' => 1,
		];
		$this->assertEquals($expected, $this->storage->getBackupCodesState($user));
	}

	public function testGetBackupCodeDisabled() {
		$user = $this->createMock(IUser::class);

		$codes = [];

		$this->mapper->expects($this->once())
			->method('getBackupCodes')
			->with($user)
			->willReturn($codes);

		$expected = [
			'enabled' => false,
			'total' => 0,
			'used' => 0,
		];
		$this->assertEquals($expected, $this->storage->getBackupCodesState($user));
	}

	public function testValidateCode() {
		$user = $this->createMock(IUser::class);
		$code = new BackupCode();
		$code->setUsed(0);
		$code->setCode('HASHEDVALUE');
		$codes = [
			$code,
		];

		$this->mapper->expects($this->once())
			->method('getBackupCodes')
			->with($user)
			->willReturn($codes);
		$this->hasher->expects($this->once())
			->method('verify')
			->with('CHALLENGE', 'HASHEDVALUE', $this->anything())
			->willReturn(true);
		$this->mapper->expects($this->once())
			->method('update')
			->with($code);

		$this->assertTrue($this->storage->validateCode($user, 'CHALLENGE'));

		$this->assertEquals(1, $code->getUsed());
	}

	public function testValidateUsedCode() {
		$user = $this->createMock(IUser::class);
		$code = new BackupCode();
		$code->setUsed('1');
		$code->setCode('HASHEDVALUE');
		$codes = [
			$code,
		];

		$this->mapper->expects($this->once())
			->method('getBackupCodes')
			->with($user)
			->willReturn($codes);
		$this->hasher->expects($this->never())
			->method('verify');
		$this->mapper->expects($this->never())
			->method('update');

		$this->assertFalse($this->storage->validateCode($user, 'CHALLENGE'));
	}

	public function testValidateCodeWithWrongHash() {
		$user = $this->createMock(IUser::class);
		$code = new BackupCode();
		$code->setUsed(0);
		$code->setCode('HASHEDVALUE');
		$codes = [
			$code,
		];

		$this->mapper->expects($this->once())
			->method('getBackupCodes')
			->with($user)
			->willReturn($codes);
		$this->hasher->expects($this->once())
			->method('verify')
			->with('CHALLENGE', 'HASHEDVALUE')
			->willReturn(false);
		$this->mapper->expects($this->never())
			->method('update');

		$this->assertFalse($this->storage->validateCode($user, 'CHALLENGE'));
	}

}
