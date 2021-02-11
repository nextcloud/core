<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Damjan Georgievski <gdamjan@gmail.com>
 * @author ideaship <ideaship@users.noreply.github.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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

namespace OC\Core\Controller;

use OC\Setup;
use OCP\ILogger;

class SetupController {
	/** @var Setup */
	protected $setupHelper;
	/** @var string */
	private $autoConfigFile;

	/**
	 * @param Setup $setupHelper
	 */
	public function __construct(Setup $setupHelper) {
		$this->autoConfigFile = \OC::$configDir.'autoconfig.php';
		$this->setupHelper = $setupHelper;
	}

	/**
	 * @param $post
	 */
	public function run($post) {
		// Check for autosetup:
		$post = $this->loadAutoConfig($post);
		$opts = $this->setupHelper->getSystemInfo();

		// convert 'abcpassword' to 'abcpass'
		if (isset($post['adminpassword'])) {
			$post['adminpass'] = $post['adminpassword'];
		}
		if (isset($post['dbpassword'])) {
			$post['dbpass'] = $post['dbpassword'];
		}

		if (!is_file(\OC::$configDir.'/CAN_INSTALL')) {
			$this->displaySetupForbidden();
			return;
		}

		if (isset($post['install']) and $post['install'] == 'true') {
			// We have to launch the installation process :
			$e = $this->setupHelper->install($post);
			$errors = ['errors' => $e];

			if (count($e) > 0) {
				$options = array_merge($opts, $post, $errors);
				$this->display($options);
			} else {
				$this->finishSetup(isset($post['install-recommended-apps']));
			}
		} else {
			$options = array_merge($opts, $post);
			$this->display($options);
		}
	}

	private function displaySetupForbidden() {
		\OC_Template::printGuestPage('', 'installation_forbidden');
	}

	public function display($post) {
		$defaults = [
			'adminlogin' => '',
			'adminpass' => '',
			'dbuser' => '',
			'dbpass' => '',
			'dbname' => '',
			'dbtablespace' => '',
			'dbhost' => 'localhost',
			'dbtype' => '',
		];
		$parameters = array_merge($defaults, $post);

		\OC_Template::printGuestPage('', 'installation', $parameters);
	}

	private function finishSetup(bool $installRecommended) {
		if (file_exists($this->autoConfigFile)) {
			unlink($this->autoConfigFile);
		}
		\OC::$server->getIntegrityCodeChecker()->runInstanceVerification();

		if (\OC_Util::getChannel() !== 'git' && is_file(\OC::$configDir.'/CAN_INSTALL')) {
			if (!unlink(\OC::$configDir.'/CAN_INSTALL')) {
				\OC_Template::printGuestPage('', 'installation_incomplete');
			}
		}

		if ($installRecommended) {
			$urlGenerator = \OC::$server->getURLGenerator();
			$location = $urlGenerator->getAbsoluteURL('index.php/core/apps/recommended');
			header('Location: ' . $location);
			exit();
		}
		\OC_Util::redirectToDefaultPage();
	}

	public function loadAutoConfig($post) {
		if (file_exists($this->autoConfigFile)) {
			\OCP\Util::writeLog('core', 'Autoconfig file found, setting up Nextcloud…', ILogger::INFO);
			$AUTOCONFIG = [];
			include $this->autoConfigFile;
			$post = array_merge($post, $AUTOCONFIG);
		}

		$dbIsSet = isset($post['dbtype']);
		$directoryIsSet = isset($post['directory']);
		$adminAccountIsSet = isset($post['adminlogin']);

		if ($dbIsSet and $directoryIsSet and $adminAccountIsSet) {
			$post['install'] = 'true';
		}
		$post['dbIsSet'] = $dbIsSet;
		$post['directoryIsSet'] = $directoryIsSet;

		return $post;
	}
}
