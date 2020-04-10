<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OC\App\CodeChecker;

use OC\Hooks\BasicEmitter;

class InfoChecker extends BasicEmitter {

	/** @var string[] */
	private $shippedApps;

	/** @var string[] */
	private $alwaysEnabled;

	/**
	 * @param string $appId
	 * @return array
	 * @throws \RuntimeException
	 */
	public function analyse($appId): array {
		$appPath = \OC_App::getAppPath($appId);
		if ($appPath === false) {
			throw new \RuntimeException("No app with given id <$appId> known.");
		}

		$xml = new \DOMDocument();
		$xml->load($appPath . '/appinfo/info.xml');

		$schema = \OC::$SERVERROOT . '/resources/app-info.xsd';
		try {
			if ($this->isShipped($appId)) {
				// Shipped apps are allowed to have the public and default_enabled tags
				$schema = \OC::$SERVERROOT . '/resources/app-info-shipped.xsd';
			}
		} catch (\Exception $e) {
			// Assume it is not shipped
		}

		$errors = [];
		if (!$xml->schemaValidate($schema)) {
			foreach (libxml_get_errors() as $error) {
				$errors[] = [
					'type' => 'parseError',
					'field' => $error->message,
				];
				$this->emit('InfoChecker', 'parseError', [$error->message]);
			}
		}

		return $errors;
	}

	/**
	 * This is a copy of \OC\App\AppManager::isShipped(), keep both in sync.
	 * This method is copied, so the code checker works even when Nextcloud is
	 * not installed yet. The AppManager requires a database connection, which
	 * fails in that case.
	 *
	 * @param string $appId
	 * @return bool
	 * @throws \Exception
	 */
	protected function isShipped(string $appId): bool {
		$this->loadShippedJson();
		return \in_array($appId, $this->shippedApps, true);
	}

	/**
	 * This is a copy of \OC\App\AppManager::loadShippedJson(), keep both in sync
	 * This method is copied, so the code checker works even when Nextcloud is
	 * not installed yet. The AppManager requires a database connection, which
	 * fails in that case.
	 *
	 * @throws \Exception
	 */
	protected function loadShippedJson() {
		if ($this->shippedApps === null) {
			$shippedJson = \OC::$SERVERROOT . '/core/shipped.json';
			if (!file_exists($shippedJson)) {
				throw new \Exception("File not found: $shippedJson");
			}
			$content = json_decode(file_get_contents($shippedJson), true);
			$this->shippedApps = $content['shippedApps'];
			$this->alwaysEnabled = $content['alwaysEnabled'];
		}
	}
}
