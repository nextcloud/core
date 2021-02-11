<?php
/**
 * @copyright Copyright (c) 2017 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
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

namespace OC\Collaboration\Collaborators;

use OCP\Collaboration\Collaborators\ISearchPlugin;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\Collaboration\Collaborators\SearchResultType;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Federation\ICloudIdManager;
use OCP\Share;
use OCP\Share\IShare;

class RemoteGroupPlugin implements ISearchPlugin {
	protected $shareeEnumeration;

	/** @var ICloudIdManager */
	private $cloudIdManager;
	/** @var bool */
	private $enabled = false;

	public function __construct(ICloudFederationProviderManager $cloudFederationProviderManager, ICloudIdManager $cloudIdManager) {
		try {
			$fileSharingProvider = $cloudFederationProviderManager->getCloudFederationProvider('file');
			$supportedShareTypes = $fileSharingProvider->getSupportedShareTypes();
			if (in_array('group', $supportedShareTypes)) {
				$this->enabled = true;
			}
		} catch (\Exception $e) {
			// do nothing, just don't enable federated group shares
		}
		$this->cloudIdManager = $cloudIdManager;
	}

	public function search($search, $limit, $offset, ISearchResult $searchResult) {
		$result = ['wide' => [], 'exact' => []];
		$resultType = new SearchResultType('remote_groups');

		if ($this->enabled && $this->cloudIdManager->isValidCloudId($search) && $offset === 0) {
			list($remoteGroup, $serverUrl) = $this->splitGroupRemote($search);
			$result['exact'][] = [
				'label' => $remoteGroup . " ($serverUrl)",
				'guid' => $remoteGroup,
				'name' => $remoteGroup,
				'value' => [
					'shareType' => IShare::TYPE_REMOTE_GROUP,
					'shareWith' => $search,
					'server' => $serverUrl,
				],
			];
		}

		$searchResult->addResultSet($resultType, $result['wide'], $result['exact']);

		return true;
	}

	/**
	 * split group and remote from federated cloud id
	 *
	 * @param string $address federated share address
	 * @return array [user, remoteURL]
	 * @throws \InvalidArgumentException
	 */
	public function splitGroupRemote($address) {
		try {
			$cloudId = $this->cloudIdManager->resolveCloudId($address);
			return [$cloudId->getUser(), $cloudId->getRemote()];
		} catch (\InvalidArgumentException $e) {
			throw new \InvalidArgumentException('Invalid Federated Cloud ID', 0, $e);
		}
	}
}
