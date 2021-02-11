<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @copyright Copyright (c) 2016, Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Brice Maron <brice@bmaron.net>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Frank Karlitschek <frank@karlitschek.de>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Kamil Domanski <kdomanski@kdemail.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Markus Staab <markus.staab@redaxo.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author root "root@oc.(none)"
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Thomas Tanghus <thomas@tanghus.net>
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

namespace OC;

use Doctrine\DBAL\Exception\TableExistsException;
use OC\App\AppStore\Bundles\Bundle;
use OC\App\AppStore\Fetcher\AppFetcher;
use OC\AppFramework\Bootstrap\Coordinator;
use OC\Archive\TAR;
use OC\DB\Connection;
use OC_App;
use OC_DB;
use OC_Helper;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\ILogger;
use OCP\ITempManager;
use phpseclib\File\X509;

/**
 * This class provides the functionality needed to install, update and remove apps
 */
class Installer {
	/** @var AppFetcher */
	private $appFetcher;
	/** @var IClientService */
	private $clientService;
	/** @var ITempManager */
	private $tempManager;
	/** @var ILogger */
	private $logger;
	/** @var IConfig */
	private $config;
	/** @var array - for caching the result of app fetcher */
	private $apps = null;
	/** @var bool|null - for caching the result of the ready status */
	private $isInstanceReadyForUpdates = null;
	/** @var bool */
	private $isCLI;

	/**
	 * @param AppFetcher $appFetcher
	 * @param IClientService $clientService
	 * @param ITempManager $tempManager
	 * @param ILogger $logger
	 * @param IConfig $config
	 */
	public function __construct(
		AppFetcher $appFetcher,
		IClientService $clientService,
		ITempManager $tempManager,
		ILogger $logger,
		IConfig $config,
		bool $isCLI
	) {
		$this->appFetcher = $appFetcher;
		$this->clientService = $clientService;
		$this->tempManager = $tempManager;
		$this->logger = $logger;
		$this->config = $config;
		$this->isCLI = $isCLI;
	}

	/**
	 * Installs an app that is located in one of the app folders already
	 *
	 * @param string $appId App to install
	 * @param bool $forceEnable
	 * @throws \Exception
	 * @return string app ID
	 */
	public function installApp(string $appId, bool $forceEnable = false): string {
		$app = \OC_App::findAppInDirectories($appId);
		if ($app === false) {
			throw new \Exception('App not found in any app directory');
		}

		$basedir = $app['path'].'/'.$appId;
		$info = OC_App::getAppInfo($basedir.'/appinfo/info.xml', true);

		$l = \OC::$server->getL10N('core');

		if (!is_array($info)) {
			throw new \Exception(
				$l->t('App "%s" cannot be installed because appinfo file cannot be read.',
					[$appId]
				)
			);
		}

		$ignoreMaxApps = $this->config->getSystemValue('app_install_overwrite', []);
		$ignoreMax = $forceEnable || in_array($appId, $ignoreMaxApps, true);

		$version = implode('.', \OCP\Util::getVersion());
		if (!\OC_App::isAppCompatible($version, $info, $ignoreMax)) {
			throw new \Exception(
				// TODO $l
				$l->t('App "%s" cannot be installed because it is not compatible with this version of the server.',
					[$info['name']]
				)
			);
		}

		// check for required dependencies
		\OC_App::checkAppDependencies($this->config, $l, $info, $ignoreMax);
		/** @var Coordinator $coordinator */
		$coordinator = \OC::$server->get(Coordinator::class);
		$coordinator->runLazyRegistration($appId);
		\OC_App::registerAutoloading($appId, $basedir);

		$previousVersion = $this->config->getAppValue($info['id'], 'installed_version', false);
		if ($previousVersion) {
			OC_App::executeRepairSteps($appId, $info['repair-steps']['pre-migration']);
		}

		//install the database
		if (is_file($basedir.'/appinfo/database.xml')) {
			if (\OC::$server->getConfig()->getAppValue($info['id'], 'installed_version') === null) {
				OC_DB::createDbFromStructure($basedir.'/appinfo/database.xml');
			} else {
				OC_DB::updateDbFromStructure($basedir.'/appinfo/database.xml');
			}
		} else {
			$ms = new \OC\DB\MigrationService($info['id'], \OC::$server->get(Connection::class));
			$ms->migrate('latest', true);
		}
		if ($previousVersion) {
			OC_App::executeRepairSteps($appId, $info['repair-steps']['post-migration']);
		}

		\OC_App::setupBackgroundJobs($info['background-jobs']);

		//run appinfo/install.php
		self::includeAppScript($basedir . '/appinfo/install.php');

		$appData = OC_App::getAppInfo($appId);
		OC_App::executeRepairSteps($appId, $appData['repair-steps']['install']);

		//set the installed version
		\OC::$server->getConfig()->setAppValue($info['id'], 'installed_version', OC_App::getAppVersion($info['id'], false));
		\OC::$server->getConfig()->setAppValue($info['id'], 'enabled', 'no');

		//set remote/public handlers
		foreach ($info['remote'] as $name => $path) {
			\OC::$server->getConfig()->setAppValue('core', 'remote_'.$name, $info['id'].'/'.$path);
		}
		foreach ($info['public'] as $name => $path) {
			\OC::$server->getConfig()->setAppValue('core', 'public_'.$name, $info['id'].'/'.$path);
		}

		OC_App::setAppTypes($info['id']);

		return $info['id'];
	}

	/**
	 * Updates the specified app from the appstore
	 *
	 * @param string $appId
	 * @param bool [$allowUnstable] Allow unstable releases
	 * @return bool
	 */
	public function updateAppstoreApp($appId, $allowUnstable = false) {
		if ($this->isUpdateAvailable($appId, $allowUnstable)) {
			try {
				$this->downloadApp($appId, $allowUnstable);
			} catch (\Exception $e) {
				$this->logger->logException($e, [
					'level' => ILogger::ERROR,
					'app' => 'core',
				]);
				return false;
			}
			return OC_App::updateApp($appId);
		}

		return false;
	}

	/**
	 * Split the certificate file in individual certs
	 *
	 * @param string $cert
	 * @return string[]
	 */
	private function splitCerts(string $cert): array {
		preg_match_all('([\-]{3,}[\S\ ]+?[\-]{3,}[\S\s]+?[\-]{3,}[\S\ ]+?[\-]{3,})', $cert, $matches);

		return $matches[0];
	}

	/**
	 * Downloads an app and puts it into the app directory
	 *
	 * @param string $appId
	 * @param bool [$allowUnstable]
	 *
	 * @throws \Exception If the installation was not successful
	 */
	public function downloadApp($appId, $allowUnstable = false) {
		$appId = strtolower($appId);

		$apps = $this->appFetcher->get($allowUnstable);
		foreach ($apps as $app) {
			if ($app['id'] === $appId) {
				// Load the certificate
				$certificate = new X509();
				$rootCrt = file_get_contents(__DIR__ . '/../../resources/codesigning/root.crt');
				$rootCrts = $this->splitCerts($rootCrt);
				foreach ($rootCrts as $rootCrt) {
					$certificate->loadCA($rootCrt);
				}
				$loadedCertificate = $certificate->loadX509($app['certificate']);

				// Verify if the certificate has been revoked
				$crl = new X509();
				foreach ($rootCrts as $rootCrt) {
					$crl->loadCA($rootCrt);
				}
				$crl->loadCRL(file_get_contents(__DIR__ . '/../../resources/codesigning/root.crl'));
				if ($crl->validateSignature() !== true) {
					throw new \Exception('Could not validate CRL signature');
				}
				$csn = $loadedCertificate['tbsCertificate']['serialNumber']->toString();
				$revoked = $crl->getRevoked($csn);
				if ($revoked !== false) {
					throw new \Exception(
						sprintf(
							'Certificate "%s" has been revoked',
							$csn
						)
					);
				}

				// Verify if the certificate has been issued by the Nextcloud Code Authority CA
				if ($certificate->validateSignature() !== true) {
					throw new \Exception(
						sprintf(
							'App with id %s has a certificate not issued by a trusted Code Signing Authority',
							$appId
						)
					);
				}

				// Verify if the certificate is issued for the requested app id
				$certInfo = openssl_x509_parse($app['certificate']);
				if (!isset($certInfo['subject']['CN'])) {
					throw new \Exception(
						sprintf(
							'App with id %s has a cert with no CN',
							$appId
						)
					);
				}
				if ($certInfo['subject']['CN'] !== $appId) {
					throw new \Exception(
						sprintf(
							'App with id %s has a cert issued to %s',
							$appId,
							$certInfo['subject']['CN']
						)
					);
				}

				// Download the release
				$tempFile = $this->tempManager->getTemporaryFile('.tar.gz');
				$timeout = $this->isCLI ? 0 : 120;
				$client = $this->clientService->newClient();
				$client->get($app['releases'][0]['download'], ['sink' => $tempFile, 'timeout' => $timeout]);

				// Check if the signature actually matches the downloaded content
				$certificate = openssl_get_publickey($app['certificate']);
				$verified = (bool)openssl_verify(file_get_contents($tempFile), base64_decode($app['releases'][0]['signature']), $certificate, OPENSSL_ALGO_SHA512);
				openssl_free_key($certificate);

				if ($verified === true) {
					// Seems to match, let's proceed
					$extractDir = $this->tempManager->getTemporaryFolder();
					$archive = new TAR($tempFile);

					if ($archive) {
						if (!$archive->extract($extractDir)) {
							$errorMessage = 'Could not extract app ' . $appId;

							$archiveError = $archive->getError();
							if ($archiveError instanceof \PEAR_Error) {
								$errorMessage .= ': ' . $archiveError->getMessage();
							}

							throw new \Exception($errorMessage);
						}
						$allFiles = scandir($extractDir);
						$folders = array_diff($allFiles, ['.', '..']);
						$folders = array_values($folders);

						if (count($folders) > 1) {
							throw new \Exception(
								sprintf(
									'Extracted app %s has more than 1 folder',
									$appId
								)
							);
						}

						// Check if appinfo/info.xml has the same app ID as well
						$loadEntities = libxml_disable_entity_loader(false);
						$xml = simplexml_load_file($extractDir . '/' . $folders[0] . '/appinfo/info.xml');
						libxml_disable_entity_loader($loadEntities);
						if ((string)$xml->id !== $appId) {
							throw new \Exception(
								sprintf(
									'App for id %s has a wrong app ID in info.xml: %s',
									$appId,
									(string)$xml->id
								)
							);
						}

						// Check if the version is lower than before
						$currentVersion = OC_App::getAppVersion($appId);
						$newVersion = (string)$xml->version;
						if (version_compare($currentVersion, $newVersion) === 1) {
							throw new \Exception(
								sprintf(
									'App for id %s has version %s and tried to update to lower version %s',
									$appId,
									$currentVersion,
									$newVersion
								)
							);
						}

						$baseDir = OC_App::getInstallPath() . '/' . $appId;
						// Remove old app with the ID if existent
						OC_Helper::rmdirr($baseDir);
						// Move to app folder
						if (@mkdir($baseDir)) {
							$extractDir .= '/' . $folders[0];
							OC_Helper::copyr($extractDir, $baseDir);
						}
						OC_Helper::copyr($extractDir, $baseDir);
						OC_Helper::rmdirr($extractDir);
						return;
					} else {
						throw new \Exception(
							sprintf(
								'Could not extract app with ID %s to %s',
								$appId,
								$extractDir
							)
						);
					}
				} else {
					// Signature does not match
					throw new \Exception(
						sprintf(
							'App with id %s has invalid signature',
							$appId
						)
					);
				}
			}
		}

		throw new \Exception(
			sprintf(
				'Could not download app %s',
				$appId
			)
		);
	}

	/**
	 * Check if an update for the app is available
	 *
	 * @param string $appId
	 * @param bool $allowUnstable
	 * @return string|false false or the version number of the update
	 */
	public function isUpdateAvailable($appId, $allowUnstable = false) {
		if ($this->isInstanceReadyForUpdates === null) {
			$installPath = OC_App::getInstallPath();
			if ($installPath === false || $installPath === null) {
				$this->isInstanceReadyForUpdates = false;
			} else {
				$this->isInstanceReadyForUpdates = true;
			}
		}

		if ($this->isInstanceReadyForUpdates === false) {
			return false;
		}

		if ($this->isInstalledFromGit($appId) === true) {
			return false;
		}

		if ($this->apps === null) {
			$this->apps = $this->appFetcher->get($allowUnstable);
		}

		foreach ($this->apps as $app) {
			if ($app['id'] === $appId) {
				$currentVersion = OC_App::getAppVersion($appId);

				if (!isset($app['releases'][0]['version'])) {
					return false;
				}
				$newestVersion = $app['releases'][0]['version'];
				if ($currentVersion !== '0' && version_compare($newestVersion, $currentVersion, '>')) {
					return $newestVersion;
				} else {
					return false;
				}
			}
		}

		return false;
	}

	/**
	 * Check if app has been installed from git
	 * @param string $name name of the application to remove
	 * @return boolean
	 *
	 * The function will check if the path contains a .git folder
	 */
	private function isInstalledFromGit($appId) {
		$app = \OC_App::findAppInDirectories($appId);
		if ($app === false) {
			return false;
		}
		$basedir = $app['path'].'/'.$appId;
		return file_exists($basedir.'/.git/');
	}

	/**
	 * Check if app is already downloaded
	 * @param string $name name of the application to remove
	 * @return boolean
	 *
	 * The function will check if the app is already downloaded in the apps repository
	 */
	public function isDownloaded($name) {
		foreach (\OC::$APPSROOTS as $dir) {
			$dirToTest = $dir['path'];
			$dirToTest .= '/';
			$dirToTest .= $name;
			$dirToTest .= '/';

			if (is_dir($dirToTest)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Removes an app
	 * @param string $appId ID of the application to remove
	 * @return boolean
	 *
	 *
	 * This function works as follows
	 *   -# call uninstall repair steps
	 *   -# removing the files
	 *
	 * The function will not delete preferences, tables and the configuration,
	 * this has to be done by the function oc_app_uninstall().
	 */
	public function removeApp($appId) {
		if ($this->isDownloaded($appId)) {
			if (\OC::$server->getAppManager()->isShipped($appId)) {
				return false;
			}
			$appDir = OC_App::getInstallPath() . '/' . $appId;
			OC_Helper::rmdirr($appDir);
			return true;
		} else {
			\OCP\Util::writeLog('core', 'can\'t remove app '.$appId.'. It is not installed.', ILogger::ERROR);

			return false;
		}
	}

	/**
	 * Installs the app within the bundle and marks the bundle as installed
	 *
	 * @param Bundle $bundle
	 * @throws \Exception If app could not get installed
	 */
	public function installAppBundle(Bundle $bundle) {
		$appIds = $bundle->getAppIdentifiers();
		foreach ($appIds as $appId) {
			if (!$this->isDownloaded($appId)) {
				$this->downloadApp($appId);
			}
			$this->installApp($appId);
			$app = new OC_App();
			$app->enable($appId);
		}
		$bundles = json_decode($this->config->getAppValue('core', 'installed.bundles', json_encode([])), true);
		$bundles[] = $bundle->getIdentifier();
		$this->config->setAppValue('core', 'installed.bundles', json_encode($bundles));
	}

	/**
	 * Installs shipped apps
	 *
	 * This function installs all apps found in the 'apps' directory that should be enabled by default;
	 * @param bool $softErrors When updating we ignore errors and simply log them, better to have a
	 *                         working ownCloud at the end instead of an aborted update.
	 * @return array Array of error messages (appid => Exception)
	 */
	public static function installShippedApps($softErrors = false) {
		$appManager = \OC::$server->getAppManager();
		$config = \OC::$server->getConfig();
		$errors = [];
		foreach (\OC::$APPSROOTS as $app_dir) {
			if ($dir = opendir($app_dir['path'])) {
				while (false !== ($filename = readdir($dir))) {
					if ($filename[0] !== '.' and is_dir($app_dir['path']."/$filename")) {
						if (file_exists($app_dir['path']."/$filename/appinfo/info.xml")) {
							if ($config->getAppValue($filename, "installed_version", null) === null) {
								$info = OC_App::getAppInfo($filename);
								$enabled = isset($info['default_enable']);
								if (($enabled || in_array($filename, $appManager->getAlwaysEnabledApps()))
									  && $config->getAppValue($filename, 'enabled') !== 'no') {
									if ($softErrors) {
										try {
											Installer::installShippedApp($filename);
										} catch (HintException $e) {
											if ($e->getPrevious() instanceof TableExistsException) {
												$errors[$filename] = $e;
												continue;
											}
											throw $e;
										}
									} else {
										Installer::installShippedApp($filename);
									}
									$config->setAppValue($filename, 'enabled', 'yes');
								}
							}
						}
					}
				}
				closedir($dir);
			}
		}

		return $errors;
	}

	/**
	 * install an app already placed in the app folder
	 * @param string $app id of the app to install
	 * @return integer
	 */
	public static function installShippedApp($app) {
		//install the database
		$appPath = OC_App::getAppPath($app);
		\OC_App::registerAutoloading($app, $appPath);

		if (is_file("$appPath/appinfo/database.xml")) {
			try {
				OC_DB::createDbFromStructure("$appPath/appinfo/database.xml");
			} catch (TableExistsException $e) {
				throw new HintException(
					'Failed to enable app ' . $app,
					'Please ask for help via one of our <a href="https://nextcloud.com/support/" target="_blank" rel="noreferrer noopener">support channels</a>.',
					0, $e
				);
			}
		} else {
			$ms = new \OC\DB\MigrationService($app, \OC::$server->get(Connection::class));
			$ms->migrate('latest', true);
		}

		//run appinfo/install.php
		self::includeAppScript("$appPath/appinfo/install.php");

		$info = OC_App::getAppInfo($app);
		if (is_null($info)) {
			return false;
		}
		\OC_App::setupBackgroundJobs($info['background-jobs']);

		OC_App::executeRepairSteps($app, $info['repair-steps']['install']);

		$config = \OC::$server->getConfig();

		$config->setAppValue($app, 'installed_version', OC_App::getAppVersion($app));
		if (array_key_exists('ocsid', $info)) {
			$config->setAppValue($app, 'ocsid', $info['ocsid']);
		}

		//set remote/public handlers
		foreach ($info['remote'] as $name => $path) {
			$config->setAppValue('core', 'remote_'.$name, $app.'/'.$path);
		}
		foreach ($info['public'] as $name => $path) {
			$config->setAppValue('core', 'public_'.$name, $app.'/'.$path);
		}

		OC_App::setAppTypes($info['id']);

		return $info['id'];
	}

	/**
	 * @param string $script
	 */
	private static function includeAppScript($script) {
		if (file_exists($script)) {
			include $script;
		}
	}
}
