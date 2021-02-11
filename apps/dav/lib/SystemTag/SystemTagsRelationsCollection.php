<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <vincent@nextcloud.com>
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

namespace OCA\DAV\SystemTag;

use OCP\IGroupManager;
use OCP\IUserSession;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\SystemTag\SystemTagsEntityEvent;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\SimpleCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SystemTagsRelationsCollection extends SimpleCollection {

	/**
	 * SystemTagsRelationsCollection constructor.
	 *
	 * @param ISystemTagManager $tagManager
	 * @param ISystemTagObjectMapper $tagMapper
	 * @param IUserSession $userSession
	 * @param IGroupManager $groupManager
	 * @param EventDispatcherInterface $dispatcher
	 */
	public function __construct(
		ISystemTagManager $tagManager,
		ISystemTagObjectMapper $tagMapper,
		IUserSession $userSession,
		IGroupManager $groupManager,
		EventDispatcherInterface $dispatcher
	) {
		$children = [
			new SystemTagsObjectTypeCollection(
				'files',
				$tagManager,
				$tagMapper,
				$userSession,
				$groupManager,
				function ($name) {
					$nodes = \OC::$server->getUserFolder()->getById((int)$name);
					return !empty($nodes);
				}
			),
		];

		$event = new SystemTagsEntityEvent(SystemTagsEntityEvent::EVENT_ENTITY);
		$dispatcher->dispatch(SystemTagsEntityEvent::EVENT_ENTITY, $event);

		foreach ($event->getEntityCollections() as $entity => $entityExistsFunction) {
			$children[] = new SystemTagsObjectTypeCollection(
				$entity,
				$tagManager,
				$tagMapper,
				$userSession,
				$groupManager,
				$entityExistsFunction
			);
		}

		parent::__construct('root', $children);
	}

	public function getName() {
		return 'systemtags-relations';
	}

	public function setName($name) {
		throw new Forbidden('Permission denied to rename this collection');
	}
}
