<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OC\Notification;

use OCP\AppFramework\QueryException;
use OCP\ILogger;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\IApp;
use OCP\Notification\IDismissableNotifier;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\RichObjectStrings\IValidator;

class Manager implements IManager {
	/** @var IValidator */
	protected $validator;
	/** @var ILogger */
	protected $logger;

	/** @var IApp[] */
	protected $apps;
	/** @var string[] */
	protected $appClasses;

	/** @var INotifier[] */
	protected $notifiers;
	/** @var string[] */
	protected $notifierClasses;

	/** @var bool */
	protected $preparingPushNotification;

	public function __construct(IValidator $validator,
								ILogger $logger) {
		$this->validator = $validator;
		$this->logger = $logger;
		$this->apps = [];
		$this->notifiers = [];
		$this->appClasses = [];
		$this->notifierClasses = [];
		$this->preparingPushNotification = false;
	}
	/**
	 * @param string $appClass The service must implement IApp, otherwise a
	 *                          \InvalidArgumentException is thrown later
	 * @since 17.0.0
	 */
	public function registerApp(string $appClass): void {
		$this->appClasses[] = $appClass;
	}

	/**
	 * @param \Closure $service The service must implement INotifier, otherwise a
	 *                          \InvalidArgumentException is thrown later
	 * @param \Closure $info    An array with the keys 'id' and 'name' containing
	 *                          the app id and the app name
	 * @deprecated 17.0.0 use registerNotifierService instead.
	 * @since 8.2.0 - Parameter $info was added in 9.0.0
	 */
	public function registerNotifier(\Closure $service, \Closure $info) {
		$infoData = $info();
		$this->logger->logException(new \InvalidArgumentException(
			'Notifier ' . $infoData['name'] . ' (id: ' . $infoData['id'] . ') is not considered because it is using the old way to register.'
		));
	}

	/**
	 * @param string $notifierService The service must implement INotifier, otherwise a
	 *                          \InvalidArgumentException is thrown later
	 * @since 17.0.0
	 */
	public function registerNotifierService(string $notifierService): void {
		$this->notifierClasses[] = $notifierService;
	}

	/**
	 * @return IApp[]
	 */
	protected function getApps(): array {
		if (empty($this->appClasses)) {
			return $this->apps;
		}

		foreach ($this->appClasses as $appClass) {
			try {
				$app = \OC::$server->query($appClass);
			} catch (QueryException $e) {
				$this->logger->logException($e, [
					'message' => 'Failed to load notification app class: ' . $appClass,
					'app' => 'notifications',
				]);
				continue;
			}

			if (!($app instanceof IApp)) {
				$this->logger->error('Notification app class ' . $appClass . ' is not implementing ' . IApp::class, [
					'app' => 'notifications',
				]);
				continue;
			}

			$this->apps[] = $app;
		}

		$this->appClasses = [];

		return $this->apps;
	}

	/**
	 * @return INotifier[]
	 */
	public function getNotifiers(): array {
		if (empty($this->notifierClasses)) {
			return $this->notifiers;
		}

		foreach ($this->notifierClasses as $notifierClass) {
			try {
				$notifier = \OC::$server->query($notifierClass);
			} catch (QueryException $e) {
				$this->logger->logException($e, [
					'message' => 'Failed to load notification notifier class: ' . $notifierClass,
					'app' => 'notifications',
				]);
				continue;
			}

			if (!($notifier instanceof INotifier)) {
				$this->logger->error('Notification notifier class ' . $notifierClass . ' is not implementing ' . INotifier::class, [
					'app' => 'notifications',
				]);
				continue;
			}

			$this->notifiers[] = $notifier;
		}

		$this->notifierClasses = [];

		return $this->notifiers;
	}

	/**
	 * @return INotification
	 * @since 8.2.0
	 */
	public function createNotification(): INotification {
		return new Notification($this->validator);
	}

	/**
	 * @return bool
	 * @since 8.2.0
	 */
	public function hasNotifiers(): bool {
		return !empty($this->notifiers) || !empty($this->notifierClasses);
	}

	/**
	 * @param bool $preparingPushNotification
	 * @since 14.0.0
	 */
	public function setPreparingPushNotification(bool $preparingPushNotification): void {
		$this->preparingPushNotification = $preparingPushNotification;
	}

	/**
	 * @return bool
	 * @since 14.0.0
	 */
	public function isPreparingPushNotification(): bool {
		return $this->preparingPushNotification;
	}

	/**
	 * @param INotification $notification
	 * @throws \InvalidArgumentException When the notification is not valid
	 * @since 8.2.0
	 */
	public function notify(INotification $notification): void {
		if (!$notification->isValid()) {
			throw new \InvalidArgumentException('The given notification is invalid');
		}

		$apps = $this->getApps();

		foreach ($apps as $app) {
			try {
				$app->notify($notification);
			} catch (\InvalidArgumentException $e) {
			}
		}
	}

	/**
	 * Identifier of the notifier, only use [a-z0-9_]
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getID(): string {
		return 'core';
	}

	/**
	 * Human readable name describing the notifier
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getName(): string {
		return 'core';
	}

	/**
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 * @return INotification
	 * @throws \InvalidArgumentException When the notification was not prepared by a notifier
	 * @throws AlreadyProcessedException When the notification is not needed anymore and should be deleted
	 * @since 8.2.0
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
		$notifiers = $this->getNotifiers();

		foreach ($notifiers as $notifier) {
			try {
				$notification = $notifier->prepare($notification, $languageCode);
			} catch (\InvalidArgumentException $e) {
				continue;
			} catch (AlreadyProcessedException $e) {
				$this->markProcessed($notification);
				throw new \InvalidArgumentException('The given notification has been processed');
			}

			if (!($notification instanceof INotification) || !$notification->isValidParsed()) {
				throw new \InvalidArgumentException('The given notification has not been handled');
			}
		}

		if (!($notification instanceof INotification) || !$notification->isValidParsed()) {
			throw new \InvalidArgumentException('The given notification has not been handled');
		}

		return $notification;
	}

	/**
	 * @param INotification $notification
	 */
	public function markProcessed(INotification $notification): void {
		$apps = $this->getApps();

		foreach ($apps as $app) {
			$app->markProcessed($notification);
		}
	}

	/**
	 * @param INotification $notification
	 * @return int
	 */
	public function getCount(INotification $notification): int {
		$apps = $this->getApps();

		$count = 0;
		foreach ($apps as $app) {
			$count += $app->getCount($notification);
		}

		return $count;
	}

	public function dismissNotification(INotification $notification): void {
		$notifiers = $this->getNotifiers();

		foreach ($notifiers as $notifier) {
			if ($notifier instanceof IDismissableNotifier) {
				try {
					$notifier->dismissNotification($notification);
				} catch (\InvalidArgumentException $e) {
					continue;
				}
			}
		}
	}
}
