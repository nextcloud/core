<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
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

namespace OCP\AppFramework\Http;

/**
 * Prompts the user to download the a file
 * @since 7.0.0
 */
class DownloadResponse extends Response {

	private $filename;
	private $contentType;

	/**
	 * Creates a response that prompts the user to download the file
	 * @param string $filename the name that the downloaded file should have
	 * @param string $contentType the mimetype that the downloaded file should have
	 * @since 7.0.0
	 */
	public function __construct($filename, $contentType) {
		parent::__construct();

		$this->filename = $filename;
		$this->contentType = $contentType;

		$this->addHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
		$this->addHeader('Content-Type', $contentType);
	}


}
