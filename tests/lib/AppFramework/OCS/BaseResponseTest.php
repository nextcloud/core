<?php

declare(strict_types=1);

/**
 * @copyright 2020 Daniel Kesselberg <mail@danielkesselberg.de>
 *
 * @author 2020 Daniel Kesselberg <mail@danielkesselberg.de>
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

namespace Test\AppFramework\Middleware;

use OC\AppFramework\OCS\BaseResponse;

class BaseResponseTest extends \Test\TestCase {

	public function testToXml(): void {

		/** @var BaseResponse $response */
		$response = $this->createMock(BaseResponse::class);

		$writer = new \XMLWriter();
		$writer->openMemory();
		$writer->setIndent(false);
		$writer->startDocument();

		$data = [
			'hello' => 'hello',
			'information' => [
				'@test' => 'some data',
				'someElement' => 'withAttribute',
			],
			'value without key',
		];

		$this->invokePrivate($response, 'toXml', [$data, $writer]);
		$writer->endDocument();

		$this->assertEquals(
			"<?xml version=\"1.0\"?>\n<hello>hello</hello><information test=\"some data\"><someElement>withAttribute</someElement></information><element>value without key</element>\n",
			$writer->outputMemory(true)
		);
	}

}
