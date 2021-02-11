<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018 Joas Schilling <coding@schilljs.com>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCA\Comments\Search;

use OCP\Comments\IComment;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IUser;
use OCP\Search\Provider;
use function count;

class LegacyProvider extends Provider {

	/**
	 * Search for $query
	 *
	 * @param string $query
	 * @return array An array of OCP\Search\Result's
	 * @since 7.0.0
	 */
	public function search($query): array {
		$cm = \OC::$server->getCommentsManager();
		$us = \OC::$server->getUserSession();

		$user = $us->getUser();
		if (!$user instanceof IUser) {
			return [];
		}
		$uf = \OC::$server->getUserFolder($user->getUID());

		if ($uf === null) {
			return [];
		}

		$result = [];
		$numComments = 50;
		$offset = 0;

		while (count($result) < $numComments) {
			/** @var IComment[] $comments */
			$comments = $cm->search($query, 'files', '', 'comment', $offset, $numComments);

			foreach ($comments as $comment) {
				if ($comment->getActorType() !== 'users') {
					continue;
				}

				$displayName = $cm->resolveDisplayName('user', $comment->getActorId());

				try {
					$file = $this->getFileForComment($uf, $comment);
					$result[] = new Result($query,
						$comment,
						$displayName,
						$file->getPath()
					);
				} catch (NotFoundException $e) {
					continue;
				}
			}

			if (count($comments) < $numComments) {
				// Didn't find more comments when we tried to get, so there are no more comments.
				return $result;
			}

			$offset += $numComments;
			$numComments = 50 - count($result);
		}

		return $result;
	}

	/**
	 * @param Folder $userFolder
	 * @param IComment $comment
	 * @return Node
	 * @throws NotFoundException
	 */
	protected function getFileForComment(Folder $userFolder, IComment $comment): Node {
		$nodes = $userFolder->getById((int) $comment->getObjectId());
		if (empty($nodes)) {
			throw new NotFoundException('File not found');
		}

		return array_shift($nodes);
	}
}
