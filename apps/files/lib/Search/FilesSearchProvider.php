<?php

declare(strict_types=1);

/**
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ <skjnldsv@protonmail.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Files\Search;

use OC\Files\Search\SearchBinaryOperator;
use OC\Files\Search\SearchComparison;
use OC\Files\Search\SearchOrder;
use OC\Files\Search\SearchQuery;
use OCP\Files\FileInfo;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Files\Search\ISearchComparison;
use OCP\Files\Node;
use OCP\Files\Search\ISearchOrder;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;

class FilesSearchProvider implements IProvider {

	/** @var IL10N */
	private $l10n;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var IMimeTypeDetector */
	private $mimeTypeDetector;

	/** @var IRootFolder */
	private $rootFolder;

	public function __construct(
		IL10N $l10n,
		IURLGenerator $urlGenerator,
		IMimeTypeDetector $mimeTypeDetector,
		IRootFolder $rootFolder
	) {
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
		$this->mimeTypeDetector = $mimeTypeDetector;
		$this->rootFolder = $rootFolder;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'files';
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->l10n->t('Files');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(string $route, array $routeParameters): int {
		if ($route === 'files.View.index') {
			// Before comments
			return -5;
		}
		return 5;
	}

	/**
	 * @inheritDoc
	 */
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		$userFolder = $this->rootFolder->getUserFolder($user->getUID());
		$fileQuery = new SearchQuery(
			new SearchBinaryOperator(SearchBinaryOperator::OPERATOR_OR, [
				new SearchComparison(ISearchComparison::COMPARE_LIKE, 'name', '%' . $query->getTerm() . '%'),
				new SearchComparison(ISearchComparison::COMPARE_LIKE, 'tagname', '%' . $query->getTerm() . '%'),
				new SearchComparison(ISearchComparison::COMPARE_LIKE, 'systemtag', '%' . $query->getTerm() . '%'),
			]),
			$query->getLimit(),
			(int)$query->getCursor(),
			$query->getSortOrder() === ISearchQuery::SORT_DATE_DESC ? [
				new SearchOrder(ISearchOrder::DIRECTION_DESCENDING, 'mtime'),
			] : [],
			$user
		);

		return SearchResult::paginated(
			$this->l10n->t('Files'),
			array_map(function (Node $result) use ($userFolder) {
				// Generate thumbnail url
				$thumbnailUrl = $this->urlGenerator->linkToRouteAbsolute('core.Preview.getPreviewByFileId', ['x' => 32, 'y' => 32, 'fileId' => $result->getId()]);
				$path = $userFolder->getRelativePath($result->getPath());
				$link = $this->urlGenerator->linkToRoute(
					'files.view.index',
					[
						'dir' => dirname($path),
						'scrollto' => $result->getName(),
						'openfile' => $result->getId(),
					]
				);

				$searchResultEntry = new SearchResultEntry(
					$thumbnailUrl,
					$result->getName(),
					$this->formatSubline($path),
					$this->urlGenerator->getAbsoluteURL($link),
					$result->getMimetype() === FileInfo::MIMETYPE_FOLDER ? 'icon-folder' : $this->mimeTypeDetector->mimeTypeIcon($result->getMimetype())
				);
				$searchResultEntry->addAttribute('fileId', (string)$result->getId());
				$searchResultEntry->addAttribute('path', $path);
				return $searchResultEntry;
			}, $userFolder->search($fileQuery)),
			$query->getCursor() + $query->getLimit()
		);
	}

	/**
	 * Format subline for files
	 *
	 * @param string $path
	 * @return string
	 */
	private function formatSubline(string $path): string {
		// Do not show the location if the file is in root
		if (strrpos($path, '/') > 0) {
			$path = ltrim(dirname($path), '/');
			return $this->l10n->t('in %s', [$path]);
		} else {
			return '';
		}
	}
}
