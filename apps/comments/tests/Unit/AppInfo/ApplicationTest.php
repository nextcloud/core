<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Comments\Tests\Unit\AppInfo;

use OCA\Comments\AppInfo\Application;
use OCA\Comments\Notification\Notifier;
use Test\TestCase;

/**
 * Class ApplicationTest
 *
 * @group DB
 *
 * @package OCA\Comments\Tests\Unit\AppInfo
 */
class ApplicationTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		\OC::$server->getUserManager()->createUser('dummy', '456');
		\OC::$server->getUserSession()->setUser(\OC::$server->getUserManager()->get('dummy'));
	}

	protected function tearDown(): void {
		\OC::$server->getUserManager()->get('dummy')->delete();
		parent::tearDown();
	}

	public function test() {
		$app = new Application();
		$c = $app->getContainer();

		// assert service instances in the container are properly setup
		$s = $c->query('NotificationsController');
		$this->assertInstanceOf('OCA\Comments\Controller\Notifications', $s);

		$services = [
			'OCA\Comments\Activity\Filter',
			'OCA\Comments\Activity\Listener',
			'OCA\Comments\Activity\Provider',
			'OCA\Comments\Activity\Setting',
			'OCA\Comments\Notification\Listener',
			Notifier::class,
		];

		foreach($services as $service) {
			$s = $c->query($service);
			$this->assertInstanceOf($service, $s);
		}
	}
}
