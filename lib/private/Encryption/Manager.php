<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OC\Encryption;

use OC\Encryption\Keys\Storage;
use OC\Files\Filesystem;
use OC\Files\View;
use OC\Memcache\ArrayCache;
use OC\ServiceUnavailableException;
use OCP\Encryption\IEncryptionModule;
use OCP\Encryption\IManager;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ILogger;

class Manager implements IManager {

	/** @var array */
	protected $encryptionModules;

	/** @var IConfig */
	protected $config;

	/** @var ILogger */
	protected $logger;

	/** @var Il10n */
	protected $l;

	/** @var View  */
	protected $rootView;

	/** @var Util  */
	protected $util;

	/** @var ArrayCache  */
	protected $arrayCache;

	/**
	 * @param IConfig $config
	 * @param ILogger $logger
	 * @param IL10N $l10n
	 * @param View $rootView
	 * @param Util $util
	 * @param ArrayCache $arrayCache
	 */
	public function __construct(IConfig $config, ILogger $logger, IL10N $l10n, View $rootView, Util $util, ArrayCache $arrayCache) {
		$this->encryptionModules = [];
		$this->config = $config;
		$this->logger = $logger;
		$this->l = $l10n;
		$this->rootView = $rootView;
		$this->util = $util;
		$this->arrayCache = $arrayCache;
	}

	/**
	 * Check if encryption is enabled
	 *
	 * @return bool true if enabled, false if not
	 */
	public function isEnabled() {

		$installed = $this->config->getSystemValue('installed', false);
		if (!$installed) {
			return false;
		}

		$enabled = $this->config->getAppValue('core', 'encryption_enabled', 'no');
		return $enabled === 'yes';
	}

	/**
	 * check if new encryption is ready
	 *
	 * @return bool
	 * @throws ServiceUnavailableException
	 */
	public function isReady() {

		if ($this->isKeyStorageReady() === false) {
			throw new ServiceUnavailableException('Key Storage is not ready');
		}

		return true;
	}

	/**
	 * @param string $user
	 */
	public function isReadyForUser($user) {
		if (!$this->isReady()) {
			return false;
		}

		foreach ($this->getEncryptionModules() as $module) {
			/** @var IEncryptionModule $m */
			$m = call_user_func($module['callback']);
			if (!$m->isReadyForUser($user)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Registers an callback function which must return an encryption module instance
	 *
	 * @param string $id
	 * @param string $displayName
	 * @param callable $callback
	 * @throws Exceptions\ModuleAlreadyExistsException
	 */
	public function registerEncryptionModule($id, $displayName, callable $callback) {

		if (isset($this->encryptionModules[$id])) {
			throw new Exceptions\ModuleAlreadyExistsException($id, $displayName);
		}

		$this->encryptionModules[$id] = [
			'id' => $id,
			'displayName' => $displayName,
			'callback' => $callback,
		];

		$defaultEncryptionModuleId = $this->getDefaultEncryptionModuleId();

		if (empty($defaultEncryptionModuleId)) {
			$this->setDefaultEncryptionModule($id);
		}
	}

	/**
	 * Unregisters an encryption module
	 *
	 * @param string $moduleId
	 */
	public function unregisterEncryptionModule($moduleId) {
		unset($this->encryptionModules[$moduleId]);
	}

	/**
	 * get a list of all encryption modules
	 *
	 * @return array [id => ['id' => $id, 'displayName' => $displayName, 'callback' => callback]]
	 */
	public function getEncryptionModules() {
		return $this->encryptionModules;
	}

	/**
	 * get a specific encryption module
	 *
	 * @param string $moduleId
	 * @return IEncryptionModule
	 * @throws Exceptions\ModuleDoesNotExistsException
	 */
	public function getEncryptionModule($moduleId = '') {
		if (!empty($moduleId)) {
			if (isset($this->encryptionModules[$moduleId])) {
				return call_user_func($this->encryptionModules[$moduleId]['callback']);
			} else {
				$message = "Module with ID: $moduleId does not exist.";
				$hint = $this->l->t('Module with ID: %s does not exist. Please enable it in your apps settings or contact your administrator.', [$moduleId]);
				throw new Exceptions\ModuleDoesNotExistsException($message, $hint);
			}
		} else {
			return $this->getDefaultEncryptionModule();
		}
	}

	/**
	 * get default encryption module
	 *
	 * @return \OCP\Encryption\IEncryptionModule
	 * @throws Exceptions\ModuleDoesNotExistsException
	 */
	protected function getDefaultEncryptionModule() {
		$defaultModuleId = $this->getDefaultEncryptionModuleId();
		if (!empty($defaultModuleId)) {
			if (isset($this->encryptionModules[$defaultModuleId])) {
				return call_user_func($this->encryptionModules[$defaultModuleId]['callback']);
			} else {
				$message = 'Default encryption module not loaded';
				throw new Exceptions\ModuleDoesNotExistsException($message);
			}
		} else {
			$message = 'No default encryption module defined';
			throw new Exceptions\ModuleDoesNotExistsException($message);
		}

	}

	/**
	 * set default encryption module Id
	 *
	 * @param string $moduleId
	 * @return bool
	 */
	public function setDefaultEncryptionModule($moduleId) {
		try {
			$this->getEncryptionModule($moduleId);
		} catch (\Exception $e) {
			return false;
		}

		$this->config->setAppValue('core', 'default_encryption_module', $moduleId);
		return true;
	}

	/**
	 * get default encryption module Id
	 *
	 * @return string
	 */
	public function getDefaultEncryptionModuleId() {
		return $this->config->getAppValue('core', 'default_encryption_module');
	}

	/**
	 * Add storage wrapper
	 */
	public function setupStorage() {
		// If encryption is disabled and there are no loaded modules it makes no sense to load the wrapper
		if (!empty($this->encryptionModules) || $this->isEnabled()) {
			$encryptionWrapper = new EncryptionWrapper($this->arrayCache, $this, $this->logger);
			Filesystem::addStorageWrapper('oc_encryption', [$encryptionWrapper, 'wrapStorage'], 2);
		}
	}


	/**
	 * check if key storage is ready
	 *
	 * @return bool
	 */
	protected function isKeyStorageReady() {

		$rootDir = $this->util->getKeyStorageRoot();

		// the default root is always valid
		if ($rootDir === '') {
			return true;
		}

		// check if key storage is mounted correctly
		if ($this->rootView->file_exists($rootDir . '/' . Storage::KEY_STORAGE_MARKER)) {
			return true;
		}

		return false;
	}


}
