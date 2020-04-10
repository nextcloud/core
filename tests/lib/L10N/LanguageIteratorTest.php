<?php
/**
 * @copyright Copyright (c) 2018 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\L10N;

use OC\L10N\LanguageIterator;
use OCP\IConfig;
use OCP\IUser;
use Test\TestCase;

class LanguageIteratorTest extends TestCase {
	/** @var IUser|\PHPUnit_Framework_MockObject_MockObject */
	protected $user;
	/** @var IConfig|\PHPUnit_Framework_MockObject_MockObject */
	protected $config;
	/** @var LanguageIterator */
	protected $iterator;

	protected function setUp(): void {
		parent::setUp();

		$this->user = $this->createMock(IUser::class);
		$this->config = $this->createMock(IConfig::class);

		$this->iterator = new LanguageIterator($this->user, $this->config);
	}

	public function languageSettingsProvider() {
		return [
			// all language settings set
			[ 'de_DE', 'es_CU', 'zh_TW', ['de_DE', 'de', 'es_CU', 'es', 'zh_TW', 'zh', 'en']],
			[ 'de', 'es', 'zh', ['de', 'es', 'zh', 'en']],
			[ 'en', 'en', 'en', ['en', 'en', 'en', 'en']],
			// one possible setting is missing each
			[ false, 'es_CU', 'zh_TW', ['es_CU', 'es', 'zh_TW', 'zh', 'en']],
			[ false, 'es', 'zh_TW', ['es', 'zh_TW', 'zh', 'en']],
			[ false, 'es_CU', 'zh', ['es_CU', 'es', 'zh', 'en']],
			[ 'de_DE', null, 'zh_TW', ['de_DE', 'de', 'zh_TW', 'zh', 'en']],
			[ 'de_DE', null, 'zh', ['de_DE', 'de', 'zh', 'en']],
			[ 'de', null, 'zh_TW', ['de', 'zh_TW', 'zh', 'en']],
			[ 'de_DE', 'es_CU', 'en', ['de_DE', 'de', 'es_CU', 'es', 'en', 'en']],
			[ 'de', 'es_CU', 'en', ['de', 'es_CU', 'es', 'en', 'en']],
			[ 'de_DE', 'es', 'en', ['de_DE', 'de', 'es', 'en', 'en']],
			// two possible settings are missing each
			[ false, null, 'zh_TW', ['zh_TW', 'zh', 'en']],
			[ false, null, 'zh', ['zh', 'en']],
			[ false, 'es_CU', 'en', ['es_CU', 'es', 'en', 'en']],
			[ false, 'es', 'en', ['es', 'en', 'en']],
			[ 'de_DE', null, 'en', ['de_DE', 'de', 'en', 'en']],
			[ 'de', null, 'en', ['de', 'en', 'en']],
			// nothing is set
			[ false, null, 'en', ['en', 'en']],

		];
	}

	/**
	 * @dataProvider languageSettingsProvider
	 */
	public function testIterator($forcedLang, $userLang, $sysLang, $expectedValues) {
		$this->config->expects($this->any())
			->method('getSystemValue')
			->willReturnMap([
				['force_language', false, $forcedLang],
				['default_language', 'en', $sysLang],
			]);
		$this->config->expects($this->any())
			->method('getUserValue')
			->willReturn($userLang);

		foreach ($expectedValues as $expected) {
			$this->assertTrue($this->iterator->valid());
			$this->assertSame($expected, $this->iterator->current());
			$this->iterator->next();
		}
		$this->assertFalse($this->iterator->valid());
	}
}
