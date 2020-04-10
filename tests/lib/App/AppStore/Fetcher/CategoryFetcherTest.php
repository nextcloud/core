<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
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

namespace Test\App\AppStore\Fetcher;

use OC\App\AppStore\Fetcher\CategoryFetcher;

class CategoryFetcherTest extends FetcherBase {
	protected function setUp(): void {
		parent::setUp();
		$this->fileName = 'categories.json';
		$this->endpoint = 'https://apps.nextcloud.com/api/v1/categories.json';

		$this->fetcher = new CategoryFetcher(
			$this->appDataFactory,
			$this->clientService,
			$this->timeFactory,
			$this->config,
			$this->logger
		);
	}

	public function testAppstoreDisabled() {
		$this->config
			->method('getSystemValue')
			->willReturnCallback(function ($var, $default) {
				if ($var === 'appstoreenabled') {
					return false;
				}
				return $default;
			});
		$this->appData
			->expects($this->never())
			->method('getFolder');

		$this->assertEquals([], $this->fetcher->get());
	}

	public function testNoInternet() {
		$this->config
			->method('getSystemValue')
			->willReturnCallback(function ($var, $default) {
				if ($var === 'has_internet_connection') {
					return false;
				}
				return $default;
			});
		$this->appData
			->expects($this->never())
			->method('getFolder');

		$this->assertEquals([], $this->fetcher->get());
	}
}
