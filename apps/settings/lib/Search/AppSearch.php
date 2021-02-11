<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Settings\Search;

use OCP\IL10N;
use OCP\INavigationManager;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;

class AppSearch implements IProvider {

	/** @var INavigationManager */
	protected $navigationManager;

	/** @var IL10N */
	protected $l;

	public function __construct(INavigationManager $navigationManager,
								IL10N $l) {
		$this->navigationManager = $navigationManager;
		$this->l = $l;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'settings_apps';
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->l->t('Apps');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(string $route, array $routeParameters): int {
		return -50;
	}

	/**
	 * @inheritDoc
	 */
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		$entries = $this->navigationManager->getAll('all');

		$result = [];
		foreach ($entries as $entry) {
			if (
				stripos($entry['name'], $query->getTerm()) === false &&
				stripos($entry['id'], $query->getTerm()) === false
			) {
				continue;
			}

			if (strpos($query->getRoute(), $entry['id'] . '.') === 0) {
				// Skip the current app, unlikely this is intended
				continue;
			}

			if ($entry['href'] === '') {
				// Nothing we can open, so ignore
				continue;
			}

			$result[] = new SearchResultEntry(
				'',
				$entry['name'],
				'',
				$entry['href'],
				'icon-confirm'
			);
		}

		return SearchResult::complete(
			$this->l->t('Apps'),
			$result
		);
	}
}
