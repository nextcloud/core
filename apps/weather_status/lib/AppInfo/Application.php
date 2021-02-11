<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Julien Veyssier
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
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

namespace OCA\WeatherStatus\AppInfo;

use OCA\WeatherStatus\Capabilities;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Dashboard\RegisterWidgetEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\Event;
use OCP\Util;

/**
 * Class Application
 *
 * @package OCA\WeatherStatus\AppInfo
 */
class Application extends App implements IBootstrap {

	/** @var string */
	public const APP_ID = 'weather_status';

	/**
	 * Application constructor.
	 *
	 * @param array $urlParams
	 */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$dispatcher = $this->getContainer()->query(IEventDispatcher::class);
		$dispatcher->addListener(RegisterWidgetEvent::class, function (Event $e) {
			Util::addScript(self::APP_ID, 'weather-status');
		});
	}

	/**
	 * @inheritDoc
	 */
	public function register(IRegistrationContext $context): void {
		// Register OCS Capabilities
		$context->registerCapability(Capabilities::class);
	}

	public function boot(IBootContext $context): void {
	}
}
