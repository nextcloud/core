<?php
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCA\DAV\CalDAV\Activity\Provider;

use OCP\Activity\IEvent;

class Todo extends Event {

	/**
	 * @param string $language
	 * @param IEvent $event
	 * @param IEvent|null $previousEvent
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 * @since 11.0.0
	 */
	public function parse($language, IEvent $event, IEvent $previousEvent = null) {
		if ($event->getApp() !== 'dav' || $event->getType() !== 'calendar_todo') {
			throw new \InvalidArgumentException();
		}

		$this->l = $this->languageFactory->get('dav', $language);

		if ($this->activityManager->getRequirePNG()) {
			$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/checkmark.png')));
		} else {
			$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/checkmark.svg')));
		}

		if ($event->getSubject() === self::SUBJECT_OBJECT_ADD . '_todo') {
			$subject = $this->l->t('{actor} created todo {todo} in list {calendar}');
		} elseif ($event->getSubject() === self::SUBJECT_OBJECT_ADD . '_todo_self') {
			$subject = $this->l->t('You created todo {todo} in list {calendar}');
		} elseif ($event->getSubject() === self::SUBJECT_OBJECT_DELETE . '_todo') {
			$subject = $this->l->t('{actor} deleted todo {todo} from list {calendar}');
		} elseif ($event->getSubject() === self::SUBJECT_OBJECT_DELETE . '_todo_self') {
			$subject = $this->l->t('You deleted todo {todo} from list {calendar}');
		} elseif ($event->getSubject() === self::SUBJECT_OBJECT_UPDATE . '_todo') {
			$subject = $this->l->t('{actor} updated todo {todo} in list {calendar}');
		} elseif ($event->getSubject() === self::SUBJECT_OBJECT_UPDATE . '_todo_self') {
			$subject = $this->l->t('You updated todo {todo} in list {calendar}');
		} elseif ($event->getSubject() === self::SUBJECT_OBJECT_UPDATE . '_todo_completed') {
			$subject = $this->l->t('{actor} solved todo {todo} in list {calendar}');
		} elseif ($event->getSubject() === self::SUBJECT_OBJECT_UPDATE . '_todo_completed_self') {
			$subject = $this->l->t('You solved todo {todo} in list {calendar}');
		} elseif ($event->getSubject() === self::SUBJECT_OBJECT_UPDATE . '_todo_needs_action') {
			$subject = $this->l->t('{actor} reopened todo {todo} in list {calendar}');
		} elseif ($event->getSubject() === self::SUBJECT_OBJECT_UPDATE . '_todo_needs_action_self') {
			$subject = $this->l->t('You reopened todo {todo} in list {calendar}');
		} else {
			throw new \InvalidArgumentException();
		}

		$parsedParameters = $this->getParameters($event);
		$this->setSubjects($event, $subject, $parsedParameters);

		$event = $this->eventMerger->mergeEvents('todo', $event, $previousEvent);

		return $event;
	}

	/**
	 * @param IEvent $event
	 * @return array
	 */
	protected function getParameters(IEvent $event) {
		$subject = $event->getSubject();
		$parameters = $event->getSubjectParameters();

		// Nextcloud 13+
		if (isset($parameters['calendar'])) {
			switch ($subject) {
				case self::SUBJECT_OBJECT_ADD . '_todo':
				case self::SUBJECT_OBJECT_DELETE . '_todo':
				case self::SUBJECT_OBJECT_UPDATE . '_todo':
				case self::SUBJECT_OBJECT_UPDATE . '_todo_completed':
				case self::SUBJECT_OBJECT_UPDATE . '_todo_needs_action':
					return [
						'actor' => $this->generateUserParameter($parameters['actor']),
						'calendar' => $this->generateCalendarParameter($parameters['calendar'], $this->l),
						'todo' => $this->generateObjectParameter($parameters['object']),
					];
				case self::SUBJECT_OBJECT_ADD . '_todo_self':
				case self::SUBJECT_OBJECT_DELETE . '_todo_self':
				case self::SUBJECT_OBJECT_UPDATE . '_todo_self':
				case self::SUBJECT_OBJECT_UPDATE . '_todo_completed_self':
				case self::SUBJECT_OBJECT_UPDATE . '_todo_needs_action_self':
					return [
						'calendar' => $this->generateCalendarParameter($parameters['calendar'], $this->l),
						'todo' => $this->generateObjectParameter($parameters['object']),
					];
			}
		}

		// Legacy - Do NOT Remove unless necessary
		// Removing this will break parsing of activities that were created on
		// Nextcloud 12, so we should keep this as long as it's acceptable.
		// Otherwise if people upgrade over multiple releases in a short period,
		// they will get the dead entries in their stream.
		switch ($subject) {
			case self::SUBJECT_OBJECT_ADD . '_todo':
			case self::SUBJECT_OBJECT_DELETE . '_todo':
			case self::SUBJECT_OBJECT_UPDATE . '_todo':
			case self::SUBJECT_OBJECT_UPDATE . '_todo_completed':
			case self::SUBJECT_OBJECT_UPDATE . '_todo_needs_action':
				return [
					'actor' => $this->generateUserParameter($parameters[0]),
					'calendar' => $this->generateLegacyCalendarParameter($event->getObjectId(), $parameters[1]),
					'todo' => $this->generateObjectParameter($parameters[2]),
				];
			case self::SUBJECT_OBJECT_ADD . '_todo_self':
			case self::SUBJECT_OBJECT_DELETE . '_todo_self':
			case self::SUBJECT_OBJECT_UPDATE . '_todo_self':
			case self::SUBJECT_OBJECT_UPDATE . '_todo_completed_self':
			case self::SUBJECT_OBJECT_UPDATE . '_todo_needs_action_self':
				return [
					'calendar' => $this->generateLegacyCalendarParameter($event->getObjectId(), $parameters[1]),
					'todo' => $this->generateObjectParameter($parameters[2]),
				];
		}

		throw new \InvalidArgumentException();
	}
}
