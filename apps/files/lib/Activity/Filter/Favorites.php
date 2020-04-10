<?php
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Files\Activity\Filter;

use OCA\Files\Activity\Helper;
use OCP\Activity\IFilter;
use OCP\Activity\IManager;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IURLGenerator;

class Favorites implements IFilter {

	/** @var IL10N */
	protected $l;

	/** @var IURLGenerator */
	protected $url;

	/** @var IManager */
	protected $activityManager;

	/** @var Helper */
	protected $helper;

	/** @var IDBConnection */
	protected $db;

	/**
	 * @param IL10N $l
	 * @param IURLGenerator $url
	 * @param IManager $activityManager
	 * @param Helper $helper
	 * @param IDBConnection $db
	 */
	public function __construct(IL10N $l, IURLGenerator $url, IManager $activityManager, Helper $helper, IDBConnection $db) {
		$this->l = $l;
		$this->url = $url;
		$this->activityManager = $activityManager;
		$this->helper = $helper;
		$this->db = $db;
	}

	/**
	 * @return string Lowercase a-z only identifier
	 * @since 11.0.0
	 */
	public function getIdentifier() {
		return 'files_favorites';
	}

	/**
	 * @return string A translated string
	 * @since 11.0.0
	 */
	public function getName() {
		return $this->l->t('Favorites');
	}

	/**
	 * @return int
	 * @since 11.0.0
	 */
	public function getPriority() {
		return 10;
	}

	/**
	 * @return string Full URL to an icon, empty string when none is given
	 * @since 11.0.0
	 */
	public function getIcon() {
		return $this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/star-dark.svg'));
	}

	/**
	 * @param string[] $types
	 * @return string[] An array of allowed apps from which activities should be displayed
	 * @since 11.0.0
	 */
	public function filterTypes(array $types) {
		return array_intersect([
			'file_created',
			'file_changed',
			'file_deleted',
			'file_restored',
		], $types);
	}

	/**
	 * @return string[] An array of allowed apps from which activities should be displayed
	 * @since 11.0.0
	 */
	public function allowedApps() {
		return ['files'];
	}

	/**
	 * @param IQueryBuilder $query
	 */
	public function filterFavorites(IQueryBuilder $query) {
		try {
			$user = $this->activityManager->getCurrentUserId();
		} catch (\UnexpectedValueException $e) {
			return;
		}

		try {
			$favorites = $this->helper->getFavoriteFilePaths($user);
		} catch (\RuntimeException $e) {
			return;
		}

		$limitations = [];
		if (!empty($favorites['items'])) {
			$limitations[] = $query->expr()->in('file', $query->createNamedParameter($favorites['items'], IQueryBuilder::PARAM_STR_ARRAY));
		}
		foreach ($favorites['folders'] as $favorite) {
			$limitations[] = $query->expr()->like('file', $query->createNamedParameter(
				$this->db->escapeLikeParameter($favorite . '/') . '%'
			));
		}

		if (empty($limitations)) {
			return;
		}

		$function = $query->createFunction('
			CASE 
				WHEN ' . $query->getColumnName('app') . ' <> ' . $query->createNamedParameter('files') . ' THEN 1
				WHEN ' . $query->getColumnName('app') . ' = ' . $query->createNamedParameter('files') . '
					AND (' . implode(' OR ', $limitations) . ')
					THEN 1 
			END = 1'
		);

		$query->andWhere($function);
	}
}
