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

namespace OCA\Files\Activity;

use OCP\Activity\IEvent;
use OCP\Activity\IEventMerger;
use OCP\Activity\IManager;
use OCP\Activity\IProvider;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;

class FavoriteProvider implements IProvider {

	const SUBJECT_ADDED = 'added_favorite';
	const SUBJECT_REMOVED = 'removed_favorite';

	/** @var IFactory */
	protected $languageFactory;

	/** @var IL10N */
	protected $l;

	/** @var IURLGenerator */
	protected $url;

	/** @var IManager */
	protected $activityManager;

	/** @var IEventMerger */
	protected $eventMerger;

	/**
	 * @param IFactory $languageFactory
	 * @param IURLGenerator $url
	 * @param IManager $activityManager
	 * @param IEventMerger $eventMerger
	 */
	public function __construct(IFactory $languageFactory, IURLGenerator $url, IManager $activityManager, IEventMerger $eventMerger) {
		$this->languageFactory = $languageFactory;
		$this->url = $url;
		$this->activityManager = $activityManager;
		$this->eventMerger = $eventMerger;
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
		if ($event->getApp() !== 'files' || $event->getType() !== 'favorite') {
			throw new \InvalidArgumentException();
		}

		$this->l = $this->languageFactory->get('files', $language);

		if ($this->activityManager->isFormattingFilteredObject()) {
			try {
				return $this->parseShortVersion($event);
			} catch (\InvalidArgumentException $e) {
				// Ignore and simply use the long version...
			}
		}

		return $this->parseLongVersion($event, $previousEvent);
	}

	/**
	 * @param IEvent $event
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 * @since 11.0.0
	 */
	public function parseShortVersion(IEvent $event) {

		if ($event->getSubject() === self::SUBJECT_ADDED) {
			$event->setParsedSubject($this->l->t('Added to favorites'));
			if ($this->activityManager->getRequirePNG()) {
				$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/starred.png')));
			} else {
				$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/starred.svg')));
			}
		} else if ($event->getSubject() === self::SUBJECT_REMOVED) {
			$event->setType('unfavorite');
			$event->setParsedSubject($this->l->t('Removed from favorites'));
			if ($this->activityManager->getRequirePNG()) {
				$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/star.png')));
			} else {
				$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/star.svg')));
			}
		} else {
			throw new \InvalidArgumentException();
		}

		return $event;
	}

	/**
	 * @param IEvent $event
	 * @param IEvent|null $previousEvent
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 * @since 11.0.0
	 */
	public function parseLongVersion(IEvent $event, IEvent $previousEvent = null) {

		if ($event->getSubject() === self::SUBJECT_ADDED) {
			$subject = $this->l->t('You added {file} to your favorites');
			if ($this->activityManager->getRequirePNG()) {
				$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/starred.png')));
			} else {
				$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/starred.svg')));
			}
		} else if ($event->getSubject() === self::SUBJECT_REMOVED) {
			$event->setType('unfavorite');
			$subject = $this->l->t('You removed {file} from your favorites');
			if ($this->activityManager->getRequirePNG()) {
				$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/star.png')));
			} else {
				$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/star.svg')));
			}
		} else {
			throw new \InvalidArgumentException();
		}

		$this->setSubjects($event, $subject);
		$event = $this->eventMerger->mergeEvents('file', $event, $previousEvent);
		return $event;
	}

	/**
	 * @param IEvent $event
	 * @param string $subject
	 */
	protected function setSubjects(IEvent $event, $subject) {
		$subjectParams = $event->getSubjectParameters();
		if (empty($subjectParams)) {
			// Try to fall back to the old way, but this does not work for emails.
			// But at least old activities still work.
			$subjectParams = [
				'id' => $event->getObjectId(),
				'path' => $event->getObjectName(),
			];
		}
		$parameter = [
			'type' => 'file',
			'id' => $subjectParams['id'],
			'name' => basename($subjectParams['path']),
			'path' => trim($subjectParams['path'], '/'),
			'link' => $this->url->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $subjectParams['id']]),
		];

		$event->setParsedSubject(str_replace('{file}', $parameter['path'], $subject))
			->setRichSubject($subject, ['file' => $parameter]);
	}
}
