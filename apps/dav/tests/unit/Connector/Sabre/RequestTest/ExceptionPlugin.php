<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Robin Appelman <robin@icewind.nl>
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

class ExceptionPlugin extends \OCA\DAV\Connector\Sabre\ExceptionLoggerPlugin {
	/**
	 * @var \Exception[]
	 */
	protected $exceptions = [];

	public function logException(\Exception $ex) {
		$exceptionClass = get_class($ex);
		if (!isset($this->nonFatalExceptions[$exceptionClass])) {
			$this->exceptions[] = $ex;
		}
	}

	/**
	 * @return \Exception[]
	 */
	public function getExceptions() {
		return $this->exceptions;
	}
}
