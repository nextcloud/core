<?php
/**
 * Copyright (c) 2013 Bart Visscher <bartv@thisnet.nl>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test;

class ConfigTest extends TestCase {
	const TESTCONTENT = '<?php $CONFIG=array("foo"=>"bar", "beers" => array("Appenzeller", "Guinness", "Kölsch"), "alcohol_free" => false);';

	/** @var array */
	private $initialConfig = ['foo' => 'bar', 'beers' => ['Appenzeller', 'Guinness', 'Kölsch'], 'alcohol_free' => false];
	/** @var string */
	private $configFile;
	/** @var \OC\Config */
	private $config;
	/** @var string */
	private $randomTmpDir;

	protected function setUp(): void {
		parent::setUp();

		$this->randomTmpDir = \OC::$server->getTempManager()->getTemporaryFolder();
		$this->configFile = $this->randomTmpDir.'testconfig.php';
		file_put_contents($this->configFile, self::TESTCONTENT);
		$this->config = new \OC\Config($this->randomTmpDir, 'testconfig.php');
	}

	protected function tearDown(): void {
		unlink($this->configFile);
		parent::tearDown();
	}

	public function testGetKeys() {
		$expectedConfig = ['foo', 'beers', 'alcohol_free'];
		$this->assertSame($expectedConfig, $this->config->getKeys());
	}

	public function testGetValue() {
		$this->assertSame('bar', $this->config->getValue('foo'));
		$this->assertSame(null, $this->config->getValue('bar'));
		$this->assertSame('moo', $this->config->getValue('bar', 'moo'));
		$this->assertSame(false, $this->config->getValue('alcohol_free', 'someBogusValue'));
		$this->assertSame(['Appenzeller', 'Guinness', 'Kölsch'], $this->config->getValue('beers', 'someBogusValue'));
		$this->assertSame(['Appenzeller', 'Guinness', 'Kölsch'], $this->config->getValue('beers'));
	}

	public function testGetValueReturnsEnvironmentValueIfSet() {
		$this->assertEquals('bar', $this->config->getValue('foo'));
		putenv('NC_foo=baz');
		$this->assertEquals('baz', $this->config->getValue('foo'));
		putenv('NC_foo'); // unset the env variable
	}

	public function testGetValueReturnsEnvironmentValueIfSetToZero() {
		$this->assertEquals('bar', $this->config->getValue('foo'));
		putenv('NC_foo=0');
		$this->assertEquals('0', $this->config->getValue('foo'));
		putenv('NC_foo'); // unset the env variable
	}

	public function testGetValueReturnsEnvironmentValueIfSetToFalse() {
		$this->assertEquals('bar', $this->config->getValue('foo'));
		putenv('NC_foo=false');
		$this->assertEquals('false', $this->config->getValue('foo'));
		putenv('NC_foo'); // unset the env variable
	}

	public function testSetValue() {
		$this->config->setValue('foo', 'moo');
		$expectedConfig = $this->initialConfig;
		$expectedConfig['foo'] = 'moo';
		$this->assertAttributeEquals($expectedConfig, 'cache', $this->config);

		$content = file_get_contents($this->configFile);
		$expected = "<?php\n\$CONFIG = array (\n  'foo' => 'moo',\n  'beers' => \n  array (\n    0 => 'Appenzeller',\n  " .
			"  1 => 'Guinness',\n    2 => 'Kölsch',\n  ),\n  'alcohol_free' => false,\n);\n";
		$this->assertEquals($expected, $content);

		$this->config->setValue('bar', 'red');
		$this->config->setValue('apps', ['files', 'gallery']);
		$expectedConfig['bar'] = 'red';
		$expectedConfig['apps'] = ['files', 'gallery'];
		$this->assertAttributeEquals($expectedConfig, 'cache', $this->config);

		$content = file_get_contents($this->configFile);

		$expected = "<?php\n\$CONFIG = array (\n  'foo' => 'moo',\n  'beers' => \n  array (\n    0 => 'Appenzeller',\n  " .
			"  1 => 'Guinness',\n    2 => 'Kölsch',\n  ),\n  'alcohol_free' => false,\n  'bar' => 'red',\n  'apps' => \n " .
			" array (\n    0 => 'files',\n    1 => 'gallery',\n  ),\n);\n";
		$this->assertEquals($expected, $content);
	}

	public function testSetValues() {
		$content = file_get_contents($this->configFile);
		$this->assertEquals(self::TESTCONTENT, $content);

		// Changing configs to existing values and deleting non-existing once
		// should not rewrite the config.php
		$this->config->setValues([
			'foo'			=> 'bar',
			'not_exists'	=> null,
		]);

		$this->assertAttributeEquals($this->initialConfig, 'cache', $this->config);
		$content = file_get_contents($this->configFile);
		$this->assertEquals(self::TESTCONTENT, $content);

		$this->config->setValues([
			'foo'			=> 'moo',
			'alcohol_free'	=> null,
		]);
		$expectedConfig = $this->initialConfig;
		$expectedConfig['foo'] = 'moo';
		unset($expectedConfig['alcohol_free']);
		$this->assertAttributeEquals($expectedConfig, 'cache', $this->config);

		$content = file_get_contents($this->configFile);
		$expected = "<?php\n\$CONFIG = array (\n  'foo' => 'moo',\n  'beers' => \n  array (\n    0 => 'Appenzeller',\n  " .
			"  1 => 'Guinness',\n    2 => 'Kölsch',\n  ),\n);\n";
		$this->assertEquals($expected, $content);
	}

	public function testDeleteKey() {
		$this->config->deleteKey('foo');
		$expectedConfig = $this->initialConfig;
		unset($expectedConfig['foo']);
		$this->assertAttributeEquals($expectedConfig, 'cache', $this->config);
		$content = file_get_contents($this->configFile);

		$expected = "<?php\n\$CONFIG = array (\n  'beers' => \n  array (\n    0 => 'Appenzeller',\n  " .
			"  1 => 'Guinness',\n    2 => 'Kölsch',\n  ),\n  'alcohol_free' => false,\n);\n";
		$this->assertEquals($expected, $content);
	}

	public function testConfigMerge() {
		// Create additional config
		$additionalConfig = '<?php $CONFIG=array("php53"=>"totallyOutdated");';
		$additionalConfigPath = $this->randomTmpDir.'additionalConfig.testconfig.php';
		file_put_contents($additionalConfigPath, $additionalConfig);

		// Reinstantiate the config to force a read-in of the additional configs
		$this->config = new \OC\Config($this->randomTmpDir, 'testconfig.php');

		// Ensure that the config value can be read and the config has not been modified
		$this->assertSame('totallyOutdated', $this->config->getValue('php53', 'bogusValue'));
		$this->assertEquals(self::TESTCONTENT, file_get_contents($this->configFile));

		// Write a new value to the config
		$this->config->setValue('CoolWebsites', ['demo.owncloud.org', 'owncloud.org', 'owncloud.com']);
		$expected = "<?php\n\$CONFIG = array (\n  'foo' => 'bar',\n  'beers' => \n  array (\n    0 => 'Appenzeller',\n  " .
			"  1 => 'Guinness',\n    2 => 'Kölsch',\n  ),\n  'alcohol_free' => false,\n  'php53' => 'totallyOutdated',\n  'CoolWebsites' => \n  array (\n  " .
			"  0 => 'demo.owncloud.org',\n    1 => 'owncloud.org',\n    2 => 'owncloud.com',\n  ),\n);\n";
		$this->assertEquals($expected, file_get_contents($this->configFile));

		// Cleanup
		unlink($additionalConfigPath);
	}

}
