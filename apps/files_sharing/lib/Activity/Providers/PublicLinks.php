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

namespace OCA\Files_Sharing\Activity\Providers;

use OCP\Activity\IEvent;

class PublicLinks extends Base {

	const SUBJECT_SHARED_LINK_SELF = 'shared_link_self';
	const SUBJECT_RESHARED_LINK_BY = 'reshared_link_by';
	const SUBJECT_UNSHARED_LINK_SELF = 'unshared_link_self';
	const SUBJECT_UNSHARED_LINK_BY = 'unshared_link_by';
	const SUBJECT_LINK_EXPIRED = 'link_expired';
	const SUBJECT_LINK_BY_EXPIRED = 'link_by_expired';

	/**
	 * @param IEvent $event
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 * @since 11.0.0
	 */
	public function parseShortVersion(IEvent $event) {
		$parsedParameters = $this->getParsedParameters($event);

		if ($event->getSubject() === self::SUBJECT_SHARED_LINK_SELF) {
			$subject = $this->l->t('Shared as public link');
		} else if ($event->getSubject() === self::SUBJECT_UNSHARED_LINK_SELF) {
			$subject = $this->l->t('Removed public link');
		} else if ($event->getSubject() === self::SUBJECT_LINK_EXPIRED) {
			$subject = $this->l->t('Public link expired');
		} else if ($event->getSubject() === self::SUBJECT_RESHARED_LINK_BY) {
			$subject = $this->l->t('{actor} shared as public link');
		} else if ($event->getSubject() === self::SUBJECT_UNSHARED_LINK_BY) {
			$subject = $this->l->t('{actor} removed public link');
		} else if ($event->getSubject() === self::SUBJECT_LINK_BY_EXPIRED) {
			$subject = $this->l->t('Public link of {actor} expired');

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

		if ($event->getSubject() === self::SUBJECT_SHARED_LINK_SELF) {
			$subject = $this->l->t('You shared {file} as public link');
		} else if ($event->getSubject() === self::SUBJECT_UNSHARED_LINK_SELF) {
			$subject = $this->l->t('You removed public link for {file}');
		} else if ($event->getSubject() === self::SUBJECT_LINK_EXPIRED) {
			$subject = $this->l->t('Public link expired for {file}');
		} else if ($event->getSubject() === self::SUBJECT_RESHARED_LINK_BY) {
			$subject = $this->l->t('{actor} shared {file} as public link');
		} else if ($event->getSubject() === self::SUBJECT_UNSHARED_LINK_BY) {
			$subject = $this->l->t('{actor} removed public link for {file}');
		} else if ($event->getSubject() === self::SUBJECT_LINK_BY_EXPIRED) {
			$subject = $this->l->t('Public link of {actor} for {file} expired');

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
			case self::SUBJECT_SHARED_LINK_SELF:
			case self::SUBJECT_UNSHARED_LINK_SELF:
			case self::SUBJECT_LINK_EXPIRED:
				return [
					'file' => $this->getFile($parameters[0], $event),
				];
			case self::SUBJECT_RESHARED_LINK_BY:
			case self::SUBJECT_UNSHARED_LINK_BY:
			case self::SUBJECT_LINK_BY_EXPIRED:
				return [
					'file' => $this->getFile($parameters[0], $event),
					'actor' => $this->getUser($parameters[1]),
				];
		}
		return [];
	}

}
