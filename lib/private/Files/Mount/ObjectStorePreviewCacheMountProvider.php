<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Morris Jobke <hey@morrisjobke.de>
 *
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OC\Files\Mount;

use OC\Files\ObjectStore\AppdataPreviewObjectStoreStorage;
use OC\Files\ObjectStore\ObjectStoreStorage;
use OC\Files\Storage\Wrapper\Jail;
use OCP\Files\Config\IRootMountProvider;
use OCP\Files\Storage\IStorageFactory;
use OCP\IConfig;
use OCP\ILogger;

/**
 * Mount provider for object store app data folder for previews
 */
class ObjectStorePreviewCacheMountProvider implements IRootMountProvider {
	/** @var ILogger */
	private $logger;
	/** @var IConfig */
	private $config;

	public function __construct(ILogger $logger, IConfig $config) {
		$this->logger = $logger;
		$this->config = $config;
	}

	/**
	 * @return MountPoint[]
	 * @throws \Exception
	 */
	public function getRootMounts(IStorageFactory $loader): array {
		if (!is_array($this->config->getSystemValue('objectstore_multibucket'))) {
			return [];
		}
		if ($this->config->getSystemValue('objectstore.multibucket.preview-distribution', false) !== true) {
			return [];
		}

		$instanceId = $this->config->getSystemValueString('instanceid', '');
		$mountPoints = [];
		$directoryRange = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f'];
		$i = 0;
		foreach ($directoryRange as $parent) {
			foreach ($directoryRange as $child) {
				$mountPoints[] = new MountPoint(
					AppdataPreviewObjectStoreStorage::class,
					'/appdata_' . $instanceId . '/preview/' . $parent . '/' . $child,
					$this->getMultiBucketObjectStore($i),
					$loader
				);
				$i++;
			}
		}

		$rootStorageArguments = $this->getMultiBucketObjectStoreForRoot();
		$fakeRootStorage = new ObjectStoreStorage($rootStorageArguments);
		$fakeRootStorageJail = new Jail([
			'storage' => $fakeRootStorage,
			'root' => '/appdata_' . $instanceId . '/preview',
		]);

		// add a fallback location to be able to fetch existing previews from the old bucket
		$mountPoints[] = new MountPoint(
			$fakeRootStorageJail,
			'/appdata_' . $instanceId . '/preview/old-multibucket',
			null,
			$loader
		);

		return $mountPoints;
	}

	protected function getMultiBucketObjectStore(int $number): array {
		$config = $this->config->getSystemValue('objectstore_multibucket');

		// sanity checks
		if (empty($config['class'])) {
			$this->logger->error('No class given for objectstore', ['app' => 'files']);
		}
		if (!isset($config['arguments'])) {
			$config['arguments'] = [];
		}

		/*
		 * Use any provided bucket argument as prefix
		 * and add the mapping from parent/child => bucket
		 */
		if (!isset($config['arguments']['bucket'])) {
			$config['arguments']['bucket'] = '';
		}

		$config['arguments']['bucket'] .= "-preview-$number";

		// instantiate object store implementation
		$config['arguments']['objectstore'] = new $config['class']($config['arguments']);

		$config['arguments']['internal-id'] = $number;

		return $config['arguments'];
	}

	protected function getMultiBucketObjectStoreForRoot(): array {
		$config = $this->config->getSystemValue('objectstore_multibucket');

		// sanity checks
		if (empty($config['class'])) {
			$this->logger->error('No class given for objectstore', ['app' => 'files']);
		}
		if (!isset($config['arguments'])) {
			$config['arguments'] = [];
		}

		/*
		 * Use any provided bucket argument as prefix
		 * and add the mapping from parent/child => bucket
		 */
		if (!isset($config['arguments']['bucket'])) {
			$config['arguments']['bucket'] = '';
		}
		$config['arguments']['bucket'] .= '0';

		// instantiate object store implementation
		$config['arguments']['objectstore'] = new $config['class']($config['arguments']);

		return $config['arguments'];
	}
}
