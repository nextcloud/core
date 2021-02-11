<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCA\Files_Sharing\External;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use OC\Files\Storage\DAV;
use OC\ForbiddenException;
use OCA\Files_Sharing\ISharedStorage;
use OCP\AppFramework\Http;
use OCP\Constants;
use OCP\Federation\ICloudId;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\IDisableEncryptionStorage;
use OCP\Files\StorageInvalidException;
use OCP\Files\StorageNotAvailableException;

class Storage extends DAV implements ISharedStorage, IDisableEncryptionStorage {
	/** @var ICloudId */
	private $cloudId;
	/** @var string */
	private $mountPoint;
	/** @var string */
	private $token;
	/** @var \OCP\ICacheFactory */
	private $memcacheFactory;
	/** @var \OCP\Http\Client\IClientService */
	private $httpClient;
	/** @var bool */
	private $updateChecked = false;

	/**
	 * @var \OCA\Files_Sharing\External\Manager
	 */
	private $manager;

	public function __construct($options) {
		$this->memcacheFactory = \OC::$server->getMemCacheFactory();
		$this->httpClient = $options['HttpClientService'];

		$this->manager = $options['manager'];
		$this->cloudId = $options['cloudId'];
		$discoveryService = \OC::$server->query(\OCP\OCS\IDiscoveryService::class);

		list($protocol, $remote) = explode('://', $this->cloudId->getRemote());
		if (strpos($remote, '/')) {
			list($host, $root) = explode('/', $remote, 2);
		} else {
			$host = $remote;
			$root = '';
		}
		$secure = $protocol === 'https';
		$federatedSharingEndpoints = $discoveryService->discover($this->cloudId->getRemote(), 'FEDERATED_SHARING');
		$webDavEndpoint = isset($federatedSharingEndpoints['webdav']) ? $federatedSharingEndpoints['webdav'] : '/public.php/webdav';
		$root = rtrim($root, '/') . $webDavEndpoint;
		$this->mountPoint = $options['mountpoint'];
		$this->token = $options['token'];

		parent::__construct([
			'secure' => $secure,
			'host' => $host,
			'root' => $root,
			'user' => $options['token'],
			'password' => (string)$options['password']
		]);
	}

	public function getWatcher($path = '', $storage = null) {
		if (!$storage) {
			$storage = $this;
		}
		if (!isset($this->watcher)) {
			$this->watcher = new Watcher($storage);
			$this->watcher->setPolicy(\OC\Files\Cache\Watcher::CHECK_ONCE);
		}
		return $this->watcher;
	}

	public function getRemoteUser() {
		return $this->cloudId->getUser();
	}

	public function getRemote() {
		return $this->cloudId->getRemote();
	}

	public function getMountPoint() {
		return $this->mountPoint;
	}

	public function getToken() {
		return $this->token;
	}

	public function getPassword() {
		return $this->password;
	}

	/**
	 * @brief get id of the mount point
	 * @return string
	 */
	public function getId() {
		return 'shared::' . md5($this->token . '@' . $this->getRemote());
	}

	public function getCache($path = '', $storage = null) {
		if (is_null($this->cache)) {
			$this->cache = new Cache($this, $this->cloudId);
		}
		return $this->cache;
	}

	/**
	 * @param string $path
	 * @param \OC\Files\Storage\Storage $storage
	 * @return \OCA\Files_Sharing\External\Scanner
	 */
	public function getScanner($path = '', $storage = null) {
		if (!$storage) {
			$storage = $this;
		}
		if (!isset($this->scanner)) {
			$this->scanner = new Scanner($storage);
		}
		return $this->scanner;
	}

	/**
	 * check if a file or folder has been updated since $time
	 *
	 * @param string $path
	 * @param int $time
	 * @throws \OCP\Files\StorageNotAvailableException
	 * @throws \OCP\Files\StorageInvalidException
	 * @return bool
	 */
	public function hasUpdated($path, $time) {
		// since for owncloud webdav servers we can rely on etag propagation we only need to check the root of the storage
		// because of that we only do one check for the entire storage per request
		if ($this->updateChecked) {
			return false;
		}
		$this->updateChecked = true;
		try {
			return parent::hasUpdated('', $time);
		} catch (StorageInvalidException $e) {
			// check if it needs to be removed
			$this->checkStorageAvailability();
			throw $e;
		} catch (StorageNotAvailableException $e) {
			// check if it needs to be removed or just temp unavailable
			$this->checkStorageAvailability();
			throw $e;
		}
	}

	public function test() {
		try {
			return parent::test();
		} catch (StorageInvalidException $e) {
			// check if it needs to be removed
			$this->checkStorageAvailability();
			throw $e;
		} catch (StorageNotAvailableException $e) {
			// check if it needs to be removed or just temp unavailable
			$this->checkStorageAvailability();
			throw $e;
		}
	}

	/**
	 * Check whether this storage is permanently or temporarily
	 * unavailable
	 *
	 * @throws \OCP\Files\StorageNotAvailableException
	 * @throws \OCP\Files\StorageInvalidException
	 */
	public function checkStorageAvailability() {
		// see if we can find out why the share is unavailable
		try {
			$this->getShareInfo();
		} catch (NotFoundException $e) {
			// a 404 can either mean that the share no longer exists or there is no Nextcloud on the remote
			if ($this->testRemote()) {
				// valid Nextcloud instance means that the public share no longer exists
				// since this is permanent (re-sharing the file will create a new token)
				// we remove the invalid storage
				$this->manager->removeShare($this->mountPoint);
				$this->manager->getMountManager()->removeMount($this->mountPoint);
				throw new StorageInvalidException();
			} else {
				// Nextcloud instance is gone, likely to be a temporary server configuration error
				throw new StorageNotAvailableException();
			}
		} catch (ForbiddenException $e) {
			// auth error, remove share for now (provide a dialog in the future)
			$this->manager->removeShare($this->mountPoint);
			$this->manager->getMountManager()->removeMount($this->mountPoint);
			throw new StorageInvalidException();
		} catch (\GuzzleHttp\Exception\ConnectException $e) {
			throw new StorageNotAvailableException();
		} catch (\GuzzleHttp\Exception\RequestException $e) {
			throw new StorageNotAvailableException();
		} catch (\Exception $e) {
			throw $e;
		}
	}

	public function file_exists($path) {
		if ($path === '') {
			return true;
		} else {
			return parent::file_exists($path);
		}
	}

	/**
	 * check if the configured remote is a valid federated share provider
	 *
	 * @return bool
	 */
	protected function testRemote() {
		try {
			return $this->testRemoteUrl($this->getRemote() . '/ocs-provider/index.php')
				|| $this->testRemoteUrl($this->getRemote() . '/ocs-provider/')
				|| $this->testRemoteUrl($this->getRemote() . '/status.php');
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * @param string $url
	 * @return bool
	 */
	private function testRemoteUrl($url) {
		$cache = $this->memcacheFactory->createDistributed('files_sharing_remote_url');
		if ($cache->hasKey($url)) {
			return (bool)$cache->get($url);
		}

		$client = $this->httpClient->newClient();
		try {
			$result = $client->get($url, [
				'timeout' => 10,
				'connect_timeout' => 10,
			])->getBody();
			$data = json_decode($result);
			$returnValue = (is_object($data) && !empty($data->version));
		} catch (ConnectException $e) {
			$returnValue = false;
		} catch (ClientException $e) {
			$returnValue = false;
		} catch (RequestException $e) {
			$returnValue = false;
		}

		$cache->set($url, $returnValue, 60 * 60 * 24);
		return $returnValue;
	}

	/**
	 * Whether the remote is an ownCloud/Nextcloud, used since some sharing features are not
	 * standardized. Let's use this to detect whether to use it.
	 *
	 * @return bool
	 */
	public function remoteIsOwnCloud() {
		if (defined('PHPUNIT_RUN') || !$this->testRemoteUrl($this->getRemote() . '/status.php')) {
			return false;
		}
		return true;
	}

	/**
	 * @return mixed
	 * @throws ForbiddenException
	 * @throws NotFoundException
	 * @throws \Exception
	 */
	public function getShareInfo() {
		$remote = $this->getRemote();
		$token = $this->getToken();
		$password = $this->getPassword();

		// If remote is not an ownCloud do not try to get any share info
		if (!$this->remoteIsOwnCloud()) {
			return ['status' => 'unsupported'];
		}

		$url = rtrim($remote, '/') . '/index.php/apps/files_sharing/shareinfo?t=' . $token;

		// TODO: DI
		$client = \OC::$server->getHTTPClientService()->newClient();
		try {
			$response = $client->post($url, [
				'body' => ['password' => $password],
				'timeout' => 10,
				'connect_timeout' => 10,
			]);
		} catch (\GuzzleHttp\Exception\RequestException $e) {
			if ($e->getCode() === Http::STATUS_UNAUTHORIZED || $e->getCode() === Http::STATUS_FORBIDDEN) {
				throw new ForbiddenException();
			}
			if ($e->getCode() === Http::STATUS_NOT_FOUND) {
				throw new NotFoundException();
			}
			// throw this to be on the safe side: the share will still be visible
			// in the UI in case the failure is intermittent, and the user will
			// be able to decide whether to remove it if it's really gone
			throw new StorageNotAvailableException();
		}

		return json_decode($response->getBody(), true);
	}

	public function getOwner($path) {
		return $this->cloudId->getDisplayId();
	}

	public function isSharable($path) {
		if (\OCP\Util::isSharingDisabledForUser() || !\OC\Share\Share::isResharingAllowed()) {
			return false;
		}
		return ($this->getPermissions($path) & Constants::PERMISSION_SHARE);
	}

	public function getPermissions($path) {
		$response = $this->propfind($path);
		// old federated sharing permissions
		if (isset($response['{http://open-collaboration-services.org/ns}share-permissions'])) {
			$permissions = $response['{http://open-collaboration-services.org/ns}share-permissions'];
		} elseif (isset($response['{http://open-cloud-mesh.org/ns}share-permissions'])) {
			// permissions provided by the OCM API
			$permissions = $this->ocmPermissions2ncPermissions($response['{http://open-collaboration-services.org/ns}share-permissions'], $path);
		} else {
			// use default permission if remote server doesn't provide the share permissions
			$permissions = $this->getDefaultPermissions($path);
		}

		return $permissions;
	}

	public function needsPartFile() {
		return false;
	}

	/**
	 * translate OCM Permissions to Nextcloud permissions
	 *
	 * @param string $ocmPermissions json encoded OCM permissions
	 * @param string $path path to file
	 * @return int
	 */
	protected function ocmPermissions2ncPermissions($ocmPermissions, $path) {
		try {
			$ocmPermissions = json_decode($ocmPermissions);
			$ncPermissions = 0;
			foreach ($ocmPermissions as $permission) {
				switch (strtolower($permission)) {
					case 'read':
						$ncPermissions += Constants::PERMISSION_READ;
						break;
					case 'write':
						$ncPermissions += Constants::PERMISSION_CREATE + Constants::PERMISSION_UPDATE;
						break;
					case 'share':
						$ncPermissions += Constants::PERMISSION_SHARE;
						break;
					default:
						throw new \Exception();
				}
			}
		} catch (\Exception $e) {
			$ncPermissions = $this->getDefaultPermissions($path);
		}

		return $ncPermissions;
	}

	/**
	 * calculate default permissions in case no permissions are provided
	 *
	 * @param $path
	 * @return int
	 */
	protected function getDefaultPermissions($path) {
		if ($this->is_dir($path)) {
			$permissions = Constants::PERMISSION_ALL;
		} else {
			$permissions = Constants::PERMISSION_ALL & ~Constants::PERMISSION_CREATE;
		}

		return $permissions;
	}
}
