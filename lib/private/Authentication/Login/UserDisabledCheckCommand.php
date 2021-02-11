<?php
/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
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

declare(strict_types=1);

namespace OC\Authentication\Login;

use OC\Core\Controller\LoginController;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class UserDisabledCheckCommand extends ALoginCommand {

	/** @var IUserManager */
	private $userManager;

	/** @var LoggerInterface */
	private $logger;

	public function __construct(IUserManager $userManager,
								LoggerInterface $logger) {
		$this->userManager = $userManager;
		$this->logger = $logger;
	}

	public function process(LoginData $loginData): LoginResult {
		$user = $this->userManager->get($loginData->getUsername());
		if ($user !== null && $user->isEnabled() === false) {
			$username = $loginData->getUsername();
			$ip = $loginData->getRequest()->getRemoteAddress();

			$this->logger->warning("Login failed: $username disabled (Remote IP: $ip)");

			return LoginResult::failure($loginData, LoginController::LOGIN_MSG_USERDISABLED);
		}

		return $this->processNextOrFinishSuccessfully($loginData);
	}
}
