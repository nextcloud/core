<?php
/**
 *
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\TwoFactorBackupCodes\Tests\Unit\Provider;

use OC\App\AppManager;
use OCA\TwoFactorBackupCodes\Provider\BackupCodesProvider;
use OCA\TwoFactorBackupCodes\Service\BackupCodeStorage;
use OCP\IInitialStateService;
use OCP\IL10N;
use OCP\IUser;
use OCP\Template;
use Test\TestCase;

class BackupCodesProviderTest extends TestCase {

	/** @var string */
	private $appName;

	/** @var BackupCodeStorage|\PHPUnit\Framework\MockObject\MockObject */
	private $storage;

	/** @var IL10N|\PHPUnit\Framework\MockObject\MockObject */
	private $l10n;

	/** @var AppManager|\PHPUnit\Framework\MockObject\MockObject */
	private $appManager;

	/** @var IInitialStateService|\PHPUnit\Framework\MockObject\MockObject */
	private $initialState;

	/** @var BackupCodesProvider */
	private $provider;

	protected function setUp(): void {
		parent::setUp();

		$this->appName = "twofactor_backupcodes";
		$this->storage = $this->createMock(BackupCodeStorage::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->appManager = $this->createMock(AppManager::class);
		$this->initialState = $this->createMock(IInitialStateService::class);

		$this->provider = new BackupCodesProvider($this->appName, $this->storage, $this->l10n, $this->appManager, $this->initialState);
	}

	public function testGetId() {
		$this->assertEquals('backup_codes', $this->provider->getId());
	}

	public function testGetDisplayName() {
		$this->l10n->expects($this->once())
			->method('t')
			->with('Backup code')
			->willReturn('l10n backup code');
		$this->assertSame('l10n backup code', $this->provider->getDisplayName());
	}

	public function testGetDescription() {
		$this->l10n->expects($this->once())
			->method('t')
			->with('Use backup code')
			->willReturn('l10n use backup code');
		$this->assertSame('l10n use backup code', $this->provider->getDescription());
	}

	public function testGetTempalte() {
		$user = $this->getMockBuilder(IUser::class)->getMock();
		$expected = new Template('twofactor_backupcodes', 'challenge');

		$this->assertEquals($expected, $this->provider->getTemplate($user));
	}

	public function testVerfiyChallenge() {
		$user = $this->getMockBuilder(IUser::class)->getMock();
		$challenge = 'xyz';

		$this->storage->expects($this->once())
			->method('validateCode')
			->with($user, $challenge)
			->willReturn(false);

		$this->assertFalse($this->provider->verifyChallenge($user, $challenge));
	}

	public function testIsTwoFactorEnabledForUser() {
		$user = $this->getMockBuilder(IUser::class)->getMock();

		$this->storage->expects($this->once())
			->method('hasBackupCodes')
			->with($user)
			->willReturn(true);

		$this->assertTrue($this->provider->isTwoFactorAuthEnabledForUser($user));
	}

	public function testIsActiveNoProviders() {
		$user = $this->getMockBuilder(IUser::class)->getMock();

		$this->appManager->expects($this->once())
			->method('getEnabledAppsForUser')
			->with($user)
			->willReturn([
				'twofactor_backupcodes',
				'mail',
			]);
		$this->appManager->expects($this->once())
			->method('getAppInfo')
			->with('mail')
			->willReturn([
				'two-factor-providers' => [],
			]);

		$this->assertFalse($this->provider->isActive($user));
	}

	public function testIsActiveWithProviders() {
		$user = $this->getMockBuilder(IUser::class)->getMock();

		$this->appManager->expects($this->once())
			->method('getEnabledAppsForUser')
			->with($user)
			->willReturn([
				'twofactor_backupcodes',
				'twofactor_u2f',
			]);
		$this->appManager->expects($this->once())
			->method('getAppInfo')
			->with('twofactor_u2f')
			->willReturn([
				'two-factor-providers' => [
					'OCA\TwoFactorU2F\Provider\U2FProvider',
				],
			]);

		$this->assertTrue($this->provider->isActive($user));
	}
}
