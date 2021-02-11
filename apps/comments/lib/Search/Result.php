<?php
/**
 * @copyright Copyright (c) 2018 Joas Schilling <coding@schilljs.com>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Michał Węgrzynek <michal.wegrzynek@malloc.com.pl>
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
use OCP\Files\NotFoundException;
use OCP\Search\Result as BaseResult;

/**
 * @deprecated 20.0.0
 */
class Result extends BaseResult {
	/**
	 * @deprecated 20.0.0
	 */
	public $type = 'comment';
	/**
	 * @deprecated 20.0.0
	 */
	public $comment;
	/**
	 * @deprecated 20.0.0
	 */
	public $authorId;
	/**
	 * @deprecated 20.0.0
	 */
	public $authorName;
	/**
	 * @deprecated 20.0.0
	 */
	public $path;
	/**
	 * @deprecated 20.0.0
	 */
	public $fileName;

	/**
	 * @param string $search
	 * @param IComment $comment
	 * @param string $authorName
	 * @param string $path
	 * @throws NotFoundException
	 * @deprecated 20.0.0
	 */
	public function __construct(string $search,
								IComment $comment,
								string $authorName,
								string $path) {
		parent::__construct(
			(int) $comment->getId(),
			$comment->getMessage()
		/* @todo , [link to file] */
		);

		$this->comment = $this->getRelevantMessagePart($comment->getMessage(), $search);
		$this->authorId = $comment->getActorId();
		$this->authorName = $authorName;
		$this->fileName = basename($path);
		$this->path = $this->getVisiblePath($path);
	}

	/**
	 * @param string $path
	 * @return string
	 * @throws NotFoundException
	 */
	protected function getVisiblePath(string $path): string {
		$segments = explode('/', trim($path, '/'), 3);

		if (!isset($segments[2])) {
			throw new NotFoundException('Path not inside visible section');
		}

		return $segments[2];
	}

	/**
	 * @param string $message
	 * @param string $search
	 * @return string
	 * @throws NotFoundException
	 */
	protected function getRelevantMessagePart(string $message, string $search): string {
		$start = mb_stripos($message, $search);
		if ($start === false) {
			throw new NotFoundException('Comment section not found');
		}

		$end = $start + mb_strlen($search);

		if ($start <= 25) {
			$start = 0;
			$prefix = '';
		} else {
			$start -= 25;
			$prefix = '…';
		}

		if ((mb_strlen($message) - $end) <= 25) {
			$end = mb_strlen($message);
			$suffix = '';
		} else {
			$end += 25;
			$suffix = '…';
		}

		return $prefix . mb_substr($message, $start, $end - $start) . $suffix;
	}
}
