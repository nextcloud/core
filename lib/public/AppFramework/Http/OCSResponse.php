<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
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

/**
 * Public interface of ownCloud for apps to use.
 * AppFramework\HTTP\JSONResponse class
 */

namespace OCP\AppFramework\Http;

/**
 * A renderer for OCS responses
 * @since 8.1.0
 * @deprecated 9.2.0 To implement an OCS endpoint extend the OCSController
 */
class OCSResponse extends Response {

	private $data;
	private $format;
	private $statuscode;
	private $message;
	private $itemscount;
	private $itemsperpage;

	/**
	 * generates the xml or json response for the API call from an multidimenional data array.
	 * @param string $format
	 * @param int $statuscode
	 * @param string $message
	 * @param array $data
	 * @param int|string $itemscount
	 * @param int|string $itemsperpage
	 * @since 8.1.0
	 * @deprecated 9.2.0 To implement an OCS endpoint extend the OCSController
	 */
	public function __construct($format, $statuscode, $message,
								$data=[], $itemscount='',
								$itemsperpage='') {
		parent::__construct();

		$this->format = $format;
		$this->statuscode = $statuscode;
		$this->message = $message;
		$this->data = $data;
		$this->itemscount = $itemscount;
		$this->itemsperpage = $itemsperpage;

		// set the correct header based on the format parameter
		if ($format === 'json') {
			$this->addHeader(
				'Content-Type', 'application/json; charset=utf-8'
			);
		} else {
			$this->addHeader(
				'Content-Type', 'application/xml; charset=utf-8'
			);
		}
	}

	/**
	 * @return string
	 * @since 8.1.0
	 * @deprecated 9.2.0 To implement an OCS endpoint extend the OCSController
	 * @suppress PhanDeprecatedClass
	 */
	public function render() {
		$r = new \OC\OCS\Result($this->data, $this->statuscode, $this->message);
		$r->setTotalItems($this->itemscount);
		$r->setItemsPerPage($this->itemsperpage);

		return \OC_API::renderResult($this->format, $r->getMeta(), $r->getData());
	}


}
