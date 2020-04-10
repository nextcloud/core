<?php
/**
 * @author Thomas Müller
 * @copyright 2014 Thomas Müller deepdiver@owncloud.com
 * later.
 * See the COPYING-README file.
 */

namespace Test\App;

use OC;
use OC\App\InfoParser;
use Test\TestCase;

class InfoParserTest extends TestCase {
	/** @var OC\Cache\CappedMemoryCache */
	private static $cache;

	public static function setUpBeforeClass(): void {
		self::$cache = new OC\Cache\CappedMemoryCache();
	}


	public function parserTest($expectedJson, $xmlFile, $cache = null) {
		$parser = new InfoParser($cache);

		$expectedData = null;
		if (!is_null($expectedJson)) {
			$expectedData = json_decode(file_get_contents(OC::$SERVERROOT . "/tests/data/app/$expectedJson"), true);
		}
		$data = $parser->parse(OC::$SERVERROOT. "/tests/data/app/$xmlFile");

		$this->assertEquals($expectedData, $data);
	}

	/**
	 * @dataProvider providesInfoXml
	 */
	public function testParsingValidXmlWithoutCache($expectedJson, $xmlFile) {
		$this->parserTest($expectedJson, $xmlFile);
	}

	/**
	 * @dataProvider providesInfoXml
	 */
	public function testParsingValidXmlWithCache($expectedJson, $xmlFile) {
		$this->parserTest($expectedJson, $xmlFile, self::$cache);
	}

	public function providesInfoXml(): array {
		return [
			['expected-info.json', 'valid-info.xml'],
			[null, 'invalid-info.xml'],
			['expected-info.json', 'valid-info.xml'],
			[null, 'invalid-info.xml'],
			['navigation-one-item.json', 'navigation-one-item.xml'],
			['navigation-two-items.json', 'navigation-two-items.xml'],
		];
	}
}
