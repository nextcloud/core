<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
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

namespace OCP\AppFramework\Http;

/**
 * Class DataDownloadResponse
 *
 * @package OCP\AppFramework\Http
 * @since 8.0.0
 */
class DataDownloadResponse extends DownloadResponse {
	/**
	 * @var string
	 */
	private $data;

	/**
	 * Creates a response that prompts the user to download the text
	 * @param string $data text to be downloaded
	 * @param string $filename the name that the downloaded file should have
	 * @param string $contentType the mimetype that the downloaded file should have
	 * @since 8.0.0
	 */
	public function __construct($data, $filename, $contentType) {
		$this->data = $data;
		parent::__construct($filename, $contentType);
	}

	/**
	 * @param string $data
	 * @since 8.0.0
	 */
	public function setData($data) {
		$this->data = $data;
	}

	/**
	 * @return string
	 * @since 8.0.0
	 */
	public function render() {
		return $this->data;
	}
}
