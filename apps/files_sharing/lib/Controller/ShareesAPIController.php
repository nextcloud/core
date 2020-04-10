<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Maxence Lange <maxence@nextcloud.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCA\Files_Sharing\Controller;

use function array_slice;
use function array_values;
use Generator;
use OC\Collaboration\Collaborators\SearchResult;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCSController;
use OCP\Collaboration\Collaborators\ISearch;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\Collaboration\Collaborators\SearchResultType;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Share;
use OCP\Share\IManager;
use function usort;

class ShareesAPIController extends OCSController {

	/** @var userId */
	protected $userId;

	/** @var IConfig */
	protected $config;

	/** @var IURLGenerator */
	protected $urlGenerator;

	/** @var IManager */
	protected $shareManager;

	/** @var int */
	protected $offset = 0;

	/** @var int */
	protected $limit = 10;

	/** @var array */
	protected $result = [
		'exact' => [
			'users' => [],
			'groups' => [],
			'remotes' => [],
			'remote_groups' => [],
			'emails' => [],
			'circles' => [],
			'rooms' => [],
		],
		'users' => [],
		'groups' => [],
		'remotes' => [],
		'remote_groups' => [],
		'emails' => [],
		'lookup' => [],
		'circles' => [],
		'rooms' => [],
		'lookupEnabled' => false,
	];

	protected $reachedEndFor = [];
	/** @var ISearch */
	private $collaboratorSearch;

	/**
	 * @param string $UserId
	 * @param string $appName
	 * @param IRequest $request
	 * @param IConfig $config
	 * @param IURLGenerator $urlGenerator
	 * @param IManager $shareManager
	 * @param ISearch $collaboratorSearch
	 */
	public function __construct(
		$UserId,
		string $appName,
		IRequest $request,
		IConfig $config,
		IURLGenerator $urlGenerator,
		IManager $shareManager,
		ISearch $collaboratorSearch
	) {
		parent::__construct($appName, $request);
		$this->userId = $UserId;
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
		$this->shareManager = $shareManager;
		$this->collaboratorSearch = $collaboratorSearch;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $search
	 * @param string $itemType
	 * @param int $page
	 * @param int $perPage
	 * @param int|int[] $shareType
	 * @param bool $lookup
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 */
	public function search(string $search = '', string $itemType = null, int $page = 1, int $perPage = 200, $shareType = null, bool $lookup = true): DataResponse {

		// only search for string larger than a given threshold
		$threshold = (int)$this->config->getSystemValue('sharing.minSearchStringLength', 0);
		if (strlen($search) < $threshold) {
			return new DataResponse($this->result);
		}

		// never return more than the max. number of results configured in the config.php
		$maxResults = (int)$this->config->getSystemValue('sharing.maxAutocompleteResults', 0);
		if ($maxResults > 0) {
			$perPage = min($perPage, $maxResults);
		}
		if ($perPage <= 0) {
			throw new OCSBadRequestException('Invalid perPage argument');
		}
		if ($page <= 0) {
			throw new OCSBadRequestException('Invalid page');
		}

		$shareTypes = [
			Share::SHARE_TYPE_USER,
		];

		if ($itemType === null) {
			throw new OCSBadRequestException('Missing itemType');
		} elseif ($itemType === 'file' || $itemType === 'folder') {
			if ($this->shareManager->allowGroupSharing()) {
				$shareTypes[] = Share::SHARE_TYPE_GROUP;
			}

			if ($this->isRemoteSharingAllowed($itemType)) {
				$shareTypes[] = Share::SHARE_TYPE_REMOTE;
			}

			if ($this->isRemoteGroupSharingAllowed($itemType)) {
				$shareTypes[] = Share::SHARE_TYPE_REMOTE_GROUP;
			}

			if ($this->shareManager->shareProviderExists(Share::SHARE_TYPE_EMAIL)) {
				$shareTypes[] = Share::SHARE_TYPE_EMAIL;
			}

			if ($this->shareManager->shareProviderExists(Share::SHARE_TYPE_ROOM)) {
				$shareTypes[] = Share::SHARE_TYPE_ROOM;
			}
		} else {
			$shareTypes[] = Share::SHARE_TYPE_GROUP;
			$shareTypes[] = Share::SHARE_TYPE_EMAIL;
		}

		// FIXME: DI
		if (\OC::$server->getAppManager()->isEnabledForUser('circles') && class_exists('\OCA\Circles\ShareByCircleProvider')) {
			$shareTypes[] = Share::SHARE_TYPE_CIRCLE;
		}

		if ($shareType !== null && is_array($shareType)) {
			$shareTypes = array_intersect($shareTypes, $shareType);
		} else if (is_numeric($shareType)) {
			$shareTypes = array_intersect($shareTypes, [(int) $shareType]);
		}
		sort($shareTypes);

		$this->limit = (int) $perPage;
		$this->offset = $perPage * ($page - 1);

		// In global scale mode we always search the loogup server
		if ($this->config->getSystemValueBool('gs.enabled', false)) {
			$lookup = true;
			$this->result['lookupEnabled'] = true;
		} else {
			$this->result['lookupEnabled'] = $this->config->getAppValue('files_sharing', 'lookupServerEnabled', 'yes') === 'yes';
		}

		list($result, $hasMoreResults) = $this->collaboratorSearch->search($search, $shareTypes, $lookup, $this->limit, $this->offset);

		// extra treatment for 'exact' subarray, with a single merge expected keys might be lost
		if(isset($result['exact'])) {
			$result['exact'] = array_merge($this->result['exact'], $result['exact']);
		}
		$this->result = array_merge($this->result, $result);
		$response = new DataResponse($this->result);

		if ($hasMoreResults) {
			$response->addHeader('Link', $this->getPaginationLink($page, [
				'search' => $search,
				'itemType' => $itemType,
				'shareType' => $shareTypes,
				'perPage' => $perPage,
			]));
		}

		return $response;
	}

	/**
	 * @param string $user
	 * @param int $shareType
	 *
	 * @return Generator<array<string>>
	 */
	private function getAllShareesByType(string $user, int $shareType): Generator {
		$offset = 0;
		$pageSize = 50;

		while (count($page = $this->shareManager->getSharesBy(
			$user,
			$shareType,
			null,
			false,
			$pageSize,
			$offset
		))) {
			foreach ($page as $share) {
				yield [$share->getSharedWith(), $share->getSharedWithDisplayName() ?? $share->getSharedWith()];
			}

			$offset += $pageSize;
		}
	}

	private function sortShareesByFrequency(array $sharees): array {
		usort($sharees, function (array $s1, array $s2) {
			return $s2['count'] - $s1['count'];
		});
		return $sharees;
	}

	private $searchResultTypeMap = [
		Share::SHARE_TYPE_USER => 'users',
		Share::SHARE_TYPE_GROUP => 'groups',
		Share::SHARE_TYPE_REMOTE => 'remotes',
		Share::SHARE_TYPE_REMOTE_GROUP => 'remote_groups',
		Share::SHARE_TYPE_EMAIL => 'emails',
	];

	private function getAllSharees(string $user, array $shareTypes): ISearchResult {
		$result = [];
		foreach ($shareTypes as $shareType) {
			$sharees = $this->getAllShareesByType($user, $shareType);
			$shareTypeResults = [];
			foreach ($sharees as list($sharee, $displayname)) {
				if (!isset($this->searchResultTypeMap[$shareType])) {
					continue;
				}

				if (!isset($shareTypeResults[$sharee])) {
					$shareTypeResults[$sharee] = [
						'count' => 1,
						'label' => $displayname,
						'value' => [
							'shareType' => $shareType,
							'shareWith' => $sharee,
						],
					];
				} else {
					$shareTypeResults[$sharee]['count']++;
				}
			}
			$result = array_merge($result, array_values($shareTypeResults));
		}

		$top5 = array_slice(
			$this->sortShareesByFrequency($result),
			0,
			5
		);

		$searchResult = new SearchResult();
		foreach ($this->searchResultTypeMap as $int => $str) {
			$searchResult->addResultSet(new SearchResultType($str), [], []);
			foreach ($top5 as $x) {
				if ($x['value']['shareType'] === $int) {
					$searchResult->addResultSet(new SearchResultType($str), [], [$x]);
				}
			}
		}
		return $searchResult;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $itemType
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 */
	public function findRecommended(string $itemType = null, $shareType = null): DataResponse {
		$shareTypes = [
			Share::SHARE_TYPE_USER,
		];

		if ($itemType === null) {
			throw new OCSBadRequestException('Missing itemType');
		} elseif ($itemType === 'file' || $itemType === 'folder') {
			if ($this->shareManager->allowGroupSharing()) {
				$shareTypes[] = Share::SHARE_TYPE_GROUP;
			}

			if ($this->isRemoteSharingAllowed($itemType)) {
				$shareTypes[] = Share::SHARE_TYPE_REMOTE;
			}

			if ($this->isRemoteGroupSharingAllowed($itemType)) {
				$shareTypes[] = Share::SHARE_TYPE_REMOTE_GROUP;
			}

			if ($this->shareManager->shareProviderExists(Share::SHARE_TYPE_EMAIL)) {
				$shareTypes[] = Share::SHARE_TYPE_EMAIL;
			}

			if ($this->shareManager->shareProviderExists(Share::SHARE_TYPE_ROOM)) {
				$shareTypes[] = Share::SHARE_TYPE_ROOM;
			}
		} else {
			$shareTypes[] = Share::SHARE_TYPE_GROUP;
			$shareTypes[] = Share::SHARE_TYPE_EMAIL;
		}

		// FIXME: DI
		if (\OC::$server->getAppManager()->isEnabledForUser('circles') && class_exists('\OCA\Circles\ShareByCircleProvider')) {
			$shareTypes[] = Share::SHARE_TYPE_CIRCLE;
		}

		if (isset($_GET['shareType']) && is_array($_GET['shareType'])) {
			$shareTypes = array_intersect($shareTypes, $_GET['shareType']);
			sort($shareTypes);
		} else if (is_numeric($shareType)) {
			$shareTypes = array_intersect($shareTypes, [(int) $shareType]);
			sort($shareTypes);
		}

		return new DataResponse(
			$this->getAllSharees($this->userId, $shareTypes)->asArray()
		);
	}

	/**
	 * Method to get out the static call for better testing
	 *
	 * @param string $itemType
	 * @return bool
	 */
	protected function isRemoteSharingAllowed(string $itemType): bool {
		try {
			// FIXME: static foo makes unit testing unnecessarily difficult
			$backend = \OC\Share\Share::getBackend($itemType);
			return $backend->isShareTypeAllowed(Share::SHARE_TYPE_REMOTE);
		} catch (\Exception $e) {
			return false;
		}
	}

	protected function isRemoteGroupSharingAllowed(string $itemType): bool {
		try {
			// FIXME: static foo makes unit testing unnecessarily difficult
			$backend = \OC\Share\Share::getBackend($itemType);
			return $backend->isShareTypeAllowed(Share::SHARE_TYPE_REMOTE_GROUP);
		} catch (\Exception $e) {
			return false;
		}
	}


	/**
	 * Generates a bunch of pagination links for the current page
	 *
	 * @param int $page Current page
	 * @param array $params Parameters for the URL
	 * @return string
	 */
	protected function getPaginationLink(int $page, array $params): string {
		if ($this->isV2()) {
			$url = $this->urlGenerator->getAbsoluteURL('/ocs/v2.php/apps/files_sharing/api/v1/sharees') . '?';
		} else {
			$url = $this->urlGenerator->getAbsoluteURL('/ocs/v1.php/apps/files_sharing/api/v1/sharees') . '?';
		}
		$params['page'] = $page + 1;
		return '<' . $url . http_build_query($params) . '>; rel="next"';
	}

	/**
	 * @return bool
	 */
	protected function isV2(): bool {
		return $this->request->getScriptName() === '/ocs/v2.php';
	}
}
