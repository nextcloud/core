<?php
/**
 * ownCloud
 *
 * @author Victor Dubiniuk
 * @copyright 2015 Victor Dubiniuk victor.dubiniuk@owncloud.com
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Test\PublicNamespace;

class UtilTest extends \Test\TestCase {
	 /**
	  * @dataProvider channelProvider
	  *
	  * @param string $channel
	  */
	public function testOverrideChannel($channel) {
		\OCP\Util::setChannel($channel);
		$actual = \OCP\Util::getChannel($channel);
		$this->assertEquals($channel, $actual);
	}
	
	public function channelProvider() {
		return [
			['daily'],
			['beta'],
			['stable'],
			['production']
		];
	}
}
