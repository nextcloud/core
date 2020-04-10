<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\WorkflowEngine\Entity;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\GenericEvent;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Share\IManager as ShareManager;
use OCP\SystemTag\ISystemTag;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\MapperEvent;
use OCP\WorkflowEngine\EntityContext\IDisplayText;
use OCP\WorkflowEngine\EntityContext\IUrl;
use OCP\WorkflowEngine\GenericEntityEvent;
use OCP\WorkflowEngine\IEntity;
use OCP\WorkflowEngine\IRuleMatcher;

class File implements IEntity, IDisplayText, IUrl {

	private const EVENT_NAMESPACE = '\OCP\Files::';

	/** @var IL10N */
	protected $l10n;
	/** @var IURLGenerator */
	protected $urlGenerator;
	/** @var IRootFolder */
	protected $root;
	/** @var ILogger */
	protected $logger;
	/** @var string */
	protected $eventName;
	/** @var Event */
	protected $event;
	/** @var ShareManager */
	private $shareManager;
	/** @var IUserSession */
	private $userSession;
	/** @var ISystemTagManager */
	private $tagManager;


	public function __construct(
		IL10N $l10n,
		IURLGenerator $urlGenerator,
		IRootFolder $root,
		ILogger $logger,
		ShareManager $shareManager,
		IUserSession $userSession,
		ISystemTagManager $tagManager
	) {
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
		$this->root = $root;
		$this->logger = $logger;
		$this->shareManager = $shareManager;
		$this->userSession = $userSession;
		$this->tagManager = $tagManager;
	}

	public function getName(): string {
		return $this->l10n->t('File');
	}

	public function getIcon(): string {
		return $this->urlGenerator->imagePath('core', 'categories/files.svg');
	}

	public function getEvents(): array {
		return [
			new GenericEntityEvent($this->l10n->t('File created'), self::EVENT_NAMESPACE . 'postCreate'),
			new GenericEntityEvent($this->l10n->t('File updated'), self::EVENT_NAMESPACE . 'postWrite'),
			new GenericEntityEvent($this->l10n->t('File renamed'), self::EVENT_NAMESPACE . 'postRename'),
			new GenericEntityEvent($this->l10n->t('File deleted'), self::EVENT_NAMESPACE . 'postDelete'),
			new GenericEntityEvent($this->l10n->t('File accessed'), self::EVENT_NAMESPACE . 'postTouch'),
			new GenericEntityEvent($this->l10n->t('File copied'), self::EVENT_NAMESPACE . 'postCopy'),
			new GenericEntityEvent($this->l10n->t('Tag assigned'), MapperEvent::EVENT_ASSIGN),
		];
	}

	public function prepareRuleMatcher(IRuleMatcher $ruleMatcher, string $eventName, Event $event): void {
		if (!$event instanceof GenericEvent && !$event instanceof MapperEvent) {
			return;
		}
		$this->eventName = $eventName;
		$this->event = $event;
		try {
			$node = $this->getNode();
			$ruleMatcher->setEntitySubject($this, $node);
			$ruleMatcher->setFileInfo($node->getStorage(), $node->getInternalPath());
		} catch (NotFoundException $e) {
			// pass
		}
	}

	public function isLegitimatedForUserId(string $uid): bool {
		try {
			$node = $this->getNode();
			if($node->getOwner()->getUID() === $uid) {
				return true;
			}
			$acl = $this->shareManager->getAccessList($node, true, true);
			return array_key_exists($uid, $acl['users']);
		} catch (NotFoundException $e) {
			return false;
		}
	}

	/**
	 * @throws NotFoundException
	 */
	protected function getNode(): Node {
		if (!$this->event instanceof GenericEvent && !$this->event instanceof MapperEvent) {
			throw new NotFoundException();
		}
		switch ($this->eventName) {
			case self::EVENT_NAMESPACE . 'postCreate':
			case self::EVENT_NAMESPACE . 'postWrite':
			case self::EVENT_NAMESPACE . 'postDelete':
			case self::EVENT_NAMESPACE . 'postTouch':
				return $this->event->getSubject();
			case self::EVENT_NAMESPACE . 'postRename':
			case self::EVENT_NAMESPACE . 'postCopy':
				return $this->event->getSubject()[1];
			case MapperEvent::EVENT_ASSIGN:
				if (!$this->event instanceof MapperEvent || $this->event->getObjectType() !== 'files') {
					throw new NotFoundException();
				}
				$nodes = $this->root->getById((int)$this->event->getObjectId());
				if (is_array($nodes) && !empty($nodes)) {
					return array_shift($nodes);
				}
				break;
		}
		throw new NotFoundException();
	}

	public function getDisplayText(int $verbosity = 0): string {
		$user = $this->userSession->getUser();
		try {
			$node = $this->getNode();
		} catch (NotFoundException $e) {
			return '';
		}

		$options = [
			$user ? $user->getDisplayName() : $this->l10n->t('Someone'),
			$node->getName()
		];

		switch ($this->eventName) {
			case self::EVENT_NAMESPACE . 'postCreate':
				return $this->l10n->t('%s created %s', $options);
			case self::EVENT_NAMESPACE . 'postWrite':
				return $this->l10n->t('%s modified %s', $options);
			case self::EVENT_NAMESPACE . 'postDelete':
				return $this->l10n->t('%s deleted %s', $options);
			case self::EVENT_NAMESPACE . 'postTouch':
				return $this->l10n->t('%s accessed %s', $options);
			case self::EVENT_NAMESPACE . 'postRename':
				return $this->l10n->t('%s renamed %s', $options);
			case self::EVENT_NAMESPACE . 'postCopy':
				return $this->l10n->t('%s copied %s', $options);
			case MapperEvent::EVENT_ASSIGN:
				$tagNames = [];
				if($this->event instanceof MapperEvent) {
					$tagIDs = $this->event->getTags();
					$tagObjects = $this->tagManager->getTagsByIds($tagIDs);
					foreach ($tagObjects as $systemTag) {
						/** @var ISystemTag $systemTag */
						if($systemTag->isUserVisible()) {
							$tagNames[] = $systemTag->getName();
						}
					}
				}
				$filename = array_pop($options);
				$tagString = implode(', ', $tagNames);
				if($tagString === '') {
					return '';
				}
				array_push($options, $tagString, $filename);
				return $this->l10n->t('%s assigned %s to %s', $options);
		}
	}

	public function getUrl(): string {
		try {
			return $this->urlGenerator->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $this->getNode()->getId()]);
		} catch (InvalidPathException $e) {
			return '';
		} catch (NotFoundException $e) {
			return '';
		}
	}
}
