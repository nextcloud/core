<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Comments\Notification;

use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\Comments\NotFoundException;
use OCP\Files\IRootFolder;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class Notifier implements INotifier {

	/** @var IFactory */
	protected $l10nFactory;

	/** @var IRootFolder  */
	protected $rootFolder;

	/** @var ICommentsManager  */
	protected $commentsManager;

	/** @var IURLGenerator */
	protected $url;

	/** @var IUserManager */
	protected $userManager;

	public function __construct(
		IFactory $l10nFactory,
		IRootFolder $rootFolder,
		ICommentsManager $commentsManager,
		IURLGenerator $url,
		IUserManager $userManager
	) {
		$this->l10nFactory = $l10nFactory;
		$this->rootFolder = $rootFolder;
		$this->commentsManager = $commentsManager;
		$this->url = $url;
		$this->userManager = $userManager;
	}

	/**
	 * Identifier of the notifier, only use [a-z0-9_]
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getID(): string {
		return 'comments';
	}

	/**
	 * Human readable name describing the notifier
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getName(): string {
		return $this->l10nFactory->get('comments')->t('Comments');
	}

	/**
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 * @return INotification
	 * @throws \InvalidArgumentException When the notification was not prepared by a notifier
	 * @throws AlreadyProcessedException When the notification is not needed anymore and should be deleted
	 * @since 9.0.0
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== 'comments') {
			throw new \InvalidArgumentException();
		}
		try {
			$comment = $this->commentsManager->get($notification->getObjectId());
		} catch (NotFoundException $e) {
			// needs to be converted to InvalidArgumentException, otherwise none Notifications will be shown at all
			throw new \InvalidArgumentException('Comment not found', 0, $e);
		}
		$l = $this->l10nFactory->get('comments', $languageCode);
		$displayName = $comment->getActorId();
		$isDeletedActor = $comment->getActorType() === ICommentsManager::DELETED_USER;
		if ($comment->getActorType() === 'users') {
			$commenter = $this->userManager->get($comment->getActorId());
			if ($commenter instanceof IUser) {
				$displayName = $commenter->getDisplayName();
			}
		}

		switch ($notification->getSubject()) {
			case 'mention':
				$parameters = $notification->getSubjectParameters();
				if ($parameters[0] !== 'files') {
					throw new \InvalidArgumentException('Unsupported comment object');
				}
				$userFolder = $this->rootFolder->getUserFolder($notification->getUser());
				$nodes = $userFolder->getById((int)$parameters[1]);
				if (empty($nodes)) {
					throw new AlreadyProcessedException();
				}
				$node = $nodes[0];

				$path = rtrim($node->getPath(), '/');
				if (strpos($path, '/' . $notification->getUser() . '/files/') === 0) {
					// Remove /user/files/...
					$fullPath = $path;
					list(,,, $path) = explode('/', $fullPath, 4);
				}
				$subjectParameters = [
					'file' => [
						'type' => 'file',
						'id' => $comment->getObjectId(),
						'name' => $node->getName(),
						'path' => $path,
						'link' => $this->url->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $comment->getObjectId()]),
					],
				];

				if ($isDeletedActor) {
					$subject = $l->t('You were mentioned on “{file}”, in a comment by a user that has since been deleted');
				} else {
					$subject = $l->t('{user} mentioned you in a comment on “{file}”');
					$subjectParameters['user'] = [
						'type' => 'user',
						'id' => $comment->getActorId(),
						'name' => $displayName,
					];
				}
				list($message, $messageParameters) = $this->commentToRichMessage($comment);
				$notification->setRichSubject($subject, $subjectParameters)
					->setParsedSubject($this->richToParsed($subject, $subjectParameters))
					->setRichMessage($message, $messageParameters)
					->setParsedMessage($this->richToParsed($message, $messageParameters))
					->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/comment.svg')))
					->setLink($this->url->linkToRouteAbsolute(
						'comments.Notifications.view',
						['id' => $comment->getId()])
					);

				return $notification;
				break;

			default:
				throw new \InvalidArgumentException('Invalid subject');
		}
	}

	public function commentToRichMessage(IComment $comment): array {
		$message = $comment->getMessage();
		$messageParameters = [];
		$mentionTypeCount = [];
		$mentions = $comment->getMentions();
		foreach ($mentions as $mention) {
			if ($mention['type'] === 'user') {
				$user = $this->userManager->get($mention['id']);
				if (!$user instanceof IUser) {
					continue;
				}
			}
			if (!array_key_exists($mention['type'], $mentionTypeCount)) {
				$mentionTypeCount[$mention['type']] = 0;
			}
			$mentionTypeCount[$mention['type']]++;
			// To keep a limited character set in parameter IDs ([a-zA-Z0-9-])
			// the mention parameter ID does not include the mention ID (which
			// could contain characters like '@' for user IDs) but a one-based
			// index of the mentions of that type.
			$mentionParameterId = 'mention-' . $mention['type'] . $mentionTypeCount[$mention['type']];
			$message = str_replace('@"' . $mention['id'] . '"', '{' . $mentionParameterId . '}', $message);
			if (strpos($mention['id'], ' ') === false && strpos($mention['id'], 'guest/') !== 0) {
				$message = str_replace('@' . $mention['id'], '{' . $mentionParameterId . '}', $message);
			}

			try {
				$displayName = $this->commentsManager->resolveDisplayName($mention['type'], $mention['id']);
			} catch (\OutOfBoundsException $e) {
				// There is no registered display name resolver for the mention
				// type, so the client decides what to display.
				$displayName = '';
			}
			$messageParameters[$mentionParameterId] = [
				'type' => $mention['type'],
				'id' => $mention['id'],
				'name' => $displayName
			];
		}
		return [$message, $messageParameters];
	}

	public function richToParsed(string $message, array $parameters): string {
		$placeholders = $replacements = [];
		foreach ($parameters as $placeholder => $parameter) {
			$placeholders[] = '{' . $placeholder . '}';
			if ($parameter['type'] === 'user') {
				$replacements[] = '@' . $parameter['name'];
			} elseif ($parameter['type'] === 'file') {
				$replacements[] = $parameter['path'];
			} else {
				$replacements[] = $parameter['name'];
			}
		}
		return str_replace($placeholders, $replacements, $message);
	}
}
