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

use OC\App\AppStore\Fetcher\Fetcher;
use OC\Files\AppData\AppData;
use OC\Files\AppData\Factory;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\ILogger;
use Test\TestCase;

abstract class FetcherBase extends TestCase {
	/** @var Factory|\PHPUnit\Framework\MockObject\MockObject */
	protected $appDataFactory;
	/** @var IAppData|\PHPUnit\Framework\MockObject\MockObject */
	protected $appData;
	/** @var IClientService|\PHPUnit\Framework\MockObject\MockObject */
	protected $clientService;
	/** @var ITimeFactory|\PHPUnit\Framework\MockObject\MockObject */
	protected $timeFactory;
	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	protected $config;
	/** @var ILogger|\PHPUnit\Framework\MockObject\MockObject */
	protected $logger;
	/** @var Fetcher */
	protected $fetcher;
	/** @var string */
	protected $fileName;
	/** @var string */
	protected $endpoint;

	protected function setUp(): void {
		parent::setUp();
		$this->appDataFactory = $this->createMock(Factory::class);
		$this->appData = $this->createMock(AppData::class);
		$this->appDataFactory->expects($this->once())
			->method('get')
			->with('appstore')
			->willReturn($this->appData);
		$this->clientService = $this->createMock(IClientService::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->config = $this->createMock(IConfig::class);
		$this->logger = $this->createMock(ILogger::class);
	}

	public function testGetWithAlreadyExistingFileAndUpToDateTimestampAndVersion() {
		$this->config
			->expects($this->at(0))
			->method('getSystemValue')
			->with('appstoreenabled', true)
			->willReturn(true);
		$this->config
			->expects($this->at(1))
			->method('getSystemValue')
			->with('has_internet_connection', true)
			->willReturn(true);
		$this->config
			->expects($this->at(2))
			->method('getSystemValue')
			->with(
				$this->equalTo('version'),
				$this->anything()
			)->willReturn('11.0.0.2');

		$folder = $this->createMock(ISimpleFolder::class);
		$file = $this->createMock(ISimpleFile::class);
		$this->appData
			->expects($this->once())
			->method('getFolder')
			->with('/')
			->willReturn($folder);
		$folder
			->expects($this->once())
			->method('getFile')
			->with($this->fileName)
			->willReturn($file);
		$file
			->expects($this->once())
			->method('getContent')
			->willReturn('{"timestamp":1200,"data":[{"id":"MyApp"}],"ncversion":"11.0.0.2"}');
		$this->timeFactory
			->expects($this->once())
			->method('getTime')
			->willReturn(1499);

		$expected = [
			[
				'id' => 'MyApp',
			],
		];
		$this->assertSame($expected, $this->fetcher->get());
	}

	public function testGetWithNotExistingFileAndUpToDateTimestampAndVersion() {
		$this->config
			->method('getSystemValue')
			->willReturnCallback(function ($var, $default) {
				if ($var === 'appstoreenabled') {
					return true;
				} elseif ($var === 'has_internet_connection') {
					return true;
				} elseif ($var === 'appstoreurl') {
					return 'https://apps.nextcloud.com/api/v1';
				} elseif ($var === 'version') {
					return '11.0.0.2';
				}
				return $default;
			});

		$folder = $this->createMock(ISimpleFolder::class);
		$file = $this->createMock(ISimpleFile::class);
		$this->appData
			->expects($this->once())
			->method('getFolder')
			->with('/')
			->willReturn($folder);
		$folder
			->expects($this->at(0))
			->method('getFile')
			->with($this->fileName)
			->willThrowException(new NotFoundException());
		$folder
			->expects($this->at(1))
			->method('newFile')
			->with($this->fileName)
			->willReturn($file);
		$client = $this->createMock(IClient::class);
		$this->clientService
			->expects($this->once())
			->method('newClient')
			->willReturn($client);
		$response = $this->createMock(IResponse::class);
		$client
			->expects($this->once())
			->method('get')
			->with($this->endpoint)
			->willReturn($response);
		$response
			->expects($this->once())
			->method('getBody')
			->willReturn('[{"id":"MyNewApp", "foo": "foo"}, {"id":"bar"}]');
		$response->method('getHeader')
			->with($this->equalTo('ETag'))
			->willReturn('"myETag"');
		$fileData = '{"data":[{"id":"MyNewApp","foo":"foo"},{"id":"bar"}],"timestamp":1502,"ncversion":"11.0.0.2","ETag":"\"myETag\""}';
		$file
			->expects($this->at(0))
			->method('putContent')
			->with($fileData);
		$file
			->expects($this->at(1))
			->method('getContent')
			->willReturn($fileData);
		$this->timeFactory
			->expects($this->at(0))
			->method('getTime')
			->willReturn(1502);

		$expected = [
			[
				'id' => 'MyNewApp',
				'foo' => 'foo',
			],
			[
				'id' => 'bar',
			],
		];
		$this->assertSame($expected, $this->fetcher->get());
	}

	public function testGetWithAlreadyExistingFileAndOutdatedTimestamp() {
		$this->config->method('getSystemValue')
			->willReturnCallback(function ($key, $default) {
				if ($key === 'appstoreenabled') {
					return true;
				} elseif ($key === 'version') {
					return '11.0.0.2';
				} else {
					return $default;
				}
			});

		$folder = $this->createMock(ISimpleFolder::class);
		$file = $this->createMock(ISimpleFile::class);
		$this->appData
			->expects($this->once())
			->method('getFolder')
			->with('/')
			->willReturn($folder);
		$folder
			->expects($this->once())
			->method('getFile')
			->with($this->fileName)
			->willReturn($file);
		$file
			->expects($this->at(0))
			->method('getContent')
			->willReturn('{"timestamp":1200,"data":{"MyApp":{"id":"MyApp"}},"ncversion":"11.0.0.2"}');
		$this->timeFactory
			->expects($this->at(0))
			->method('getTime')
			->willReturn(4801);
		$client = $this->createMock(IClient::class);
		$this->clientService
			->expects($this->once())
			->method('newClient')
			->willReturn($client);
		$response = $this->createMock(IResponse::class);
		$client
			->expects($this->once())
			->method('get')
			->with($this->endpoint)
			->willReturn($response);
		$response
			->expects($this->once())
			->method('getBody')
			->willReturn('[{"id":"MyNewApp", "foo": "foo"}, {"id":"bar"}]');
		$response->method('getHeader')
			->with($this->equalTo('ETag'))
			->willReturn('"myETag"');
		$fileData = '{"data":[{"id":"MyNewApp","foo":"foo"},{"id":"bar"}],"timestamp":1502,"ncversion":"11.0.0.2","ETag":"\"myETag\""}';
		$file
			->expects($this->at(1))
			->method('putContent')
			->with($fileData);
		$file
			->expects($this->at(2))
			->method('getContent')
			->willReturn($fileData);
		$this->timeFactory
			->expects($this->at(1))
			->method('getTime')
			->willReturn(1502);

		$expected = [
			[
				'id' => 'MyNewApp',
				'foo' => 'foo',
			],
			[
				'id' => 'bar',
			],
		];
		$this->assertSame($expected, $this->fetcher->get());
	}

	public function testGetWithAlreadyExistingFileAndNoVersion() {
		$this->config
			->method('getSystemValue')
			->willReturnCallback(function ($var, $default) {
				if ($var === 'appstoreenabled') {
					return true;
				} elseif ($var === 'has_internet_connection') {
					return true;
				} elseif ($var === 'appstoreurl') {
					return 'https://apps.nextcloud.com/api/v1';
				} elseif ($var === 'version') {
					return '11.0.0.2';
				}
				return $default;
			});

		$folder = $this->createMock(ISimpleFolder::class);
		$file = $this->createMock(ISimpleFile::class);
		$this->appData
			->expects($this->once())
			->method('getFolder')
			->with('/')
			->willReturn($folder);
		$folder
			->expects($this->once())
			->method('getFile')
			->with($this->fileName)
			->willReturn($file);
		$file
			->expects($this->at(0))
			->method('getContent')
			->willReturn('{"timestamp":1200,"data":{"MyApp":{"id":"MyApp"}}');
		$this->timeFactory
			->expects($this->at(0))
			->method('getTime')
			->willReturn(1201);
		$client = $this->createMock(IClient::class);
		$this->clientService
			->expects($this->once())
			->method('newClient')
			->willReturn($client);
		$response = $this->createMock(IResponse::class);
		$client
			->expects($this->once())
			->method('get')
			->with($this->endpoint)
			->willReturn($response);
		$response
			->expects($this->once())
			->method('getBody')
			->willReturn('[{"id":"MyNewApp", "foo": "foo"}, {"id":"bar"}]');
		$response->method('getHeader')
			->with($this->equalTo('ETag'))
			->willReturn('"myETag"');
		$fileData = '{"data":[{"id":"MyNewApp","foo":"foo"},{"id":"bar"}],"timestamp":1201,"ncversion":"11.0.0.2","ETag":"\"myETag\""}';
		$file
			->expects($this->at(1))
			->method('putContent')
			->with($fileData);
		$file
			->expects($this->at(2))
			->method('getContent')
			->willReturn($fileData);

		$expected = [
			[
				'id' => 'MyNewApp',
				'foo' => 'foo',
			],
			[
				'id' => 'bar',
			],
		];
		$this->assertSame($expected, $this->fetcher->get());
	}

	public function testGetWithAlreadyExistingFileAndOutdatedVersion() {
		$this->config
			->method('getSystemValue')
			->willReturnCallback(function ($var, $default) {
				if ($var === 'appstoreenabled') {
					return true;
				} elseif ($var === 'has_internet_connection') {
					return true;
				} elseif ($var === 'appstoreurl') {
					return 'https://apps.nextcloud.com/api/v1';
				} elseif ($var === 'version') {
					return '11.0.0.2';
				}
				return $default;
			});

		$folder = $this->createMock(ISimpleFolder::class);
		$file = $this->createMock(ISimpleFile::class);
		$this->appData
			->expects($this->once())
			->method('getFolder')
			->with('/')
			->willReturn($folder);
		$folder
			->expects($this->once())
			->method('getFile')
			->with($this->fileName)
			->willReturn($file);
		$file
			->expects($this->at(0))
			->method('getContent')
			->willReturn('{"timestamp":1200,"data":{"MyApp":{"id":"MyApp"}},"ncversion":"11.0.0.1"');
		$this->timeFactory
			->method('getTime')
			->willReturn(1201);
		$client = $this->createMock(IClient::class);
		$this->clientService
			->expects($this->once())
			->method('newClient')
			->willReturn($client);
		$response = $this->createMock(IResponse::class);
		$client
			->expects($this->once())
			->method('get')
			->with($this->endpoint)
			->willReturn($response);
		$response
			->expects($this->once())
			->method('getBody')
			->willReturn('[{"id":"MyNewApp", "foo": "foo"}, {"id":"bar"}]');
		$response->method('getHeader')
			->with($this->equalTo('ETag'))
			->willReturn('"myETag"');
		$fileData = '{"data":[{"id":"MyNewApp","foo":"foo"},{"id":"bar"}],"timestamp":1201,"ncversion":"11.0.0.2","ETag":"\"myETag\""}';
		$file
			->expects($this->at(1))
			->method('putContent')
			->with($fileData);
		$file
			->expects($this->at(2))
			->method('getContent')
			->willReturn($fileData);

		$expected = [
			[
				'id' => 'MyNewApp',
				'foo' => 'foo',
			],
			[
				'id' => 'bar',
			],
		];
		$this->assertSame($expected, $this->fetcher->get());
	}

	public function testGetWithExceptionInClient() {
		$this->config->method('getSystemValue')
			->willReturnCallback(function ($key, $default) {
				if ($key === 'appstoreenabled') {
					return true;
				} else {
					return $default;
				}
			});

		$folder = $this->createMock(ISimpleFolder::class);
		$file = $this->createMock(ISimpleFile::class);
		$this->appData
			->expects($this->once())
			->method('getFolder')
			->with('/')
			->willReturn($folder);
		$folder
			->expects($this->once())
			->method('getFile')
			->with($this->fileName)
			->willReturn($file);
		$file
			->expects($this->at(0))
			->method('getContent')
			->willReturn('{"timestamp":1200,"data":{"MyApp":{"id":"MyApp"}}}');
		$client = $this->createMock(IClient::class);
		$this->clientService
			->expects($this->once())
			->method('newClient')
			->willReturn($client);
		$client
			->expects($this->once())
			->method('get')
			->with($this->endpoint)
			->willThrowException(new \Exception());

		$this->assertSame([], $this->fetcher->get());
	}

	public function testGetMatchingETag() {
		$this->config->method('getSystemValue')
			->willReturnCallback(function ($key, $default) {
				if ($key === 'appstoreenabled') {
					return true;
				} elseif ($key === 'version') {
					return '11.0.0.2';
				} else {
					return $default;
				}
			});

		$folder = $this->createMock(ISimpleFolder::class);
		$file = $this->createMock(ISimpleFile::class);
		$this->appData
			->expects($this->once())
			->method('getFolder')
			->with('/')
			->willReturn($folder);
		$folder
			->expects($this->once())
			->method('getFile')
			->with($this->fileName)
			->willReturn($file);
		$origData = '{"data":[{"id":"MyNewApp","foo":"foo"},{"id":"bar"}],"timestamp":1200,"ncversion":"11.0.0.2","ETag":"\"myETag\""}';
		$file
			->expects($this->at(0))
			->method('getContent')
			->willReturn($origData);
		$this->timeFactory
			->expects($this->at(0))
			->method('getTime')
			->willReturn(4801);
		$this->timeFactory
			->expects($this->at(1))
			->method('getTime')
			->willReturn(4802);
		$client = $this->createMock(IClient::class);
		$this->clientService
			->expects($this->once())
			->method('newClient')
			->willReturn($client);
		$response = $this->createMock(IResponse::class);
		$client
			->expects($this->once())
			->method('get')
			->with(
				$this->equalTo($this->endpoint),
				$this->equalTo([
					'timeout' => 60,
					'headers' => [
						'If-None-Match' => '"myETag"'
					]
				])
			)->willReturn($response);
		$response->method('getStatusCode')
			->willReturn(304);

		$newData = '{"data":[{"id":"MyNewApp","foo":"foo"},{"id":"bar"}],"timestamp":4802,"ncversion":"11.0.0.2","ETag":"\"myETag\""}';
		$file
			->expects($this->at(1))
			->method('putContent')
			->with($newData);
		$file
			->expects($this->at(2))
			->method('getContent')
			->willReturn($newData);

		$expected = [
			[
				'id' => 'MyNewApp',
				'foo' => 'foo',
			],
			[
				'id' => 'bar',
			],
		];

		$this->assertSame($expected, $this->fetcher->get());
	}

	public function testGetNoMatchingETag() {
		$this->config->method('getSystemValue')
			->willReturnCallback(function ($key, $default) {
				if ($key === 'appstoreenabled') {
					return true;
				} elseif ($key === 'version') {
					return '11.0.0.2';
				} else {
					return $default;
				}
			});

		$folder = $this->createMock(ISimpleFolder::class);
		$file = $this->createMock(ISimpleFile::class);
		$this->appData
			->expects($this->once())
			->method('getFolder')
			->with('/')
			->willReturn($folder);
		$folder
			->expects($this->at(0))
			->method('getFile')
			->with($this->fileName)
			->willReturn($file);
		$file
			->expects($this->at(0))
			->method('getContent')
			->willReturn('{"data":[{"id":"MyOldApp","abc":"def"}],"timestamp":1200,"ncversion":"11.0.0.2","ETag":"\"myETag\""}');
		$client = $this->createMock(IClient::class);
		$this->clientService
			->expects($this->once())
			->method('newClient')
			->willReturn($client);
		$response = $this->createMock(IResponse::class);
		$client
			->expects($this->once())
			->method('get')
			->with(
				$this->equalTo($this->endpoint),
				$this->equalTo([
					'timeout' => 60,
					'headers' => [
						'If-None-Match' => '"myETag"',
					]
				])
			)
			->willReturn($response);
		$response->method('getStatusCode')
			->willReturn(200);
		$response
			->expects($this->once())
			->method('getBody')
			->willReturn('[{"id":"MyNewApp","foo":"foo"},{"id":"bar"}]');
		$response->method('getHeader')
			->with($this->equalTo('ETag'))
			->willReturn('"newETag"');
		$fileData = '{"data":[{"id":"MyNewApp","foo":"foo"},{"id":"bar"}],"timestamp":4802,"ncversion":"11.0.0.2","ETag":"\"newETag\""}';
		$file
			->expects($this->at(1))
			->method('putContent')
			->with($fileData);
		$file
			->expects($this->at(2))
			->method('getContent')
			->willReturn($fileData);
		$this->timeFactory
			->expects($this->at(0))
			->method('getTime')
			->willReturn(4801);
		$this->timeFactory
			->expects($this->at(1))
			->method('getTime')
			->willReturn(4802);

		$expected = [
			[
				'id' => 'MyNewApp',
				'foo' => 'foo',
			],
			[
				'id' => 'bar',
			],
		];
		$this->assertSame($expected, $this->fetcher->get());
	}


	public function testFetchAfterUpgradeNoETag() {
		$this->config->method('getSystemValue')
			->willReturnCallback(function ($key, $default) {
				if ($key === 'appstoreenabled') {
					return true;
				} elseif ($key === 'version') {
					return '11.0.0.3';
				} else {
					return $default;
				}
			});

		$folder = $this->createMock(ISimpleFolder::class);
		$file = $this->createMock(ISimpleFile::class);
		$this->appData
			->expects($this->once())
			->method('getFolder')
			->with('/')
			->willReturn($folder);
		$folder
			->expects($this->at(0))
			->method('getFile')
			->with($this->fileName)
			->willReturn($file);
		$file
			->expects($this->at(0))
			->method('getContent')
			->willReturn('{"data":[{"id":"MyOldApp","abc":"def"}],"timestamp":1200,"ncversion":"11.0.0.2","ETag":"\"myETag\""}');
		$client = $this->createMock(IClient::class);
		$this->clientService
			->expects($this->once())
			->method('newClient')
			->willReturn($client);
		$response = $this->createMock(IResponse::class);
		$client
			->expects($this->once())
			->method('get')
			->with(
				$this->equalTo($this->endpoint),
				$this->equalTo([
					'timeout' => 60,
				])
			)
			->willReturn($response);
		$response->method('getStatusCode')
			->willReturn(200);
		$response
			->expects($this->once())
			->method('getBody')
			->willReturn('[{"id":"MyNewApp","foo":"foo"},{"id":"bar"}]');
		$response->method('getHeader')
			->with($this->equalTo('ETag'))
			->willReturn('"newETag"');
		$fileData = '{"data":[{"id":"MyNewApp","foo":"foo"},{"id":"bar"}],"timestamp":1501,"ncversion":"11.0.0.3","ETag":"\"newETag\""}';
		$file
			->expects($this->at(1))
			->method('putContent')
			->with($fileData);
		$file
			->expects($this->at(2))
			->method('getContent')
			->willReturn($fileData);
		$this->timeFactory
			->expects($this->once())
			->method('getTime')
			->willReturn(1501);

		$expected = [
			[
				'id' => 'MyNewApp',
				'foo' => 'foo',
			],
			[
				'id' => 'bar',
			],
		];
		$this->assertSame($expected, $this->fetcher->get());
	}
}
