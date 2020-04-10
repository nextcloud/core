<?php
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
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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
use OC\Server;
use OCA\Settings\Activity\Provider;
use OCA\Settings\Hooks;
use OCA\Settings\Mailer\NewUserMailHelper;
use OCA\Settings\Middleware\SubadminMiddleware;
use OCP\Activity\IManager as IActivityManager;
use OCP\AppFramework\App;
use OCP\Defaults;
use OCP\IContainer;
use OCP\IGroup;
use OCP\ILogger;
use OCP\IUser;
use OCP\Settings\IManager;
use OCP\Util;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class Application extends App {

	const APP_ID = 'settings';

	/**
	 * @param array $urlParams
	 */
	public function __construct(array $urlParams=[]) {
		parent::__construct(self::APP_ID, $urlParams);

		$container = $this->getContainer();

		// Register Middleware
		$container->registerAlias('SubadminMiddleware', SubadminMiddleware::class);
		$container->registerMiddleWare('SubadminMiddleware');

		/**
		 * Core class wrappers
		 */
		/** FIXME: Remove once OC_User is non-static and mockable */
		$container->registerService('isAdmin', function () {
			return \OC_User::isAdminUser(\OC_User::getUser());
		});
		/** FIXME: Remove once OC_SubAdmin is non-static and mockable */
		$container->registerService('isSubAdmin', function (IContainer $c) {
			$userObject = \OC::$server->getUserSession()->getUser();
			$isSubAdmin = false;
			if($userObject !== null) {
				$isSubAdmin = \OC::$server->getGroupManager()->getSubAdmin()->isSubAdmin($userObject);
			}
			return $isSubAdmin;
		});
		$container->registerService('userCertificateManager', function (IContainer $c) {
			return $c->query('ServerContainer')->getCertificateManager();
		}, false);
		$container->registerService('systemCertificateManager', function (IContainer $c) {
			return $c->query('ServerContainer')->getCertificateManager(null);
		}, false);
		$container->registerService(IProvider::class, function (IContainer $c) {
			return $c->query('ServerContainer')->query(IProvider::class);
		});
		$container->registerService(IManager::class, function (IContainer $c) {
			return $c->query('ServerContainer')->getSettingsManager();
		});

		$container->registerService(NewUserMailHelper::class, function (IContainer $c) {
			/** @var Server $server */
			$server = $c->query('ServerContainer');
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

		/** @var EventDispatcherInterface $eventDispatcher */
		$eventDispatcher = $container->getServer()->getEventDispatcher();
		$eventDispatcher->addListener('app_password_created', function (GenericEvent $event) use ($container) {
			if (($token = $event->getSubject()) instanceof IToken) {
				/** @var IActivityManager $activityManager */
				$activityManager = $container->query(IActivityManager::class);
				/** @var ILogger $logger */
				$logger = $container->query(ILogger::class);

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
	}

	public function register() {
		Util::connectHook('OC_User', 'post_setPassword', $this, 'onChangePassword');
		Util::connectHook('OC_User', 'changeUser', $this, 'onChangeInfo');

		$groupManager = $this->getContainer()->getServer()->getGroupManager();
		$groupManager->listen('\OC\Group', 'postRemoveUser',  [$this, 'removeUserFromGroup']);
		$groupManager->listen('\OC\Group', 'postAddUser',  [$this, 'addUserToGroup']);

		Util::connectHook('\OCP\Config', 'js', $this, 'extendJsConfig');
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

	/**
	 * @param array $settings
	 */
	public function extendJsConfig(array $settings) {
		$appConfig = json_decode($settings['array']['oc_appconfig'], true);

		$publicWebFinger = \OC::$server->getConfig()->getAppValue('core', 'public_webfinger', '');
		if (!empty($publicWebFinger)) {
			$appConfig['core']['public_webfinger'] = $publicWebFinger;
		}

		$publicNodeInfo = \OC::$server->getConfig()->getAppValue('core', 'public_nodeinfo', '');
		if (!empty($publicNodeInfo)) {
			$appConfig['core']['public_nodeinfo'] = $publicNodeInfo;
		}

		$settings['array']['oc_appconfig'] = json_encode($appConfig);
	}
}
