<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
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

namespace OCA\DAV\Connector\Sabre\Exception;

use Sabre\DAV\Exception;

class InvalidPath extends Exception {

	const NS_OWNCLOUD = 'http://owncloud.org/ns';

	/**
	 * @var bool
	 */
	private $retry;

	/**
	 * @param string $message
	 * @param bool $retry
	 * @param \Exception|null $previous
	 */
	public function __construct($message, $retry = false, \Exception $previous = null) {
		parent::__construct($message, 0, $previous);
		$this->retry = $retry;
	}

	/**
	 * Returns the HTTP status code for this exception
	 *
	 * @return int
	 */
	public function getHTTPCode() {

		return 400;

	}

	/**
	 * This method allows the exception to include additional information
	 * into the WebDAV error response
	 *
	 * @param \Sabre\DAV\Server $server
	 * @param \DOMElement $errorNode
	 * @return void
	 */
	public function serialize(\Sabre\DAV\Server $server,\DOMElement $errorNode) {

		// set ownCloud namespace
		$errorNode->setAttribute('xmlns:o', self::NS_OWNCLOUD);

		// adding the retry node
		$error = $errorNode->ownerDocument->createElementNS('o:','o:retry', var_export($this->retry, true));
		$errorNode->appendChild($error);

		// adding the message node
		$error = $errorNode->ownerDocument->createElementNS('o:','o:reason', $this->getMessage());
		$errorNode->appendChild($error);
	}

}
