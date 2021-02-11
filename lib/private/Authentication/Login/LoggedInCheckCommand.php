<?php
/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
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

declare(strict_types=1);

namespace OC\Authentication\Login;

use OC\Authentication\Events\LoginFailed;
use OC\Core\Controller\LoginController;
use OCP\EventDispatcher\IEventDispatcher;
use Psr\Log\LoggerInterface;

class LoggedInCheckCommand extends ALoginCommand {

	/** @var LoggerInterface */
	private $logger;
	/** @var IEventDispatcher */
	private $dispatcher;

	public function __construct(LoggerInterface $logger,
								IEventDispatcher $dispatcher) {
		$this->logger = $logger;
		$this->dispatcher = $dispatcher;
	}

	public function process(LoginData $loginData): LoginResult {
		if ($loginData->getUser() === false) {
			$loginName = $loginData->getUsername();
			$ip = $loginData->getRequest()->getRemoteAddress();

			$this->logger->warning("Login failed: $loginName (Remote IP: $ip)");

			$this->dispatcher->dispatchTyped(new LoginFailed($loginName));

			return LoginResult::failure($loginData, LoginController::LOGIN_MSG_INVALIDPASSWORD);
		}

		return $this->processNextOrFinishSuccessfully($loginData);
	}
}
