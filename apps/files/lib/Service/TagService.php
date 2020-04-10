<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Vincent Petry <pvince81@owncloud.com>
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

namespace OCA\Files\Service;

use OCA\Files\Activity\FavoriteProvider;
use OCP\Activity\IManager;
use OCP\Files\Folder;
use OCP\ITags;
use OCP\IUser;
use OCP\IUserSession;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Service class to manage tags on files.
 */
class TagService {

	/** @var IUserSession */
	private $userSession;
	/** @var IManager */
	private $activityManager;
	/** @var ITags */
	private $tagger;
	/** @var Folder */
	private $homeFolder;
	/** @var EventDispatcherInterface */
	private $dispatcher;

	/**
	 * @param IUserSession $userSession
	 * @param IManager $activityManager
	 * @param ITags $tagger
	 * @param Folder $homeFolder
	 * @param EventDispatcherInterface $dispatcher
	 */
	public function __construct(
		IUserSession $userSession,
		IManager $activityManager,
		ITags $tagger,
		Folder $homeFolder,
		EventDispatcherInterface $dispatcher
	) {
		$this->userSession = $userSession;
		$this->activityManager = $activityManager;
		$this->tagger = $tagger;
		$this->homeFolder = $homeFolder;
		$this->dispatcher = $dispatcher;
	}

	/**
	 * Updates the tags of the specified file path.
	 * The passed tags are absolute, which means they will
	 * replace the actual tag selection.
	 *
	 * @param string $path path
	 * @param array  $tags array of tags
	 * @return array list of tags
	 * @throws \OCP\Files\NotFoundException if the file does not exist
	 */
	public function updateFileTags($path, $tags) {
		$fileId = $this->homeFolder->get($path)->getId();

		$currentTags = $this->tagger->getTagsForObjects([$fileId]);

		if (!empty($currentTags)) {
			$currentTags = current($currentTags);
		}

		$newTags = array_diff($tags, $currentTags);
		foreach ($newTags as $tag) {
			if ($tag === ITags::TAG_FAVORITE) {
				$this->addActivity(true, $fileId, $path);
			}
			$this->tagger->tagAs($fileId, $tag);
		}
		$deletedTags = array_diff($currentTags, $tags);
		foreach ($deletedTags as $tag) {
			if ($tag === ITags::TAG_FAVORITE) {
				$this->addActivity(false, $fileId, $path);
			}
			$this->tagger->unTag($fileId, $tag);
		}

		// TODO: re-read from tagger to make sure the
		// list is up to date, in case of concurrent changes ?
		return $tags;
	}

	/**
	 * @param bool $addToFavorite
	 * @param int $fileId
	 * @param string $path
	 */
	protected function addActivity($addToFavorite, $fileId, $path) {
		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			return;
		}

		$eventName = $addToFavorite ? 'addFavorite' : 'removeFavorite';
		$this->dispatcher->dispatch(self::class . '::' . $eventName, new GenericEvent(null, [
			'userId' => $user->getUID(),
			'fileId' => $fileId,
			'path' => $path,
		]));

		$event = $this->activityManager->generateEvent();
		try {
			$event->setApp('files')
				->setObject('files', $fileId, $path)
				->setType('favorite')
				->setAuthor($user->getUID())
				->setAffectedUser($user->getUID())
				->setTimestamp(time())
				->setSubject(
					$addToFavorite ? FavoriteProvider::SUBJECT_ADDED : FavoriteProvider::SUBJECT_REMOVED,
					['id' => $fileId, 'path' => $path]
				);
			$this->activityManager->publish($event);
		} catch (\InvalidArgumentException $e) {
		} catch (\BadMethodCallException $e) {
		}
	}
}
