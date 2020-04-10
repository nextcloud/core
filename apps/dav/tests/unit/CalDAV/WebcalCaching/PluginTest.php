<?php
/**
 * @copyright Copyright (c) 2018 Georg Ehrke
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
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

namespace OCA\DAV\Tests\unit\CalDAV\WebcalCaching;

use OCA\DAV\CalDAV\WebcalCaching\Plugin;
use OCP\IRequest;

class PluginTest extends \Test\TestCase {

	public function testDisabled() {
		$request = $this->createMock(IRequest::class);
		$request->expects($this->at(0))
			->method('isUserAgent')
			->with([])
			->willReturn(false);

		$request->expects($this->at(1))
			->method('getHeader')
			->with('X-NC-CalDAV-Webcal-Caching')
			->willReturn('');

		$plugin = new Plugin($request);

		$this->assertEquals(false, $plugin->isCachingEnabledForThisRequest());
	}

	public function testEnabled() {
		$request = $this->createMock(IRequest::class);
		$request->expects($this->at(0))
			->method('isUserAgent')
			->with([])
			->willReturn(false);

		$request->expects($this->at(1))
			->method('getHeader')
			->with('X-NC-CalDAV-Webcal-Caching')
			->willReturn('On');

		$plugin = new Plugin($request);

		$this->assertEquals(true, $plugin->isCachingEnabledForThisRequest());
	}
}
