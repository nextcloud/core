<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Lukas Reschke <lukas@statuscode.ch>
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
namespace OCA\DAV\Files;

use OC\AppFramework\Http\Request;
use OC_Template;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\IRequest;
use Sabre\DAV\Exception;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;

class BrowserErrorPagePlugin extends ServerPlugin {
	/** @var Server */
	private $server;

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
		$server->on('exception', [$this, 'logException'], 1000);
	}

	/**
	 * @param IRequest $request
	 * @return bool
	 */
	public static function isBrowserRequest(IRequest $request) {
		if ($request->getMethod() !== 'GET') {
			return false;
		}
		return $request->isUserAgent([
			Request::USER_AGENT_MS_EDGE,
			Request::USER_AGENT_CHROME,
			Request::USER_AGENT_FIREFOX,
			Request::USER_AGENT_SAFARI,
		]);
	}

	/**
	 * @param \Exception $ex
	 */
	public function logException(\Exception $ex) {
		if ($ex instanceof Exception) {
			$httpCode = $ex->getHTTPCode();
			$headers = $ex->getHTTPHeaders($this->server);
		} else {
			$httpCode = 500;
			$headers = [];
		}
		$this->server->httpResponse->addHeaders($headers);
		$this->server->httpResponse->setStatus($httpCode);
		$body = $this->generateBody($httpCode);
		$this->server->httpResponse->setBody($body);
		$csp = new ContentSecurityPolicy();
		$this->server->httpResponse->addHeader('Content-Security-Policy', $csp->buildPolicy());
		$this->sendResponse();
	}

	/**
	 * @codeCoverageIgnore
	 * @return bool|string
	 */
	public function generateBody(int $httpCode) {
		$request = \OC::$server->getRequest();

		$templateName = 'exception';
		if ($httpCode === 403 || $httpCode === 404) {
			$templateName = (string)$httpCode;
		}

		$content = new OC_Template('core', $templateName, 'guest');
		$content->assign('title', $this->server->httpResponse->getStatusText());
		$content->assign('remoteAddr', $request->getRemoteAddress());
		$content->assign('requestID', $request->getId());
		return $content->fetchPage();
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function sendResponse() {
		$this->server->sapi->sendResponse($this->server->httpResponse);
		exit();
	}
}
