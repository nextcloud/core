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

namespace OCA\Comments\Activity;

use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\Activity\IProvider;
use OCP\Comments\ICommentsManager;
use OCP\Comments\NotFoundException;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;

class Provider implements IProvider {

	/** @var IFactory */
	protected $languageFactory;

	/** @var IL10N */
	protected $l;

	/** @var IURLGenerator */
	protected $url;

	/** @var ICommentsManager */
	protected $commentsManager;

	/** @var IUserManager */
	protected $userManager;

	/** @var IManager */
	protected $activityManager;

	/** @var string[] */
	protected $displayNames = [];

	/**
	 * @param IFactory $languageFactory
	 * @param IURLGenerator $url
	 * @param ICommentsManager $commentsManager
	 * @param IUserManager $userManager
	 * @param IManager $activityManager
	 */
	public function __construct(IFactory $languageFactory, IURLGenerator $url, ICommentsManager $commentsManager, IUserManager $userManager, IManager $activityManager) {
		$this->languageFactory = $languageFactory;
		$this->url = $url;
		$this->commentsManager = $commentsManager;
		$this->userManager = $userManager;
		$this->activityManager = $activityManager;
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
		if ($event->getApp() !== 'comments') {
			throw new \InvalidArgumentException();
		}

		$this->l = $this->languageFactory->get('comments', $language);

		if ($event->getSubject() === 'add_comment_subject') {
			$this->parseMessage($event);
			if ($this->activityManager->getRequirePNG()) {
				$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/comment.png')));
			} else {
				$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/comment.svg')));
			}

			if ($this->activityManager->isFormattingFilteredObject()) {
				try {
					return $this->parseShortVersion($event);
				} catch (\InvalidArgumentException $e) {
					// Ignore and simply use the long version...
				}
			}

			return $this->parseLongVersion($event);
		} else {
			throw new \InvalidArgumentException();
		}
	}

	/**
	 * @param IEvent $event
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 */
	protected function parseShortVersion(IEvent $event) {
		$subjectParameters = $this->getSubjectParameters($event);

		if ($event->getSubject() === 'add_comment_subject') {
			if ($subjectParameters['actor'] === $this->activityManager->getCurrentUserId()) {
				$event->setParsedSubject($this->l->t('You commented'))
					->setRichSubject($this->l->t('You commented'), []);
			} else {
				$author = $this->generateUserParameter($subjectParameters['actor']);
				$event->setParsedSubject($this->l->t('%1$s commented', [$author['name']]))
					->setRichSubject($this->l->t('{author} commented'), [
						'author' => $author,
					]);
			}
		} else {
			throw new \InvalidArgumentException();
		}

		return $event;
	}

	/**
	 * @param IEvent $event
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 */
	protected function parseLongVersion(IEvent $event) {
		$subjectParameters = $this->getSubjectParameters($event);

		if ($event->getSubject() === 'add_comment_subject') {
			if ($subjectParameters['actor'] === $this->activityManager->getCurrentUserId()) {
				$event->setParsedSubject($this->l->t('You commented on %1$s', [
					$subjectParameters['filePath'],
				]))
					->setRichSubject($this->l->t('You commented on {file}'), [
						'file' => $this->generateFileParameter($subjectParameters['fileId'], $subjectParameters['filePath']),
					]);
			} else {
				$author = $this->generateUserParameter($subjectParameters['actor']);
				$event->setParsedSubject($this->l->t('%1$s commented on %2$s', [
					$author['name'],
					$subjectParameters['filePath'],
				]))
					->setRichSubject($this->l->t('{author} commented on {file}'), [
						'author' => $author,
						'file' => $this->generateFileParameter($subjectParameters['fileId'], $subjectParameters['filePath']),
					]);
			}
		} else {
			throw new \InvalidArgumentException();
		}

		return $event;
	}

	protected function getSubjectParameters(IEvent $event) {
		$subjectParameters = $event->getSubjectParameters();
		if (isset($subjectParameters['fileId'])) {
			return $subjectParameters;
		}

		// Fix subjects from 12.0.3 and older
		//
		// Do NOT Remove unless necessary
		// Removing this will break parsing of activities that were created on
		// Nextcloud 12, so we should keep this as long as it's acceptable.
		// Otherwise if people upgrade over multiple releases in a short period,
		// they will get the dead entries in their stream.
		return [
			'actor' => $subjectParameters[0],
			'fileId' => $event->getObjectId(),
			'filePath' => trim($subjectParameters[1], '/'),
		];
	}

	/**
	 * @param IEvent $event
	 */
	protected function parseMessage(IEvent $event) {
		$messageParameters = $event->getMessageParameters();
		if (empty($messageParameters)) {
			// Email
			return;
		}

		$commentId = isset($messageParameters['commentId']) ? $messageParameters['commentId'] : $messageParameters[0];

		try {
			$comment = $this->commentsManager->get((string) $commentId);
			$message = $comment->getMessage();

			$mentionCount = 1;
			$mentions = [];
			foreach ($comment->getMentions() as $mention) {
				if ($mention['type'] !== 'user') {
					continue;
				}

				$message = str_replace('@"' . $mention['id'] . '"', '{mention' . $mentionCount . '}', $message);
				if (strpos($mention['id'], ' ') === false && strpos($mention['id'], 'guest/') !== 0) {
					$message = str_replace('@' . $mention['id'], '{mention' . $mentionCount . '}', $message);
				}

				$mentions['mention' . $mentionCount] = $this->generateUserParameter($mention['id']);
				$mentionCount++;
			}

			$event->setParsedMessage($comment->getMessage())
				->setRichMessage($message, $mentions);
		} catch (NotFoundException $e) {
		}
	}

	/**
	 * @param int $id
	 * @param string $path
	 * @return array
	 */
	protected function generateFileParameter($id, $path) {
		return [
			'type' => 'file',
			'id' => $id,
			'name' => basename($path),
			'path' => $path,
			'link' => $this->url->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $id]),
		];
	}

	/**
	 * @param string $uid
	 * @return array
	 */
	protected function generateUserParameter($uid) {
		if (!isset($this->displayNames[$uid])) {
			$this->displayNames[$uid] = $this->getDisplayName($uid);
		}

		return [
			'type' => 'user',
			'id' => $uid,
			'name' => $this->displayNames[$uid],
		];
	}

	/**
	 * @param string $uid
	 * @return string
	 */
	protected function getDisplayName($uid) {
		$user = $this->userManager->get($uid);
		if ($user instanceof IUser) {
			return $user->getDisplayName();
		} else {
			return $uid;
		}
	}
}
