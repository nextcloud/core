<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCA\DAV\DAV\Sharing;

use OCA\DAV\Connector\Sabre\Auth;
use OCA\DAV\DAV\Sharing\Xml\Invite;
use OCA\DAV\DAV\Sharing\Xml\ShareRequest;
use OCP\IConfig;
use OCP\IRequest;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\INode;
use Sabre\DAV\PropFind;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class Plugin extends ServerPlugin {
	public const NS_OWNCLOUD = 'http://owncloud.org/ns';
	public const NS_NEXTCLOUD = 'http://nextcloud.com/ns';

	/** @var Auth */
	private $auth;

	/** @var IRequest */
	private $request;

	/** @var IConfig */
	private $config;

	/**
	 * Plugin constructor.
	 *
	 * @param Auth $authBackEnd
	 * @param IRequest $request
	 * @param IConfig $config
	 */
	public function __construct(Auth $authBackEnd, IRequest $request, IConfig $config) {
		$this->auth = $authBackEnd;
		$this->request = $request;
		$this->config = $config;
	}

	/**
	 * Reference to SabreDAV server object.
	 *
	 * @var \Sabre\DAV\Server
	 */
	protected $server;

	/**
	 * This method should return a list of server-features.
	 *
	 * This is for example 'versioning' and is added to the DAV: header
	 * in an OPTIONS response.
	 *
	 * @return string[]
	 */
	public function getFeatures() {
		return ['oc-resource-sharing'];
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
		return 'oc-resource-sharing';
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
	 * @return void
	 */
	public function initialize(Server $server) {
		$this->server = $server;
		$this->server->xml->elementMap['{' . Plugin::NS_OWNCLOUD . '}share'] = ShareRequest::class;
		$this->server->xml->elementMap['{' . Plugin::NS_OWNCLOUD . '}invite'] = Invite::class;

		$this->server->on('method:POST', [$this, 'httpPost']);
		$this->server->on('propFind',    [$this, 'propFind']);
	}

	/**
	 * We intercept this to handle POST requests on a dav resource.
	 *
	 * @param RequestInterface $request
	 * @param ResponseInterface $response
	 * @return null|false
	 */
	public function httpPost(RequestInterface $request, ResponseInterface $response) {
		$path = $request->getPath();

		// Only handling xml
		$contentType = $request->getHeader('Content-Type');
		if (strpos($contentType, 'application/xml') === false && strpos($contentType, 'text/xml') === false) {
			return;
		}

		// Making sure the node exists
		try {
			$node = $this->server->tree->getNodeForPath($path);
		} catch (NotFound $e) {
			return;
		}

		$requestBody = $request->getBodyAsString();

		// If this request handler could not deal with this POST request, it
		// will return 'null' and other plugins get a chance to handle the
		// request.
		//
		// However, we already requested the full body. This is a problem,
		// because a body can only be read once. This is why we preemptively
		// re-populated the request body with the existing data.
		$request->setBody($requestBody);

		$message = $this->server->xml->parse($requestBody, $request->getUrl(), $documentType);

		switch ($documentType) {

			// Dealing with the 'share' document, which modified invitees on a
			// calendar.
			case '{' . self::NS_OWNCLOUD . '}share':

				// We can only deal with IShareableCalendar objects
				if (!$node instanceof IShareable) {
					return;
				}

				$this->server->transactionType = 'post-oc-resource-share';

				// Getting ACL info
				$acl = $this->server->getPlugin('acl');

				// If there's no ACL support, we allow everything
				if ($acl) {
					/** @var \Sabre\DAVACL\Plugin $acl */
					$acl->checkPrivileges($path, '{DAV:}write');

					$limitSharingToOwner = $this->config->getAppValue('dav', 'limitAddressBookAndCalendarSharingToOwner', 'no') === 'yes';
					$isOwner = $acl->getCurrentUserPrincipal() === $node->getOwner();
					if ($limitSharingToOwner && !$isOwner) {
						return;
					}
				}

				$node->updateShares($message->set, $message->remove);

				$response->setStatus(200);
				// Adding this because sending a response body may cause issues,
				// and I wanted some type of indicator the response was handled.
				$response->setHeader('X-Sabre-Status', 'everything-went-well');

				// Breaking the event chain
				return false;
		}
	}

	/**
	 * This event is triggered when properties are requested for a certain
	 * node.
	 *
	 * This allows us to inject any properties early.
	 *
	 * @param PropFind $propFind
	 * @param INode $node
	 * @return void
	 */
	public function propFind(PropFind $propFind, INode $node) {
		if ($node instanceof IShareable) {
			$propFind->handle('{' . Plugin::NS_OWNCLOUD . '}invite', function () use ($node) {
				return new Invite(
					$node->getShares()
				);
			});
		}
	}
}
