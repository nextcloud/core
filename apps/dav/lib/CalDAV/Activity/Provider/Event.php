<?php
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Thomas Citharel <nextcloud@tcit.fr>
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

use OC_App;
use OCP\Activity\IEvent;
use OCP\Activity\IEventMerger;
use OCP\Activity\IManager;
use OCP\App\IAppManager;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;

class Event extends Base {
	public const SUBJECT_OBJECT_ADD = 'object_add';
	public const SUBJECT_OBJECT_UPDATE = 'object_update';
	public const SUBJECT_OBJECT_DELETE = 'object_delete';

	/** @var IFactory */
	protected $languageFactory;

	/** @var IL10N */
	protected $l;

	/** @var IManager */
	protected $activityManager;

	/** @var IEventMerger */
	protected $eventMerger;

	/** @var IAppManager */
	protected $appManager;

	/**
	 * @param IFactory $languageFactory
	 * @param IURLGenerator $url
	 * @param IManager $activityManager
	 * @param IUserManager $userManager
	 * @param IGroupManager $groupManager
	 * @param IEventMerger $eventMerger
	 * @param IAppManager $appManager
	 */
	public function __construct(IFactory $languageFactory, IURLGenerator $url, IManager $activityManager, IUserManager $userManager, IGroupManager $groupManager, IEventMerger $eventMerger, IAppManager $appManager) {
		parent::__construct($userManager, $groupManager, $url);
		$this->languageFactory = $languageFactory;
		$this->activityManager = $activityManager;
		$this->eventMerger = $eventMerger;
		$this->appManager = $appManager;
	}

	/**
	 * @param array $eventData
	 * @return array
	 */
	protected function generateObjectParameter(array $eventData) {
		if (!isset($eventData['id']) || !isset($eventData['name'])) {
			throw new \InvalidArgumentException();
		}

		$params = [
			'type' => 'calendar-event',
			'id' => $eventData['id'],
			'name' => $eventData['name'],

		];
		if (isset($eventData['link']) && is_array($eventData['link']) && $this->appManager->isEnabledForUser('calendar')) {
			try {
				// The calendar app needs to be manually loaded for the routes to be loaded
				OC_App::loadApp('calendar');
				$linkData = $eventData['link'];
				$objectId = base64_encode('/remote.php/dav/calendars/' . $linkData['owner'] . '/' . $linkData['calendar_uri'] . '/' . $linkData['object_uri']);
				$link = [
					'view' => 'dayGridMonth',
					'timeRange' => 'now',
					'mode' => 'sidebar',
					'objectId' => $objectId,
					'recurrenceId' => 'next'
				];
				$params['link'] = $this->url->linkToRouteAbsolute('calendar.view.indexview.timerange.edit', $link);
			} catch (\Exception $error) {
				// Do nothing
			}
		}
		return $params;
	}

	/**
	 * @param string $language
	 * @param IEvent $event
	 * @param IEvent|null $previousEvent
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 * @since 11.0.0
	 */
	public function parse($language, IEvent $event, IEvent $previousEvent = null) {
		if ($event->getApp() !== 'dav' || $event->getType() !== 'calendar_event') {
			throw new \InvalidArgumentException();
		}

		$this->l = $this->languageFactory->get('dav', $language);

		if ($this->activityManager->getRequirePNG()) {
			$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'places/calendar-dark.png')));
		} else {
			$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'places/calendar.svg')));
		}

		if ($event->getSubject() === self::SUBJECT_OBJECT_ADD . '_event') {
			$subject = $this->l->t('{actor} created event {event} in calendar {calendar}');
		} elseif ($event->getSubject() === self::SUBJECT_OBJECT_ADD . '_event_self') {
			$subject = $this->l->t('You created event {event} in calendar {calendar}');
		} elseif ($event->getSubject() === self::SUBJECT_OBJECT_DELETE . '_event') {
			$subject = $this->l->t('{actor} deleted event {event} from calendar {calendar}');
		} elseif ($event->getSubject() === self::SUBJECT_OBJECT_DELETE . '_event_self') {
			$subject = $this->l->t('You deleted event {event} from calendar {calendar}');
		} elseif ($event->getSubject() === self::SUBJECT_OBJECT_UPDATE . '_event') {
			$subject = $this->l->t('{actor} updated event {event} in calendar {calendar}');
		} elseif ($event->getSubject() === self::SUBJECT_OBJECT_UPDATE . '_event_self') {
			$subject = $this->l->t('You updated event {event} in calendar {calendar}');
		} else {
			throw new \InvalidArgumentException();
		}

		$parsedParameters = $this->getParameters($event);
		$this->setSubjects($event, $subject, $parsedParameters);

		$event = $this->eventMerger->mergeEvents('event', $event, $previousEvent);

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
				case self::SUBJECT_OBJECT_ADD . '_event':
				case self::SUBJECT_OBJECT_DELETE . '_event':
				case self::SUBJECT_OBJECT_UPDATE . '_event':
					return [
						'actor' => $this->generateUserParameter($parameters['actor']),
						'calendar' => $this->generateCalendarParameter($parameters['calendar'], $this->l),
						'event' => $this->generateClassifiedObjectParameter($parameters['object']),
					];
				case self::SUBJECT_OBJECT_ADD . '_event_self':
				case self::SUBJECT_OBJECT_DELETE . '_event_self':
				case self::SUBJECT_OBJECT_UPDATE . '_event_self':
					return [
						'calendar' => $this->generateCalendarParameter($parameters['calendar'], $this->l),
						'event' => $this->generateClassifiedObjectParameter($parameters['object']),
					];
			}
		}

		// Legacy - Do NOT Remove unless necessary
		// Removing this will break parsing of activities that were created on
		// Nextcloud 12, so we should keep this as long as it's acceptable.
		// Otherwise if people upgrade over multiple releases in a short period,
		// they will get the dead entries in their stream.
		switch ($subject) {
			case self::SUBJECT_OBJECT_ADD . '_event':
			case self::SUBJECT_OBJECT_DELETE . '_event':
			case self::SUBJECT_OBJECT_UPDATE . '_event':
				return [
					'actor' => $this->generateUserParameter($parameters[0]),
					'calendar' => $this->generateLegacyCalendarParameter($event->getObjectId(), $parameters[1]),
					'event' => $this->generateObjectParameter($parameters[2]),
				];
			case self::SUBJECT_OBJECT_ADD . '_event_self':
			case self::SUBJECT_OBJECT_DELETE . '_event_self':
			case self::SUBJECT_OBJECT_UPDATE . '_event_self':
				return [
					'calendar' => $this->generateLegacyCalendarParameter($event->getObjectId(), $parameters[1]),
					'event' => $this->generateObjectParameter($parameters[2]),
				];
		}

		throw new \InvalidArgumentException();
	}

	private function generateClassifiedObjectParameter(array $eventData) {
		$parameter = $this->generateObjectParameter($eventData);
		if (!empty($eventData['classified'])) {
			$parameter['name'] = $this->l->t('Busy');
		}
		return $parameter;
	}
}
