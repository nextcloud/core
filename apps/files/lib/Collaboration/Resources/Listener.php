<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Files\Collaboration\Resources;

use OCP\Collaboration\Resources\IManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Listener {
	public static function register(EventDispatcherInterface $dispatcher): void {
		$dispatcher->addListener('OCP\Share::postShare', [self::class, 'shareModification']);
		$dispatcher->addListener('OCP\Share::postUnshare', [self::class, 'shareModification']);
		$dispatcher->addListener('OCP\Share::postUnshareFromSelf', [self::class, 'shareModification']);
	}

	public static function shareModification(): void {
		/** @var IManager $resourceManager */
		$resourceManager = \OC::$server->query(IManager::class);
		/** @var ResourceProvider $resourceProvider */
		$resourceProvider = \OC::$server->query(ResourceProvider::class);

		$resourceManager->invalidateAccessCacheForProvider($resourceProvider);
	}
}
