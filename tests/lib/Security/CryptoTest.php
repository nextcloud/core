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
	public function defaultEncryptionProvider() {
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
		$this->crypto = new Crypto(\OC::$server->getConfig());
	}

	/**
	 * @dataProvider defaultEncryptionProvider
	 */
	public function testDefaultEncrypt($stringToEncrypt) {
		$ciphertext = $this->crypto->encrypt($stringToEncrypt);
		$this->assertEquals($stringToEncrypt, $this->crypto->decrypt($ciphertext));
	}


	public function testWrongPassword() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('HMAC does not match.');

		$stringToEncrypt = 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt.';
		$ciphertext = $this->crypto->encrypt($stringToEncrypt);
		$this->crypto->decrypt($ciphertext, 'A wrong password!');
	}

	public function testLaterDecryption() {
		$stringToEncrypt = 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt.';
		$encryptedString = '44a35023cca2e7a6125e06c29fc4b2ad9d8a33d0873a8b45b0de4ef9284f260c6c46bf25dc62120644c59b8bafe4281ddc47a70c35ae6c29ef7a63d79eefacc297e60b13042ac582733598d0a6b4de37311556bb5c480fd2633de4e6ebafa868c2d1e2d80a5d24f9660360dba4d6e0c8|lhrFgK0zd9U160Wo|a75e57ab701f9124e1113543fd1dc596f21e20d456a0d1e813d5a8aaec9adcb11213788e96598b67fe9486a9f0b99642c18296d0175db44b1ae426e4e91080ee';
		$this->assertEquals($stringToEncrypt, $this->crypto->decrypt($encryptedString, 'ThisIsAVeryS3cur3P4ssw0rd'));
	}


	public function testWrongIV() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('HMAC does not match.');

		$encryptedString = '560f5436ba864b9f12f7f7ca6d41c327554a6f2c0a160a03316b202af07c65163274993f3a46e7547c07ba89304f00594a2f3bd99f83859097c58049c39d0d4ade10e0de914ff0604961e7c849d0271ed6c0b23f984ba16e7d033e3305fb0910e7b6a2a65c988d17dbee71d8f953684d|d2kdFUspVjC0o0sr|1a5feacf87eaa6869a6abdfba9a296e7bbad45b6ad89f7dce67cdc98e2da5dc4379cc672cc655e52bbf19599bf59482fbea13a73937697fa656bf10f3fc4f1aa';
		$this->crypto->decrypt($encryptedString, 'ThisIsAVeryS3cur3P4ssw0rd');
	}


	public function testWrongParameters() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Authenticated ciphertext could not be decoded.');

		$encryptedString = '1|2';
		$this->crypto->decrypt($encryptedString, 'ThisIsAVeryS3cur3P4ssw0rd');
	}

	public function testLegacy() {
		$cipherText = 'e16599188e3d212f5c7f17fdc2abca46|M1WfLAxbcAmITeD6|509457885d6ca5e6c3bfd3741852687a7f2bffce197f8d5ae97b65818b15a1b7f616b68326ff312371540f4ca8ac55f8e2de4aa13aab3474bd3431e51214e3ee';
		$password = 'mypass';

		$this->assertSame('legacy test', $this->crypto->decrypt($cipherText, $password));
	}

	public function testVersion2CiphertextDecryptsToCorrectPlaintext() {
		$this->assertSame(
			'This is a plaintext value that will be encrypted with version 2. Which addresses the reduced permutations on the IV.',
			$this->crypto->decrypt(
				'be006387f753e8728717e43cfc5526c37adf7b2c9b4a113ceec03b7b0bccfebee74e0acfa0015c5712b4376dacbd7bce26a8fbca916fdccee46203d8289f6b2e4c19318044d375edfc67c72e6c3ae329d4c276b8d866ac1b281844e81f7681fe83d90bc4b6fffa4f3cbc157d64257a493b67fd2af3c8976cb76df520f5739305|02e78ea7c73a32f3b407c54227a9d2ce|3e7a09628f818b7b1cd7724467f5b1b33135de6d2ec62d8c0361be4f2c5203385f10babdcae017d7b30abe5be2117803e3195fb6d9ef20949fe35dad5e9241ea|2',
				'insecure-static-password'
			)
		);
	}

	public function testVersion3CiphertextDecryptsToCorrectPlaintext() {
		$this->assertSame(
			'Another plaintext value that will be encrypted with version 3. It addresses the related key issue. Old ciphertexts should be decrypted properly, but only use the better version for encryption.',
			$this->crypto->decrypt(
				'c99823461db746aa74f819c8640e9e3c367fa3bb9c21dff905b5dd14072c1d1b0da8b7e6b7307bf1561b6ba7aaa932a16c23b1fd5217dc019d55233ef0813c65fccaeabd6ea3a971ce1bbbdfda790ae00fb4442693cbb50072e02875b9f50591df74d00e96fd5b9bd13cb02a5f57b062ec98a4c64fc518ed325d097454883adbfc1687c2af995a392407c5e040a54afee4b2997ab158fe48ef67ccf721a6a7031fcb44d51170892ce7971021a7f3a00d19002eb9b007efe7aecf397ec0dc22064fb5d4a15ad83949f0804feca3c69cdd|8476f53c8d49a7e119798a70086d8911|ae3f7e23d469fbc791714ceb07d854624b1bbd39ac6a4edc05d552e10659adfdcada3a059fae737ffd7d842dd3fcc84bcc364cd298e814dd4967de4ad4a658eb|3',
				'insecure-static-password'
			)
		);
	}
}
