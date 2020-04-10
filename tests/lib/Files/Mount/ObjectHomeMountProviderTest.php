<?php

namespace Test\Files\Mount;

use OC\Files\Mount\ObjectHomeMountProvider;
use OCP\Files\Storage\IStorageFactory;
use OCP\IConfig;
use OCP\IUser;

class ObjectHomeMountProviderTest extends \Test\TestCase {

	/** @var ObjectHomeMountProvider */
	protected $provider;

	/** @var IConfig|\PHPUnit_Framework_MockObject_MockObject */
	protected $config;

	/** @var IUser|\PHPUnit_Framework_MockObject_MockObject */
	protected $user;

	/** @var IStorageFactory|\PHPUnit_Framework_MockObject_MockObject */
	protected $loader;

	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);
		$this->user = $this->createMock(IUser::class);
		$this->loader = $this->createMock(IStorageFactory::class);

		$this->provider = new ObjectHomeMountProvider($this->config);
	}

	public function testSingleBucket() {
		$this->config->expects($this->once())
			->method('getSystemValue')
			->with($this->equalTo('objectstore'), '')
			->willReturn([
				'class' => 'Test\Files\Mount\FakeObjectStore',
			]);

		$this->user->expects($this->never())->method($this->anything());
		$this->loader->expects($this->never())->method($this->anything());

		$config = $this->invokePrivate($this->provider, 'getSingleBucketObjectStoreConfig', [$this->user, $this->loader]);

		$this->assertArrayHasKey('class', $config);
		$this->assertEquals($config['class'], 'Test\Files\Mount\FakeObjectStore');
		$this->assertArrayHasKey('arguments', $config);
		$this->assertArrayHasKey('user', $config['arguments']);
		$this->assertSame($this->user, $config['arguments']['user']);
		$this->assertArrayHasKey('objectstore', $config['arguments']);
		$this->assertInstanceOf('Test\Files\Mount\FakeObjectStore', $config['arguments']['objectstore']);
	}

	public function testMultiBucket() {
		$this->config->expects($this->once())
			->method('getSystemValue')
			->with($this->equalTo('objectstore_multibucket'), '')
			->willReturn([
				'class' => 'Test\Files\Mount\FakeObjectStore',
			]);

		$this->user->method('getUID')
			->willReturn('uid');
		$this->loader->expects($this->never())->method($this->anything());

		$this->config->expects($this->once())
			->method('getUserValue')
			->with(
				$this->equalTo('uid'),
				$this->equalTo('homeobjectstore'),
				$this->equalTo('bucket'),
				$this->equalTo(null)
			)->willReturn(null);

		$this->config->expects($this->once())
			->method('setUserValue')
			->with(
				$this->equalTo('uid'),
				$this->equalTo('homeobjectstore'),
				$this->equalTo('bucket'),
				$this->equalTo('49'),
				$this->equalTo(null)
			);

		$config = $this->invokePrivate($this->provider, 'getMultiBucketObjectStoreConfig', [$this->user, $this->loader]);

		$this->assertArrayHasKey('class', $config);
		$this->assertEquals($config['class'], 'Test\Files\Mount\FakeObjectStore');
		$this->assertArrayHasKey('arguments', $config);
		$this->assertArrayHasKey('user', $config['arguments']);
		$this->assertSame($this->user, $config['arguments']['user']);
		$this->assertArrayHasKey('objectstore', $config['arguments']);
		$this->assertInstanceOf('Test\Files\Mount\FakeObjectStore', $config['arguments']['objectstore']);
		$this->assertArrayHasKey('bucket', $config['arguments']);
		$this->assertEquals('49', $config['arguments']['bucket']);
	}

	public function testMultiBucketWithPrefix() {
		$this->config->expects($this->once())
			->method('getSystemValue')
			->with($this->equalTo('objectstore_multibucket'), '')
			->willReturn([
				'class' => 'Test\Files\Mount\FakeObjectStore',
				'arguments' => [
					'bucket' => 'myBucketPrefix',
				],
			]);

		$this->user->method('getUID')
			->willReturn('uid');
		$this->loader->expects($this->never())->method($this->anything());

		$this->config->expects($this->once())
			->method('getUserValue')
			->with(
				$this->equalTo('uid'),
				$this->equalTo('homeobjectstore'),
				$this->equalTo('bucket'),
				$this->equalTo(null)
			)->willReturn(null);

		$this->config->expects($this->once())
			->method('setUserValue')
			->with(
				$this->equalTo('uid'),
				$this->equalTo('homeobjectstore'),
				$this->equalTo('bucket'),
				$this->equalTo('myBucketPrefix49'),
				$this->equalTo(null)
			);

		$config = $this->invokePrivate($this->provider, 'getMultiBucketObjectStoreConfig', [$this->user, $this->loader]);

		$this->assertArrayHasKey('class', $config);
		$this->assertEquals($config['class'], 'Test\Files\Mount\FakeObjectStore');
		$this->assertArrayHasKey('arguments', $config);
		$this->assertArrayHasKey('user', $config['arguments']);
		$this->assertSame($this->user, $config['arguments']['user']);
		$this->assertArrayHasKey('objectstore', $config['arguments']);
		$this->assertInstanceOf('Test\Files\Mount\FakeObjectStore', $config['arguments']['objectstore']);
		$this->assertArrayHasKey('bucket', $config['arguments']);
		$this->assertEquals('myBucketPrefix49', $config['arguments']['bucket']);
	}

	public function testMultiBucketBucketAlreadySet() {
		$this->config->expects($this->once())
			->method('getSystemValue')
			->with($this->equalTo('objectstore_multibucket'), '')
			->willReturn([
				'class' => 'Test\Files\Mount\FakeObjectStore',
				'arguments' => [
					'bucket' => 'myBucketPrefix',
				],
			]);

		$this->user->method('getUID')
			->willReturn('uid');
		$this->loader->expects($this->never())->method($this->anything());

		$this->config->expects($this->once())
			->method('getUserValue')
			->with(
				$this->equalTo('uid'),
				$this->equalTo('homeobjectstore'),
				$this->equalTo('bucket'),
				$this->equalTo(null)
			)->willReturn('awesomeBucket1');

		$this->config->expects($this->never())
			->method('setUserValue');

		$config = $this->invokePrivate($this->provider, 'getMultiBucketObjectStoreConfig', [$this->user, $this->loader]);

		$this->assertArrayHasKey('class', $config);
		$this->assertEquals($config['class'], 'Test\Files\Mount\FakeObjectStore');
		$this->assertArrayHasKey('arguments', $config);
		$this->assertArrayHasKey('user', $config['arguments']);
		$this->assertSame($this->user, $config['arguments']['user']);
		$this->assertArrayHasKey('objectstore', $config['arguments']);
		$this->assertInstanceOf('Test\Files\Mount\FakeObjectStore', $config['arguments']['objectstore']);
		$this->assertArrayHasKey('bucket', $config['arguments']);
		$this->assertEquals('awesomeBucket1', $config['arguments']['bucket']);
	}

	public function testMultiBucketConfigFirst() {
		$this->config->expects($this->once())
			->method('getSystemValue')
			->with($this->equalTo('objectstore_multibucket'))
			->willReturn([
				'class' => 'Test\Files\Mount\FakeObjectStore',
			]);

		$this->user->method('getUID')
			->willReturn('uid');
		$this->loader->expects($this->never())->method($this->anything());

		$mount = $this->provider->getHomeMountForUser($this->user, $this->loader);
		$this->assertInstanceOf('OC\Files\Mount\MountPoint', $mount);
	}

	public function testMultiBucketConfigFirstFallBackSingle() {
		$this->config->expects($this->at(0))
			->method('getSystemValue')
			->with($this->equalTo('objectstore_multibucket'))
			->willReturn('');

		$this->config->expects($this->at(1))
			->method('getSystemValue')
			->with($this->equalTo('objectstore'))
			->willReturn([
				'class' => 'Test\Files\Mount\FakeObjectStore',
			]);

		$this->user->method('getUID')
			->willReturn('uid');
		$this->loader->expects($this->never())->method($this->anything());
		
		$mount = $this->provider->getHomeMountForUser($this->user, $this->loader);
		$this->assertInstanceOf('OC\Files\Mount\MountPoint', $mount);
	}

	public function testNoObjectStore() {
		$this->config->expects($this->exactly(2))
			->method('getSystemValue')
			->willReturn('');

		$mount = $this->provider->getHomeMountForUser($this->user, $this->loader);
		$this->assertNull($mount);
	}
}

class FakeObjectStore {
	private $arguments;

	public function __construct(array $arguments) {
		$this->arguments = $arguments;
	}

	public function getArguments() {
		return $this->arguments;
	}
}
