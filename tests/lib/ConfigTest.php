<?php
/**
 * Copyright (c) 2013 Bart Visscher <bartv@thisnet.nl>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test;

use OC\Config;

class ConfigTest extends TestCase {
	public const TESTCONTENT = '<?php $CONFIG=array("foo"=>"bar", "beers" => array("Appenzeller", "Guinness", "Kölsch"), "alcohol_free" => false);';

	/** @var array */
	private $initialConfig = ['foo' => 'bar', 'beers' => ['Appenzeller', 'Guinness', 'Kölsch'], 'alcohol_free' => false];
	/** @var string */
	private $configFile;
	/** @var string */
	private $randomTmpDir;

	protected function setUp(): void {
		parent::setUp();

		$this->randomTmpDir = \OC::$server->getTempManager()->getTemporaryFolder();
		$this->configFile = $this->randomTmpDir.'testconfig.php';
		file_put_contents($this->configFile, self::TESTCONTENT);
	}

	protected function tearDown(): void {
		unlink($this->configFile);
		parent::tearDown();
	}

	private function getConfig(): Config {
		return new \OC\Config($this->randomTmpDir, 'testconfig.php');
	}

	public function testGetKeys() {
		$expectedConfig = ['foo', 'beers', 'alcohol_free'];
		$this->assertSame($expectedConfig, $this->getConfig()->getKeys());
	}

	public function testGetValue() {
		$config = $this->getConfig();
		$this->assertSame('bar', $config->getValue('foo'));
		$this->assertSame(null, $config->getValue('bar'));
		$this->assertSame('moo', $config->getValue('bar', 'moo'));
		$this->assertSame(false, $config->getValue('alcohol_free', 'someBogusValue'));
		$this->assertSame(['Appenzeller', 'Guinness', 'Kölsch'], $config->getValue('beers', 'someBogusValue'));
		$this->assertSame(['Appenzeller', 'Guinness', 'Kölsch'], $config->getValue('beers'));
	}

	public function testGetValueReturnsEnvironmentValueIfSet() {
		$config = $this->getConfig();
		$this->assertEquals('bar', $config->getValue('foo'));

		putenv('NC_foo=baz');
		$config = $this->getConfig();
		$this->assertEquals('baz', $config->getValue('foo'));
		putenv('NC_foo'); // unset the env variable
	}

	public function testGetValueReturnsEnvironmentValueIfSetToZero() {
		$config = $this->getConfig();
		$this->assertEquals('bar', $config->getValue('foo'));

		putenv('NC_foo=0');
		$config = $this->getConfig();
		$this->assertEquals('0', $config->getValue('foo'));
		putenv('NC_foo'); // unset the env variable
	}

	public function testGetValueReturnsEnvironmentValueIfSetToFalse() {
		$config = $this->getConfig();
		$this->assertEquals('bar', $config->getValue('foo'));

		putenv('NC_foo=false');
		$config = $this->getConfig();
		$this->assertEquals('false', $config->getValue('foo'));
		putenv('NC_foo'); // unset the env variable
	}

	public function testSetValue() {
		$config = $this->getConfig();
		$config->setValue('foo', 'moo');
		$this->assertSame('moo', $config->getValue('foo'));

		$content = file_get_contents($this->configFile);
		$expected = "<?php\n\$CONFIG = array (\n  'foo' => 'moo',\n  'beers' => \n  array (\n    0 => 'Appenzeller',\n  " .
			"  1 => 'Guinness',\n    2 => 'Kölsch',\n  ),\n  'alcohol_free' => false,\n);\n";
		$this->assertEquals($expected, $content);

		$config->setValue('bar', 'red');
		$config->setValue('apps', ['files', 'gallery']);
		$this->assertSame('red', $config->getValue('bar'));
		$this->assertSame(['files', 'gallery'], $config->getValue('apps'));

		$content = file_get_contents($this->configFile);

		$expected = "<?php\n\$CONFIG = array (\n  'foo' => 'moo',\n  'beers' => \n  array (\n    0 => 'Appenzeller',\n  " .
			"  1 => 'Guinness',\n    2 => 'Kölsch',\n  ),\n  'alcohol_free' => false,\n  'bar' => 'red',\n  'apps' => \n " .
			" array (\n    0 => 'files',\n    1 => 'gallery',\n  ),\n);\n";
		$this->assertEquals($expected, $content);
	}

	public function testSetValues() {
		$config = $this->getConfig();
		$content = file_get_contents($this->configFile);
		$this->assertEquals(self::TESTCONTENT, $content);

		// Changing configs to existing values and deleting non-existing once
		// should not rewrite the config.php
		$config->setValues([
			'foo' => 'bar',
			'not_exists' => null,
		]);

		$this->assertSame('bar', $config->getValue('foo'));
		$this->assertSame(null, $config->getValue('not_exists'));
		$content = file_get_contents($this->configFile);
		$this->assertEquals(self::TESTCONTENT, $content);

		$config->setValues([
			'foo' => 'moo',
			'alcohol_free' => null,
		]);
		$this->assertSame('moo', $config->getValue('foo'));
		$this->assertSame(null, $config->getValue('not_exists'));

		$content = file_get_contents($this->configFile);
		$expected = "<?php\n\$CONFIG = array (\n  'foo' => 'moo',\n  'beers' => \n  array (\n    0 => 'Appenzeller',\n  " .
			"  1 => 'Guinness',\n    2 => 'Kölsch',\n  ),\n);\n";
		$this->assertEquals($expected, $content);
	}

	public function testDeleteKey() {
		$config = $this->getConfig();
		$config->deleteKey('foo');
		$this->assertSame('this_was_clearly_not_set_before', $config->getValue('foo', 'this_was_clearly_not_set_before'));
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
		$config = new \OC\Config($this->randomTmpDir, 'testconfig.php');

		// Ensure that the config value can be read and the config has not been modified
		$this->assertSame('totallyOutdated', $config->getValue('php53', 'bogusValue'));
		$this->assertEquals(self::TESTCONTENT, file_get_contents($this->configFile));

		// Write a new value to the config
		$config->setValue('CoolWebsites', ['demo.owncloud.org', 'owncloud.org', 'owncloud.com']);
		$expected = "<?php\n\$CONFIG = array (\n  'foo' => 'bar',\n  'beers' => \n  array (\n    0 => 'Appenzeller',\n  " .
			"  1 => 'Guinness',\n    2 => 'Kölsch',\n  ),\n  'alcohol_free' => false,\n  'php53' => 'totallyOutdated',\n  'CoolWebsites' => \n  array (\n  " .
			"  0 => 'demo.owncloud.org',\n    1 => 'owncloud.org',\n    2 => 'owncloud.com',\n  ),\n);\n";
		$this->assertEquals($expected, file_get_contents($this->configFile));

		// Cleanup
		unlink($additionalConfigPath);
	}
}
