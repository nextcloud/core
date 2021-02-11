<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Vincent Petry <vincent@nextcloud.com>
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

namespace OCA\SystemTags\Activity;

use OCP\Activity\IManager;
use OCP\App\IAppManager;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Share\IShareHelper;
use OCP\SystemTag\ISystemTag;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ManagerEvent;
use OCP\SystemTag\MapperEvent;
use OCP\SystemTag\TagNotFoundException;

class Listener {
	/** @var IGroupManager */
	protected $groupManager;
	/** @var IManager */
	protected $activityManager;
	/** @var IUserSession */
	protected $session;
	/** @var IConfig */
	protected $config;
	/** @var \OCP\SystemTag\ISystemTagManager */
	protected $tagManager;
	/** @var \OCP\App\IAppManager */
	protected $appManager;
	/** @var \OCP\Files\Config\IMountProviderCollection */
	protected $mountCollection;
	/** @var \OCP\Files\IRootFolder */
	protected $rootFolder;
	/** @var IShareHelper */
	protected $shareHelper;

	/**
	 * Listener constructor.
	 *
	 * @param IGroupManager $groupManager
	 * @param IManager $activityManager
	 * @param IUserSession $session
	 * @param IConfig $config
	 * @param ISystemTagManager $tagManager
	 * @param IAppManager $appManager
	 * @param IMountProviderCollection $mountCollection
	 * @param IRootFolder $rootFolder
	 * @param IShareHelper $shareHelper
	 */
	public function __construct(IGroupManager $groupManager,
								IManager $activityManager,
								IUserSession $session,
								IConfig $config,
								ISystemTagManager $tagManager,
								IAppManager $appManager,
								IMountProviderCollection $mountCollection,
								IRootFolder $rootFolder,
								IShareHelper $shareHelper) {
		$this->groupManager = $groupManager;
		$this->activityManager = $activityManager;
		$this->session = $session;
		$this->config = $config;
		$this->tagManager = $tagManager;
		$this->appManager = $appManager;
		$this->mountCollection = $mountCollection;
		$this->rootFolder = $rootFolder;
		$this->shareHelper = $shareHelper;
	}

	/**
	 * @param ManagerEvent $event
	 */
	public function event(ManagerEvent $event) {
		$actor = $this->session->getUser();
		if ($actor instanceof IUser) {
			$actor = $actor->getUID();
		} else {
			$actor = '';
		}
		$tag = $event->getTag();

		$activity = $this->activityManager->generateEvent();
		$activity->setApp('systemtags')
			->setType('systemtags')
			->setAuthor($actor)
			->setObject('systemtag', (int)$tag->getId(), $tag->getName());
		if ($event->getEvent() === ManagerEvent::EVENT_CREATE) {
			$activity->setSubject(Provider::CREATE_TAG, [
				$actor,
				$this->prepareTagAsParameter($event->getTag()),
			]);
		} elseif ($event->getEvent() === ManagerEvent::EVENT_UPDATE) {
			$activity->setSubject(Provider::UPDATE_TAG, [
				$actor,
				$this->prepareTagAsParameter($event->getTag()),
				$this->prepareTagAsParameter($event->getTagBefore()),
			]);
		} elseif ($event->getEvent() === ManagerEvent::EVENT_DELETE) {
			$activity->setSubject(Provider::DELETE_TAG, [
				$actor,
				$this->prepareTagAsParameter($event->getTag()),
			]);
		} else {
			return;
		}

		$group = $this->groupManager->get('admin');
		if ($group instanceof IGroup) {
			foreach ($group->getUsers() as $user) {
				$activity->setAffectedUser($user->getUID());
				$this->activityManager->publish($activity);
			}
		}


		if ($actor !== '' && ($event->getEvent() === ManagerEvent::EVENT_CREATE || $event->getEvent() === ManagerEvent::EVENT_UPDATE)) {
			$this->updateLastUsedTags($actor, $event->getTag());
		}
	}

	/**
	 * @param MapperEvent $event
	 */
	public function mapperEvent(MapperEvent $event) {
		$tagIds = $event->getTags();
		if ($event->getObjectType() !== 'files' || empty($tagIds)
			|| !in_array($event->getEvent(), [MapperEvent::EVENT_ASSIGN, MapperEvent::EVENT_UNASSIGN])
			|| !$this->appManager->isInstalled('activity')) {
			// System tags not for files, no tags, not (un-)assigning or no activity-app enabled (save the energy)
			return;
		}

		try {
			$tags = $this->tagManager->getTagsByIds($tagIds);
		} catch (TagNotFoundException $e) {
			// User assigned/unassigned a non-existing tag, ignore...
			return;
		}

		if (empty($tags)) {
			return;
		}

		// Get all mount point owners
		$cache = $this->mountCollection->getMountCache();
		$mounts = $cache->getMountsForFileId($event->getObjectId());
		if (empty($mounts)) {
			return;
		}

		$users = [];
		foreach ($mounts as $mount) {
			$owner = $mount->getUser()->getUID();
			$ownerFolder = $this->rootFolder->getUserFolder($owner);
			$nodes = $ownerFolder->getById($event->getObjectId());
			if (!empty($nodes)) {
				/** @var Node $node */
				$node = array_shift($nodes);
				$al = $this->shareHelper->getPathsForAccessList($node);
				$users += $al['users'];
			}
		}

		$actor = $this->session->getUser();
		if ($actor instanceof IUser) {
			$actor = $actor->getUID();
		} else {
			$actor = '';
		}

		$activity = $this->activityManager->generateEvent();
		$activity->setApp('systemtags')
			->setType('systemtags')
			->setAuthor($actor)
			->setObject($event->getObjectType(), (int) $event->getObjectId());

		foreach ($users as $user => $path) {
			$user = (string)$user; // numerical ids could be ints which are not accepted everywhere
			$activity->setAffectedUser($user);

			foreach ($tags as $tag) {
				// don't publish activity for non-admins if tag is invisible
				if (!$tag->isUserVisible() && !$this->groupManager->isAdmin($user)) {
					continue;
				}
				if ($event->getEvent() === MapperEvent::EVENT_ASSIGN) {
					$activity->setSubject(Provider::ASSIGN_TAG, [
						$actor,
						$path,
						$this->prepareTagAsParameter($tag),
					]);
				} elseif ($event->getEvent() === MapperEvent::EVENT_UNASSIGN) {
					$activity->setSubject(Provider::UNASSIGN_TAG, [
						$actor,
						$path,
						$this->prepareTagAsParameter($tag),
					]);
				}

				$this->activityManager->publish($activity);
			}
		}

		if ($actor !== '' && $event->getEvent() === MapperEvent::EVENT_ASSIGN) {
			foreach ($tags as $tag) {
				$this->updateLastUsedTags($actor, $tag);
			}
		}
	}

	/**
	 * @param string $actor
	 * @param ISystemTag $tag
	 */
	protected function updateLastUsedTags($actor, ISystemTag $tag) {
		$lastUsedTags = $this->config->getUserValue($actor, 'systemtags', 'last_used', '[]');
		$lastUsedTags = json_decode($lastUsedTags, true);

		array_unshift($lastUsedTags, $tag->getId());
		array_unique($lastUsedTags);
		$lastUsedTags = array_slice($lastUsedTags, 0, 10);

		$this->config->setUserValue($actor, 'systemtags', 'last_used', json_encode($lastUsedTags));
	}

	/**
	 * @param ISystemTag $tag
	 * @return string
	 */
	protected function prepareTagAsParameter(ISystemTag $tag) {
		return json_encode([
			'id' => $tag->getId(),
			'name' => $tag->getName(),
			'assignable' => $tag->isUserAssignable(),
			'visible' => $tag->isUserVisible(),
		]);
	}
}
