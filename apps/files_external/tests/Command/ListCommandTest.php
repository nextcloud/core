<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Files_External\Tests\Command;

use OCA\Files_External\Command\ListCommand;
use OCA\Files_External\Lib\Auth\NullMechanism;
use OCA\Files_External\Lib\Auth\Password\Password;
use OCA\Files_External\Lib\Auth\Password\SessionCredentials;
use OCA\Files_External\Lib\Backend\Local;
use OCA\Files_External\Lib\StorageConfig;
use OCA\Files_External\Service\GlobalStoragesService;
use OCA\Files_External\Service\UserStoragesService;
use OCP\Authentication\LoginCredentials\IStore;
use OCP\IL10N;
use OCP\ISession;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Security\ICrypto;
use PHPUnit_Framework_MockObject_MockObject;
use Symfony\Component\Console\Output\BufferedOutput;

class ListCommandTest extends CommandTest {
	/**
	 * @return ListCommand|PHPUnit_Framework_MockObject_MockObject
	 */
	private function getInstance() {
		/** @var GlobalStoragesService|PHPUnit_Framework_MockObject_MockObject $globalService */
		$globalService = $this->createMock(GlobalStoragesService::class);
		/** @var UserStoragesService|PHPUnit_Framework_MockObject_MockObject $userService */
		$userService = $this->createMock(UserStoragesService::class);
		/** @var IUserManager|PHPUnit_Framework_MockObject_MockObject $userManager */
		$userManager = $this->createMock(IUserManager::class);
		/** @var IUserSession|PHPUnit_Framework_MockObject_MockObject $userSession */
		$userSession = $this->createMock(IUserSession::class);

		return new ListCommand($globalService, $userService, $userSession, $userManager);
	}

	public function testListAuthIdentifier() {
		$l10n = $this->createMock(IL10N::class);
		$session = $this->createMock(ISession::class);
		$crypto = $this->createMock(ICrypto::class);
		$instance = $this->getInstance();
		$mount1 = new StorageConfig();
		$mount1->setAuthMechanism(new Password($l10n));
		$mount1->setBackend(new Local($l10n, new NullMechanism($l10n)));
		$mount2 = new StorageConfig();
		$credentialStore = $this->createMock(IStore::class);
		$mount2->setAuthMechanism(new SessionCredentials($l10n, $credentialStore));
		$mount2->setBackend(new Local($l10n, new NullMechanism($l10n)));
		$input = $this->getInput($instance, [], [
			'output' => 'json'
		]);
		$output = new BufferedOutput();

		$instance->listMounts('', [$mount1, $mount2], $input, $output);
		$output = json_decode($output->fetch(), true);

		$this->assertNotEquals($output[0]['authentication_type'], $output[1]['authentication_type']);
	}
}
