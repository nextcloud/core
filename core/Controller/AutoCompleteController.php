<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2017 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OC\Core\Controller;

use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController as Controller;
use OCP\Collaboration\AutoComplete\AutoCompleteEvent;
use OCP\Collaboration\AutoComplete\IManager;
use OCP\Collaboration\Collaborators\ISearch;
use OCP\IRequest;
use OCP\Share;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AutoCompleteController extends Controller {
	/** @var ISearch */
	private $collaboratorSearch;
	/** @var IManager */
	private $autoCompleteManager;
	/** @var EventDispatcherInterface */
	private $dispatcher;

	public function __construct(
		string $appName,
		IRequest $request,
		ISearch $collaboratorSearch,
		IManager $autoCompleteManager,
		EventDispatcherInterface $dispatcher
	) {
		parent::__construct($appName, $request);

		$this->collaboratorSearch = $collaboratorSearch;
		$this->autoCompleteManager = $autoCompleteManager;
		$this->dispatcher = $dispatcher;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $search
	 * @param string $itemType
	 * @param string $itemId
	 * @param string|null $sorter can be piped, top prio first, e.g.: "commenters|share-recipients"
	 * @param array $shareTypes
	 * @param int $limit
	 * @return DataResponse
	 */
	public function get($search, $itemType, $itemId, $sorter = null, $shareTypes = [Share::SHARE_TYPE_USER], $limit = 10): DataResponse {
		// if enumeration/user listings are disabled, we'll receive an empty
		// result from search() – thus nothing else to do here.
		[$results,] = $this->collaboratorSearch->search($search, $shareTypes, false, $limit, 0);

		$event = new AutoCompleteEvent([
			'search' => $search,
			'results' => $results,
			'itemType' => $itemType,
			'itemId' => $itemId,
			'sorter' => $sorter,
			'shareTypes' => $shareTypes,
			'limit' => $limit,
		]);
		$this->dispatcher->dispatch(IManager::class . '::filterResults', $event);
		$results = $event->getResults();

		$exactMatches = $results['exact'];
		unset($results['exact']);
		$results = array_merge_recursive($exactMatches, $results);

		if($sorter !== null) {
			$sorters = array_reverse(explode('|', $sorter));
			$this->autoCompleteManager->runSorters($sorters, $results, [
				'itemType' => $itemType,
				'itemId' => $itemId,
			]);
		}

		// transform to expected format
		$results = $this->prepareResultArray($results);

		return new DataResponse($results);
	}


	protected function prepareResultArray(array $results): array {
		$output = [];
		foreach ($results as $type => $subResult) {
			foreach ($subResult as $result) {
				$output[] = [
					'id' => (string) $result['value']['shareWith'],
					'label' => $result['label'],
					'source' => $type,
				];
			}
		}
		return $output;
	}
}
