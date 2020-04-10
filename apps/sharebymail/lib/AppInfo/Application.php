<?php
/**
 * @copyright Copyright (c) 2017 Bjoern Schiessle <bjoern@schiessle.org>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCA\ShareByMail\AppInfo;

use OCA\ShareByMail\Capabilities;
use OCA\ShareByMail\Settings;
use OCP\AppFramework\App;
use OCP\Util;

class Application extends App {

	public function __construct(array $urlParams = []) {
		parent::__construct('sharebymail', $urlParams);

		$settingsManager = \OC::$server->query(Settings\SettingsManager::class);
		$settings = new Settings($settingsManager);

		/** register capabilities */
		$container = $this->getContainer();
		$container->registerCapability(Capabilities::class);

		/** register hooks */
		Util::connectHook('\OCP\Config', 'js', $settings, 'announceShareProvider');
		Util::connectHook('\OCP\Config', 'js', $settings, 'announceShareByMailSettings');
	}

}
