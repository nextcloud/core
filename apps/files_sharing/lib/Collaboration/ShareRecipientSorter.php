<?php
/**
 * @copyright Copyright (c) 2017 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OCA\Files_Sharing\Collaboration;

use OCP\Collaboration\AutoComplete\ISorter;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IUserSession;
use OCP\Share\IManager;

class ShareRecipientSorter implements ISorter {

	/** @var IManager */
	private $shareManager;
	/** @var Folder */
	private $rootFolder;
	/** @var IUserSession */
	private $userSession;

	public function __construct(IManager $shareManager, IRootFolder $rootFolder, IUserSession $userSession) {
		$this->shareManager = $shareManager;
		$this->rootFolder = $rootFolder;
		$this->userSession = $userSession;
	}

	public function getId() {
		return 'share-recipients';
	}

	public function sort(array &$sortArray, array $context) {
		// let's be tolerant. Comments  uses "files" by default, other usages are often singular
		if($context['itemType'] !== 'files' && $context['itemType'] !== 'file') {
			return;
		}
		$user = $this->userSession->getUser();
		if($user === null) {
			return;
		}
		$userFolder = $this->rootFolder->getUserFolder($user->getUID());
		/** @var Node[] $nodes */
		$nodes = $userFolder->getById((int)$context['itemId']);
		if(count($nodes) === 0) {
			return;
		}
		$al = $this->shareManager->getAccessList($nodes[0]);

		foreach ($sortArray as $type => &$byType) {
			if(!isset($al[$type]) || !is_array($al[$type])) {
				continue;
			}

			// at least on PHP 5.6 usort turned out to be not stable. So we add
			// the current index to the value and compare it on a draw
			$i = 0;
			$workArray = array_map(function ($element) use (&$i) {
				return [$i++, $element];
			}, $byType);

			usort($workArray, function ($a, $b) use ($al, $type) {
				$result = $this->compare($a[1], $b[1], $al[$type]);
				if($result === 0) {
					$result = $a[0] - $b[0];
				}
				return $result;
			});

			// and remove the index values again
			$byType = array_column($workArray, 1);
		}
	}

	/**
	 * @param array $a
	 * @param array $b
	 * @param array $al
	 * @return int
	 */
	protected function compare(array $a, array $b, array $al) {
		$a = $a['value']['shareWith'];
		$b = $b['value']['shareWith'];

		$valueA = (int)in_array($a, $al, true);
		$valueB = (int)in_array($b, $al, true);

		return $valueB - $valueA;
	}
}
