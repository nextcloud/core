<?php
/**
 *
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author zulan <git@zulan.net>
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

namespace OCA\Provisioning_API\AppInfo;

use OC\AppFramework\Utility\SimpleContainer;
use OC\AppFramework\Utility\TimeFactory;
use OCA\Provisioning_API\Middleware\ProvisioningApiMiddleware;
use OCA\Settings\Mailer\NewUserMailHelper;
use OCP\AppFramework\App;
use OCP\AppFramework\Utility\IControllerMethodReflector;
use OCP\Defaults;
use OCP\Util;

class Application extends App {
	public function __construct(array $urlParams = []) {
		parent::__construct('provisioning_api', $urlParams);

		$container = $this->getContainer();
		$server = $container->getServer();

		$container->registerService(NewUserMailHelper::class, function (SimpleContainer $c) use ($server) {
			return new NewUserMailHelper(
				$server->query(Defaults::class),
				$server->getURLGenerator(),
				$server->getL10NFactory(),
				$server->getMailer(),
				$server->getSecureRandom(),
				new TimeFactory(),
				$server->getConfig(),
				$server->getCrypto(),
				Util::getDefaultEmailAddress('no-reply')
			);
		});
		$container->registerService('ProvisioningApiMiddleware', function (SimpleContainer $c) use ($server) {
			$user = $server->getUserManager()->get($c['UserId']);
			$isAdmin = $user !== null ? $server->getGroupManager()->isAdmin($user->getUID()) : false;
			$isSubAdmin = $user !== null ? $server->getGroupManager()->getSubAdmin()->isSubAdmin($user) : false;
			return new ProvisioningApiMiddleware(
				$c->query(IControllerMethodReflector::class),
				$isAdmin,
				$isSubAdmin
			);
		});
		$container->registerMiddleWare('ProvisioningApiMiddleware');
	}
}
