<?php
/**
 * @author Lukas Reschke <lukas@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace Test\IntegrityCheck\Helpers;

use OC\IntegrityCheck\Helpers\EnvironmentHelper;
use Test\TestCase;

class EnvironmentHelperTest extends TestCase {
	/** @var EnvironmentHelper */
	private $environmentHelper;

	protected function setUp(): void {
		$this->environmentHelper = new EnvironmentHelper();
		parent::setUp();
	}

	public function testGetServerRoot() {
		$this->assertSame(\OC::$SERVERROOT, $this->environmentHelper->getServerRoot());
	}

	public function testGetChannel() {
		$this->assertSame(\OC_Util::getChannel(), $this->environmentHelper->getChannel());
	}
}
