<?php
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
 *
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

namespace OCA\Files_Sharing\Activity\Providers;

use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\Contacts\IManager as IContactsManager;
use OCP\Federation\ICloudIdManager;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;

class Groups extends Base {

	const SUBJECT_SHARED_GROUP_SELF = 'shared_group_self';
	const SUBJECT_RESHARED_GROUP_BY = 'reshared_group_by';

	const SUBJECT_UNSHARED_GROUP_SELF = 'unshared_group_self';
	const SUBJECT_UNSHARED_GROUP_BY = 'unshared_group_by';

	const SUBJECT_EXPIRED_GROUP = 'expired_group';

	/** @var IGroupManager */
	protected $groupManager;

	/** @var string[] */
	protected $groupDisplayNames = [];

	public function __construct(IFactory $languageFactory,
								IURLGenerator $url,
								IManager $activityManager,
								IUserManager $userManager,
								ICloudIdManager $cloudIdManager,
								IContactsManager $contactsManager,
								IGroupManager $groupManager) {
		parent::__construct($languageFactory, $url, $activityManager, $userManager, $cloudIdManager, $contactsManager);
		$this->groupManager = $groupManager;
	}

	/**
	 * @param IEvent $event
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 * @since 11.0.0
	 */
	public function parseShortVersion(IEvent $event) {
		$parsedParameters = $this->getParsedParameters($event);

		if ($event->getSubject() === self::SUBJECT_SHARED_GROUP_SELF) {
			$subject = $this->l->t('Shared with group {group}');
		} else if ($event->getSubject() === self::SUBJECT_UNSHARED_GROUP_SELF) {
			$subject = $this->l->t('Removed share for group {group}');
		} else if ($event->getSubject() === self::SUBJECT_RESHARED_GROUP_BY) {
			$subject = $this->l->t('{actor} shared with group {group}');
		} else if ($event->getSubject() === self::SUBJECT_UNSHARED_GROUP_BY) {
			$subject = $this->l->t('{actor} removed share for group {group}');
		} else if ($event->getSubject() === self::SUBJECT_EXPIRED_GROUP) {
			$subject = $this->l->t('Share for group {group} expired');
		} else {
			throw new \InvalidArgumentException();
		}

		if ($this->activityManager->getRequirePNG()) {
			$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/share.png')));
		} else {
			$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/share.svg')));
		}
		$this->setSubjects($event, $subject, $parsedParameters);

		return $event;
	}

	/**
	 * @param IEvent $event
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 * @since 11.0.0
	 */
	public function parseLongVersion(IEvent $event) {
		$parsedParameters = $this->getParsedParameters($event);

		if ($event->getSubject() === self::SUBJECT_SHARED_GROUP_SELF) {
			$subject = $this->l->t('You shared {file} with group {group}');
		} else if ($event->getSubject() === self::SUBJECT_UNSHARED_GROUP_SELF) {
			$subject = $this->l->t('You removed group {group} from {file}');
		} else if ($event->getSubject() === self::SUBJECT_RESHARED_GROUP_BY) {
			$subject = $this->l->t('{actor} shared {file} with group {group}');
		} else if ($event->getSubject() === self::SUBJECT_UNSHARED_GROUP_BY) {
			$subject = $this->l->t('{actor} removed group {group} from {file}');
		} else if ($event->getSubject() === self::SUBJECT_EXPIRED_GROUP) {
			$subject = $this->l->t('Share for file {file} with group {group} expired');
		} else {
			throw new \InvalidArgumentException();
		}

		if ($this->activityManager->getRequirePNG()) {
			$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/share.png')));
		} else {
			$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/share.svg')));
		}
		$this->setSubjects($event, $subject, $parsedParameters);

		return $event;
	}

	protected function getParsedParameters(IEvent $event) {
		$subject = $event->getSubject();
		$parameters = $event->getSubjectParameters();

		switch ($subject) {
			case self::SUBJECT_RESHARED_GROUP_BY:
			case self::SUBJECT_UNSHARED_GROUP_BY:
				return [
					'file' => $this->getFile($parameters[0], $event),
					'group' => $this->generateGroupParameter($parameters[2]),
					'actor' => $this->getUser($parameters[1]),
				];
			case self::SUBJECT_SHARED_GROUP_SELF:
			case self::SUBJECT_UNSHARED_GROUP_SELF:
			case self::SUBJECT_EXPIRED_GROUP:
				return [
					'file' => $this->getFile($parameters[0], $event),
					'group' => $this->generateGroupParameter($parameters[1]),
				];
		}
		return [];
	}

	/**
	 * @param string $gid
	 * @return array
	 */
	protected function generateGroupParameter($gid) {
		if (!isset($this->groupDisplayNames[$gid])) {
			$this->groupDisplayNames[$gid] = $this->getGroupDisplayName($gid);
		}

		return [
			'type' => 'user-group',
			'id' => $gid,
			'name' => $this->groupDisplayNames[$gid],
		];
	}

	/**
	 * @param string $gid
	 * @return string
	 */
	protected function getGroupDisplayName($gid) {
		$group = $this->groupManager->get($gid);
		if ($group instanceof IGroup) {
			return $group->getDisplayName();
		}
		return $gid;
	}
}
