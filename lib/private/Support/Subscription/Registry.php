<?php

declare(strict_types=1);

/**
 *
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OC\Support\Subscription;

use OC\User\Backend;
use OCP\AppFramework\QueryException;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IServerContainer;
use OCP\IUserManager;
use OCP\Notification\IManager;
use OCP\Support\Subscription\Exception\AlreadyRegisteredException;
use OCP\Support\Subscription\IRegistry;
use OCP\Support\Subscription\ISubscription;
use OCP\Support\Subscription\ISupportedApps;
use Psr\Log\LoggerInterface;

class Registry implements IRegistry {

	/** @var ISubscription */
	private $subscription = null;

	/** @var string */
	private $subscriptionService = null;

	/** @var IConfig */
	private $config;

	/** @var IServerContainer */
	private $container;
	/** @var IUserManager */
	private $userManager;
	/** @var IGroupManager */
	private $groupManager;
	/** @var LoggerInterface */
	private $logger;
	/** @var IManager */
	private $notificationManager;

	public function __construct(IConfig $config,
								IServerContainer $container,
								IUserManager $userManager,
								IGroupManager $groupManager,
								LoggerInterface $logger,
								IManager $notificationManager) {
		$this->config = $config;
		$this->container = $container;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->logger = $logger;
		$this->notificationManager = $notificationManager;
	}

	private function getSubscription(): ?ISubscription {
		if ($this->subscription === null && $this->subscriptionService !== null) {
			try {
				$this->subscription = $this->container->query($this->subscriptionService);
			} catch (QueryException $e) {
				// Ignore this
			}
		}

		return $this->subscription;
	}

	/**
	 * Register a subscription instance. In case it is called multiple times the
	 * first one is used.
	 *
	 * @param ISubscription $subscription
	 * @throws AlreadyRegisteredException
	 *
	 * @since 17.0.0
	 */
	public function register(ISubscription $subscription): void {
		if ($this->subscription !== null || $this->subscriptionService !== null) {
			throw new AlreadyRegisteredException();
		}
		$this->subscription = $subscription;
	}

	public function registerService(string $subscriptionService): void {
		if ($this->subscription !== null || $this->subscriptionService !== null) {
			throw new AlreadyRegisteredException();
		}

		$this->subscriptionService = $subscriptionService;
	}


	/**
	 * Fetches the list of app IDs that are supported by the subscription
	 *
	 * @since 17.0.0
	 */
	public function delegateGetSupportedApps(): array {
		if ($this->getSubscription() instanceof ISupportedApps) {
			return $this->getSubscription()->getSupportedApps();
		}
		return [];
	}

	/**
	 * Indicates if a valid subscription is available
	 *
	 * @since 17.0.0
	 */
	public function delegateHasValidSubscription(): bool {
		// Allow overwriting this manually for environments where the subscription information cannot be fetched
		if ($this->config->getSystemValueBool('has_valid_subscription')) {
			return true;
		}

		if ($this->getSubscription() instanceof ISubscription) {
			return $this->getSubscription()->hasValidSubscription();
		}
		return false;
	}

	/**
	 * Indicates if the subscription has extended support
	 *
	 * @since 17.0.0
	 */
	public function delegateHasExtendedSupport(): bool {
		if ($this->getSubscription() instanceof ISubscription) {
			return $this->getSubscription()->hasExtendedSupport();
		}
		return false;
	}


	/**
	 * Indicates if a hard user limit is reached and no new users should be created
	 *
	 * @since 21.0.0
	 */
	public function delegateIsHardUserLimitReached(): bool {
		$subscription = $this->getSubscription();
		if ($subscription instanceof ISubscription &&
			$subscription->hasValidSubscription()) {
			$userLimitReached = $subscription->isHardUserLimitReached();
			if ($userLimitReached) {
				$this->notifyAboutReachedUserLimit();
			}
			return $userLimitReached;
		}

		$isOneClickInstance = $this->config->getSystemValueBool('one-click-instance', false);

		if (!$isOneClickInstance) {
			return false;
		}

		$userCount = $this->getUserCount();
		$hardUserLimit = $this->config->getSystemValue('one-click-instance.user-limit', 50);

		$userLimitReached = $userCount >= $hardUserLimit;
		if ($userLimitReached) {
			$this->notifyAboutReachedUserLimit();
		}
		return $userLimitReached;
	}

	private function getUserCount(): int {
		$userCount = 0;
		$backends = $this->userManager->getBackends();
		foreach ($backends as $backend) {
			if ($backend->implementsActions(Backend::COUNT_USERS)) {
				$backendUsers = $backend->countUsers();
				if ($backendUsers !== false) {
					$userCount += $backendUsers;
				} else {
					// TODO what if the user count can't be determined?
					$this->logger->warning('Can not determine user count for ' . get_class($backend), ['app' => 'lib']);
				}
			}
		}

		$disabledUsers = $this->config->getUsersForUserValue('core', 'enabled', 'false');
		$disabledUsersCount = count($disabledUsers);
		$userCount = $userCount - $disabledUsersCount;

		if ($userCount < 0) {
			$userCount = 0;

			// this should never happen
			$this->logger->warning("Total user count was negative (users: $userCount, disabled: $disabledUsersCount)", ['app' => 'lib']);
		}

		return $userCount;
	}

	private function notifyAboutReachedUserLimit() {
		$admins = $this->groupManager->get('admin')->getUsers();
		foreach ($admins as $admin) {
			$notification = $this->notificationManager->createNotification();

			$notification->setApp('core')
				->setUser($admin->getUID())
				->setDateTime(new \DateTime())
				->setObject('user_limit_reached', '1')
				->setSubject('user_limit_reached');
			$this->notificationManager->notify($notification);
		}

		$this->logger->warning('The user limit was reached and the new user was not created', ['app' => 'lib']);
	}
}
