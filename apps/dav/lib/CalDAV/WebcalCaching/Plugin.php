<?php

declare(strict_types=1);

/**
 * @copyright 2018 Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
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

namespace OCA\DAV\CalDAV\WebcalCaching;

use OCA\DAV\CalDAV\CalendarHome;
use OCP\IRequest;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class Plugin extends ServerPlugin {

	/**
	 * list of regular expressions for calendar user agents,
	 * that do not support subscriptions on their own
	 *
	 * @var string[]
	 */
	public const ENABLE_FOR_CLIENTS = [];

	/**
	 * @var bool
	 */
	private $enabled = false;

	/**
	 * @var Server
	 */
	private $server;

	/**
	 * Plugin constructor.
	 *
	 * @param IRequest $request
	 */
	public function __construct(IRequest $request) {
		if ($request->isUserAgent(self::ENABLE_FOR_CLIENTS)) {
			$this->enabled = true;
		}

		$magicHeader = $request->getHeader('X-NC-CalDAV-Webcal-Caching');
		if ($magicHeader === 'On') {
			$this->enabled = true;
		}
	}

	/**
	 * This initializes the plugin.
	 *
	 * This function is called by Sabre\DAV\Server, after
	 * addPlugin is called.
	 *
	 * This method should set up the required event subscriptions.
	 *
	 * @param Server $server
	 */
	public function initialize(Server $server) {
		$this->server = $server;
		$server->on('beforeMethod:*', [$this, 'beforeMethod']);
	}

	/**
	 * @param RequestInterface $request
	 * @param ResponseInterface $response
	 */
	public function beforeMethod(RequestInterface $request, ResponseInterface $response) {
		if (!$this->enabled) {
			return;
		}

		$path = $request->getPath();
		$pathParts = explode('/', ltrim($path, '/'));
		if (\count($pathParts) < 2) {
			return;
		}

		// $calendarHomePath will look like: calendars/username
		$calendarHomePath = $pathParts[0] . '/' . $pathParts[1];
		try {
			$calendarHome = $this->server->tree->getNodeForPath($calendarHomePath);
			if (!($calendarHome instanceof CalendarHome)) {
				//how did we end up here?
				return;
			}

			$calendarHome->enableCachedSubscriptionsForThisRequest();
		} catch (NotFound $ex) {
			return;
		}
	}

	/**
	 * @return bool
	 */
	public function isCachingEnabledForThisRequest():bool {
		return $this->enabled;
	}

	/**
	 * This method should return a list of server-features.
	 *
	 * This is for example 'versioning' and is added to the DAV: header
	 * in an OPTIONS response.
	 *
	 * @return string[]
	 */
	public function getFeatures():array {
		return ['nc-calendar-webcal-cache'];
	}

	/**
	 * Returns a plugin name.
	 *
	 * Using this name other plugins will be able to access other plugins
	 * using Sabre\DAV\Server::getPlugin
	 *
	 * @return string
	 */
	public function getPluginName():string {
		return 'nc-calendar-webcal-cache';
	}
}
