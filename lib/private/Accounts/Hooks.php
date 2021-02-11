<?php
/**
 * @copyright Copyright (c) 2016 Bjoern Schiessle <bjoern@schiessle.org>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OC\Accounts;

use OCP\Accounts\IAccountManager;
use OCP\IUser;
use Psr\Log\LoggerInterface;

class Hooks {

	/** @var AccountManager|null */
	private $accountManager;

	/** @var LoggerInterface */
	private $logger;

	public function __construct(LoggerInterface $logger) {
		$this->logger = $logger;
	}

	/**
	 * update accounts table if email address or display name was changed from outside
	 *
	 * @param array $params
	 */
	public function changeUserHook($params) {
		$accountManager = $this->getAccountManager();

		/** @var IUser $user */
		$user = isset($params['user']) ? $params['user'] : null;
		$feature = isset($params['feature']) ? $params['feature'] : null;
		$newValue = isset($params['value']) ? $params['value'] : null;

		if (is_null($user) || is_null($feature) || is_null($newValue)) {
			$this->logger->warning('Missing expected parameters in change user hook');
			return;
		}

		$accountData = $accountManager->getUser($user);

		switch ($feature) {
			case 'eMailAddress':
				if ($accountData[IAccountManager::PROPERTY_EMAIL]['value'] !== $newValue) {
					$accountData[IAccountManager::PROPERTY_EMAIL]['value'] = $newValue;
					$accountManager->updateUser($user, $accountData);
				}
				break;
			case 'displayName':
				if ($accountData[IAccountManager::PROPERTY_DISPLAYNAME]['value'] !== $newValue) {
					$accountData[IAccountManager::PROPERTY_DISPLAYNAME]['value'] = $newValue;
					$accountManager->updateUser($user, $accountData);
				}
				break;
		}
	}

	/**
	 * return instance of accountManager
	 *
	 * @return AccountManager
	 */
	protected function getAccountManager(): AccountManager {
		if ($this->accountManager === null) {
			$this->accountManager = \OC::$server->query(AccountManager::class);
		}
		return $this->accountManager;
	}
}
