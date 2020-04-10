<?php
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
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

use OCA\WorkflowEngine\Entity\File;
use OCP\Files\Cache\ICache;
use OCP\Files\IHomeStorage;
use OCP\IL10N;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\SystemTag\TagNotFoundException;
use OCP\WorkflowEngine\ICheck;
use OCP\WorkflowEngine\IFileCheck;

class FileSystemTags implements ICheck, IFileCheck {
	use TFileCheck;

	/** @var array */
	protected $fileIds;

	/** @var array */
	protected $fileSystemTags;

	/** @var IL10N */
	protected $l;

	/** @var ISystemTagManager */
	protected $systemTagManager;

	/** @var ISystemTagObjectMapper */
	protected $systemTagObjectMapper;

	/**
	 * @param IL10N $l
	 * @param ISystemTagManager $systemTagManager
	 * @param ISystemTagObjectMapper $systemTagObjectMapper
	 */
	public function __construct(IL10N $l, ISystemTagManager $systemTagManager, ISystemTagObjectMapper $systemTagObjectMapper) {
		$this->l = $l;
		$this->systemTagManager = $systemTagManager;
		$this->systemTagObjectMapper = $systemTagObjectMapper;
	}

	/**
	 * @param string $operator
	 * @param string $value
	 * @return bool
	 */
	public function executeCheck($operator, $value) {
		$systemTags = $this->getSystemTags();
		return ($operator === 'is') === in_array($value, $systemTags);
	}

	/**
	 * @param string $operator
	 * @param string $value
	 * @throws \UnexpectedValueException
	 */
	public function validateCheck($operator, $value) {
		if (!in_array($operator, ['is', '!is'])) {
			throw new \UnexpectedValueException($this->l->t('The given operator is invalid'), 1);
		}

		try {
			$this->systemTagManager->getTagsByIds($value);
		} catch (TagNotFoundException $e) {
			throw new \UnexpectedValueException($this->l->t('The given tag id is invalid'), 2);
		} catch (\InvalidArgumentException $e) {
			throw new \UnexpectedValueException($this->l->t('The given tag id is invalid'), 3);
		}
	}

	/**
	 * Get the ids of the assigned system tags
	 * @return string[]
	 */
	protected function getSystemTags() {
		$cache = $this->storage->getCache();
		$fileIds = $this->getFileIds($cache, $this->path, !$this->storage->instanceOfStorage(IHomeStorage::class));

		$systemTags = [];
		foreach ($fileIds as $i => $fileId) {
			if (isset($this->fileSystemTags[$fileId])) {
				$systemTags[] = $this->fileSystemTags[$fileId];
				unset($fileIds[$i]);
			}
		}

		if (!empty($fileIds)) {
			$mappedSystemTags = $this->systemTagObjectMapper->getTagIdsForObjects($fileIds, 'files');
			foreach ($mappedSystemTags as $fileId => $fileSystemTags) {
				$this->fileSystemTags[$fileId] = $fileSystemTags;
				$systemTags[] = $fileSystemTags;
			}
		}

		$systemTags = call_user_func_array('array_merge', $systemTags);
		$systemTags = array_unique($systemTags);
		return $systemTags;
	}

	/**
	 * Get the file ids of the given path and its parents
	 * @param ICache $cache
	 * @param string $path
	 * @param bool $isExternalStorage
	 * @return int[]
	 */
	protected function getFileIds(ICache $cache, $path, $isExternalStorage) {
		$cacheId = $cache->getNumericStorageId();
		if (isset($this->fileIds[$cacheId][$path])) {
			return $this->fileIds[$cacheId][$path];
		}

		$parentIds = [];
		if ($path !== $this->dirname($path)) {
			$parentIds = $this->getFileIds($cache, $this->dirname($path), $isExternalStorage);
		} else if (!$isExternalStorage) {
			return [];
		}

		$fileId = $cache->getId($path);
		if ($fileId !== -1) {
			$parentIds[] = $cache->getId($path);
		}

		$this->fileIds[$cacheId][$path] = $parentIds;

		return $parentIds;
	}

	protected function dirname($path) {
		$dir = dirname($path);
		return $dir === '.' ? '' : $dir;
	}

	public function supportedEntities(): array {
		return [ File::class ];
	}

	public function isAvailableForScope(int $scope): bool {
		return true;
	}
}
