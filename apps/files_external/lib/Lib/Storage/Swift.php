<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Benjamin Liles <benliles@arch.tamu.edu>
 * @author Christian Berendt <berendt@b1-systems.de>
 * @author Christopher Bartz <bartz@dkrz.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Felix Moeller <mail@felixmoeller.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Martin Mattel <martin.mattel@diemattels.at>
 * @author Michael Zamot <michael@zamot.io>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Philipp Kapfer <philipp.kapfer@gmx.at>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Tim Dettrick <t.dettrick@uq.edu.au>
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

namespace OCA\Files_External\Lib\Storage;

use GuzzleHttp\Psr7\Uri;
use Icewind\Streams\CallbackWrapper;
use Icewind\Streams\IteratorDirectory;
use OC\Files\ObjectStore\SwiftFactory;
use OCP\Files\StorageBadConfigException;
use OCP\ILogger;
use OpenStack\Common\Error\BadResponseError;
use OpenStack\ObjectStore\v1\Models\StorageObject;

class Swift extends \OC\Files\Storage\Common {
	/** @var SwiftFactory */
	private $connectionFactory;
	/**
	 * @var \OpenStack\ObjectStore\v1\Models\Container
	 */
	private $container;
	/**
	 * @var string
	 */
	private $bucket;
	/**
	 * Connection parameters
	 *
	 * @var array
	 */
	private $params;

	/** @var string */
	private $id;

	/** @var \OC\Files\ObjectStore\Swift */
	private $objectStore;

	/**
	 * Key value cache mapping path to data object. Maps path to
	 * \OpenCloud\OpenStack\ObjectStorage\Resource\DataObject for existing
	 * paths and path to false for not existing paths.
	 *
	 * @var \OCP\ICache
	 */
	private $objectCache;

	/**
	 * @param string $path
	 * @return mixed|string
	 */
	private function normalizePath(string $path) {
		$path = trim($path, '/');

		if (!$path) {
			$path = '.';
		}

		$path = str_replace('#', '%23', $path);

		return $path;
	}

	public const SUBCONTAINER_FILE = '.subcontainers';

	/**
	 * translate directory path to container name
	 *
	 * @param string $path
	 * @return string
	 */

	/**
	 * Fetches an object from the API.
	 * If the object is cached already or a
	 * failed "doesn't exist" response was cached,
	 * that one will be returned.
	 *
	 * @param string $path
	 * @return StorageObject|bool object
	 * or false if the object did not exist
	 * @throws \OCP\Files\StorageAuthException
	 * @throws \OCP\Files\StorageNotAvailableException
	 */
	private function fetchObject(string $path) {
		if ($this->objectCache->hasKey($path)) {
			// might be "false" if object did not exist from last check
			return $this->objectCache->get($path);
		}
		try {
			$object = $this->getContainer()->getObject($path);
			$object->retrieve();
			$this->objectCache->set($path, $object);
			return $object;
		} catch (BadResponseError $e) {
			// Expected response is "404 Not Found", so only log if it isn't
			if ($e->getResponse()->getStatusCode() !== 404) {
				\OC::$server->getLogger()->logException($e, [
					'level' => ILogger::ERROR,
					'app' => 'files_external',
				]);
			}
			$this->objectCache->set($path, false);
			return false;
		}
	}

	/**
	 * Returns whether the given path exists.
	 *
	 * @param string $path
	 *
	 * @return bool true if the object exist, false otherwise
	 * @throws \OCP\Files\StorageAuthException
	 * @throws \OCP\Files\StorageNotAvailableException
	 */
	private function doesObjectExist($path) {
		return $this->fetchObject($path) !== false;
	}

	public function __construct($params) {
		if ((empty($params['key']) and empty($params['password']))
			or (empty($params['user']) && empty($params['userid'])) or empty($params['bucket'])
			or empty($params['region'])
		) {
			throw new StorageBadConfigException("API Key or password, Username, Bucket and Region have to be configured.");
		}

		$user = $params['user'];
		$this->id = 'swift::' . $user . md5($params['bucket']);

		$bucketUrl = new Uri($params['bucket']);
		if ($bucketUrl->getHost()) {
			$params['bucket'] = basename($bucketUrl->getPath());
			$params['endpoint_url'] = (string)$bucketUrl->withPath(dirname($bucketUrl->getPath()));
		}

		if (empty($params['url'])) {
			$params['url'] = 'https://identity.api.rackspacecloud.com/v2.0/';
		}

		if (empty($params['service_name'])) {
			$params['service_name'] = 'cloudFiles';
		}

		$params['autocreate'] = true;

		if (isset($params['domain'])) {
			$params['user'] = [
				'name' => $params['user'],
				'password' => $params['password'],
				'domain' => [
					'name' => $params['domain'],
				]
			];
		}

		$this->params = $params;
		// FIXME: private class...
		$this->objectCache = new \OC\Cache\CappedMemoryCache();
		$this->connectionFactory = new SwiftFactory(
			\OC::$server->getMemCacheFactory()->createDistributed('swift/'),
			$this->params,
			\OC::$server->getLogger()
		);
		$this->objectStore = new \OC\Files\ObjectStore\Swift($this->params, $this->connectionFactory);
		$this->bucket = $params['bucket'];
	}

	public function mkdir($path) {
		$path = $this->normalizePath($path);

		if ($this->is_dir($path)) {
			return false;
		}

		if ($path !== '.') {
			$path .= '/';
		}

		try {
			$this->getContainer()->createObject([
				'name' => $path,
				'content' => '',
				'headers' => ['content-type' => 'httpd/unix-directory']
			]);
			// invalidate so that the next access gets the real object
			// with all properties
			$this->objectCache->remove($path);
		} catch (BadResponseError $e) {
			\OC::$server->getLogger()->logException($e, [
				'level' => ILogger::ERROR,
				'app' => 'files_external',
			]);
			return false;
		}

		return true;
	}

	public function file_exists($path) {
		$path = $this->normalizePath($path);

		if ($path !== '.' && $this->is_dir($path)) {
			$path .= '/';
		}

		return $this->doesObjectExist($path);
	}

	public function rmdir($path) {
		$path = $this->normalizePath($path);

		if (!$this->is_dir($path) || !$this->isDeletable($path)) {
			return false;
		}

		$dh = $this->opendir($path);
		while ($file = readdir($dh)) {
			if (\OC\Files\Filesystem::isIgnoredDir($file)) {
				continue;
			}

			if ($this->is_dir($path . '/' . $file)) {
				$this->rmdir($path . '/' . $file);
			} else {
				$this->unlink($path . '/' . $file);
			}
		}

		try {
			$this->objectStore->deleteObject($path . '/');
			$this->objectCache->remove($path . '/');
		} catch (BadResponseError $e) {
			\OC::$server->getLogger()->logException($e, [
				'level' => ILogger::ERROR,
				'app' => 'files_external',
			]);
			return false;
		}

		return true;
	}

	public function opendir($path) {
		$path = $this->normalizePath($path);

		if ($path === '.') {
			$path = '';
		} else {
			$path .= '/';
		}

//		$path = str_replace('%23', '#', $path); // the prefix is sent as a query param, so revert the encoding of #

		try {
			$files = [];
			$objects = $this->getContainer()->listObjects([
				'prefix' => $path,
				'delimiter' => '/'
			]);

			/** @var StorageObject $object */
			foreach ($objects as $object) {
				$file = basename($object->name);
				if ($file !== basename($path) && $file !== '.') {
					$files[] = $file;
				}
			}

			return IteratorDirectory::wrap($files);
		} catch (\Exception $e) {
			\OC::$server->getLogger()->logException($e, [
				'level' => ILogger::ERROR,
				'app' => 'files_external',
			]);
			return false;
		}
	}

	public function stat($path) {
		$path = $this->normalizePath($path);

		if ($path === '.') {
			$path = '';
		} elseif ($this->is_dir($path)) {
			$path .= '/';
		}

		try {
			$object = $this->fetchObject($path);
			if (!$object) {
				return false;
			}
		} catch (BadResponseError $e) {
			\OC::$server->getLogger()->logException($e, [
				'level' => ILogger::ERROR,
				'app' => 'files_external',
			]);
			return false;
		}

		$dateTime = $object->lastModified ? \DateTime::createFromFormat(\DateTime::RFC1123, $object->lastModified) : false;
		$mtime = $dateTime ? $dateTime->getTimestamp() : null;
		$objectMetadata = $object->getMetadata();
		if (isset($objectMetadata['timestamp'])) {
			$mtime = $objectMetadata['timestamp'];
		}

		if (!empty($mtime)) {
			$mtime = floor($mtime);
		}

		$stat = [];
		$stat['size'] = (int)$object->contentLength;
		$stat['mtime'] = $mtime;
		$stat['atime'] = time();
		return $stat;
	}

	public function filetype($path) {
		$path = $this->normalizePath($path);

		if ($path !== '.' && $this->doesObjectExist($path)) {
			return 'file';
		}

		if ($path !== '.') {
			$path .= '/';
		}

		if ($this->doesObjectExist($path)) {
			return 'dir';
		}
	}

	public function unlink($path) {
		$path = $this->normalizePath($path);

		if ($this->is_dir($path)) {
			return $this->rmdir($path);
		}

		try {
			$this->objectStore->deleteObject($path);
			$this->objectCache->remove($path);
			$this->objectCache->remove($path . '/');
		} catch (BadResponseError $e) {
			if ($e->getResponse()->getStatusCode() !== 404) {
				\OC::$server->getLogger()->logException($e, [
					'level' => ILogger::ERROR,
					'app' => 'files_external',
				]);
				throw $e;
			}
		}

		return true;
	}

	public function fopen($path, $mode) {
		$path = $this->normalizePath($path);

		switch ($mode) {
			case 'a':
			case 'ab':
			case 'a+':
				return false;
			case 'r':
			case 'rb':
				try {
					return $this->objectStore->readObject($path);
				} catch (BadResponseError $e) {
					\OC::$server->getLogger()->logException($e, [
						'level' => ILogger::ERROR,
						'app' => 'files_external',
					]);
					return false;
				}
			case 'w':
			case 'wb':
			case 'r+':
			case 'w+':
			case 'wb+':
			case 'x':
			case 'x+':
			case 'c':
			case 'c+':
				if (strrpos($path, '.') !== false) {
					$ext = substr($path, strrpos($path, '.'));
				} else {
					$ext = '';
				}
				$tmpFile = \OC::$server->getTempManager()->getTemporaryFile($ext);
				// Fetch existing file if required
				if ($mode[0] !== 'w' && $this->file_exists($path)) {
					if ($mode[0] === 'x') {
						// File cannot already exist
						return false;
					}
					$source = $this->fopen($path, 'r');
					file_put_contents($tmpFile, $source);
				}
				$handle = fopen($tmpFile, $mode);
				return CallbackWrapper::wrap($handle, null, null, function () use ($path, $tmpFile) {
					$this->writeBack($tmpFile, $path);
				});
		}
	}

	public function touch($path, $mtime = null) {
		$path = $this->normalizePath($path);
		if (is_null($mtime)) {
			$mtime = time();
		}
		$metadata = ['timestamp' => (string)$mtime];
		if ($this->file_exists($path)) {
			if ($this->is_dir($path) && $path !== '.') {
				$path .= '/';
			}

			$object = $this->fetchObject($path);
			if ($object->mergeMetadata($metadata)) {
				// invalidate target object to force repopulation on fetch
				$this->objectCache->remove($path);
			}
			return true;
		} else {
			$mimeType = \OC::$server->getMimeTypeDetector()->detectPath($path);
			$this->getContainer()->createObject([
				'name' => $path,
				'content' => '',
				'headers' => ['content-type' => 'httpd/unix-directory']
			]);
			// invalidate target object to force repopulation on fetch
			$this->objectCache->remove($path);
			return true;
		}
	}

	public function copy($path1, $path2) {
		$path1 = $this->normalizePath($path1);
		$path2 = $this->normalizePath($path2);

		$fileType = $this->filetype($path1);
		if ($fileType) {
			// make way
			$this->unlink($path2);
		}

		if ($fileType === 'file') {
			try {
				$source = $this->fetchObject($path1);
				$source->copy([
					'destination' => $this->bucket . '/' . $path2
				]);
				// invalidate target object to force repopulation on fetch
				$this->objectCache->remove($path2);
				$this->objectCache->remove($path2 . '/');
			} catch (BadResponseError $e) {
				\OC::$server->getLogger()->logException($e, [
					'level' => ILogger::ERROR,
					'app' => 'files_external',
				]);
				return false;
			}
		} elseif ($fileType === 'dir') {
			try {
				$source = $this->fetchObject($path1 . '/');
				$source->copy([
					'destination' => $this->bucket . '/' . $path2 . '/'
				]);
				// invalidate target object to force repopulation on fetch
				$this->objectCache->remove($path2);
				$this->objectCache->remove($path2 . '/');
			} catch (BadResponseError $e) {
				\OC::$server->getLogger()->logException($e, [
					'level' => ILogger::ERROR,
					'app' => 'files_external',
				]);
				return false;
			}

			$dh = $this->opendir($path1);
			while ($file = readdir($dh)) {
				if (\OC\Files\Filesystem::isIgnoredDir($file)) {
					continue;
				}

				$source = $path1 . '/' . $file;
				$target = $path2 . '/' . $file;
				$this->copy($source, $target);
			}
		} else {
			//file does not exist
			return false;
		}

		return true;
	}

	public function rename($path1, $path2) {
		$path1 = $this->normalizePath($path1);
		$path2 = $this->normalizePath($path2);

		$fileType = $this->filetype($path1);

		if ($fileType === 'dir' || $fileType === 'file') {
			// copy
			if ($this->copy($path1, $path2) === false) {
				return false;
			}

			// cleanup
			if ($this->unlink($path1) === false) {
				throw new \Exception('failed to remove original');
				$this->unlink($path2);
				return false;
			}

			return true;
		}

		return false;
	}

	public function getId() {
		return $this->id;
	}

	/**
	 * Returns the initialized object store container.
	 *
	 * @return \OpenStack\ObjectStore\v1\Models\Container
	 * @throws \OCP\Files\StorageAuthException
	 * @throws \OCP\Files\StorageNotAvailableException
	 */
	public function getContainer() {
		if (is_null($this->container)) {
			$this->container = $this->connectionFactory->getContainer();

			if (!$this->file_exists('.')) {
				$this->mkdir('.');
			}
		}
		return $this->container;
	}

	public function writeBack($tmpFile, $path) {
		$fileData = fopen($tmpFile, 'r');
		$this->objectStore->writeObject($path, $fileData);
		// invalidate target object to force repopulation on fetch
		$this->objectCache->remove($path);
		unlink($tmpFile);
	}

	public function hasUpdated($path, $time) {
		if ($this->is_file($path)) {
			return parent::hasUpdated($path, $time);
		}
		$path = $this->normalizePath($path);
		$dh = $this->opendir($path);
		$content = [];
		while (($file = readdir($dh)) !== false) {
			$content[] = $file;
		}
		if ($path === '.') {
			$path = '';
		}
		$cachedContent = $this->getCache()->getFolderContents($path);
		$cachedNames = array_map(function ($content) {
			return $content['name'];
		}, $cachedContent);
		sort($cachedNames);
		sort($content);
		return $cachedNames !== $content;
	}

	/**
	 * check if curl is installed
	 */
	public static function checkDependencies() {
		return true;
	}
}
