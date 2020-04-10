<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\DAV\Tests\unit\Connector\Sabre\RequestTest;

use OC\Files\View;
use OCA\DAV\Connector\Sabre\Server;
use OCA\DAV\Connector\Sabre\ServerFactory;
use OCP\IRequest;
use Sabre\HTTP\Request;
use Test\TestCase;
use Test\Traits\MountProviderTrait;
use Test\Traits\UserTrait;

abstract class RequestTestCase extends TestCase {
	use UserTrait;
	use MountProviderTrait;

	/**
	 * @var \OCA\DAV\Connector\Sabre\ServerFactory
	 */
	protected $serverFactory;

	protected function getStream($string) {
		$stream = fopen('php://temp', 'r+');
		fwrite($stream, $string);
		fseek($stream, 0);
		return $stream;
	}

	protected function setUp(): void {
		parent::setUp();

		unset($_SERVER['HTTP_OC_CHUNKED']);

		$this->serverFactory = new ServerFactory(
			\OC::$server->getConfig(),
			\OC::$server->getLogger(),
			\OC::$server->getDatabaseConnection(),
			\OC::$server->getUserSession(),
			\OC::$server->getMountManager(),
			\OC::$server->getTagManager(),
			$this->getMockBuilder(IRequest::class)
				->disableOriginalConstructor()
				->getMock(),
			\OC::$server->getPreviewManager(),
			\OC::$server->getEventDispatcher()
		);
	}

	protected function setupUser($name, $password) {
		$this->createUser($name, $password);
		$tmpFolder = \OC::$server->getTempManager()->getTemporaryFolder();
		$this->registerMount($name, '\OC\Files\Storage\Local', '/' . $name, ['datadir' => $tmpFolder]);
		$this->loginAsUser($name);
		return new View('/' . $name . '/files');
	}

	/**
	 * @param \OC\Files\View $view the view to run the webdav server against
	 * @param string $user
	 * @param string $password
	 * @param string $method
	 * @param string $url
	 * @param resource|string|null $body
	 * @param array|null $headers
	 * @return \Sabre\HTTP\Response
	 * @throws \Exception
	 */
	protected function request($view, $user, $password, $method, $url, $body = null, $headers = []) {
		if (is_string($body)) {
			$body = $this->getStream($body);
		}
		$this->logout();
		$exceptionPlugin = new ExceptionPlugin('webdav', null);
		$server = $this->getSabreServer($view, $user, $password, $exceptionPlugin);
		$request = new Request($method, $url, $headers, $body);

		// since sabre catches all exceptions we need to save them and throw them from outside the sabre server

		$originalServer = $_SERVER;

		if (is_array($headers)) {
			foreach ($headers as $header => $value) {
				$_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $header))] = $value;
			}
		}

		$result = $this->makeRequest($server, $request);

		foreach ($exceptionPlugin->getExceptions() as $exception) {
			throw $exception;
		}
		$_SERVER = $originalServer;
		return $result;
	}

	/**
	 * @param Server $server
	 * @param Request $request
	 * @return \Sabre\HTTP\Response
	 */
	protected function makeRequest(Server $server, Request $request) {
		$sapi = new Sapi($request);
		$server->sapi = $sapi;
		$server->httpRequest = $request;
		$server->exec();
		return $sapi->getResponse();
	}

	/**
	 * @param View $view
	 * @param string $user
	 * @param string $password
	 * @param ExceptionPlugin $exceptionPlugin
	 * @return Server
	 */
	protected function getSabreServer(View $view, $user, $password, ExceptionPlugin $exceptionPlugin) {
		$authBackend = new Auth($user, $password);
		$authPlugin = new \Sabre\DAV\Auth\Plugin($authBackend);

		$server = $this->serverFactory->createServer('/', 'dummy', $authPlugin, function () use ($view) {
			return $view;
		});
		$server->addPlugin($exceptionPlugin);

		return $server;
	}
}
