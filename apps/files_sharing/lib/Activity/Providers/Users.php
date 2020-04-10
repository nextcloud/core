<?php
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Kevin Ndung'u <kevgathuku@gmail.com>
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

class Users extends Base {


	const SUBJECT_SHARED_USER_SELF = 'shared_user_self';
	const SUBJECT_RESHARED_USER_BY = 'reshared_user_by';
	const SUBJECT_UNSHARED_USER_SELF = 'unshared_user_self';
	const SUBJECT_UNSHARED_USER_BY = 'unshared_user_by';

	const SUBJECT_SHARED_WITH_BY = 'shared_with_by';
	const SUBJECT_UNSHARED_BY = 'unshared_by';
	const SUBJECT_SELF_UNSHARED = 'self_unshared';
	const SUBJECT_SELF_UNSHARED_BY = 'self_unshared_by';

	const SUBJECT_EXPIRED_USER = 'expired_user';
	const SUBJECT_EXPIRED = 'expired';

	/**
	 * @param IEvent $event
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 * @since 11.0.0
	 */
	public function parseShortVersion(IEvent $event) {
		$parsedParameters = $this->getParsedParameters($event);

		if ($event->getSubject() === self::SUBJECT_SHARED_USER_SELF) {
			$subject = $this->l->t('Shared with {user}');
		} else if ($event->getSubject() === self::SUBJECT_UNSHARED_USER_SELF) {
			$subject = $this->l->t('Removed share for {user}');
		} else if ($event->getSubject() === self::SUBJECT_SELF_UNSHARED) {
			$subject = $this->l->t('You removed yourself');
		} else if ($event->getSubject() === self::SUBJECT_SELF_UNSHARED_BY) {
			$subject = $this->l->t('{actor} removed themselves');
		} else if ($event->getSubject() === self::SUBJECT_RESHARED_USER_BY) {
			$subject = $this->l->t('{actor} shared with {user}');
		} else if ($event->getSubject() === self::SUBJECT_UNSHARED_USER_BY) {
			$subject = $this->l->t('{actor} removed share for {user}');
		} else if ($event->getSubject() === self::SUBJECT_SHARED_WITH_BY) {
			$subject = $this->l->t('Shared by {actor}');
		} else if ($event->getSubject() === self::SUBJECT_UNSHARED_BY) {
			$subject = $this->l->t('{actor} removed share');
		} else if ($event->getSubject() === self::SUBJECT_EXPIRED_USER) {
			$subject = $this->l->t('Share for {user} expired');
		} else if ($event->getSubject() === self::SUBJECT_EXPIRED) {
			$subject = $this->l->t('Share expired');
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

		if ($event->getSubject() === self::SUBJECT_SHARED_USER_SELF) {
			$subject = $this->l->t('You shared {file} with {user}');
		} else if ($event->getSubject() === self::SUBJECT_UNSHARED_USER_SELF) {
			$subject = $this->l->t('You removed {user} from {file}');
		} else if ($event->getSubject() === self::SUBJECT_SELF_UNSHARED) {
			$subject = $this->l->t('You removed yourself from {file}');
		} else if ($event->getSubject() === self::SUBJECT_SELF_UNSHARED_BY) {
			$subject = $this->l->t('{actor} removed themselves from {file}');
		} else if ($event->getSubject() === self::SUBJECT_RESHARED_USER_BY) {
			$subject = $this->l->t('{actor} shared {file} with {user}');
		} else if ($event->getSubject() === self::SUBJECT_UNSHARED_USER_BY) {
			$subject = $this->l->t('{actor} removed {user} from {file}');
		} else if ($event->getSubject() === self::SUBJECT_SHARED_WITH_BY) {
			$subject = $this->l->t('{actor} shared {file} with you');
		} else if ($event->getSubject() === self::SUBJECT_UNSHARED_BY) {
			$subject = $this->l->t('{actor} removed you from the share named {file}');
		} else if ($event->getSubject() === self::SUBJECT_EXPIRED_USER) {
			$subject = $this->l->t('Share for file {file} with {user} expired');
		} else if ($event->getSubject() === self::SUBJECT_EXPIRED) {
			$subject = $this->l->t('Share for file {file} expired');

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
			case self::SUBJECT_SHARED_USER_SELF:
			case self::SUBJECT_UNSHARED_USER_SELF:
			case self::SUBJECT_EXPIRED_USER:
			case self::SUBJECT_EXPIRED:
				return [
					'file' => $this->getFile($parameters[0], $event),
					'user' => $this->getUser($parameters[1]),
				];
			case self::SUBJECT_SHARED_WITH_BY:
			case self::SUBJECT_UNSHARED_BY:
			case self::SUBJECT_SELF_UNSHARED:
			case self::SUBJECT_SELF_UNSHARED_BY:
				return [
					'file' => $this->getFile($parameters[0], $event),
					'actor' => $this->getUser($parameters[1]),
				];
			case self::SUBJECT_RESHARED_USER_BY:
			case self::SUBJECT_UNSHARED_USER_BY:
				return [
					'file' => $this->getFile($parameters[0], $event),
					'user' => $this->getUser($parameters[2]),
					'actor' => $this->getUser($parameters[1]),
				];
		}
		return [];
	}
}
