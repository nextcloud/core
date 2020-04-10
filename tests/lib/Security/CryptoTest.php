<?php
/**
 * Copyright (c) 2014 Lukas Reschke <lukas@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Security;

use OC\Security\Crypto;

class CryptoTest extends \Test\TestCase {

	public function defaultEncryptionProvider()
	{
		return [
			['Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt.'],
			[''],
			['我看这本书。 我看這本書']
		];
	}

	/** @var Crypto */
	protected $crypto;

	protected function setUp(): void {
		parent::setUp();
		$this->crypto = new Crypto(\OC::$server->getConfig(), \OC::$server->getSecureRandom());
	}

	/**
	 * @dataProvider defaultEncryptionProvider
	 */
	function testDefaultEncrypt($stringToEncrypt) {
		$ciphertext = $this->crypto->encrypt($stringToEncrypt);
		$this->assertEquals($stringToEncrypt, $this->crypto->decrypt($ciphertext));
	}

	
	function testWrongPassword() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('HMAC does not match.');

		$stringToEncrypt = 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt.';
		$ciphertext = $this->crypto->encrypt($stringToEncrypt);
		$this->crypto->decrypt($ciphertext, 'A wrong password!');
	}

	function testLaterDecryption() {
		$stringToEncrypt = 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt.';
		$encryptedString = '44a35023cca2e7a6125e06c29fc4b2ad9d8a33d0873a8b45b0de4ef9284f260c6c46bf25dc62120644c59b8bafe4281ddc47a70c35ae6c29ef7a63d79eefacc297e60b13042ac582733598d0a6b4de37311556bb5c480fd2633de4e6ebafa868c2d1e2d80a5d24f9660360dba4d6e0c8|lhrFgK0zd9U160Wo|a75e57ab701f9124e1113543fd1dc596f21e20d456a0d1e813d5a8aaec9adcb11213788e96598b67fe9486a9f0b99642c18296d0175db44b1ae426e4e91080ee';
		$this->assertEquals($stringToEncrypt, $this->crypto->decrypt($encryptedString, 'ThisIsAVeryS3cur3P4ssw0rd'));
	}

	
	function testWrongIV() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('HMAC does not match.');

		$encryptedString = '560f5436ba864b9f12f7f7ca6d41c327554a6f2c0a160a03316b202af07c65163274993f3a46e7547c07ba89304f00594a2f3bd99f83859097c58049c39d0d4ade10e0de914ff0604961e7c849d0271ed6c0b23f984ba16e7d033e3305fb0910e7b6a2a65c988d17dbee71d8f953684d|d2kdFUspVjC0o0sr|1a5feacf87eaa6869a6abdfba9a296e7bbad45b6ad89f7dce67cdc98e2da5dc4379cc672cc655e52bbf19599bf59482fbea13a73937697fa656bf10f3fc4f1aa';
		$this->crypto->decrypt($encryptedString, 'ThisIsAVeryS3cur3P4ssw0rd');
	}

	
	function testWrongParameters() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Authenticated ciphertext could not be decoded.');

		$encryptedString = '1|2';
		$this->crypto->decrypt($encryptedString, 'ThisIsAVeryS3cur3P4ssw0rd');
	}
}
