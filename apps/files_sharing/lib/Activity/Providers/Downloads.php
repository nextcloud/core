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

class Downloads extends Base {


	const SUBJECT_PUBLIC_SHARED_FILE_DOWNLOADED = 'public_shared_file_downloaded';
	const SUBJECT_PUBLIC_SHARED_FOLDER_DOWNLOADED = 'public_shared_folder_downloaded';

	const SUBJECT_SHARED_FILE_BY_EMAIL_DOWNLOADED = 'file_shared_with_email_downloaded';
	const SUBJECT_SHARED_FOLDER_BY_EMAIL_DOWNLOADED = 'folder_shared_with_email_downloaded';

	/**
	 * @param IEvent $event
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 * @since 11.0.0
	 */
	public function parseShortVersion(IEvent $event) {
		$parsedParameters = $this->getParsedParameters($event);

		if ($event->getSubject() === self::SUBJECT_PUBLIC_SHARED_FILE_DOWNLOADED ||
			$event->getSubject() === self::SUBJECT_PUBLIC_SHARED_FOLDER_DOWNLOADED) {
			$subject = $this->l->t('Downloaded via public link');
		} else if ($event->getSubject() === self::SUBJECT_SHARED_FILE_BY_EMAIL_DOWNLOADED ||
			$event->getSubject() === self::SUBJECT_SHARED_FOLDER_BY_EMAIL_DOWNLOADED) {
			$subject = $this->l->t('Downloaded by {email}');
		} else {
			throw new \InvalidArgumentException();
		}

		if ($this->activityManager->getRequirePNG()) {
			$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/download.png')));
		} else {
			$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/download.svg')));
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

		if ($event->getSubject() === self::SUBJECT_PUBLIC_SHARED_FILE_DOWNLOADED ||
			$event->getSubject() === self::SUBJECT_PUBLIC_SHARED_FOLDER_DOWNLOADED) {
			$subject = $this->l->t('{file} downloaded via public link');
		} else if ($event->getSubject() === self::SUBJECT_SHARED_FILE_BY_EMAIL_DOWNLOADED ||
			$event->getSubject() === self::SUBJECT_SHARED_FOLDER_BY_EMAIL_DOWNLOADED) {
			$subject = $this->l->t('{email} downloaded {file}');
		} else {
			throw new \InvalidArgumentException();
		}

		if ($this->activityManager->getRequirePNG()) {
			$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/download.png')));
		} else {
			$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/download.svg')));
		}
		$this->setSubjects($event, $subject, $parsedParameters);

		return $event;
	}

	/**
	 * @param IEvent $event
	 * @return array
	 * @throws \InvalidArgumentException
	 */
	protected function getParsedParameters(IEvent $event) {
		$subject = $event->getSubject();
		$parameters = $event->getSubjectParameters();

		switch ($subject) {
			case self::SUBJECT_PUBLIC_SHARED_FILE_DOWNLOADED:
			case self::SUBJECT_PUBLIC_SHARED_FOLDER_DOWNLOADED:
				return [
					'file' => $this->getFile($parameters[0], $event),
				];
			case self::SUBJECT_SHARED_FILE_BY_EMAIL_DOWNLOADED:
			case self::SUBJECT_SHARED_FOLDER_BY_EMAIL_DOWNLOADED:
				return [
					'file' => $this->getFile($parameters[0], $event),
					'email' => [
						'type' => 'email',
						'id' => $parameters[1],
						'name' => $parameters[1],
					],
				];
		}

		throw new \InvalidArgumentException();
	}
}
