<?php
/**
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace Test\App\CodeChecker;

use OC\App\CodeChecker\InfoChecker;
use Test\TestCase;

class InfoCheckerTest extends TestCase {
	/** @var InfoChecker */
	protected $infoChecker;

	public static function setUpBeforeClass(): void {
		\OC::$APPSROOTS[] = [
			'path' => \OC::$SERVERROOT . '/tests/apps',
			'url' => '/apps-test',
			'writable' => false,
		];
	}

	public static function tearDownAfterClass(): void {
		// remove last element
		array_pop(\OC::$APPSROOTS);
	}

	protected function setUp(): void {
		parent::setUp();
		$this->infoChecker = new InfoChecker();
	}

	public function appInfoData() {
		return [
			['testapp_infoxml', []],
			['testapp_version', [
				['type' => 'parseError', 'field' => 'Element \'licence\': This element is not expected. Expected is one of ( description, version ).' . "\n"],
			]],
			['testapp_dependency_missing', [
				['type' => 'parseError', 'field' => 'Element \'info\': Missing child element(s). Expected is one of ( repository, screenshot, dependencies ).' . "\n"],
			]],
			['testapp_name_missing', [
				['type' => 'parseError', 'field' => 'Element \'summary\': This element is not expected. Expected is ( name ).' . "\n"],
			]],
		];
	}

	/**
	 * @dataProvider appInfoData
	 *
	 * @param $appId
	 * @param $expectedErrors
	 */
	public function testApps($appId, $expectedErrors) {
		$errors = $this->infoChecker->analyse($appId);
		libxml_clear_errors();

		$this->assertEquals($expectedErrors, $errors);
	}
}
