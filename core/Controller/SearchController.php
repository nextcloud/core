<?php

declare(strict_types=1);

/**
 * @copyright 2018, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\Core\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\ILogger;
use OCP\IRequest;
use OCP\ISearch;
use OCP\Search\Result;

class SearchController extends Controller {

	/** @var ISearch */
	private $searcher;
	/** @var ILogger */
	private $logger;

	public function __construct(
		string $appName,
		IRequest $request,
		ISearch $search,
		ILogger $logger
	) {
		parent::__construct($appName, $request);

		$this->searcher = $search;
		$this->logger = $logger;
	}

	/**
	 * @NoAdminRequired
	 */
	public function search(string $query, array $inApps = [], int $page = 1, int $size = 30): JSONResponse {
		$results = $this->searcher->searchPaged($query, $inApps, $page, $size);

		$results = array_filter($results, function (Result $result) {
			if (json_encode($result, JSON_HEX_TAG) === false) {
				$this->logger->warning("Skipping search result due to invalid encoding: {type: " . $result->type . ", id: " . $result->id . "}");
				return false;
			} else {
				return true;
			}
		});

		return new JSONResponse($results);
	}
}
