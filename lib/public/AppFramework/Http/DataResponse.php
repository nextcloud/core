<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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
 * AppFramework\HTTP\DataResponse class
 */

namespace OCP\AppFramework\Http;

use OCP\AppFramework\Http;

/**
 * A generic DataResponse class that is used to return generic data responses
 * for responders to transform
 * @since 8.0.0
 */
class DataResponse extends Response {

	/**
	 * response data
	 * @var array|int|float|string|bool|object
	 */
	protected $data;


	/**
	 * @param array|int|float|string|bool|object $data the object or array that should be transformed
	 * @param int $statusCode the Http status code, defaults to 200
	 * @param array $headers additional key value based headers
	 * @since 8.0.0
	 */
	public function __construct($data = [], $statusCode = Http::STATUS_OK,
								array $headers = []) {
		parent::__construct();

		$this->data = $data;
		$this->setStatus($statusCode);
		$this->setHeaders(array_merge($this->getHeaders(), $headers));
	}


	/**
	 * Sets values in the data json array
	 * @param array|int|float|string|object $data an array or object which will be transformed
	 * @return DataResponse Reference to this object
	 * @since 8.0.0
	 */
	public function setData($data) {
		$this->data = $data;

		return $this;
	}


	/**
	 * Used to get the set parameters
	 * @return array|int|float|string|bool|object the data
	 * @since 8.0.0
	 */
	public function getData() {
		return $this->data;
	}
}
