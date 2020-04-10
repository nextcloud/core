<?php
/**
 * @copyright 2016 Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OC\AppFramework\OCS;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;

abstract class BaseResponse extends Response   {
	/** @var array */
	protected $data;

	/** @var string */
	protected $format;

	/** @var string */
	protected $statusMessage;

	/** @var int */
	protected $itemsCount;

	/** @var int */
	protected $itemsPerPage;

	/**
	 * BaseResponse constructor.
	 *
	 * @param DataResponse $dataResponse
	 * @param string $format
	 * @param string|null $statusMessage
	 * @param int|null $itemsCount
	 * @param int|null $itemsPerPage
	 */
	public function __construct(DataResponse $dataResponse,
								$format = 'xml',
								$statusMessage = null,
								$itemsCount = null,
								$itemsPerPage = null) {
		parent::__construct();

		$this->format = $format;
		$this->statusMessage = $statusMessage;
		$this->itemsCount = $itemsCount;
		$this->itemsPerPage = $itemsPerPage;

		$this->data = $dataResponse->getData();

		$this->setHeaders($dataResponse->getHeaders());
		$this->setStatus($dataResponse->getStatus());
		$this->setETag($dataResponse->getETag());
		$this->setLastModified($dataResponse->getLastModified());
		$this->setCookies($dataResponse->getCookies());

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
	 * @param string[] $meta
	 * @return string
	 */
	protected function renderResult(array $meta): string {
		$status = $this->getStatus();
		if ($status === Http::STATUS_NO_CONTENT ||
			$status === Http::STATUS_NOT_MODIFIED ||
			($status >= 100 && $status <= 199)) {
			// Those status codes are not supposed to have a body:
			// https://stackoverflow.com/q/8628725
			return '';
		}

		$response = [
			'ocs' => [
				'meta' => $meta,
				'data' => $this->data,
			],
		];

		if ($this->format === 'json') {
			return json_encode($response, JSON_HEX_TAG);
		}

		$writer = new \XMLWriter();
		$writer->openMemory();
		$writer->setIndent(true);
		$writer->startDocument();
		$this->toXML($response, $writer);
		$writer->endDocument();
		return $writer->outputMemory(true);

	}

	/**
	 * @param array $array
	 * @param \XMLWriter $writer
	 */
	protected function toXML(array $array, \XMLWriter $writer) {
		foreach ($array as $k => $v) {
			if (\is_string($k) && strpos($k, '@') === 0) {
				$writer->writeAttribute(substr($k, 1), $v);
				continue;
			}

			if (\is_numeric($k)) {
				$k = 'element';
			}

			if (\is_array($v)) {
				$writer->startElement($k);
				$this->toXML($v, $writer);
				$writer->endElement();
			} else {
				$writer->writeElement($k, $v);
			}
		}
	}

	public function getOCSStatus() {
		return parent::getStatus();
	}
}
