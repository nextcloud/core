<?php

declare(strict_types=1);

/*
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
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
 */

namespace Test\Http\WellKnown;

use OCP\AppFramework\Http\JSONResponse;
use OCP\Http\WellKnown\JrdResponse;
use Test\TestCase;

class JrdResponseTest extends TestCase {
	public function testEmptyToHttpResponse(): void {
		$response = new JrdResponse("subject");
		$httpResponse = $response->toHttpResponse();

		self::assertTrue($response->isEmpty());
		self::assertInstanceOf(JSONResponse::class, $httpResponse);
		/** @var JSONResponse $httpResponse */
		self::assertEquals(
			[
				'subject' => 'subject',
			],
			$httpResponse->getData()
		);
	}

	public function testComplexToHttpResponse(): void {
		$response = new JrdResponse("subject");
		$response->addAlias('alias');
		$response->addAlias('blias');
		$response->addProperty('propa', 'a');
		$response->addProperty('propb', null);
		$response->setExpires('tomorrow');
		$response->addLink('rel', null, null);
		$response->addLink('rel', 'type', null);
		$response->addLink('rel', 'type', 'href', ['title' => 'titlevalue']);
		$response->addLink('rel', 'type', 'href', ['title' => 'titlevalue'], ['propx' => 'valx']);
		$httpResponse = $response->toHttpResponse();

		self::assertFalse($response->isEmpty());
		self::assertInstanceOf(JSONResponse::class, $httpResponse);
		/** @var JSONResponse $httpResponse */
		self::assertEquals(
			[
				'subject' => 'subject',
				'aliases' => [
					'alias',
					'blias',
				],
				'properties' => [
					'propa' => 'a',
					'propb' => null,
				],
				'expires' => 'tomorrow',
				'links' => [
					[
						'rel' => 'rel',
					],
					[
						'rel' => 'rel',
						'type' => 'type',
					],
					[
						'rel' => 'rel',
						'type' => 'type',
						'href' => 'href',
						'titles' => [
							'title' => 'titlevalue',
						],
					],
					[
						'rel' => 'rel',
						'type' => 'type',
						'href' => 'href',
						'titles' => [
							'title' => 'titlevalue',
						],
						'properties' => [
							'propx' => 'valx',
						],
					],
				]
			],
			$httpResponse->getData()
		);
	}
}
