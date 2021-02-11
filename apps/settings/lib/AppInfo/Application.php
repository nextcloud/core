<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author zulan <git@zulan.net>
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

namespace OCA\Settings\AppInfo;

use BadMethodCallException;
use OC\AppFramework\Utility\TimeFactory;
use OC\Authentication\Token\IProvider;
use OC\Authentication\Token\IToken;
use OC\Group\Manager;
use OC\Server;
use OCA\Settings\Activity\Provider;
use OCA\Settings\Hooks;
use OCA\Settings\Mailer\NewUserMailHelper;
use OCA\Settings\Middleware\SubadminMiddleware;
use OCA\Settings\Search\AppSearch;
use OCA\Settings\Search\SectionSearch;
use OCP\Activity\IManager as IActivityManager;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\IAppContainer;
use OCP\Defaults;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IServerContainer;
use OCP\IUser;
use OCP\Settings\IManager;
use OCP\Util;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'settings';

	/**
	 * @param array $urlParams
	 */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		// Register Middleware
		$context->registerServiceAlias('SubadminMiddleware', SubadminMiddleware::class);
		$context->registerMiddleware(SubadminMiddleware::class);
		$context->registerSearchProvider(SectionSearch::class);
		$context->registerSearchProvider(AppSearch::class);

		/**
		 * Core class wrappers
		 */
		/** FIXME: Remove once OC_User is non-static and mockable */
		$context->registerService('isAdmin', function () {
			return \OC_User::isAdminUser(\OC_User::getUser());
		});
		/** FIXME: Remove once OC_SubAdmin is non-static and mockable */
		$context->registerService('isSubAdmin', function () {
			$userObject = \OC::$server->getUserSession()->getUser();
			$isSubAdmin = false;
			if ($userObject !== null) {
				$isSubAdmin = \OC::$server->getGroupManager()->getSubAdmin()->isSubAdmin($userObject);
			}
			return $isSubAdmin;
		});
		$context->registerService(IProvider::class, function (IAppContainer $appContainer) {
			/** @var IServerContainer $serverContainer */
			$serverContainer = $appContainer->query(IServerContainer::class);
			return $serverContainer->query(IProvider::class);
		});
		$context->registerService(IManager::class, function (IAppContainer $appContainer) {
			/** @var IServerContainer $serverContainer */
			$serverContainer = $appContainer->query(IServerContainer::class);
			return $serverContainer->getSettingsManager();
		});

		$context->registerService(NewUserMailHelper::class, function (IAppContainer $appContainer) {
			/** @var Server $server */
			$server = $appContainer->query(IServerContainer::class);
			/** @var Defaults $defaults */
			$defaults = $server->query(Defaults::class);

			return new NewUserMailHelper(
				$defaults,
				$server->getURLGenerator(),
				$server->getL10NFactory(),
				$server->getMailer(),
				$server->getSecureRandom(),
				new TimeFactory(),
				$server->getConfig(),
				$server->getCrypto(),
				Util::getDefaultEmailAddress('no-reply')
			);
		});
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(function (EventDispatcherInterface $dispatcher, IAppContainer $appContainer) {
			$dispatcher->addListener('app_password_created', function (GenericEvent $event) use ($appContainer) {
				if (($token = $event->getSubject()) instanceof IToken) {
					/** @var IActivityManager $activityManager */
					$activityManager = $appContainer->get(IActivityManager::class);
					/** @var ILogger $logger */
					$logger = $appContainer->get(ILogger::class);

					$activity = $activityManager->generateEvent();
					$activity->setApp('settings')
						->setType('security')
						->setAffectedUser($token->getUID())
						->setAuthor($token->getUID())
						->setSubject(Provider::APP_TOKEN_CREATED, ['name' => $token->getName()])
						->setObject('app_token', $token->getId());

					try {
						$activityManager->publish($activity);
					} catch (BadMethodCallException $e) {
						$logger->logException($e, ['message' => 'could not publish activity', 'level' => ILogger::WARN]);
					}
				}
			});
		});

		Util::connectHook('OC_User', 'post_setPassword', $this, 'onChangePassword');
		Util::connectHook('OC_User', 'changeUser', $this, 'onChangeInfo');

		$context->injectFn(function (IGroupManager $groupManager) {
			/** @var IGroupManager|Manager $groupManager */
			$groupManager->listen('\OC\Group', 'postRemoveUser',  [$this, 'removeUserFromGroup']);
			$groupManager->listen('\OC\Group', 'postAddUser',  [$this, 'addUserToGroup']);
		});
	}

	public function addUserToGroup(IGroup $group, IUser $user): void {
		/** @var Hooks $hooks */
		$hooks = $this->getContainer()->query(Hooks::class);
		$hooks->addUserToGroup($group, $user);
	}

	public function removeUserFromGroup(IGroup $group, IUser $user): void {
		/** @var Hooks $hooks */
		$hooks = $this->getContainer()->query(Hooks::class);
		$hooks->removeUserFromGroup($group, $user);
	}


	/**
	 * @param array $parameters
	 * @throws \InvalidArgumentException
	 * @throws \BadMethodCallException
	 * @throws \Exception
	 * @throws \OCP\AppFramework\QueryException
	 */
	public function onChangePassword(array $parameters) {
		/** @var Hooks $hooks */
		$hooks = $this->getContainer()->query(Hooks::class);
		$hooks->onChangePassword($parameters['uid']);
	}

	/**
	 * @param array $parameters
	 * @throws \InvalidArgumentException
	 * @throws \BadMethodCallException
	 * @throws \Exception
	 * @throws \OCP\AppFramework\QueryException
	 */
	public function onChangeInfo(array $parameters) {
		if ($parameters['feature'] !== 'eMailAddress') {
			return;
		}

		/** @var Hooks $hooks */
		$hooks = $this->getContainer()->query(Hooks::class);
		$hooks->onChangeEmail($parameters['user'], $parameters['old_value']);
	}
}
