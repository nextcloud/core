<?php
/**
 * @copyright 2016 Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCP\AppFramework\Http;

use OCP\AppFramework\Http;

/**
 * Class FileDisplayResponse
 *
 * @package OCP\AppFramework\Http
 * @since 11.0.0
 */
class FileDisplayResponse extends Response implements ICallbackResponse {

	/** @var \OCP\Files\File|\OCP\Files\SimpleFS\ISimpleFile */
	private $file;

	/**
	 * FileDisplayResponse constructor.
	 *
	 * @param \OCP\Files\File|\OCP\Files\SimpleFS\ISimpleFile $file
	 * @param int $statusCode
	 * @param array $headers
	 * @since 11.0.0
	 */
	public function __construct($file, $statusCode=Http::STATUS_OK,
								$headers=[]) {
		parent::__construct();

		$this->file = $file;
		$this->setStatus($statusCode);
		$this->setHeaders(array_merge($this->getHeaders(), $headers));
		$this->addHeader('Content-Disposition', 'inline; filename="' . rawurldecode($file->getName()) . '"');

		$this->setETag($file->getEtag());
		$lastModified = new \DateTime();
		$lastModified->setTimestamp($file->getMTime());
		$this->setLastModified($lastModified);
	}

	/**
	 * @param IOutput $output
	 * @since 11.0.0
	 */
	public function callback(IOutput $output) {
		if ($output->getHttpResponseCode() !== Http::STATUS_NOT_MODIFIED) {
			$output->setHeader('Content-Length: ' . $this->file->getSize());
			$output->setOutput($this->file->getContent());
		}
	}
}
