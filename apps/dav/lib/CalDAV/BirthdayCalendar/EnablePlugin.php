<?php
/**
 * @copyright 2017, Georg Ehrke <oc.list@georgehrke.com>
 *
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

namespace OCA\DAV\CalDAV\BirthdayCalendar;

use OCA\DAV\CalDAV\BirthdayService;
use OCA\DAV\CalDAV\CalendarHome;
use OCP\IConfig;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

/**
 * Class EnablePlugin
 * allows users to re-enable the birthday calendar via CalDAV
 *
 * @package OCA\DAV\CalDAV\BirthdayCalendar
 */
class EnablePlugin extends ServerPlugin {
	const NS_Nextcloud = 'http://nextcloud.com/ns';

	/**
	 * @var IConfig
	 */
	protected $config;

	/**
	 * @var BirthdayService
	 */
	protected $birthdayService;

	/**
	 * @var Server
	 */
	protected $server;

	/**
	 * PublishPlugin constructor.
	 *
	 * @param IConfig $config
	 * @param BirthdayService $birthdayService
	 */
	public function __construct(IConfig $config, BirthdayService $birthdayService) {
		$this->config = $config;
		$this->birthdayService = $birthdayService;
	}

	/**
	 * This method should return a list of server-features.
	 *
	 * This is for example 'versioning' and is added to the DAV: header
	 * in an OPTIONS response.
	 *
	 * @return string[]
	 */
	public function getFeatures() {
		return ['nc-enable-birthday-calendar'];
	}

	/**
	 * Returns a plugin name.
	 *
	 * Using this name other plugins will be able to access other plugins
	 * using Sabre\DAV\Server::getPlugin
	 *
	 * @return string
	 */
	public function getPluginName() {
		return 'nc-enable-birthday-calendar';
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

		$this->server->on('method:POST', [$this, 'httpPost']);
	}

	/**
	 * We intercept this to handle POST requests on calendar homes.
	 *
	 * @param RequestInterface $request
	 * @param ResponseInterface $response
	 *
	 * @return bool|void
	 */
	public function httpPost(RequestInterface $request, ResponseInterface $response) {
		$node = $this->server->tree->getNodeForPath($this->server->getRequestUri());
		if (!($node instanceof CalendarHome)) {
			return;
		}

		$requestBody = $request->getBodyAsString();
		$this->server->xml->parse($requestBody, $request->getUrl(), $documentType);
		if ($documentType !== '{'.self::NS_Nextcloud.'}enable-birthday-calendar') {
			return;
		}

		$principalUri = $node->getOwner();
		$userId = substr($principalUri, 17);

		$this->config->setUserValue($userId, 'dav', 'generateBirthdayCalendar', 'yes');
		$this->birthdayService->syncUser($userId);

		$this->server->httpResponse->setStatus(204);

		return false;
	}
}
