<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
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

use OCP\Collaboration\Resources\IProvider;
use OCP\Collaboration\Resources\IResource;
use OCP\Collaboration\Resources\ResourceException;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IPreview;
use OCP\IURLGenerator;
use OCP\IUser;

class ResourceProvider implements IProvider {

	public const RESOURCE_TYPE = 'file';

	/** @var IRootFolder */
	protected $rootFolder;
	/** @var IPreview */
	private $preview;
	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var array */
	protected $nodes = [];

	public function __construct(IRootFolder $rootFolder,
								IPreview $preview,
								IURLGenerator $urlGenerator) {
		$this->rootFolder = $rootFolder;
		$this->preview = $preview;
		$this->urlGenerator = $urlGenerator;
	}

	private function getNode(IResource $resource): ?Node {
		if (isset($this->nodes[(int) $resource->getId()])) {
			return $this->nodes[(int) $resource->getId()];
		}
		$nodes = $this->rootFolder->getById((int) $resource->getId());
		if (!empty($nodes)) {
			$this->nodes[(int) $resource->getId()] = array_shift($nodes);
			return $this->nodes[(int) $resource->getId()];
		}
		return null;
	}

	/**
	 * @param IResource $resource
	 * @return array
	 * @since 16.0.0
	 */
	public function getResourceRichObject(IResource $resource): array {
		if (isset($this->nodes[(int) $resource->getId()])) {
			$node = $this->nodes[(int) $resource->getId()]->getPath();
		} else {
			$node = $this->getNode($resource);
		}

		if ($node instanceof Node) {
			$link = $this->urlGenerator->linkToRouteAbsolute(
				'files.viewcontroller.showFile',
				['fileid' => $resource->getId()]
			);
			return [
				'type' => 'file',
				'id' => $resource->getId(),
				'name' => $node->getName(),
				'path' => $node->getInternalPath(),
				'link' => $link,
				'mimetype' => $node->getMimetype(),
				'preview-available' => $this->preview->isAvailable($node),
			];
		}

		throw new ResourceException('File not found');
	}

	/**
	 * Can a user/guest access the collection
	 *
	 * @param IResource $resource
	 * @param IUser $user
	 * @return bool
	 * @since 16.0.0
	 */
	public function canAccessResource(IResource $resource, IUser $user = null): bool {
		if (!$user instanceof IUser) {
			return false;
		}

		$userFolder = $this->rootFolder->getUserFolder($user->getUID());
		$nodes = $userFolder->getById((int) $resource->getId());

		if (!empty($nodes)) {
			$this->nodes[(int) $resource->getId()] = array_shift($nodes);
			return true;
		}

		return false;
	}

	/**
	 * Get the resource type of the provider
	 *
	 * @return string
	 * @since 16.0.0
	 */
	public function getType(): string {
		return self::RESOURCE_TYPE;
	}
}
