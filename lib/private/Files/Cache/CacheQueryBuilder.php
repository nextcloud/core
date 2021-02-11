<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019 Robin Appelman <robin@icewind.nl>
 *
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\Files\Cache;

use OC\DB\QueryBuilder\QueryBuilder;
use OC\SystemConfig;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\ILogger;

/**
 * Query builder with commonly used helpers for filecache queries
 */
class CacheQueryBuilder extends QueryBuilder {
	private $cache;
	private $alias = null;

	public function __construct(IDBConnection $connection, SystemConfig $systemConfig, ILogger $logger, Cache $cache) {
		parent::__construct($connection, $systemConfig, $logger);

		$this->cache = $cache;
	}

	public function selectFileCache(string $alias = null) {
		$name = $alias ? $alias : 'filecache';
		$this->select("$name.fileid", 'storage', 'path', 'path_hash', "$name.parent", 'name', 'mimetype', 'mimepart', 'size', 'mtime',
			'storage_mtime', 'encrypted', 'etag', 'permissions', 'checksum', 'metadata_etag', 'creation_time', 'upload_time')
			->from('filecache', $name)
			->leftJoin($name, 'filecache_extended', 'fe', $this->expr()->eq("$name.fileid", 'fe.fileid'));

		$this->alias = $name;

		return $this;
	}

	public function whereStorageId() {
		$this->andWhere($this->expr()->eq('storage', $this->createNamedParameter($this->cache->getNumericStorageId(), IQueryBuilder::PARAM_INT)));

		return $this;
	}

	public function whereFileId(int $fileId) {
		$alias = $this->alias;
		if ($alias) {
			$alias .= '.';
		} else {
			$alias = '';
		}

		$this->andWhere($this->expr()->eq("{$alias}fileid", $this->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));

		return $this;
	}

	public function wherePath(string $path) {
		$this->andWhere($this->expr()->eq('path_hash', $this->createNamedParameter(md5($path))));

		return $this;
	}

	public function whereParent(int $parent) {
		$alias = $this->alias;
		if ($alias) {
			$alias .= '.';
		} else {
			$alias = '';
		}

		$this->andWhere($this->expr()->eq("{$alias}parent", $this->createNamedParameter($parent, IQueryBuilder::PARAM_INT)));

		return $this;
	}

	public function whereParentIn(array $parents) {
		$alias = $this->alias;
		if ($alias) {
			$alias .= '.';
		} else {
			$alias = '';
		}

		$this->andWhere($this->expr()->in("{$alias}parent", $this->createNamedParameter($parents, IQueryBuilder::PARAM_INT_ARRAY)));

		return $this;
	}
}
