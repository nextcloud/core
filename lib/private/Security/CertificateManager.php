<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OC\Security;

use OC\Files\Filesystem;
use OCP\ICertificateManager;
use OCP\IConfig;
use OCP\ILogger;
use OCP\Security\ISecureRandom;

/**
 * Manage trusted certificates for users
 */
class CertificateManager implements ICertificateManager {
	/**
	 * @var \OC\Files\View
	 */
	protected $view;

	/**
	 * @var IConfig
	 */
	protected $config;

	/**
	 * @var ILogger
	 */
	protected $logger;

	/** @var ISecureRandom */
	protected $random;

	/**
	 * @param \OC\Files\View $view relative to data/
	 * @param IConfig $config
	 * @param ILogger $logger
	 * @param ISecureRandom $random
	 */
	public function __construct(\OC\Files\View $view,
								IConfig $config,
								ILogger $logger,
								ISecureRandom $random) {
		$this->view = $view;
		$this->config = $config;
		$this->logger = $logger;
		$this->random = $random;
	}

	/**
	 * Returns all certificates trusted by the user
	 *
	 * @return \OCP\ICertificate[]
	 */
	public function listCertificates() {
		if (!$this->config->getSystemValue('installed', false)) {
			return [];
		}

		$path = $this->getPathToCertificates() . 'uploads/';
		if (!$this->view->is_dir($path)) {
			return [];
		}
		$result = [];
		$handle = $this->view->opendir($path);
		if (!is_resource($handle)) {
			return [];
		}
		while (false !== ($file = readdir($handle))) {
			if ($file != '.' && $file != '..') {
				try {
					$result[] = new Certificate($this->view->file_get_contents($path . $file), $file);
				} catch (\Exception $e) {
				}
			}
		}
		closedir($handle);
		return $result;
	}

	private function hasCertificates(): bool {
		if (!$this->config->getSystemValue('installed', false)) {
			return false;
		}

		$path = $this->getPathToCertificates() . 'uploads/';
		if (!$this->view->is_dir($path)) {
			return false;
		}
		$result = [];
		$handle = $this->view->opendir($path);
		if (!is_resource($handle)) {
			return false;
		}
		while (false !== ($file = readdir($handle))) {
			if ($file !== '.' && $file !== '..') {
				return true;
			}
		}
		closedir($handle);
		return false;
	}

	/**
	 * create the certificate bundle of all trusted certificated
	 */
	public function createCertificateBundle() {
		$path = $this->getPathToCertificates();
		$certs = $this->listCertificates();

		if (!$this->view->file_exists($path)) {
			$this->view->mkdir($path);
		}

		$defaultCertificates = file_get_contents(\OC::$SERVERROOT . '/resources/config/ca-bundle.crt');
		if (strlen($defaultCertificates) < 1024) { // sanity check to verify that we have some content for our bundle
			// log as exception so we have a stacktrace
			$this->logger->logException(new \Exception('Shipped ca-bundle is empty, refusing to create certificate bundle'));
			return;
		}

		$certPath = $path . 'rootcerts.crt';
		$tmpPath = $certPath . '.tmp' . $this->random->generate(10, ISecureRandom::CHAR_DIGITS);
		$fhCerts = $this->view->fopen($tmpPath, 'w');

		// Write user certificates
		foreach ($certs as $cert) {
			$file = $path . '/uploads/' . $cert->getName();
			$data = $this->view->file_get_contents($file);
			if (strpos($data, 'BEGIN CERTIFICATE')) {
				fwrite($fhCerts, $data);
				fwrite($fhCerts, "\r\n");
			}
		}

		// Append the default certificates
		fwrite($fhCerts, $defaultCertificates);

		// Append the system certificate bundle
		$systemBundle = $this->getCertificateBundle();
		if ($systemBundle !== $certPath && $this->view->file_exists($systemBundle)) {
			$systemCertificates = $this->view->file_get_contents($systemBundle);
			fwrite($fhCerts, $systemCertificates);
		}

		fclose($fhCerts);

		$this->view->rename($tmpPath, $certPath);
	}

	/**
	 * Save the certificate and re-generate the certificate bundle
	 *
	 * @param string $certificate the certificate data
	 * @param string $name the filename for the certificate
	 * @return \OCP\ICertificate
	 * @throws \Exception If the certificate could not get added
	 */
	public function addCertificate($certificate, $name) {
		if (!Filesystem::isValidPath($name) or Filesystem::isFileBlacklisted($name)) {
			throw new \Exception('Filename is not valid');
		}

		$dir = $this->getPathToCertificates() . 'uploads/';
		if (!$this->view->file_exists($dir)) {
			$this->view->mkdir($dir);
		}

		try {
			$file = $dir . $name;
			$certificateObject = new Certificate($certificate, $name);
			$this->view->file_put_contents($file, $certificate);
			$this->createCertificateBundle();
			return $certificateObject;
		} catch (\Exception $e) {
			throw $e;
		}
	}

	/**
	 * Remove the certificate and re-generate the certificate bundle
	 *
	 * @param string $name
	 * @return bool
	 */
	public function removeCertificate($name) {
		if (!Filesystem::isValidPath($name)) {
			return false;
		}
		$path = $this->getPathToCertificates() . 'uploads/';
		if ($this->view->file_exists($path . $name)) {
			$this->view->unlink($path . $name);
			$this->createCertificateBundle();
		}
		return true;
	}

	/**
	 * Get the path to the certificate bundle
	 *
	 * @return string
	 */
	public function getCertificateBundle() {
		return $this->getPathToCertificates() . 'rootcerts.crt';
	}

	/**
	 * Get the full local path to the certificate bundle
	 *
	 * @return string
	 */
	public function getAbsoluteBundlePath() {
		if (!$this->hasCertificates()) {
			return \OC::$SERVERROOT . '/resources/config/ca-bundle.crt';
		}

		if ($this->needsRebundling()) {
			$this->createCertificateBundle();
		}

		return $this->view->getLocalFile($this->getCertificateBundle());
	}

	/**
	 * @return string
	 */
	private function getPathToCertificates() {
		return '/files_external/';
	}

	/**
	 * Check if we need to re-bundle the certificates because one of the sources has updated
	 *
	 * @return bool
	 */
	private function needsRebundling() {
		$targetBundle = $this->getCertificateBundle();
		if (!$this->view->file_exists($targetBundle)) {
			return true;
		}

		$sourceMTime = $this->getFilemtimeOfCaBundle();
		return $sourceMTime > $this->view->filemtime($targetBundle);
	}

	/**
	 * get mtime of ca-bundle shipped by Nextcloud
	 *
	 * @return int
	 */
	protected function getFilemtimeOfCaBundle() {
		return filemtime(\OC::$SERVERROOT . '/resources/config/ca-bundle.crt');
	}
}
