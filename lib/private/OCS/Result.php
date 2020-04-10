<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christopher Schäpers <kondou@ts.unde.re>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Tom Needham <tom@owncloud.com>
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

namespace OC\OCS;

class Result {

	/** @var array  */
	protected $data;

	/** @var null|string */
	protected $message;

	/** @var int */
	protected $statusCode;

	/** @var integer */
	protected $items;

	/** @var integer */
	protected $perPage;

	/** @var array */
	private $headers = [];

	/**
	 * create the OCS_Result object
	 * @param mixed $data the data to return
	 * @param int $code
	 * @param null|string $message
	 * @param array $headers
	 */
	public function __construct($data = null, $code = 100, $message = null, $headers = []) {
		if ($data === null) {
			$this->data = [];
		} elseif (!is_array($data)) {
			$this->data = [$this->data];
		} else {
			$this->data = $data;
		}
		$this->statusCode = $code;
		$this->message = $message;
		$this->headers = $headers;
	}

	/**
	 * optionally set the total number of items available
	 * @param int $items
	 */
	public function setTotalItems($items) {
		$this->items = $items;
	}

	/**
	 * optionally set the the number of items per page
	 * @param int $items
	 */
	public function setItemsPerPage($items) {
		$this->perPage = $items;
	}

	/**
	 * get the status code
	 * @return int
	 */
	public function getStatusCode() {
		return $this->statusCode;
	}

	/**
	 * get the meta data for the result
	 * @return array
	 */
	public function getMeta() {
		$meta = [];
		$meta['status'] = $this->succeeded() ? 'ok' : 'failure';
		$meta['statuscode'] = $this->statusCode;
		$meta['message'] = $this->message;
		if(isset($this->items)) {
			$meta['totalitems'] = $this->items;
		}
		if(isset($this->perPage)) {
			$meta['itemsperpage'] = $this->perPage;
		}
		return $meta;

	}

	/**
	 * get the result data
	 * @return array
	 */
	public function getData() {
		return $this->data;
	}

	/**
	 * return bool Whether the method succeeded
	 * @return bool
	 */
	public function succeeded() {
		return ($this->statusCode == 100);
	}

	/**
	 * Adds a new header to the response
	 * @param string $name The name of the HTTP header
	 * @param string $value The value, null will delete it
	 * @return $this
	 */
	public function addHeader($name, $value) {
		$name = trim($name);  // always remove leading and trailing whitespace
		// to be able to reliably check for security
		// headers

		if(is_null($value)) {
			unset($this->headers[$name]);
		} else {
			$this->headers[$name] = $value;
		}

		return $this;
	}

	/**
	 * Returns the set headers
	 * @return array the headers
	 */
	public function getHeaders() {
		return $this->headers;
	}

}
