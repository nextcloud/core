<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\Federation\Middleware;

use OC\HintException;
use OCA\Federation\Controller\SettingsController;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Middleware;
use OCP\IL10N;
use OCP\ILogger;

class AddServerMiddleware extends Middleware {

	/** @var  string */
	protected $appName;

	/** @var  IL10N */
	protected $l;

	/** @var  ILogger */
	protected $logger;

	/**
	 * @param string $appName
	 * @param IL10N $l
	 * @param ILogger $logger
	 */
	public function __construct($appName, IL10N $l, ILogger $logger) {
		$this->appName = $appName;
		$this->l = $l;
		$this->logger = $logger;
	}

	/**
	 * Log error message and return a response which can be displayed to the user
	 *
	 * @param Controller $controller
	 * @param string $methodName
	 * @param \Exception $exception
	 * @return JSONResponse
	 * @throws \Exception
	 */
	public function afterException($controller, $methodName, \Exception $exception) {
		if (($controller instanceof SettingsController) === false) {
			throw $exception;
		}
		$this->logger->logException($exception, [
			'level' => ILogger::ERROR,
			'app' => $this->appName,
		]);
		if ($exception instanceof HintException) {
			$message = $exception->getHint();
		} else {
			$message = $exception->getMessage();
		}

		return new JSONResponse(
			['message' => $message],
			Http::STATUS_BAD_REQUEST
		);

	}

}
