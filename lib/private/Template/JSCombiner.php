<?php
/**
 * @copyright 2017, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OC\Template;

use OC\SystemConfig;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\ILogger;
use OCP\IURLGenerator;

class JSCombiner {

	/** @var IAppData */
	protected $appData;

	/** @var IURLGenerator */
	protected $urlGenerator;

	/** @var ICache */
	protected $depsCache;

	/** @var SystemConfig */
	protected $config;

	/** @var ILogger */
	protected $logger;

	/** @var ICacheFactory */
	private $cacheFactory;

	/**
	 * @param IAppData $appData
	 * @param IURLGenerator $urlGenerator
	 * @param ICacheFactory $cacheFactory
	 * @param SystemConfig $config
	 * @param ILogger $logger
	 */
	public function __construct(IAppData $appData,
								IURLGenerator $urlGenerator,
								ICacheFactory $cacheFactory,
								SystemConfig $config,
								ILogger $logger) {
		$this->appData = $appData;
		$this->urlGenerator = $urlGenerator;
		$this->cacheFactory = $cacheFactory;
		$this->depsCache = $this->cacheFactory->createDistributed('JS-' . md5($this->urlGenerator->getBaseUrl()));
		$this->config = $config;
		$this->logger = $logger;
	}

	/**
	 * @param string $root
	 * @param string $file
	 * @param string $app
	 * @return bool
	 */
	public function process($root, $file, $app) {
		if ($this->config->getValue('debug') || !$this->config->getValue('installed')) {
			return false;
		}

		$path = explode('/', $root . '/' . $file);

		$fileName = array_pop($path);
		$path = implode('/', $path);

		try {
			$folder = $this->appData->getFolder($app);
		} catch (NotFoundException $e) {
			// creating css appdata folder
			$folder = $this->appData->newFolder($app);
		}

		if ($this->isCached($fileName, $folder)) {
			return true;
		}
		return $this->cache($path, $fileName, $folder);
	}

	/**
	 * @param string $fileName
	 * @param ISimpleFolder $folder
	 * @return bool
	 */
	protected function isCached($fileName, ISimpleFolder $folder) {
		$fileName = str_replace('.json', '.js', $fileName);

		if (!$folder->fileExists($fileName)) {
			return false;
		}

		$fileName = $fileName . '.deps';
		try {
			$deps = $this->depsCache->get($folder->getName() . '-' . $fileName);
			$fromCache = true;
			if ($deps === null || $deps === '') {
				$fromCache = false;
				$depFile = $folder->getFile($fileName);
				$deps = $depFile->getContent();
			}

			// check again
			if ($deps === null || $deps === '') {
				$this->logger->info('JSCombiner: deps file empty: ' . $fileName);
				return false;
			}

			$deps = json_decode($deps, true);

			if ($deps === null) {
				return false;
			}

			foreach ($deps as $file => $mtime) {
				if (!file_exists($file) || filemtime($file) > $mtime) {
					return false;
				}
			}

			if ($fromCache === false) {
				$this->depsCache->set($folder->getName() . '-' . $fileName, json_encode($deps));
			}

			return true;
		} catch (NotFoundException $e) {
			return false;
		}
	}

	/**
	 * @param string $path
	 * @param string $fileName
	 * @param ISimpleFolder $folder
	 * @return bool
	 */
	protected function cache($path, $fileName, ISimpleFolder $folder) {
		$deps = [];
		$fullPath = $path . '/' . $fileName;
		$data = json_decode(file_get_contents($fullPath));
		$deps[$fullPath] = filemtime($fullPath);

		$res = '';
		foreach ($data as $file) {
			$filePath = $path . '/' . $file;

			if (is_file($filePath)) {
				$res .= file_get_contents($filePath);
				$res .= PHP_EOL . PHP_EOL;
				$deps[$filePath] = filemtime($filePath);
			}
		}

		$fileName = str_replace('.json', '.js', $fileName);
		try {
			$cachedfile = $folder->getFile($fileName);
		} catch (NotFoundException $e) {
			$cachedfile = $folder->newFile($fileName);
		}

		$depFileName = $fileName . '.deps';
		try {
			$depFile = $folder->getFile($depFileName);
		} catch (NotFoundException $e) {
			$depFile = $folder->newFile($depFileName);
		}

		try {
			$gzipFile = $folder->getFile($fileName . '.gzip'); # Safari doesn't like .gz
		} catch (NotFoundException $e) {
			$gzipFile = $folder->newFile($fileName . '.gzip'); # Safari doesn't like .gz
		}

		try {
			$cachedfile->putContent($res);
			$deps = json_encode($deps);
			$depFile->putContent($deps);
			$this->depsCache->set($folder->getName() . '-' . $depFileName, $deps);
			$gzipFile->putContent(gzencode($res, 9));
			$this->logger->debug('JSCombiner: successfully cached: ' . $fileName);
			return true;
		} catch (NotPermittedException $e) {
			$this->logger->error('JSCombiner: unable to cache: ' . $fileName);
			return false;
		}
	}

	/**
	 * @param string $appName
	 * @param string $fileName
	 * @return string
	 */
	public function getCachedJS($appName, $fileName) {
		$tmpfileLoc = explode('/', $fileName);
		$fileName = array_pop($tmpfileLoc);
		$fileName = str_replace('.json', '.js', $fileName);

		return substr($this->urlGenerator->linkToRoute('core.Js.getJs', ['fileName' => $fileName, 'appName' => $appName]), strlen(\OC::$WEBROOT) + 1);
	}

	/**
	 * @param string $root
	 * @param string $file
	 * @return string[]
	 */
	public function getContent($root, $file) {
		/** @var array $data */
		$data = json_decode(file_get_contents($root . '/' . $file));
		if (!is_array($data)) {
			return [];
		}

		$path = explode('/', $file);
		array_pop($path);
		$path = implode('/', $path);

		$result = [];
		foreach ($data as $f) {
			$result[] = $path . '/' . $f;
		}

		return $result;
	}


	/**
	 * Clear cache with combined javascript files
	 *
	 * @throws NotFoundException
	 */
	public function resetCache() {
		$this->cacheFactory->createDistributed('JS-')->clear();
		$appDirectory = $this->appData->getDirectoryListing();
		foreach ($appDirectory as $folder) {
			foreach ($folder->getDirectoryListing() as $file) {
				$file->delete();
			}
		}
	}
}
