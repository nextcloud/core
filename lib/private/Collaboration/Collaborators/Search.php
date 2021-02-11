<?php
/**
 * @copyright Copyright (c) 2017 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author onehappycat <one.happy.cat@gmx.com>
 * @author Robin Appelman <robin@icewind.nl>
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

use OCP\Collaboration\Collaborators\ISearch;
use OCP\Collaboration\Collaborators\ISearchPlugin;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\Collaboration\Collaborators\SearchResultType;
use OCP\IContainer;
use OCP\Share;

class Search implements ISearch {
	/** @var IContainer */
	private $c;

	protected $pluginList = [];

	public function __construct(IContainer $c) {
		$this->c = $c;
	}

	/**
	 * @param string $search
	 * @param array $shareTypes
	 * @param bool $lookup
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return array
	 * @throws \OCP\AppFramework\QueryException
	 */
	public function search($search, array $shareTypes, $lookup, $limit, $offset) {
		$hasMoreResults = false;

		// Trim leading and trailing whitespace characters, e.g. when query is copy-pasted
		$search = trim($search);

		/** @var ISearchResult $searchResult */
		$searchResult = $this->c->resolve(SearchResult::class);

		foreach ($shareTypes as $type) {
			if (!isset($this->pluginList[$type])) {
				continue;
			}
			foreach ($this->pluginList[$type] as $plugin) {
				/** @var ISearchPlugin $searchPlugin */
				$searchPlugin = $this->c->resolve($plugin);
				$hasMoreResults = $searchPlugin->search($search, $limit, $offset, $searchResult) || $hasMoreResults;
			}
		}

		// Get from lookup server, not a separate share type
		if ($lookup) {
			$searchPlugin = $this->c->resolve(LookupPlugin::class);
			$hasMoreResults = $searchPlugin->search($search, $limit, $offset, $searchResult) || $hasMoreResults;
		}

		// sanitizing, could go into the plugins as well

		// if we have a exact match, either for the federated cloud id or for the
		// email address we only return the exact match. It is highly unlikely
		// that the exact same email address and federated cloud id exists
		$emailType = new SearchResultType('emails');
		$remoteType = new SearchResultType('remotes');
		if ($searchResult->hasExactIdMatch($emailType) && !$searchResult->hasExactIdMatch($remoteType)) {
			$searchResult->unsetResult($remoteType);
		} elseif (!$searchResult->hasExactIdMatch($emailType) && $searchResult->hasExactIdMatch($remoteType)) {
			$searchResult->unsetResult($emailType);
		}

		// if we have an exact local user match with an email-a-like query,
		// there is no need to show the remote and email matches.
		$userType = new SearchResultType('users');
		if (strpos($search, '@') !== false && $searchResult->hasExactIdMatch($userType)) {
			$searchResult->unsetResult($remoteType);
			$searchResult->unsetResult($emailType);
		}

		return [$searchResult->asArray(), $hasMoreResults];
	}

	public function registerPlugin(array $pluginInfo) {
		$shareType = constant(Share::class . '::' . $pluginInfo['shareType']);
		if ($shareType === null) {
			throw new \InvalidArgumentException('Provided ShareType is invalid');
		}
		$this->pluginList[$shareType][] = $pluginInfo['class'];
	}
}
