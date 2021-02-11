<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Michael Gapczynski <GapczynskiM@gmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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
use OCP\API;
use OCP\AppFramework\Http;

class OC_API {

	/**
	 * api actions
	 */
	protected static $actions = [];

	/**
	 * respond to a call
	 * @param \OC\OCS\Result $result
	 * @param string $format the format xml|json
	 * @psalm-taint-escape html
	 */
	public static function respond($result, $format = 'xml') {
		$request = \OC::$server->getRequest();

		// Send 401 headers if unauthorised
		if ($result->getStatusCode() === API::RESPOND_UNAUTHORISED) {
			// If request comes from JS return dummy auth request
			if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				header('WWW-Authenticate: DummyBasic realm="Authorisation Required"');
			} else {
				header('WWW-Authenticate: Basic realm="Authorisation Required"');
			}
			http_response_code(401);
		}

		foreach ($result->getHeaders() as $name => $value) {
			header($name . ': ' . $value);
		}

		$meta = $result->getMeta();
		$data = $result->getData();
		if (self::isV2($request)) {
			$statusCode = self::mapStatusCodes($result->getStatusCode());
			if (!is_null($statusCode)) {
				$meta['statuscode'] = $statusCode;
				http_response_code($statusCode);
			}
		}

		self::setContentType($format);
		$body = self::renderResult($format, $meta, $data);
		echo $body;
	}

	/**
	 * @param XMLWriter $writer
	 */
	private static function toXML($array, $writer) {
		foreach ($array as $k => $v) {
			if ($k[0] === '@') {
				$writer->writeAttribute(substr($k, 1), $v);
				continue;
			} elseif (is_numeric($k)) {
				$k = 'element';
			}
			if (is_array($v)) {
				$writer->startElement($k);
				self::toXML($v, $writer);
				$writer->endElement();
			} else {
				$writer->writeElement($k, $v);
			}
		}
	}

	/**
	 * @return string
	 */
	public static function requestedFormat() {
		$formats = ['json', 'xml'];

		$format = !empty($_GET['format']) && in_array($_GET['format'], $formats) ? $_GET['format'] : 'xml';
		return $format;
	}

	/**
	 * Based on the requested format the response content type is set
	 * @param string $format
	 */
	public static function setContentType($format = null) {
		$format = is_null($format) ? self::requestedFormat() : $format;
		if ($format === 'xml') {
			header('Content-type: text/xml; charset=UTF-8');
			return;
		}

		if ($format === 'json') {
			header('Content-Type: application/json; charset=utf-8');
			return;
		}

		header('Content-Type: application/octet-stream; charset=utf-8');
	}

	/**
	 * @param \OCP\IRequest $request
	 * @return bool
	 */
	protected static function isV2(\OCP\IRequest $request) {
		$script = $request->getScriptName();

		return substr($script, -11) === '/ocs/v2.php';
	}

	/**
	 * @param integer $sc
	 * @return int
	 */
	public static function mapStatusCodes($sc) {
		switch ($sc) {
			case API::RESPOND_NOT_FOUND:
				return Http::STATUS_NOT_FOUND;
			case API::RESPOND_SERVER_ERROR:
				return Http::STATUS_INTERNAL_SERVER_ERROR;
			case API::RESPOND_UNKNOWN_ERROR:
				return Http::STATUS_INTERNAL_SERVER_ERROR;
			case API::RESPOND_UNAUTHORISED:
				// already handled for v1
				return null;
			case 100:
				return Http::STATUS_OK;
		}
		// any 2xx, 4xx and 5xx will be used as is
		if ($sc >= 200 && $sc < 600) {
			return $sc;
		}

		return Http::STATUS_BAD_REQUEST;
	}

	/**
	 * @param string $format
	 * @return string
	 */
	public static function renderResult($format, $meta, $data) {
		$response = [
			'ocs' => [
				'meta' => $meta,
				'data' => $data,
			],
		];
		if ($format == 'json') {
			return OC_JSON::encode($response);
		}

		$writer = new XMLWriter();
		$writer->openMemory();
		$writer->setIndent(true);
		$writer->startDocument();
		self::toXML($response, $writer);
		$writer->endDocument();
		return $writer->outputMemory(true);
	}
}
