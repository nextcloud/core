<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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

namespace OCA\Files_External\Tests;

use OCA\Files_External\Lib\DefinitionParameter;
use OCA\Files_External\Lib\StorageConfig;

class FrontendDefinitionTraitTest extends \Test\TestCase {

	public function testJsonSerialization() {
		$param = $this->getMockBuilder(DefinitionParameter::class)
			->disableOriginalConstructor()
			->getMock();
		$param->method('getName')->willReturn('foo');

		$trait = $this->getMockForTrait('\OCA\Files_External\Lib\FrontendDefinitionTrait');
		$trait->setText('test');
		$trait->addParameters([$param]);
		$trait->addCustomJs('foo/bar.js');
		$trait->addCustomJs('bar/foo.js');

		$json = $trait->jsonSerializeDefinition();

		$this->assertEquals('test', $json['name']);
		$this->assertContains('foo/bar.js', $json['custom']);
		$this->assertContains('bar/foo.js', $json['custom']);

		$configuration = $json['configuration'];
		$this->assertArrayHasKey('foo', $configuration);
	}

	public function validateStorageProvider() {
		return [
			[true, ['foo' => true, 'bar' => true, 'baz' => true]],
			[false, ['foo' => true, 'bar' => false]]
		];
	}

	/**
	 * @dataProvider validateStorageProvider
	 */
	public function testValidateStorage($expectedSuccess, $params) {
		$backendParams = [];
		foreach ($params as $name => $valid) {
			$param = $this->getMockBuilder(DefinitionParameter::class)
				->disableOriginalConstructor()
				->getMock();
			$param->method('getName')
				->willReturn($name);
			$param->method('isOptional')
				->willReturn(false);
			$param->expects($this->once())
				->method('validateValue')
				->willReturn($valid);
			$backendParams[] = $param;
		}

		$storageConfig = $this->getMockBuilder(StorageConfig::class)
			->disableOriginalConstructor()
			->getMock();
		$storageConfig->expects($this->any())
			->method('getBackendOption')
			->willReturn(null);
		$storageConfig->expects($this->any())
			->method('setBackendOption');

		$trait = $this->getMockForTrait('\OCA\Files_External\Lib\FrontendDefinitionTrait');
		$trait->setText('test');
		$trait->addParameters($backendParams);

		$this->assertEquals($expectedSuccess, $trait->validateStorageDefinition($storageConfig));
	}

	public function testValidateStorageSet() {
		$param = $this->getMockBuilder(DefinitionParameter::class)
			->disableOriginalConstructor()
			->getMock();
		$param->method('getName')
			->willReturn('param');
		$param->expects($this->once())
			->method('validateValue')
			->willReturnCallback(function (&$value) {
				$value = 'foobar';
				return true;
			});

		$storageConfig = $this->getMockBuilder(StorageConfig::class)
			->disableOriginalConstructor()
			->getMock();
		$storageConfig->expects($this->once())
			->method('getBackendOption')
			->with('param')
			->willReturn('barfoo');
		$storageConfig->expects($this->once())
			->method('setBackendOption')
			->with('param', 'foobar');

		$trait = $this->getMockForTrait('\OCA\Files_External\Lib\FrontendDefinitionTrait');
		$trait->setText('test');
		$trait->addParameter($param);

		$this->assertEquals(true, $trait->validateStorageDefinition($storageConfig));
	}
}
