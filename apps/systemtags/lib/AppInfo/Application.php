<?php

declare(strict_types=1);

/**
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCA\SystemTags\AppInfo;

use OCA\SystemTags\Activity\Listener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\SystemTag\ManagerEvent;
use OCP\SystemTag\MapperEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;

class Application extends App implements IBootstrap {
	public const APP_ID = 'systemtags';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(function (EventDispatcher $dispatcher) use ($context) {
			/*
			 * @todo move the OCP events and then move the registration to `register`
			 */
			$dispatcher->addListener(
				'OCA\Files::loadAdditionalScripts',
				function () {
					// FIXME: no public API for these ?
					\OCP\Util::addScript('dist/systemtags');
					\OCP\Util::addScript(self::APP_ID, 'systemtags');
				}
			);

			$managerListener = function (ManagerEvent $event) use ($context) {
				/** @var \OCA\SystemTags\Activity\Listener $listener */
				$listener = $context->getServerContainer()->query(Listener::class);
				$listener->event($event);
			};
			$dispatcher->addListener(ManagerEvent::EVENT_CREATE, $managerListener);
			$dispatcher->addListener(ManagerEvent::EVENT_DELETE, $managerListener);
			$dispatcher->addListener(ManagerEvent::EVENT_UPDATE, $managerListener);

			$mapperListener = function (MapperEvent $event) use ($context) {
				/** @var \OCA\SystemTags\Activity\Listener $listener */
				$listener = $context->getServerContainer()->query(Listener::class);
				$listener->mapperEvent($event);
			};
			$dispatcher->addListener(MapperEvent::EVENT_ASSIGN, $mapperListener);
			$dispatcher->addListener(MapperEvent::EVENT_UNASSIGN, $mapperListener);
		});

		\OCA\Files\App::getNavigationManager()->add(function () {
			$l = \OC::$server->getL10N(self::APP_ID);
			return [
				'id' => 'systemtagsfilter',
				'appname' => self::APP_ID,
				'script' => 'list.php',
				'order' => 25,
				'name' => $l->t('Tags'),
			];
		});
	}
}
