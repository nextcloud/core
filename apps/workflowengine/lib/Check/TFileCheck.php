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

namespace OCA\WorkflowEngine\Check;

use OCA\WorkflowEngine\AppInfo\Application;
use OCA\WorkflowEngine\Entity\File;
use OCP\Files\Node;
use OCP\Files\Storage\IStorage;
use OCP\WorkflowEngine\IEntity;

trait TFileCheck {
	/** @var IStorage */
	protected $storage;

	/** @var string */
	protected $path;

	/** @var bool */
	protected $isDir;

	/**
	 * @param IStorage $storage
	 * @param string $path
	 * @param bool $isDir
	 * @since 18.0.0
	 */
	public function setFileInfo(IStorage $storage, string $path, bool $isDir = false): void {
		$this->storage = $storage;
		$this->path = $path;
		$this->isDir = $isDir;
	}

	/**
	 * @throws \OCP\Files\NotFoundException
	 */
	public function setEntitySubject(IEntity $entity, $subject): void {
		if ($entity instanceof File) {
			if (!$subject instanceof Node) {
				throw new \UnexpectedValueException(
					'Expected Node subject for File entity, got {class}',
					['app' => Application::APP_ID, 'class' => get_class($subject)]
				);
			}
			$this->storage = $subject->getStorage();
			$this->path = $subject->getPath();
		}
	}
}
