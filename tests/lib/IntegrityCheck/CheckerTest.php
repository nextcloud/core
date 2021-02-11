<?php
/**
 * @author Lukas Reschke <lukas@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace Test\IntegrityCheck;

use OC\IntegrityCheck\Checker;
use OC\IntegrityCheck\Helpers\AppLocator;
use OC\IntegrityCheck\Helpers\EnvironmentHelper;
use OC\IntegrityCheck\Helpers\FileAccessHelper;
use OC\Memcache\NullCache;
use OCP\App\IAppManager;
use OCP\ICacheFactory;
use OCP\IConfig;
use phpseclib\Crypt\RSA;
use phpseclib\File\X509;
use Test\TestCase;

class CheckerTest extends TestCase {
	/** @var EnvironmentHelper|\PHPUnit\Framework\MockObject\MockObject */
	private $environmentHelper;
	/** @var AppLocator|\PHPUnit\Framework\MockObject\MockObject */
	private $appLocator;
	/** @var Checker */
	private $checker;
	/** @var FileAccessHelper|\PHPUnit\Framework\MockObject\MockObject */
	private $fileAccessHelper;
	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	private $config;
	/** @var ICacheFactory|\PHPUnit\Framework\MockObject\MockObject */
	private $cacheFactory;
	/** @var IAppManager|\PHPUnit\Framework\MockObject\MockObject */
	private $appManager;
	/** @var \OC\Files\Type\Detection|\PHPUnit\Framework\MockObject\MockObject */
	private $mimeTypeDetector;

	protected function setUp(): void {
		parent::setUp();
		$this->environmentHelper = $this->createMock(EnvironmentHelper::class);
		$this->fileAccessHelper = $this->createMock(FileAccessHelper::class);
		$this->appLocator = $this->createMock(AppLocator::class);
		$this->config = $this->createMock(IConfig::class);
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->mimeTypeDetector = $this->createMock(\OC\Files\Type\Detection::class);

		$this->config->method('getAppValue')
			->willReturnArgument(2);

		$this->cacheFactory
			->expects($this->any())
			->method('createDistributed')
			->with('oc.integritycheck.checker')
			->willReturn(new NullCache());

		$this->checker = new Checker(
			$this->environmentHelper,
			$this->fileAccessHelper,
			$this->appLocator,
			$this->config,
			$this->cacheFactory,
			$this->appManager,
			$this->mimeTypeDetector
		);
	}


	public function testWriteAppSignatureOfNotExistingApp() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Exception message');

		$this->fileAccessHelper
			->expects($this->at(0))
			->method('assertDirectoryExists')
			->with('NotExistingApp/appinfo')
			->willThrowException(new \Exception('Exception message'));
		$this->fileAccessHelper
			->expects($this->at(1))
			->method('is_writable')
			->with('NotExistingApp/appinfo')
			->willReturn(true);

		$keyBundle = file_get_contents(__DIR__ .'/../../data/integritycheck/SomeApp.crt');
		$rsaPrivateKey = file_get_contents(__DIR__ .'/../../data/integritycheck/SomeApp.key');
		$rsa = new RSA();
		$rsa->loadKey($rsaPrivateKey);
		$x509 = new X509();
		$x509->loadX509($keyBundle);
		$this->checker->writeAppSignature('NotExistingApp', $x509, $rsa);
	}


	public function testWriteAppSignatureWrongPermissions() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessageMatches('/[a-zA-Z\\/_-]+ is not writable/');

		$this->fileAccessHelper
			->expects($this->once())
			->method('file_put_contents')
			->will($this->throwException(new \Exception('Exception message')));

		$keyBundle = file_get_contents(__DIR__ .'/../../data/integritycheck/SomeApp.crt');
		$rsaPrivateKey = file_get_contents(__DIR__ .'/../../data/integritycheck/SomeApp.key');
		$rsa = new RSA();
		$rsa->loadKey($rsaPrivateKey);
		$x509 = new X509();
		$x509->loadX509($keyBundle);
		$this->checker->writeAppSignature(\OC::$SERVERROOT . '/tests/data/integritycheck/app/', $x509, $rsa);
	}

	public function testWriteAppSignature() {
		$expectedSignatureFileData = '{
    "hashes": {
        "AnotherFile.txt": "1570ca9420e37629de4328f48c51da29840ddeaa03ae733da4bf1d854b8364f594aac560601270f9e1797ed4cd57c1aea87bf44cf4245295c94f2e935a2f0112",
        "subfolder\/file.txt": "410738545fb623c0a5c8a71f561e48ea69e3ada0981a455e920a5ae9bf17c6831ae654df324f9328ff8453de179276ae51931cca0fa71fe8ccde6c083ca0574b"
    },
    "signature": "Y5yvXvcGHVPuRRatKVDUONWq1FpLXugZd6Km\/+aEHsQj7coVl9FeMj9OsWamBf7yRIw3dtNLguTLlAA9QAv\/b0uHN3JnbNZN+dwFOve4NMtqXfSDlWftqKN00VS+RJXpG1S2IIx9Poyp2NoghL\/5AuTv4GHiNb7zU\/DT\/kt71pUGPgPR6IIFaE+zHOD96vjYkrH+GfWZzKR0FCdLib9yyNvk+EGrcjKM6qjs2GKfS\/XFjj\/\/neDnh\/0kcPuKE3ZbofnI4TIDTv0CGqvOp7PtqVNc3Vy\/UKa7uF1PT0MAUKMww6EiMUSFZdUVP4WWF0Y72W53Qdtf1hrAZa2kfKyoK5kd7sQmCSKUPSU8978AUVZlBtTRlyT803IKwMV0iHMkw+xYB1sN2FlHup\/DESADqxhdgYuK35bCPvgkb4SBe4B8Voz\/izTvcP7VT5UvkYdAO+05\/jzdaHEmzmsD92CFfvX0q8O\/Y\/29ubftUJsqcHeMDKgcR4eZOE8+\/QVc\/89QO6WnKNuNuV+5bybO6g6PAdC9ZPsCvnihS61O2mwRXHLR3jv2UleFWm+lZEquPKtkhi6SLtDiijA4GV6dmS+dzujSLb7hGeD5o1plZcZ94uhWljl+QIp82+zU\/lYB1Zfr4Mb4e+V7r2gv7Fbv7y6YtjE2GIQwRhC5jq56bD0ZB+I=",
    "certificate": "-----BEGIN CERTIFICATE-----\r\nMIIEwTCCAqmgAwIBAgIUWv0iujufs5lUr0svCf\/qTQvoyKAwDQYJKoZIhvcNAQEF\r\nBQAwIzEhMB8GA1UECgwYb3duQ2xvdWQgQ29kZSBTaWduaW5nIENBMB4XDTE1MTEw\r\nMzIyNDk1M1oXDTE2MTEwMzIyNDk1M1owEjEQMA4GA1UEAwwHU29tZUFwcDCCAiIw\r\nDQYJKoZIhvcNAQEBBQADggIPADCCAgoCggIBAK8q0x62agGSRBqeWsaeEwFfepMk\r\nF8cAobMMi50qHCv9IrOn\/ZH9l52xBrbIkErVmRjmly0d4JhD8Ymhidsh9ONKYl\/j\r\n+ishsZDM8eNNdp3Ew+fEYVvY1W7mR1qU24NWj0bzVsClI7hvPVIuw7AjfBDq1C5+\r\nA+ZSLSXYvOK2cEWjdxQfuNZwEZSjmA63DUllBIrm35IaTvfuyhU6BW9yHZxmb8+M\r\nw0xDv30D5UkE\/2N7Pa\/HQJLxCR+3zKibRK3nUyRDLSXxMkU9PnFNaPNX59VPgyj4\r\nGB1CFSToldJVPF4pzh7p36uGXZVxs8m3LFD4Ol8mhi7jkxDZjqFN46gzR0r23Py6\r\ndol9vfawGIoUwp9LvL0S7MvdRY0oazLXwClLP4OQ17zpSMAiCj7fgNT661JamPGj\r\nt5O7Zn2wA7I4ddDS\/HDTWCu98Zwc9fHIpsJPgCZ9awoqxi4Mnf7Pk9g5nnXhszGC\r\ncxxIASQKM+GhdzoRxKknax2RzUCwCzcPRtCj8AQT\/x\/mqN3PfRmlnFBNACUw9bpZ\r\nSOoNq2pCF9igftDWpSIXQ38pVpKLWowjjg3DVRmVKBgivHnUnVLyzYBahHPj0vaz\r\ntFtUFRaqXDnt+4qyUGyrT5h5pjZaTcHIcSB4PiarYwdVvgslgwnQzOUcGAzRWBD4\r\n6jV2brP5vFY3g6iPAgMBAAEwDQYJKoZIhvcNAQEFBQADggIBACTY3CCHC+Z28gCf\r\nFWGKQ3wAKs+k4+0yoti0qm2EKX7rSGQ0PHSas6uW79WstC4Rj+DYkDtIhGMSg8FS\r\nHVGZHGBCc0HwdX+BOAt3zi4p7Sf3oQef70\/4imPoKxbAVCpd\/cveVcFyDC19j1yB\r\nBapwu87oh+muoeaZxOlqQI4UxjBlR\/uRSMhOn2UGauIr3dWJgAF4pGt7TtIzt+1v\r\n0uA6FtN1Y4R5O8AaJPh1bIG0CVvFBE58esGzjEYLhOydgKFnEP94kVPgJD5ds9C3\r\npPhEpo1dRpiXaF7WGIV1X6DI\/ipWvfrF7CEy6I\/kP1InY\/vMDjQjeDnJ\/VrXIWXO\r\nyZvHXVaN\/m+1RlETsH7YO\/QmxRue9ZHN3gvvWtmpCeA95sfpepOk7UcHxHZYyQbF\r\n49\/au8j+5tsr4A83xzsT1JbcKRxkAaQ7WDJpOnE5O1+H0fB+BaLakTg6XX9d4Fo7\r\n7Gin7hVWX7pL+JIyxMzME3LhfI61+CRcqZQIrpyaafUziPQbWIPfEs7h8tCOWyvW\r\nUO8ZLervYCB3j44ivkrxPlcBklDCqqKKBzDP9dYOtS\/P4RB1NkHA9+NTvmBpTonS\r\nSFXdg9fFMD7VfjDE3Vnk+8DWkVH5wBYowTAD7w9Wuzr7DumiAULexnP\/Y7xwxLv7\r\n4B+pXTAcRK0zECDEaX3npS8xWzrB\r\n-----END CERTIFICATE-----"
}';
		$this->fileAccessHelper
			->expects($this->once())
			->method('file_put_contents')
			->with(
					$this->equalTo(\OC::$SERVERROOT . '/tests/data/integritycheck/app//appinfo/signature.json'),
					$this->callback(function ($signature) use ($expectedSignatureFileData) {
						$expectedArray = json_decode($expectedSignatureFileData, true);
						$actualArray = json_decode($signature, true);
						$this->assertEquals($expectedArray, $actualArray);
						return true;
					})
			);

		$keyBundle = file_get_contents(__DIR__ .'/../../data/integritycheck/SomeApp.crt');
		$rsaPrivateKey = file_get_contents(__DIR__ .'/../../data/integritycheck/SomeApp.key');
		$rsa = new RSA();
		$rsa->loadKey($rsaPrivateKey);
		$x509 = new X509();
		$x509->loadX509($keyBundle);
		$this->checker->writeAppSignature(\OC::$SERVERROOT . '/tests/data/integritycheck/app/', $x509, $rsa);
	}

	public function testVerifyAppSignatureWithoutSignatureData() {
		$this->environmentHelper
				->expects($this->once())
				->method('getChannel')
				->willReturn('stable');
		$this->config
				->expects($this->any())
				->method('getSystemValue')
				->with('integrity.check.disabled', false)
				->willReturn(false);

		$expected = [
			'EXCEPTION' => [
				'class' => 'OC\IntegrityCheck\Exceptions\InvalidSignatureException',
				'message' => 'Signature data not found.',
			],
		];
		$this->assertSame($expected, $this->checker->verifyAppSignature('SomeApp'));
	}

	public function testVerifyAppSignatureWithValidSignatureData() {
		$this->environmentHelper
				->expects($this->once())
				->method('getChannel')
				->willReturn('stable');
		$this->config
				->expects($this->any())
				->method('getSystemValue')
				->with('integrity.check.disabled', false)
				->willReturn(false);

		$this->appLocator
				->expects($this->once())
				->method('getAppPath')
				->with('SomeApp')
				->willReturn(\OC::$SERVERROOT . '/tests/data/integritycheck/app/');
		$signatureDataFile = '{
    "hashes": {
        "AnotherFile.txt": "1570ca9420e37629de4328f48c51da29840ddeaa03ae733da4bf1d854b8364f594aac560601270f9e1797ed4cd57c1aea87bf44cf4245295c94f2e935a2f0112",
        "subfolder\/file.txt": "410738545fb623c0a5c8a71f561e48ea69e3ada0981a455e920a5ae9bf17c6831ae654df324f9328ff8453de179276ae51931cca0fa71fe8ccde6c083ca0574b"
    },
    "signature": "dYoohBaWIFR\/To1FXEbMQB5apUhVYlEauBGSPo12nq84wxWkBx2EM3KDRgkB5Sub2tr0CgmAc2EVjPhKIEzAam26cyUb48bJziz1V6wvW7z4GZAfaJpzLkyHdSfV5117VSf5w1rDcAeZDXfGUaaNEJPWytaF4ZIxVge7f3NGshHy4odFVPADy\/u6c43BWvaOtJ4m3aJQbP6sxCO9dxwcm5yJJJR3n36jfh229sdWBxyl8BhwhH1e1DEv78\/aiL6ckKFPVNzx01R6yDFt3TgEMR97YZ\/R6lWiXG+dsJ305jNFlusLu518zBUvl7g5yjzGN778H29b2C8VLZKmi\/h1CH9jGdD72fCqCYdenD2uZKzb6dsUtXtvBmVcVT6BUGz41W1pkkEEB+YJpMrHILIxAiHRGv1+aZa9\/Oz8LWFd+BEUQjC2LJgojPnpzaG\/msw1nBkX16NNVDWWtJ25Bc\/r\/mG46rwjWB\/cmV6Lwt6KODiqlxgrC4lm9ALOCEWw+23OcYhLwNfQTYevXqHqsFfXOkhUnM8z5vDUb\/HBraB1DjFXN8iLK+1YewD4P495e+SRzrR79Oi3F8SEqRIzRLfN2rnW1BTms\/wYsz0p67cup1Slk1XlNmHwbWX25NVd2PPlLOvZRGoqcKFpIjC5few8THiZfyjiNFwt3RM0AFdZcXY=",
    "certificate": "-----BEGIN CERTIFICATE-----\r\nMIIEvjCCAqagAwIBAgIUc\/0FxYrsgSs9rDxp03EJmbjN0NwwDQYJKoZIhvcNAQEF\r\nBQAwIzEhMB8GA1UECgwYb3duQ2xvdWQgQ29kZSBTaWduaW5nIENBMB4XDTE1MTEw\r\nMzIxMDMzM1oXDTE2MTEwMzIxMDMzM1owDzENMAsGA1UEAwwEY29yZTCCAiIwDQYJ\r\nKoZIhvcNAQEBBQADggIPADCCAgoCggIBALb6EgHpkAqZbO5vRO8XSh7G7XGWHw5s\r\niOf4RwPXR6SE9bWZEm\/b72SfWk\/\/J6AbrD8WiOzBuT\/ODy6k5T1arEdHO+Pux0W1\r\nMxYJJI4kH74KKgMpC0SB0Rt+8WrMqV1r3hhJ46df6Xr\/xolP3oD+eLbShPcblhdS\r\nVtkZEkoev8Sh6L2wDCeHDyPxzvj1w2dTdGVO9Kztn0xIlyfEBakqvBWtcxyi3Ln0\r\nklnxlMx3tPDUE4kqvpia9qNiB1AN2PV93eNr5\/2riAzIssMFSCarWCx0AKYb54+d\r\nxLpcYFyqPJ0ydBCkF78DD45RCZet6PNYkdzgbqlUWEGGomkuDoJbBg4wzgzO0D77\r\nH87KFhYW8tKFFvF1V3AHl\/sFQ9tDHaxM9Y0pZ2jPp\/ccdiqnmdkBxBDqsiRvHvVB\r\nCn6qpb4vWGFC7vHOBfYspmEL1zLlKXZv3ezMZEZw7O9ZvUP3VO\/wAtd2vUW8UFiq\r\ns2v1QnNLN6jNh51obcwmrBvWhJy9vQIdtIjQbDxqWTHh1zUSrw9wrlklCBZ\/zrM0\r\ni8nfCFwTxWRxp3H9KoECzO\/zS5R5KIS7s3\/wq\/w9T2Ie4rcecgXwDizwnn0C\/aKc\r\nbDIjujpL1s9HO05pcD\/V3wKcPZ1izymBkmMyIbL52iRVN5FTVHeZdXPpFuq+CTQJ\r\nQ238lC+A\/KOVAgMBAAEwDQYJKoZIhvcNAQEFBQADggIBAGoKTnh8RfJV4sQItVC2\r\nAvfJagkrIqZ3iiQTUBQGTKBsTnAqE1H7QgUSV9vSd+8rgvHkyZsRjmtyR1e3A6Ji\r\noNCXUbExC\/0iCPUqdHZIVb+Lc\/vWuv4ByFMybGPydgtLoEUX2ZrKFWmcgZFDUSRd\r\n9Uj26vtUhCC4bU4jgu6hIrR9IuxOBLQUxGTRZyAcXvj7obqRAEZwFAKQgFpfpqTb\r\nH+kjcbZSaAlLVSF7vBc1syyI8RGYbqpwvtREqJtl5IEIwe6huEqJ3zPnlP2th\/55\r\ncf3Fovj6JJgbb9XFxrdnsOsDOu\/tpnaRWlvv5ib4+SzG5wWFT5UUEo4Wg2STQiiX\r\nuVSRQxK1LE1yg84bs3NZk9FSQh4B8vZVuRr5FaJsZZkwlFlhRO\/\/+TJtXRbyNgsf\r\noMRZGi8DLGU2SGEAHcRH\/QZHq\/XDUWVzdxrSBYcy7GSpT7UDVzGv1rEJUrn5veP1\r\n0KmauAqtiIaYRm4f6YBsn0INcZxzIPZ0p8qFtVZBPeHhvQtvOt0iXI\/XUxEWOa2F\r\nK2EqhErgMK\/N07U1JJJay5tYZRtvkGq46oP\/5kQG8hYST0MDK6VihJoPpvCmAm4E\r\npEYKQ96x6A4EH9Y9mZlYozH\/eqmxPbTK8n89\/p7Ydun4rI+B2iiLnY8REWWy6+UQ\r\nV204fGUkJqW5CrKy3P3XvY9X\r\n-----END CERTIFICATE-----"
}';
		$this->fileAccessHelper
				->expects($this->at(0))
				->method('file_get_contents')
				->with(
						\OC::$SERVERROOT . '/tests/data/integritycheck/app//appinfo/signature.json'
				)
				->willReturn($signatureDataFile);
		$this->fileAccessHelper
				->expects($this->at(1))
				->method('file_get_contents')
				->with(
						'/resources/codesigning/root.crt'
				)
				->willReturn(file_get_contents(__DIR__ .'/../../data/integritycheck/root.crt'));

		$this->assertSame([], $this->checker->verifyAppSignature('SomeApp'));
	}

	public function testVerifyAppSignatureWithTamperedSignatureData() {
		$this->environmentHelper
				->expects($this->once())
				->method('getChannel')
				->willReturn('stable');
		$this->config
				->expects($this->any())
				->method('getSystemValue')
				->with('integrity.check.disabled', false)
				->willReturn(false);

		$this->appLocator
				->expects($this->once())
				->method('getAppPath')
				->with('SomeApp')
				->willReturn(\OC::$SERVERROOT . '/tests/data/integritycheck/app/');
		$signatureDataFile = '{
    "hashes": {
        "AnotherFile.txt": "tampered",
        "subfolder\/file.txt": "tampered"
    },
    "signature": "EL49UaSeyMAqyMtqId+tgOhhwgOevPZsRLX4j2blnybAB6fN07z0936JqZV7+eMPiE30Idx+UCY6rCFN531Kqe9vAOCdgtHUSOjjKyKc+lvULESlMb6YQcrZrvDlEMMjzjH49ewG7Ai8sNN6HrRUd9U8ws+ewSkW2DOOBItj\/21RBnkrSt+2AtGXGigEvuTm57HrCYDj8\/lSkumC2GVkjLUHeLOKYo4PRNOr6yP5mED5v7zo66AWvXl2fKv54InZcdxsAk35lyK9DGZbk\/027ZRd0AOHT3LImRLvQ+8EAg3XLlRUy0hOFGgPC+jYonMzgYvsAXAXi2j8LnLJlsLwpFwu1k1B+kZVPMumKZvP9OvJb70EirecXmz62V+Jiyuaq7ne4y7Kp5gKZT\/T8SeZ0lFtCmPfYyzBB0y8s5ldmTTmdVYHs54t\/OCCW82HzQZxnFNPzDTRa8HglsaMKrqPtW59+R4UvRKSWhB8M\/Ah57qgzycvPV4KMz\/FbD4l\/\/9chRKSlCfc2k3b8ZSHNmi+EzCKgJjWIoKdgN1yax94puU8jfn8UW+G7H9Y1Jsf\/jox6QLyYEgtV1vOHY2xLT7fVs2vhyvkN2MNjJnmQ70gFG5Qz2lBz5wi6ZpB+tOfCcpbLxWAkoWoIrmC\/Ilqh7mfmRZ43g5upjkepHNd93ONuY8=",
    "certificate": "-----BEGIN CERTIFICATE-----\r\nMIIEwTCCAqmgAwIBAgIUWv0iujufs5lUr0svCf\/qTQvoyKAwDQYJKoZIhvcNAQEF\r\nBQAwIzEhMB8GA1UECgwYb3duQ2xvdWQgQ29kZSBTaWduaW5nIENBMB4XDTE1MTEw\r\nMzIyNDk1M1oXDTE2MTEwMzIyNDk1M1owEjEQMA4GA1UEAwwHU29tZUFwcDCCAiIw\r\nDQYJKoZIhvcNAQEBBQADggIPADCCAgoCggIBAK8q0x62agGSRBqeWsaeEwFfepMk\r\nF8cAobMMi50qHCv9IrOn\/ZH9l52xBrbIkErVmRjmly0d4JhD8Ymhidsh9ONKYl\/j\r\n+ishsZDM8eNNdp3Ew+fEYVvY1W7mR1qU24NWj0bzVsClI7hvPVIuw7AjfBDq1C5+\r\nA+ZSLSXYvOK2cEWjdxQfuNZwEZSjmA63DUllBIrm35IaTvfuyhU6BW9yHZxmb8+M\r\nw0xDv30D5UkE\/2N7Pa\/HQJLxCR+3zKibRK3nUyRDLSXxMkU9PnFNaPNX59VPgyj4\r\nGB1CFSToldJVPF4pzh7p36uGXZVxs8m3LFD4Ol8mhi7jkxDZjqFN46gzR0r23Py6\r\ndol9vfawGIoUwp9LvL0S7MvdRY0oazLXwClLP4OQ17zpSMAiCj7fgNT661JamPGj\r\nt5O7Zn2wA7I4ddDS\/HDTWCu98Zwc9fHIpsJPgCZ9awoqxi4Mnf7Pk9g5nnXhszGC\r\ncxxIASQKM+GhdzoRxKknax2RzUCwCzcPRtCj8AQT\/x\/mqN3PfRmlnFBNACUw9bpZ\r\nSOoNq2pCF9igftDWpSIXQ38pVpKLWowjjg3DVRmVKBgivHnUnVLyzYBahHPj0vaz\r\ntFtUFRaqXDnt+4qyUGyrT5h5pjZaTcHIcSB4PiarYwdVvgslgwnQzOUcGAzRWBD4\r\n6jV2brP5vFY3g6iPAgMBAAEwDQYJKoZIhvcNAQEFBQADggIBACTY3CCHC+Z28gCf\r\nFWGKQ3wAKs+k4+0yoti0qm2EKX7rSGQ0PHSas6uW79WstC4Rj+DYkDtIhGMSg8FS\r\nHVGZHGBCc0HwdX+BOAt3zi4p7Sf3oQef70\/4imPoKxbAVCpd\/cveVcFyDC19j1yB\r\nBapwu87oh+muoeaZxOlqQI4UxjBlR\/uRSMhOn2UGauIr3dWJgAF4pGt7TtIzt+1v\r\n0uA6FtN1Y4R5O8AaJPh1bIG0CVvFBE58esGzjEYLhOydgKFnEP94kVPgJD5ds9C3\r\npPhEpo1dRpiXaF7WGIV1X6DI\/ipWvfrF7CEy6I\/kP1InY\/vMDjQjeDnJ\/VrXIWXO\r\nyZvHXVaN\/m+1RlETsH7YO\/QmxRue9ZHN3gvvWtmpCeA95sfpepOk7UcHxHZYyQbF\r\n49\/au8j+5tsr4A83xzsT1JbcKRxkAaQ7WDJpOnE5O1+H0fB+BaLakTg6XX9d4Fo7\r\n7Gin7hVWX7pL+JIyxMzME3LhfI61+CRcqZQIrpyaafUziPQbWIPfEs7h8tCOWyvW\r\nUO8ZLervYCB3j44ivkrxPlcBklDCqqKKBzDP9dYOtS\/P4RB1NkHA9+NTvmBpTonS\r\nSFXdg9fFMD7VfjDE3Vnk+8DWkVH5wBYowTAD7w9Wuzr7DumiAULexnP\/Y7xwxLv7\r\n4B+pXTAcRK0zECDEaX3npS8xWzrB\r\n-----END CERTIFICATE-----"
}';
		$this->fileAccessHelper
				->expects($this->at(0))
				->method('file_get_contents')
				->with(
						\OC::$SERVERROOT . '/tests/data/integritycheck/app//appinfo/signature.json'
				)
				->willReturn($signatureDataFile);
		$this->fileAccessHelper
				->expects($this->at(1))
				->method('file_get_contents')
				->with(
						'/resources/codesigning/root.crt'
				)
				->willReturn(file_get_contents(__DIR__ .'/../../data/integritycheck/root.crt'));

		$expected = [
			'EXCEPTION' => [
				'class' => 'OC\\IntegrityCheck\\Exceptions\\InvalidSignatureException',
				'message' => 'Signature could not get verified.',
			],
		];
		$this->assertEquals($expected, $this->checker->verifyAppSignature('SomeApp'));
	}

	public function testVerifyAppSignatureWithTamperedFiles() {
		$this->environmentHelper
				->expects($this->once())
				->method('getChannel')
				->willReturn('stable');
		$this->config
				->expects($this->any())
				->method('getSystemValue')
				->with('integrity.check.disabled', false)
				->willReturn(false);

		$this->appLocator
				->expects($this->once())
				->method('getAppPath')
				->with('SomeApp')
				->willReturn(\OC::$SERVERROOT . '/tests/data/integritycheck/appWithInvalidData/');
		$signatureDataFile = '{
    "hashes": {
        "AnotherFile.txt": "1570ca9420e37629de4328f48c51da29840ddeaa03ae733da4bf1d854b8364f594aac560601270f9e1797ed4cd57c1aea87bf44cf4245295c94f2e935a2f0112",
        "subfolder\/file.txt": "410738545fb623c0a5c8a71f561e48ea69e3ada0981a455e920a5ae9bf17c6831ae654df324f9328ff8453de179276ae51931cca0fa71fe8ccde6c083ca0574b"
    },
    "signature": "dYoohBaWIFR\/To1FXEbMQB5apUhVYlEauBGSPo12nq84wxWkBx2EM3KDRgkB5Sub2tr0CgmAc2EVjPhKIEzAam26cyUb48bJziz1V6wvW7z4GZAfaJpzLkyHdSfV5117VSf5w1rDcAeZDXfGUaaNEJPWytaF4ZIxVge7f3NGshHy4odFVPADy\/u6c43BWvaOtJ4m3aJQbP6sxCO9dxwcm5yJJJR3n36jfh229sdWBxyl8BhwhH1e1DEv78\/aiL6ckKFPVNzx01R6yDFt3TgEMR97YZ\/R6lWiXG+dsJ305jNFlusLu518zBUvl7g5yjzGN778H29b2C8VLZKmi\/h1CH9jGdD72fCqCYdenD2uZKzb6dsUtXtvBmVcVT6BUGz41W1pkkEEB+YJpMrHILIxAiHRGv1+aZa9\/Oz8LWFd+BEUQjC2LJgojPnpzaG\/msw1nBkX16NNVDWWtJ25Bc\/r\/mG46rwjWB\/cmV6Lwt6KODiqlxgrC4lm9ALOCEWw+23OcYhLwNfQTYevXqHqsFfXOkhUnM8z5vDUb\/HBraB1DjFXN8iLK+1YewD4P495e+SRzrR79Oi3F8SEqRIzRLfN2rnW1BTms\/wYsz0p67cup1Slk1XlNmHwbWX25NVd2PPlLOvZRGoqcKFpIjC5few8THiZfyjiNFwt3RM0AFdZcXY=",
    "certificate": "-----BEGIN CERTIFICATE-----\r\nMIIEvjCCAqagAwIBAgIUc\/0FxYrsgSs9rDxp03EJmbjN0NwwDQYJKoZIhvcNAQEF\r\nBQAwIzEhMB8GA1UECgwYb3duQ2xvdWQgQ29kZSBTaWduaW5nIENBMB4XDTE1MTEw\r\nMzIxMDMzM1oXDTE2MTEwMzIxMDMzM1owDzENMAsGA1UEAwwEY29yZTCCAiIwDQYJ\r\nKoZIhvcNAQEBBQADggIPADCCAgoCggIBALb6EgHpkAqZbO5vRO8XSh7G7XGWHw5s\r\niOf4RwPXR6SE9bWZEm\/b72SfWk\/\/J6AbrD8WiOzBuT\/ODy6k5T1arEdHO+Pux0W1\r\nMxYJJI4kH74KKgMpC0SB0Rt+8WrMqV1r3hhJ46df6Xr\/xolP3oD+eLbShPcblhdS\r\nVtkZEkoev8Sh6L2wDCeHDyPxzvj1w2dTdGVO9Kztn0xIlyfEBakqvBWtcxyi3Ln0\r\nklnxlMx3tPDUE4kqvpia9qNiB1AN2PV93eNr5\/2riAzIssMFSCarWCx0AKYb54+d\r\nxLpcYFyqPJ0ydBCkF78DD45RCZet6PNYkdzgbqlUWEGGomkuDoJbBg4wzgzO0D77\r\nH87KFhYW8tKFFvF1V3AHl\/sFQ9tDHaxM9Y0pZ2jPp\/ccdiqnmdkBxBDqsiRvHvVB\r\nCn6qpb4vWGFC7vHOBfYspmEL1zLlKXZv3ezMZEZw7O9ZvUP3VO\/wAtd2vUW8UFiq\r\ns2v1QnNLN6jNh51obcwmrBvWhJy9vQIdtIjQbDxqWTHh1zUSrw9wrlklCBZ\/zrM0\r\ni8nfCFwTxWRxp3H9KoECzO\/zS5R5KIS7s3\/wq\/w9T2Ie4rcecgXwDizwnn0C\/aKc\r\nbDIjujpL1s9HO05pcD\/V3wKcPZ1izymBkmMyIbL52iRVN5FTVHeZdXPpFuq+CTQJ\r\nQ238lC+A\/KOVAgMBAAEwDQYJKoZIhvcNAQEFBQADggIBAGoKTnh8RfJV4sQItVC2\r\nAvfJagkrIqZ3iiQTUBQGTKBsTnAqE1H7QgUSV9vSd+8rgvHkyZsRjmtyR1e3A6Ji\r\noNCXUbExC\/0iCPUqdHZIVb+Lc\/vWuv4ByFMybGPydgtLoEUX2ZrKFWmcgZFDUSRd\r\n9Uj26vtUhCC4bU4jgu6hIrR9IuxOBLQUxGTRZyAcXvj7obqRAEZwFAKQgFpfpqTb\r\nH+kjcbZSaAlLVSF7vBc1syyI8RGYbqpwvtREqJtl5IEIwe6huEqJ3zPnlP2th\/55\r\ncf3Fovj6JJgbb9XFxrdnsOsDOu\/tpnaRWlvv5ib4+SzG5wWFT5UUEo4Wg2STQiiX\r\nuVSRQxK1LE1yg84bs3NZk9FSQh4B8vZVuRr5FaJsZZkwlFlhRO\/\/+TJtXRbyNgsf\r\noMRZGi8DLGU2SGEAHcRH\/QZHq\/XDUWVzdxrSBYcy7GSpT7UDVzGv1rEJUrn5veP1\r\n0KmauAqtiIaYRm4f6YBsn0INcZxzIPZ0p8qFtVZBPeHhvQtvOt0iXI\/XUxEWOa2F\r\nK2EqhErgMK\/N07U1JJJay5tYZRtvkGq46oP\/5kQG8hYST0MDK6VihJoPpvCmAm4E\r\npEYKQ96x6A4EH9Y9mZlYozH\/eqmxPbTK8n89\/p7Ydun4rI+B2iiLnY8REWWy6+UQ\r\nV204fGUkJqW5CrKy3P3XvY9X\r\n-----END CERTIFICATE-----"
}';
		$this->fileAccessHelper
				->expects($this->at(0))
				->method('file_get_contents')
				->with(
						\OC::$SERVERROOT . '/tests/data/integritycheck/appWithInvalidData//appinfo/signature.json'
				)
				->willReturn($signatureDataFile);
		$this->fileAccessHelper
				->expects($this->at(1))
				->method('file_get_contents')
				->with(
						'/resources/codesigning/root.crt'
				)
				->willReturn(file_get_contents(__DIR__ .'/../../data/integritycheck/root.crt'));


		$expected = [
			'INVALID_HASH' => [
				'AnotherFile.txt' => [
					'expected' => '1570ca9420e37629de4328f48c51da29840ddeaa03ae733da4bf1d854b8364f594aac560601270f9e1797ed4cd57c1aea87bf44cf4245295c94f2e935a2f0112',
					'current' => '7322348ba269c6d5522efe02f424fa3a0da319a7cd9c33142a5afe32a2d9af2da3a411f086fcfc96ff4301ea566f481dba0960c2abeef3594c4d930462f6584c',
				],
			],
			'FILE_MISSING' => [
				'subfolder/file.txt' => [
					'expected' => '410738545fb623c0a5c8a71f561e48ea69e3ada0981a455e920a5ae9bf17c6831ae654df324f9328ff8453de179276ae51931cca0fa71fe8ccde6c083ca0574b',
					'current' => '',
				],
			],
			'EXTRA_FILE' => [
				'UnecessaryFile' => [
					'expected' => '',
					'current' => 'cf83e1357eefb8bdf1542850d66d8007d620e4050b5715dc83f4a921d36ce9ce47d0d13c5d85f2b0ff8318d2877eec2f63b931bd47417a81a538327af927da3e',
				],
			],

		];
		$this->assertSame($expected, $this->checker->verifyAppSignature('SomeApp'));
	}

	public function testVerifyAppSignatureWithTamperedFilesAndAlternatePath() {
		$this->environmentHelper
				->expects($this->once())
				->method('getChannel')
				->willReturn('stable');
		$this->config
				->expects($this->any())
				->method('getSystemValue')
				->with('integrity.check.disabled', false)
				->willReturn(false);

		$this->appLocator
				->expects($this->never())
				->method('getAppPath')
				->with('SomeApp');
		$signatureDataFile = '{
    "hashes": {
        "AnotherFile.txt": "1570ca9420e37629de4328f48c51da29840ddeaa03ae733da4bf1d854b8364f594aac560601270f9e1797ed4cd57c1aea87bf44cf4245295c94f2e935a2f0112",
        "subfolder\/file.txt": "410738545fb623c0a5c8a71f561e48ea69e3ada0981a455e920a5ae9bf17c6831ae654df324f9328ff8453de179276ae51931cca0fa71fe8ccde6c083ca0574b"
    },
    "signature": "dYoohBaWIFR\/To1FXEbMQB5apUhVYlEauBGSPo12nq84wxWkBx2EM3KDRgkB5Sub2tr0CgmAc2EVjPhKIEzAam26cyUb48bJziz1V6wvW7z4GZAfaJpzLkyHdSfV5117VSf5w1rDcAeZDXfGUaaNEJPWytaF4ZIxVge7f3NGshHy4odFVPADy\/u6c43BWvaOtJ4m3aJQbP6sxCO9dxwcm5yJJJR3n36jfh229sdWBxyl8BhwhH1e1DEv78\/aiL6ckKFPVNzx01R6yDFt3TgEMR97YZ\/R6lWiXG+dsJ305jNFlusLu518zBUvl7g5yjzGN778H29b2C8VLZKmi\/h1CH9jGdD72fCqCYdenD2uZKzb6dsUtXtvBmVcVT6BUGz41W1pkkEEB+YJpMrHILIxAiHRGv1+aZa9\/Oz8LWFd+BEUQjC2LJgojPnpzaG\/msw1nBkX16NNVDWWtJ25Bc\/r\/mG46rwjWB\/cmV6Lwt6KODiqlxgrC4lm9ALOCEWw+23OcYhLwNfQTYevXqHqsFfXOkhUnM8z5vDUb\/HBraB1DjFXN8iLK+1YewD4P495e+SRzrR79Oi3F8SEqRIzRLfN2rnW1BTms\/wYsz0p67cup1Slk1XlNmHwbWX25NVd2PPlLOvZRGoqcKFpIjC5few8THiZfyjiNFwt3RM0AFdZcXY=",
    "certificate": "-----BEGIN CERTIFICATE-----\r\nMIIEvjCCAqagAwIBAgIUc\/0FxYrsgSs9rDxp03EJmbjN0NwwDQYJKoZIhvcNAQEF\r\nBQAwIzEhMB8GA1UECgwYb3duQ2xvdWQgQ29kZSBTaWduaW5nIENBMB4XDTE1MTEw\r\nMzIxMDMzM1oXDTE2MTEwMzIxMDMzM1owDzENMAsGA1UEAwwEY29yZTCCAiIwDQYJ\r\nKoZIhvcNAQEBBQADggIPADCCAgoCggIBALb6EgHpkAqZbO5vRO8XSh7G7XGWHw5s\r\niOf4RwPXR6SE9bWZEm\/b72SfWk\/\/J6AbrD8WiOzBuT\/ODy6k5T1arEdHO+Pux0W1\r\nMxYJJI4kH74KKgMpC0SB0Rt+8WrMqV1r3hhJ46df6Xr\/xolP3oD+eLbShPcblhdS\r\nVtkZEkoev8Sh6L2wDCeHDyPxzvj1w2dTdGVO9Kztn0xIlyfEBakqvBWtcxyi3Ln0\r\nklnxlMx3tPDUE4kqvpia9qNiB1AN2PV93eNr5\/2riAzIssMFSCarWCx0AKYb54+d\r\nxLpcYFyqPJ0ydBCkF78DD45RCZet6PNYkdzgbqlUWEGGomkuDoJbBg4wzgzO0D77\r\nH87KFhYW8tKFFvF1V3AHl\/sFQ9tDHaxM9Y0pZ2jPp\/ccdiqnmdkBxBDqsiRvHvVB\r\nCn6qpb4vWGFC7vHOBfYspmEL1zLlKXZv3ezMZEZw7O9ZvUP3VO\/wAtd2vUW8UFiq\r\ns2v1QnNLN6jNh51obcwmrBvWhJy9vQIdtIjQbDxqWTHh1zUSrw9wrlklCBZ\/zrM0\r\ni8nfCFwTxWRxp3H9KoECzO\/zS5R5KIS7s3\/wq\/w9T2Ie4rcecgXwDizwnn0C\/aKc\r\nbDIjujpL1s9HO05pcD\/V3wKcPZ1izymBkmMyIbL52iRVN5FTVHeZdXPpFuq+CTQJ\r\nQ238lC+A\/KOVAgMBAAEwDQYJKoZIhvcNAQEFBQADggIBAGoKTnh8RfJV4sQItVC2\r\nAvfJagkrIqZ3iiQTUBQGTKBsTnAqE1H7QgUSV9vSd+8rgvHkyZsRjmtyR1e3A6Ji\r\noNCXUbExC\/0iCPUqdHZIVb+Lc\/vWuv4ByFMybGPydgtLoEUX2ZrKFWmcgZFDUSRd\r\n9Uj26vtUhCC4bU4jgu6hIrR9IuxOBLQUxGTRZyAcXvj7obqRAEZwFAKQgFpfpqTb\r\nH+kjcbZSaAlLVSF7vBc1syyI8RGYbqpwvtREqJtl5IEIwe6huEqJ3zPnlP2th\/55\r\ncf3Fovj6JJgbb9XFxrdnsOsDOu\/tpnaRWlvv5ib4+SzG5wWFT5UUEo4Wg2STQiiX\r\nuVSRQxK1LE1yg84bs3NZk9FSQh4B8vZVuRr5FaJsZZkwlFlhRO\/\/+TJtXRbyNgsf\r\noMRZGi8DLGU2SGEAHcRH\/QZHq\/XDUWVzdxrSBYcy7GSpT7UDVzGv1rEJUrn5veP1\r\n0KmauAqtiIaYRm4f6YBsn0INcZxzIPZ0p8qFtVZBPeHhvQtvOt0iXI\/XUxEWOa2F\r\nK2EqhErgMK\/N07U1JJJay5tYZRtvkGq46oP\/5kQG8hYST0MDK6VihJoPpvCmAm4E\r\npEYKQ96x6A4EH9Y9mZlYozH\/eqmxPbTK8n89\/p7Ydun4rI+B2iiLnY8REWWy6+UQ\r\nV204fGUkJqW5CrKy3P3XvY9X\r\n-----END CERTIFICATE-----"
}';
		$this->fileAccessHelper
				->expects($this->at(0))
				->method('file_get_contents')
				->with(
						\OC::$SERVERROOT . '/tests/data/integritycheck/appWithInvalidData//appinfo/signature.json'
				)
				->willReturn($signatureDataFile);
		$this->fileAccessHelper
				->expects($this->at(1))
				->method('file_get_contents')
				->with(
						'/resources/codesigning/root.crt'
				)
				->willReturn(file_get_contents(__DIR__ .'/../../data/integritycheck/root.crt'));


		$expected = [
			'INVALID_HASH' => [
				'AnotherFile.txt' => [
					'expected' => '1570ca9420e37629de4328f48c51da29840ddeaa03ae733da4bf1d854b8364f594aac560601270f9e1797ed4cd57c1aea87bf44cf4245295c94f2e935a2f0112',
					'current' => '7322348ba269c6d5522efe02f424fa3a0da319a7cd9c33142a5afe32a2d9af2da3a411f086fcfc96ff4301ea566f481dba0960c2abeef3594c4d930462f6584c',
				],
			],
			'FILE_MISSING' => [
				'subfolder/file.txt' => [
					'expected' => '410738545fb623c0a5c8a71f561e48ea69e3ada0981a455e920a5ae9bf17c6831ae654df324f9328ff8453de179276ae51931cca0fa71fe8ccde6c083ca0574b',
					'current' => '',
				],
			],
			'EXTRA_FILE' => [
				'UnecessaryFile' => [
					'expected' => '',
					'current' => 'cf83e1357eefb8bdf1542850d66d8007d620e4050b5715dc83f4a921d36ce9ce47d0d13c5d85f2b0ff8318d2877eec2f63b931bd47417a81a538327af927da3e',
				],
			],

		];
		$this->assertSame($expected, $this->checker->verifyAppSignature('SomeApp', \OC::$SERVERROOT . '/tests/data/integritycheck/appWithInvalidData/'));
	}

	public function testVerifyAppWithDifferentScope() {
		$this->environmentHelper
				->expects($this->once())
				->method('getChannel')
				->willReturn('stable');
		$this->config
				->expects($this->any())
				->method('getSystemValue')
				->with('integrity.check.disabled', false)
				->willReturn(false);

		$this->appLocator
				->expects($this->once())
				->method('getAppPath')
				->with('SomeApp')
				->willReturn(\OC::$SERVERROOT . '/tests/data/integritycheck/appWithInvalidData/');
		$signatureDataFile = '{
    "hashes": {
        "AnotherFile.txt": "1570ca9420e37629de4328f48c51da29840ddeaa03ae733da4bf1d854b8364f594aac560601270f9e1797ed4cd57c1aea87bf44cf4245295c94f2e935a2f0112",
        "subfolder\/file.txt": "410738545fb623c0a5c8a71f561e48ea69e3ada0981a455e920a5ae9bf17c6831ae654df324f9328ff8453de179276ae51931cca0fa71fe8ccde6c083ca0574b"
    },
    "signature": "eXesvDm3pkek12xSwMG10y9suRES79Nye3jYNe5KYq1tTUPqRRNgxmMGAfcUro0zpLeAr2YgHeSMWtglblGOW7pmwGVPZ0O1Y4r1fE6jnep0kW+35PLIaqCorIOnCAtSzDNKBhwd1ow3zW2wC0DFouuEkIO8u5Fw28g8E8dp8zEk1xMblNPy+xtWkmYHrVJ\/dQgun1bYOF2ZFtAzatwndTI\/bGsy1i3Wsl+x6HyWKQdq8y8VObtOqKDH7uERBEpB9DHVyKflj1v1gQuEH6BhaRdATc7ee0MiQdGblraIySwYRdfo2d8i82OVKrenMB3SLwyCvDPyQ9iKpTOnSF52ZBqaqSXKM2N\/RAkweeBFQQCwcHhqxvB0cfbyHcbkOLeCZe\/tsh68IxwTiYgzvLfl7sOZ5arnZbzrPpZmB+hfV2omkoJ1tDwOWz9hEmLLNtfo2OxyUH1m0+XFaC+Gbn4WkVDgf7YZkwUcG+Qoa3oKDNMss8MEyZxewl2iDGZcf402dlidHRprlfmXbAYuVQ08\/a0HxIKYPGh\/nsMGmwnO15CWtFpAbhUA\/D5oRjsIxnvXaMDg0iAFpdu\/5Ffsj7g3EPdBkiQHNYK7YU1RRx609eH0bZyiIYHdUPw7ikLupvrebZmELqi3mqDFO99u4eISlxFJlUbUND3L4BtmWTWrKwI=",
    "certificate": "-----BEGIN CERTIFICATE-----\r\nMIIExjCCAq6gAwIBAgIUHSJjhJqMwr+3TkoiQFg4SVVYQ1gwDQYJKoZIhvcNAQEF\r\nBQAwIzEhMB8GA1UECgwYb3duQ2xvdWQgQ29kZSBTaWduaW5nIENBMB4XDTE1MTEw\r\nMzIzMjc1NVoXDTE2MTEwMzIzMjc1NVowFzEVMBMGA1UEAwwMQW5vdGhlclNjb3Bl\r\nMIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEA33npb5RmUkXrDT+TbwMf\r\n0zQ33SlzsjoGxCrbSwJOn6leGGInJ6ZrdzLL0WTi\/dTpg+Y\/JS+72XWm5NSjaTxo\r\n7OHc3cQBwXQj4tN6j\/y5qqY0GDLYufEkx2rpazqt9lBSJ72u1bGl2yoOXzYCz5i0\r\n60KsJXC9K44LKzGsarzbwAgskSVNkjAsPgjnCWZmcl6icpLi5Fz9rs2UMOWbdvdI\r\nAROsn0eC9E\/akmXTy5YMu6bAIGpvjZFHzyA83FQRbvv5o1V5Gsye\/VQLEgh7rqfz\r\nT\/jgWifP+JgoeB6otzuRZ3fFsmbBiyCIRtIOzQQflozhUlWtmiEGwg4GySuMUjEH\r\nA1LF86LO+ZzDQgd2oYNKmrQ8O+EcLqx9BpV4AFhEvqdk7uycJYPHs6yl+yfbzTeJ\r\n2Xd0yVAfd9r\/iDr36clLj2bzEObdl9xzKjcCIXE4Q0G4Pur41\/BJUDK9PI390ccQ\r\nnFjjVYBMsC859OwW64tMP0zkM9Vv72LCaEzaR8jqH0j11catqxunr+StfMcmxLTN\r\nbqBJbSEq4ER3mJxCTI2UrIVmdQ7+wRxgv3QTDNOZyqrz2L8A1Rpb3h0APxtQv+oA\r\n8KIZYID5\/qsS2V2jITkMQ8Nd1W3b0cZhZ600z+znh3jLJ0TYLvwN6\/qBQTUDaM2o\r\ng1+icMqXIXIeKuoPCVVsG7cCAwEAATANBgkqhkiG9w0BAQUFAAOCAgEAHc4F\/kOV\r\nHc8In5MmGg2YtjwZzjdeoC5TIPZczRqz0B+wRbJzN6aYryKZKLmP+wKpgRnJWDzp\r\nrgKGyyEQIAfK63DEv4B9p4N1B+B3aeMKsSpVcw7wbFTD57V5A7pURGoo31d0mw5L\r\nUIXZ2u+TUfGbzucMxLdFhTwjGpz9M6Kkm\/POxmV0tvLija5LdbdKnYR9BFmyu4IX\r\nqyoIAtComATNLl+3URu3SZxhE3NxhzMz+eAeNfh1KuIf2gWIIeDCXalVSJLym+OQ\r\nHFDpqRhJqfTMprrRlmmU7Zntgbj8\/RRZuXnBvH9cQ2KykLOb4UoCPlGUqOqKyP9m\r\nDJSFRiMJfpgMQUaJk1TLhKF+IR6FnmwURLEtkONJumtDQju9KaWPlhueONdyGi0p\r\nqxLVUo1Vb52XnPhk2GEEduxpDc9V5ePJ+pdcEdMifY\/uPNBRuBj2c87yq1DLH+U4\r\n3XzP1MlwjnBWZYuoFo0j6Jq0r\/MG6HjGdmkGIsRoheRi8Z8Scz5AW5QRkNz8pKop\r\nTELFqQy9g6TyQzzC8t6HZcpNe842ZUk4raEAbCZe\/XqxWMw5svPgNceBqM3fh7sZ\r\nBSykOHLaL8kiRO\/IS3y1yZEAuiWBvtxcTNLzBb+hdRpm2y8\/qH\/pKo+CMj1VzjNT\r\nD8YRQg0cjmDytJzHDrtV\/aTc9W1aPHun0vw=\r\n-----END CERTIFICATE-----"
}';
		$this->fileAccessHelper
				->expects($this->at(0))
				->method('file_get_contents')
				->with(\OC::$SERVERROOT . '/tests/data/integritycheck/appWithInvalidData//appinfo/signature.json')
				->willReturn($signatureDataFile);
		$this->fileAccessHelper
				->expects($this->at(1))
				->method('file_get_contents')
				->with(
						'/resources/codesigning/root.crt'
				)
				->willReturn(file_get_contents(__DIR__ .'/../../data/integritycheck/root.crt'));

		$expected = [
			'EXCEPTION' => [
				'class' => 'OC\\IntegrityCheck\\Exceptions\\InvalidSignatureException',
				'message' => 'Certificate is not valid for required scope. (Requested: SomeApp, current: CN=AnotherScope)',
			],
		];
		$this->assertSame($expected, $this->checker->verifyAppSignature('SomeApp'));
	}

	public function testVerifyAppWithDifferentScopeAndAlwaysTrustedCore() {
		$this->environmentHelper
				->expects($this->once())
				->method('getChannel')
				->willReturn('stable');
		$this->config
				->expects($this->any())
				->method('getSystemValue')
				->with('integrity.check.disabled', false)
				->willReturn(false);

		$this->appLocator
				->expects($this->once())
				->method('getAppPath')
				->with('SomeApp')
				->willReturn(\OC::$SERVERROOT . '/tests/data/integritycheck/app/');
		$signatureDataFile = '{
    "hashes": {
        "AnotherFile.txt": "1570ca9420e37629de4328f48c51da29840ddeaa03ae733da4bf1d854b8364f594aac560601270f9e1797ed4cd57c1aea87bf44cf4245295c94f2e935a2f0112",
        "subfolder\/file.txt": "410738545fb623c0a5c8a71f561e48ea69e3ada0981a455e920a5ae9bf17c6831ae654df324f9328ff8453de179276ae51931cca0fa71fe8ccde6c083ca0574b"
    },
    "signature": "dYoohBaWIFR\/To1FXEbMQB5apUhVYlEauBGSPo12nq84wxWkBx2EM3KDRgkB5Sub2tr0CgmAc2EVjPhKIEzAam26cyUb48bJziz1V6wvW7z4GZAfaJpzLkyHdSfV5117VSf5w1rDcAeZDXfGUaaNEJPWytaF4ZIxVge7f3NGshHy4odFVPADy\/u6c43BWvaOtJ4m3aJQbP6sxCO9dxwcm5yJJJR3n36jfh229sdWBxyl8BhwhH1e1DEv78\/aiL6ckKFPVNzx01R6yDFt3TgEMR97YZ\/R6lWiXG+dsJ305jNFlusLu518zBUvl7g5yjzGN778H29b2C8VLZKmi\/h1CH9jGdD72fCqCYdenD2uZKzb6dsUtXtvBmVcVT6BUGz41W1pkkEEB+YJpMrHILIxAiHRGv1+aZa9\/Oz8LWFd+BEUQjC2LJgojPnpzaG\/msw1nBkX16NNVDWWtJ25Bc\/r\/mG46rwjWB\/cmV6Lwt6KODiqlxgrC4lm9ALOCEWw+23OcYhLwNfQTYevXqHqsFfXOkhUnM8z5vDUb\/HBraB1DjFXN8iLK+1YewD4P495e+SRzrR79Oi3F8SEqRIzRLfN2rnW1BTms\/wYsz0p67cup1Slk1XlNmHwbWX25NVd2PPlLOvZRGoqcKFpIjC5few8THiZfyjiNFwt3RM0AFdZcXY=",
    "certificate": "-----BEGIN CERTIFICATE-----\r\nMIIEvjCCAqagAwIBAgIUc\/0FxYrsgSs9rDxp03EJmbjN0NwwDQYJKoZIhvcNAQEF\r\nBQAwIzEhMB8GA1UECgwYb3duQ2xvdWQgQ29kZSBTaWduaW5nIENBMB4XDTE1MTEw\r\nMzIxMDMzM1oXDTE2MTEwMzIxMDMzM1owDzENMAsGA1UEAwwEY29yZTCCAiIwDQYJ\r\nKoZIhvcNAQEBBQADggIPADCCAgoCggIBALb6EgHpkAqZbO5vRO8XSh7G7XGWHw5s\r\niOf4RwPXR6SE9bWZEm\/b72SfWk\/\/J6AbrD8WiOzBuT\/ODy6k5T1arEdHO+Pux0W1\r\nMxYJJI4kH74KKgMpC0SB0Rt+8WrMqV1r3hhJ46df6Xr\/xolP3oD+eLbShPcblhdS\r\nVtkZEkoev8Sh6L2wDCeHDyPxzvj1w2dTdGVO9Kztn0xIlyfEBakqvBWtcxyi3Ln0\r\nklnxlMx3tPDUE4kqvpia9qNiB1AN2PV93eNr5\/2riAzIssMFSCarWCx0AKYb54+d\r\nxLpcYFyqPJ0ydBCkF78DD45RCZet6PNYkdzgbqlUWEGGomkuDoJbBg4wzgzO0D77\r\nH87KFhYW8tKFFvF1V3AHl\/sFQ9tDHaxM9Y0pZ2jPp\/ccdiqnmdkBxBDqsiRvHvVB\r\nCn6qpb4vWGFC7vHOBfYspmEL1zLlKXZv3ezMZEZw7O9ZvUP3VO\/wAtd2vUW8UFiq\r\ns2v1QnNLN6jNh51obcwmrBvWhJy9vQIdtIjQbDxqWTHh1zUSrw9wrlklCBZ\/zrM0\r\ni8nfCFwTxWRxp3H9KoECzO\/zS5R5KIS7s3\/wq\/w9T2Ie4rcecgXwDizwnn0C\/aKc\r\nbDIjujpL1s9HO05pcD\/V3wKcPZ1izymBkmMyIbL52iRVN5FTVHeZdXPpFuq+CTQJ\r\nQ238lC+A\/KOVAgMBAAEwDQYJKoZIhvcNAQEFBQADggIBAGoKTnh8RfJV4sQItVC2\r\nAvfJagkrIqZ3iiQTUBQGTKBsTnAqE1H7QgUSV9vSd+8rgvHkyZsRjmtyR1e3A6Ji\r\noNCXUbExC\/0iCPUqdHZIVb+Lc\/vWuv4ByFMybGPydgtLoEUX2ZrKFWmcgZFDUSRd\r\n9Uj26vtUhCC4bU4jgu6hIrR9IuxOBLQUxGTRZyAcXvj7obqRAEZwFAKQgFpfpqTb\r\nH+kjcbZSaAlLVSF7vBc1syyI8RGYbqpwvtREqJtl5IEIwe6huEqJ3zPnlP2th\/55\r\ncf3Fovj6JJgbb9XFxrdnsOsDOu\/tpnaRWlvv5ib4+SzG5wWFT5UUEo4Wg2STQiiX\r\nuVSRQxK1LE1yg84bs3NZk9FSQh4B8vZVuRr5FaJsZZkwlFlhRO\/\/+TJtXRbyNgsf\r\noMRZGi8DLGU2SGEAHcRH\/QZHq\/XDUWVzdxrSBYcy7GSpT7UDVzGv1rEJUrn5veP1\r\n0KmauAqtiIaYRm4f6YBsn0INcZxzIPZ0p8qFtVZBPeHhvQtvOt0iXI\/XUxEWOa2F\r\nK2EqhErgMK\/N07U1JJJay5tYZRtvkGq46oP\/5kQG8hYST0MDK6VihJoPpvCmAm4E\r\npEYKQ96x6A4EH9Y9mZlYozH\/eqmxPbTK8n89\/p7Ydun4rI+B2iiLnY8REWWy6+UQ\r\nV204fGUkJqW5CrKy3P3XvY9X\r\n-----END CERTIFICATE-----"
}';
		$this->fileAccessHelper
				->expects($this->at(0))
				->method('file_get_contents')
				->with(\OC::$SERVERROOT . '/tests/data/integritycheck/app//appinfo/signature.json')
				->willReturn($signatureDataFile);
		$this->fileAccessHelper
				->expects($this->at(1))
				->method('file_get_contents')
				->with(
					'/resources/codesigning/root.crt'
				)
				->willReturn(file_get_contents(__DIR__ .'/../../data/integritycheck/root.crt'));

		$this->assertSame([], $this->checker->verifyAppSignature('SomeApp'));
	}


	public function testWriteCoreSignatureWithException() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Exception message');

		$this->fileAccessHelper
			->expects($this->at(0))
			->method('assertDirectoryExists')
			->will($this->throwException(new \Exception('Exception message')));
		$this->fileAccessHelper
			->expects($this->at(1))
			->method('is_writable')
			->with(__DIR__ . '/core')
			->willReturn(true);

		$keyBundle = file_get_contents(__DIR__ .'/../../data/integritycheck/SomeApp.crt');
		$rsaPrivateKey = file_get_contents(__DIR__ .'/../../data/integritycheck/SomeApp.key');
		$rsa = new RSA();
		$rsa->loadKey($rsaPrivateKey);
		$x509 = new X509();
		$x509->loadX509($keyBundle);
		$this->checker->writeCoreSignature($x509, $rsa, __DIR__);
	}


	public function testWriteCoreSignatureWrongPermissions() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessageMatches('/[a-zA-Z\\/_-]+ is not writable/');

		$this->fileAccessHelper
			->expects($this->at(0))
			->method('assertDirectoryExists')
			->will($this->throwException(new \Exception('Exception message')));
		$this->fileAccessHelper
			->expects($this->at(1))
			->method('is_writable')
			->with(__DIR__ . '/core')
			->willReturn(false);

		$keyBundle = file_get_contents(__DIR__ .'/../../data/integritycheck/SomeApp.crt');
		$rsaPrivateKey = file_get_contents(__DIR__ .'/../../data/integritycheck/SomeApp.key');
		$rsa = new RSA();
		$rsa->loadKey($rsaPrivateKey);
		$x509 = new X509();
		$x509->loadX509($keyBundle);
		$this->checker->writeCoreSignature($x509, $rsa, __DIR__);
	}

	public function testWriteCoreSignature() {
		$expectedSignatureFileData = '{
    "hashes": {
        "AnotherFile.txt": "1570ca9420e37629de4328f48c51da29840ddeaa03ae733da4bf1d854b8364f594aac560601270f9e1797ed4cd57c1aea87bf44cf4245295c94f2e935a2f0112",
        "subfolder\/file.txt": "410738545fb623c0a5c8a71f561e48ea69e3ada0981a455e920a5ae9bf17c6831ae654df324f9328ff8453de179276ae51931cca0fa71fe8ccde6c083ca0574b"
    },
    "signature": "dYoohBaWIFR\/To1FXEbMQB5apUhVYlEauBGSPo12nq84wxWkBx2EM3KDRgkB5Sub2tr0CgmAc2EVjPhKIEzAam26cyUb48bJziz1V6wvW7z4GZAfaJpzLkyHdSfV5117VSf5w1rDcAeZDXfGUaaNEJPWytaF4ZIxVge7f3NGshHy4odFVPADy\/u6c43BWvaOtJ4m3aJQbP6sxCO9dxwcm5yJJJR3n36jfh229sdWBxyl8BhwhH1e1DEv78\/aiL6ckKFPVNzx01R6yDFt3TgEMR97YZ\/R6lWiXG+dsJ305jNFlusLu518zBUvl7g5yjzGN778H29b2C8VLZKmi\/h1CH9jGdD72fCqCYdenD2uZKzb6dsUtXtvBmVcVT6BUGz41W1pkkEEB+YJpMrHILIxAiHRGv1+aZa9\/Oz8LWFd+BEUQjC2LJgojPnpzaG\/msw1nBkX16NNVDWWtJ25Bc\/r\/mG46rwjWB\/cmV6Lwt6KODiqlxgrC4lm9ALOCEWw+23OcYhLwNfQTYevXqHqsFfXOkhUnM8z5vDUb\/HBraB1DjFXN8iLK+1YewD4P495e+SRzrR79Oi3F8SEqRIzRLfN2rnW1BTms\/wYsz0p67cup1Slk1XlNmHwbWX25NVd2PPlLOvZRGoqcKFpIjC5few8THiZfyjiNFwt3RM0AFdZcXY=",
    "certificate": "-----BEGIN CERTIFICATE-----\r\nMIIEvjCCAqagAwIBAgIUc\/0FxYrsgSs9rDxp03EJmbjN0NwwDQYJKoZIhvcNAQEF\r\nBQAwIzEhMB8GA1UECgwYb3duQ2xvdWQgQ29kZSBTaWduaW5nIENBMB4XDTE1MTEw\r\nMzIxMDMzM1oXDTE2MTEwMzIxMDMzM1owDzENMAsGA1UEAwwEY29yZTCCAiIwDQYJ\r\nKoZIhvcNAQEBBQADggIPADCCAgoCggIBALb6EgHpkAqZbO5vRO8XSh7G7XGWHw5s\r\niOf4RwPXR6SE9bWZEm\/b72SfWk\/\/J6AbrD8WiOzBuT\/ODy6k5T1arEdHO+Pux0W1\r\nMxYJJI4kH74KKgMpC0SB0Rt+8WrMqV1r3hhJ46df6Xr\/xolP3oD+eLbShPcblhdS\r\nVtkZEkoev8Sh6L2wDCeHDyPxzvj1w2dTdGVO9Kztn0xIlyfEBakqvBWtcxyi3Ln0\r\nklnxlMx3tPDUE4kqvpia9qNiB1AN2PV93eNr5\/2riAzIssMFSCarWCx0AKYb54+d\r\nxLpcYFyqPJ0ydBCkF78DD45RCZet6PNYkdzgbqlUWEGGomkuDoJbBg4wzgzO0D77\r\nH87KFhYW8tKFFvF1V3AHl\/sFQ9tDHaxM9Y0pZ2jPp\/ccdiqnmdkBxBDqsiRvHvVB\r\nCn6qpb4vWGFC7vHOBfYspmEL1zLlKXZv3ezMZEZw7O9ZvUP3VO\/wAtd2vUW8UFiq\r\ns2v1QnNLN6jNh51obcwmrBvWhJy9vQIdtIjQbDxqWTHh1zUSrw9wrlklCBZ\/zrM0\r\ni8nfCFwTxWRxp3H9KoECzO\/zS5R5KIS7s3\/wq\/w9T2Ie4rcecgXwDizwnn0C\/aKc\r\nbDIjujpL1s9HO05pcD\/V3wKcPZ1izymBkmMyIbL52iRVN5FTVHeZdXPpFuq+CTQJ\r\nQ238lC+A\/KOVAgMBAAEwDQYJKoZIhvcNAQEFBQADggIBAGoKTnh8RfJV4sQItVC2\r\nAvfJagkrIqZ3iiQTUBQGTKBsTnAqE1H7QgUSV9vSd+8rgvHkyZsRjmtyR1e3A6Ji\r\noNCXUbExC\/0iCPUqdHZIVb+Lc\/vWuv4ByFMybGPydgtLoEUX2ZrKFWmcgZFDUSRd\r\n9Uj26vtUhCC4bU4jgu6hIrR9IuxOBLQUxGTRZyAcXvj7obqRAEZwFAKQgFpfpqTb\r\nH+kjcbZSaAlLVSF7vBc1syyI8RGYbqpwvtREqJtl5IEIwe6huEqJ3zPnlP2th\/55\r\ncf3Fovj6JJgbb9XFxrdnsOsDOu\/tpnaRWlvv5ib4+SzG5wWFT5UUEo4Wg2STQiiX\r\nuVSRQxK1LE1yg84bs3NZk9FSQh4B8vZVuRr5FaJsZZkwlFlhRO\/\/+TJtXRbyNgsf\r\noMRZGi8DLGU2SGEAHcRH\/QZHq\/XDUWVzdxrSBYcy7GSpT7UDVzGv1rEJUrn5veP1\r\n0KmauAqtiIaYRm4f6YBsn0INcZxzIPZ0p8qFtVZBPeHhvQtvOt0iXI\/XUxEWOa2F\r\nK2EqhErgMK\/N07U1JJJay5tYZRtvkGq46oP\/5kQG8hYST0MDK6VihJoPpvCmAm4E\r\npEYKQ96x6A4EH9Y9mZlYozH\/eqmxPbTK8n89\/p7Ydun4rI+B2iiLnY8REWWy6+UQ\r\nV204fGUkJqW5CrKy3P3XvY9X\r\n-----END CERTIFICATE-----"
}';
		$this->environmentHelper
				->expects($this->any())
				->method('getServerRoot')
				->willReturn(\OC::$SERVERROOT . '/tests/data/integritycheck/app/');
		$this->fileAccessHelper
				->expects($this->once())
				->method('file_put_contents')
				->with(
						\OC::$SERVERROOT . '/tests/data/integritycheck/app//core/signature.json',
						$this->callback(function ($signature) use ($expectedSignatureFileData) {
							$expectedArray = json_decode($expectedSignatureFileData, true);
							$actualArray = json_decode($signature, true);
							$this->assertEquals($expectedArray, $actualArray);
							return true;
						})
				);

		$keyBundle = file_get_contents(__DIR__ .'/../../data/integritycheck/core.crt');
		$rsaPrivateKey = file_get_contents(__DIR__ .'/../../data/integritycheck/core.key');
		$rsa = new RSA();
		$rsa->loadKey($rsaPrivateKey);
		$x509 = new X509();
		$x509->loadX509($keyBundle);
		$this->checker->writeCoreSignature($x509, $rsa, \OC::$SERVERROOT . '/tests/data/integritycheck/app/');
	}

	public function testWriteCoreSignatureWithUnmodifiedHtaccess() {
		$expectedSignatureFileData = '{
    "hashes": {
        ".htaccess": "dc479770a6232061e04a768ee1f9133fdb3aea7b3a99f7105b0e0b6197474733e8d14b5b2bbad054e6b62a410fe5d0b3d790242dee1e0f11274af2100f5289e2",
        "subfolder\/.htaccess": "2c57b1e25050e11dc3ae975832f378c452159f7b69f818e47eeeafadd6ba568517461dcb4d843b90b906cd7c89d161bc1b89dff8e3ae0eb6f5088508c47befd1"
    },
    "signature": "nRtR377DB\/I\/4hmh9q3elMQYfSHnQFlNtjchNgrdfmUQqVmgkU\/4qgGyxDqYkV8mSMbH2gYysfP42nx\/3zSo7n0dBYDfU87Q6f96Cv597vEV27do8CaBkEk8Xjn2SxhHw8hVxracvE2OBAPxk0H3sRp\/cQBgjoXpju4kQin0N5E+DEJMh7Sp+u8aKoFpb+2FaAZJFn\/hnqxLTlVi2nyDxGL3U0eobWY+jWH9XPt52v3Hyh8TDhcAnQ1cN30B8Jn2+jkrm8ib+buchaCXHk0cPX72xuPECdwOEKLCBNrJa3FGSvO1zWiecnCgxCXgt+R8hUgsVPTsbrdFY2YRJGIhHndYZL98XzgG7cw85SnnMMe2SulzeL7xANGF8qiEVyiC7x83bbj5xOkeM\/CUTajrLBO3vyZ23KKOxvskjgI0t+Zw1zFsl+sYW0\/O\/V5WzPOwMwV8+iApQ8k9gEMiYQg98QLEMYnSohncmp0Z9qx2qFcQuHLcKJVa1J6wGtE\/EHR\/4d0aYPd6IRjg+qshCJmdzud\/12xjpGTl+BT0Hi0VsU5o7ZMi7WhmukZmmv8u0uZsvKREQNATm4cO4WCkYySt5O9gZEJOF+jjgeynDoAh09lyrNXIgMpM9ufm\/XEG\/I\/f2zIwbAUc6J6qks5OuYlJzW5vscTiOKhwcGZU9WBLgh0=",
    "certificate": "-----BEGIN CERTIFICATE-----\r\nMIIEvjCCAqagAwIBAgIUc\/0FxYrsgSs9rDxp03EJmbjN0NwwDQYJKoZIhvcNAQEF\r\nBQAwIzEhMB8GA1UECgwYb3duQ2xvdWQgQ29kZSBTaWduaW5nIENBMB4XDTE1MTEw\r\nMzIxMDMzM1oXDTE2MTEwMzIxMDMzM1owDzENMAsGA1UEAwwEY29yZTCCAiIwDQYJ\r\nKoZIhvcNAQEBBQADggIPADCCAgoCggIBALb6EgHpkAqZbO5vRO8XSh7G7XGWHw5s\r\niOf4RwPXR6SE9bWZEm\/b72SfWk\/\/J6AbrD8WiOzBuT\/ODy6k5T1arEdHO+Pux0W1\r\nMxYJJI4kH74KKgMpC0SB0Rt+8WrMqV1r3hhJ46df6Xr\/xolP3oD+eLbShPcblhdS\r\nVtkZEkoev8Sh6L2wDCeHDyPxzvj1w2dTdGVO9Kztn0xIlyfEBakqvBWtcxyi3Ln0\r\nklnxlMx3tPDUE4kqvpia9qNiB1AN2PV93eNr5\/2riAzIssMFSCarWCx0AKYb54+d\r\nxLpcYFyqPJ0ydBCkF78DD45RCZet6PNYkdzgbqlUWEGGomkuDoJbBg4wzgzO0D77\r\nH87KFhYW8tKFFvF1V3AHl\/sFQ9tDHaxM9Y0pZ2jPp\/ccdiqnmdkBxBDqsiRvHvVB\r\nCn6qpb4vWGFC7vHOBfYspmEL1zLlKXZv3ezMZEZw7O9ZvUP3VO\/wAtd2vUW8UFiq\r\ns2v1QnNLN6jNh51obcwmrBvWhJy9vQIdtIjQbDxqWTHh1zUSrw9wrlklCBZ\/zrM0\r\ni8nfCFwTxWRxp3H9KoECzO\/zS5R5KIS7s3\/wq\/w9T2Ie4rcecgXwDizwnn0C\/aKc\r\nbDIjujpL1s9HO05pcD\/V3wKcPZ1izymBkmMyIbL52iRVN5FTVHeZdXPpFuq+CTQJ\r\nQ238lC+A\/KOVAgMBAAEwDQYJKoZIhvcNAQEFBQADggIBAGoKTnh8RfJV4sQItVC2\r\nAvfJagkrIqZ3iiQTUBQGTKBsTnAqE1H7QgUSV9vSd+8rgvHkyZsRjmtyR1e3A6Ji\r\noNCXUbExC\/0iCPUqdHZIVb+Lc\/vWuv4ByFMybGPydgtLoEUX2ZrKFWmcgZFDUSRd\r\n9Uj26vtUhCC4bU4jgu6hIrR9IuxOBLQUxGTRZyAcXvj7obqRAEZwFAKQgFpfpqTb\r\nH+kjcbZSaAlLVSF7vBc1syyI8RGYbqpwvtREqJtl5IEIwe6huEqJ3zPnlP2th\/55\r\ncf3Fovj6JJgbb9XFxrdnsOsDOu\/tpnaRWlvv5ib4+SzG5wWFT5UUEo4Wg2STQiiX\r\nuVSRQxK1LE1yg84bs3NZk9FSQh4B8vZVuRr5FaJsZZkwlFlhRO\/\/+TJtXRbyNgsf\r\noMRZGi8DLGU2SGEAHcRH\/QZHq\/XDUWVzdxrSBYcy7GSpT7UDVzGv1rEJUrn5veP1\r\n0KmauAqtiIaYRm4f6YBsn0INcZxzIPZ0p8qFtVZBPeHhvQtvOt0iXI\/XUxEWOa2F\r\nK2EqhErgMK\/N07U1JJJay5tYZRtvkGq46oP\/5kQG8hYST0MDK6VihJoPpvCmAm4E\r\npEYKQ96x6A4EH9Y9mZlYozH\/eqmxPbTK8n89\/p7Ydun4rI+B2iiLnY8REWWy6+UQ\r\nV204fGUkJqW5CrKy3P3XvY9X\r\n-----END CERTIFICATE-----"
}';
		$this->environmentHelper
				->expects($this->any())
				->method('getServerRoot')
				->willReturn(\OC::$SERVERROOT . '/tests/data/integritycheck/htaccessUnmodified/');
		$this->fileAccessHelper
				->expects($this->once())
				->method('file_put_contents')
				->with(
						\OC::$SERVERROOT . '/tests/data/integritycheck/htaccessUnmodified//core/signature.json',
					$this->callback(function ($signature) use ($expectedSignatureFileData) {
						$expectedArray = json_decode($expectedSignatureFileData, true);
						$actualArray = json_decode($signature, true);
						$this->assertEquals($expectedArray, $actualArray);
						return true;
					})
				);

		$keyBundle = file_get_contents(__DIR__ .'/../../data/integritycheck/core.crt');
		$rsaPrivateKey = file_get_contents(__DIR__ .'/../../data/integritycheck/core.key');
		$rsa = new RSA();
		$rsa->loadKey($rsaPrivateKey);
		$x509 = new X509();
		$x509->loadX509($keyBundle);
		$this->checker->writeCoreSignature($x509, $rsa, \OC::$SERVERROOT . '/tests/data/integritycheck/htaccessUnmodified/');
	}

	public function testWriteCoreSignatureWithInvalidModifiedHtaccess() {
		$expectedSignatureFileData = '{
    "hashes": {
        ".htaccess": "4a54273dc8d697b2ca615acf2ae2c1ee3c1c643492cb04f42b10984fa9aacff1420dc829fd82f93ad3476fbd0cdab0251142c887dc8f872d03e39a3a3eb6d381"
    },
    "signature": "qpDddYGgAKNR3TszOgjPXRphUl2P9Ym5OQaetltocgZASGDkOun5D64+1D0QJRKb4SG2+48muxGOHyL2Ngos4NUrrSR+SIkywZacay82YQBCEdr7\/4MjW1WHRPjvboLwEJwViw0EdAjsWRpD68aPnzUGrGsy2BsCo06P5iwjk9cXcHxdjC9R39npvoC3QNvQ2jmNIbh1Lc4U97dbb+CsXEQCLU1OSa9p3q6cEFV98Easwt7uF\/DzHK+CbeZlxVZ0DwLh2\/ylT1PyGou8QC1b3vKAnPjLWMO+UsCPpCKhk3C5pV+5etQ8puGd+0x2t5tEU+qXxLzek91zWNC+rqgC\/WlqLKbwPb\/BCHs4zLGV55Q2fEQmT21x0KCUELdPs4dBnYP4Ox5tEDugtJujWFzOHzoY6gGa\/BY\/78pSZXmq9o8dWkBEtioWWvaNZ1rM0ddE83GBlBTgjigi9Ay1D++bUW\/FCBB7CMk6qyNlV81H+cBuIEODw2aymmkM9LLDD2Qbmvo8gHEPRjiQxPC5OpDlcdSNiL+zcxVxeuX4FpT+9xzz\/\/DRONhufxRpsbuCOMxd96RW7y9U2N2Uxb3Bzn\/BIqEayUUsdgZjfaGcXXYKR+chu\/LOwNYN6RlnLsgqL\/dhGKwlRVKXw1RA2\/af\/CpqyR7uVP6al1YJo\/YJ+5XJ6zE=",
    "certificate": "-----BEGIN CERTIFICATE-----\r\nMIIEvjCCAqagAwIBAgIUc\/0FxYrsgSs9rDxp03EJmbjN0NwwDQYJKoZIhvcNAQEF\r\nBQAwIzEhMB8GA1UECgwYb3duQ2xvdWQgQ29kZSBTaWduaW5nIENBMB4XDTE1MTEw\r\nMzIxMDMzM1oXDTE2MTEwMzIxMDMzM1owDzENMAsGA1UEAwwEY29yZTCCAiIwDQYJ\r\nKoZIhvcNAQEBBQADggIPADCCAgoCggIBALb6EgHpkAqZbO5vRO8XSh7G7XGWHw5s\r\niOf4RwPXR6SE9bWZEm\/b72SfWk\/\/J6AbrD8WiOzBuT\/ODy6k5T1arEdHO+Pux0W1\r\nMxYJJI4kH74KKgMpC0SB0Rt+8WrMqV1r3hhJ46df6Xr\/xolP3oD+eLbShPcblhdS\r\nVtkZEkoev8Sh6L2wDCeHDyPxzvj1w2dTdGVO9Kztn0xIlyfEBakqvBWtcxyi3Ln0\r\nklnxlMx3tPDUE4kqvpia9qNiB1AN2PV93eNr5\/2riAzIssMFSCarWCx0AKYb54+d\r\nxLpcYFyqPJ0ydBCkF78DD45RCZet6PNYkdzgbqlUWEGGomkuDoJbBg4wzgzO0D77\r\nH87KFhYW8tKFFvF1V3AHl\/sFQ9tDHaxM9Y0pZ2jPp\/ccdiqnmdkBxBDqsiRvHvVB\r\nCn6qpb4vWGFC7vHOBfYspmEL1zLlKXZv3ezMZEZw7O9ZvUP3VO\/wAtd2vUW8UFiq\r\ns2v1QnNLN6jNh51obcwmrBvWhJy9vQIdtIjQbDxqWTHh1zUSrw9wrlklCBZ\/zrM0\r\ni8nfCFwTxWRxp3H9KoECzO\/zS5R5KIS7s3\/wq\/w9T2Ie4rcecgXwDizwnn0C\/aKc\r\nbDIjujpL1s9HO05pcD\/V3wKcPZ1izymBkmMyIbL52iRVN5FTVHeZdXPpFuq+CTQJ\r\nQ238lC+A\/KOVAgMBAAEwDQYJKoZIhvcNAQEFBQADggIBAGoKTnh8RfJV4sQItVC2\r\nAvfJagkrIqZ3iiQTUBQGTKBsTnAqE1H7QgUSV9vSd+8rgvHkyZsRjmtyR1e3A6Ji\r\noNCXUbExC\/0iCPUqdHZIVb+Lc\/vWuv4ByFMybGPydgtLoEUX2ZrKFWmcgZFDUSRd\r\n9Uj26vtUhCC4bU4jgu6hIrR9IuxOBLQUxGTRZyAcXvj7obqRAEZwFAKQgFpfpqTb\r\nH+kjcbZSaAlLVSF7vBc1syyI8RGYbqpwvtREqJtl5IEIwe6huEqJ3zPnlP2th\/55\r\ncf3Fovj6JJgbb9XFxrdnsOsDOu\/tpnaRWlvv5ib4+SzG5wWFT5UUEo4Wg2STQiiX\r\nuVSRQxK1LE1yg84bs3NZk9FSQh4B8vZVuRr5FaJsZZkwlFlhRO\/\/+TJtXRbyNgsf\r\noMRZGi8DLGU2SGEAHcRH\/QZHq\/XDUWVzdxrSBYcy7GSpT7UDVzGv1rEJUrn5veP1\r\n0KmauAqtiIaYRm4f6YBsn0INcZxzIPZ0p8qFtVZBPeHhvQtvOt0iXI\/XUxEWOa2F\r\nK2EqhErgMK\/N07U1JJJay5tYZRtvkGq46oP\/5kQG8hYST0MDK6VihJoPpvCmAm4E\r\npEYKQ96x6A4EH9Y9mZlYozH\/eqmxPbTK8n89\/p7Ydun4rI+B2iiLnY8REWWy6+UQ\r\nV204fGUkJqW5CrKy3P3XvY9X\r\n-----END CERTIFICATE-----"
}';
		$this->fileAccessHelper
				->expects($this->once())
				->method('file_put_contents')
				->with(
						\OC::$SERVERROOT . '/tests/data/integritycheck/htaccessWithInvalidModifiedContent//core/signature.json',
					$this->callback(function ($signature) use ($expectedSignatureFileData) {
						$expectedArray = json_decode($expectedSignatureFileData, true);
						$actualArray = json_decode($signature, true);
						$this->assertEquals($expectedArray, $actualArray);
						return true;
					})
				);

		$keyBundle = file_get_contents(__DIR__ .'/../../data/integritycheck/core.crt');
		$rsaPrivateKey = file_get_contents(__DIR__ .'/../../data/integritycheck/core.key');
		$rsa = new RSA();
		$rsa->loadKey($rsaPrivateKey);
		$x509 = new X509();
		$x509->loadX509($keyBundle);
		$this->checker->writeCoreSignature($x509, $rsa, \OC::$SERVERROOT . '/tests/data/integritycheck/htaccessWithInvalidModifiedContent/');
	}

	public function testWriteCoreSignatureWithValidModifiedHtaccess() {
		$expectedSignatureFileData = '{
    "hashes": {
        ".htaccess": "7e6a7a4d8ee4f3fbc45dd579407c643471575a9d127d1c75f6d0a49e80766c3c587104b2139ef76d2a4bffce3f45777900605aaa49519c9532909b71e5030227",
        "subfolder\/.htaccess": "2c57b1e25050e11dc3ae975832f378c452159f7b69f818e47eeeafadd6ba568517461dcb4d843b90b906cd7c89d161bc1b89dff8e3ae0eb6f5088508c47befd1"
    },
    "signature": "YVwQvl9Dh8UebCumfgzFxfz3NiZJLmYG8oJVTfEBhulI4KXBnTG1jZTprf4XxG2XIriEYAZXsoXpu9xWsUFe9QfdncwoEpqJtGq7l6aVDTofX5Be5b03MQFJr4cflgllqW77QZ84D9O9qWF\/vNDAofXcwrzT04CxLDhyQgTCgYUnRjG9pnuP\/gtbDKbTjRvxhTyfg3T0Phv1+XAvpTPnH2q5A+1+LmiqziUJ1sMipsKo+jQP614eCi9qjmqhHIgLRgcuOBvsi4g5WUcdcAIZ6qLt5gm2Y3r6rKNVchosU9ZydMUTfjuejDbVwE2fNH5UUnV57fQBxwg9CfX7iFHqKv1bfv5Zviu12paShgWCB12uR3iH\/3lmTJn8K5Xqit3G4eymFaJ5IChdUThBp\/jhQSI2r8sPcZDYSJ\/UZKuFnezFdKhEBd5hMXe8aKAd6ijGDjLARksFuqpi1sS8llC5K1Q+DzktSL\/o64TY4Vuvykiwe\/BAk2SkL9voOtrvU7vfDBcuCPbDJnSBBC0ESpcXeClTBIn6xZ9WaxqoS7sinE\/kUwtWsRd04I7d79\/ouotyNb+mBhTuRsZT12p\/gn4JHXXNUAIpTwchYzGxbfNJ4kxnYBFZWVmvsSqOLFZu1yi5BP3ktA9yhFyWIa5659azRFEKRdXpVHtQVa4IgdhxEqA=",
    "certificate": "-----BEGIN CERTIFICATE-----\r\nMIIEvjCCAqagAwIBAgIUc\/0FxYrsgSs9rDxp03EJmbjN0NwwDQYJKoZIhvcNAQEF\r\nBQAwIzEhMB8GA1UECgwYb3duQ2xvdWQgQ29kZSBTaWduaW5nIENBMB4XDTE1MTEw\r\nMzIxMDMzM1oXDTE2MTEwMzIxMDMzM1owDzENMAsGA1UEAwwEY29yZTCCAiIwDQYJ\r\nKoZIhvcNAQEBBQADggIPADCCAgoCggIBALb6EgHpkAqZbO5vRO8XSh7G7XGWHw5s\r\niOf4RwPXR6SE9bWZEm\/b72SfWk\/\/J6AbrD8WiOzBuT\/ODy6k5T1arEdHO+Pux0W1\r\nMxYJJI4kH74KKgMpC0SB0Rt+8WrMqV1r3hhJ46df6Xr\/xolP3oD+eLbShPcblhdS\r\nVtkZEkoev8Sh6L2wDCeHDyPxzvj1w2dTdGVO9Kztn0xIlyfEBakqvBWtcxyi3Ln0\r\nklnxlMx3tPDUE4kqvpia9qNiB1AN2PV93eNr5\/2riAzIssMFSCarWCx0AKYb54+d\r\nxLpcYFyqPJ0ydBCkF78DD45RCZet6PNYkdzgbqlUWEGGomkuDoJbBg4wzgzO0D77\r\nH87KFhYW8tKFFvF1V3AHl\/sFQ9tDHaxM9Y0pZ2jPp\/ccdiqnmdkBxBDqsiRvHvVB\r\nCn6qpb4vWGFC7vHOBfYspmEL1zLlKXZv3ezMZEZw7O9ZvUP3VO\/wAtd2vUW8UFiq\r\ns2v1QnNLN6jNh51obcwmrBvWhJy9vQIdtIjQbDxqWTHh1zUSrw9wrlklCBZ\/zrM0\r\ni8nfCFwTxWRxp3H9KoECzO\/zS5R5KIS7s3\/wq\/w9T2Ie4rcecgXwDizwnn0C\/aKc\r\nbDIjujpL1s9HO05pcD\/V3wKcPZ1izymBkmMyIbL52iRVN5FTVHeZdXPpFuq+CTQJ\r\nQ238lC+A\/KOVAgMBAAEwDQYJKoZIhvcNAQEFBQADggIBAGoKTnh8RfJV4sQItVC2\r\nAvfJagkrIqZ3iiQTUBQGTKBsTnAqE1H7QgUSV9vSd+8rgvHkyZsRjmtyR1e3A6Ji\r\noNCXUbExC\/0iCPUqdHZIVb+Lc\/vWuv4ByFMybGPydgtLoEUX2ZrKFWmcgZFDUSRd\r\n9Uj26vtUhCC4bU4jgu6hIrR9IuxOBLQUxGTRZyAcXvj7obqRAEZwFAKQgFpfpqTb\r\nH+kjcbZSaAlLVSF7vBc1syyI8RGYbqpwvtREqJtl5IEIwe6huEqJ3zPnlP2th\/55\r\ncf3Fovj6JJgbb9XFxrdnsOsDOu\/tpnaRWlvv5ib4+SzG5wWFT5UUEo4Wg2STQiiX\r\nuVSRQxK1LE1yg84bs3NZk9FSQh4B8vZVuRr5FaJsZZkwlFlhRO\/\/+TJtXRbyNgsf\r\noMRZGi8DLGU2SGEAHcRH\/QZHq\/XDUWVzdxrSBYcy7GSpT7UDVzGv1rEJUrn5veP1\r\n0KmauAqtiIaYRm4f6YBsn0INcZxzIPZ0p8qFtVZBPeHhvQtvOt0iXI\/XUxEWOa2F\r\nK2EqhErgMK\/N07U1JJJay5tYZRtvkGq46oP\/5kQG8hYST0MDK6VihJoPpvCmAm4E\r\npEYKQ96x6A4EH9Y9mZlYozH\/eqmxPbTK8n89\/p7Ydun4rI+B2iiLnY8REWWy6+UQ\r\nV204fGUkJqW5CrKy3P3XvY9X\r\n-----END CERTIFICATE-----"
}';
		$this->environmentHelper
				->expects($this->any())
				->method('getServerRoot')
				->willReturn(\OC::$SERVERROOT . '/tests/data/integritycheck/htaccessWithValidModifiedContent');
		$this->fileAccessHelper
				->expects($this->once())
				->method('file_put_contents')
				->with(
						\OC::$SERVERROOT . '/tests/data/integritycheck/htaccessWithValidModifiedContent/core/signature.json',
					$this->callback(function ($signature) use ($expectedSignatureFileData) {
						$expectedArray = json_decode($expectedSignatureFileData, true);
						$actualArray = json_decode($signature, true);
						$this->assertEquals($expectedArray, $actualArray);
						return true;
					})
				);

		$keyBundle = file_get_contents(__DIR__ .'/../../data/integritycheck/core.crt');
		$rsaPrivateKey = file_get_contents(__DIR__ .'/../../data/integritycheck/core.key');
		$rsa = new RSA();
		$rsa->loadKey($rsaPrivateKey);
		$x509 = new X509();
		$x509->loadX509($keyBundle);
		$this->checker->writeCoreSignature($x509, $rsa, \OC::$SERVERROOT . '/tests/data/integritycheck/htaccessWithValidModifiedContent');
	}

	public function testVerifyCoreSignatureWithoutSignatureData() {
		$this->environmentHelper
				->expects($this->once())
				->method('getChannel')
				->willReturn('stable');
		$this->config
				->expects($this->any())
				->method('getSystemValue')
				->with('integrity.check.disabled', false)
				->willReturn(false);

		$expected = [
			'EXCEPTION' => [
				'class' => 'OC\\IntegrityCheck\\Exceptions\\InvalidSignatureException',
				'message' => 'Signature data not found.',
			],
		];
		$this->assertSame($expected, $this->checker->verifyCoreSignature());
	}

	public function testVerifyCoreSignatureWithValidSignatureData() {
		$this->environmentHelper
				->expects($this->once())
				->method('getChannel')
				->willReturn('stable');
		$this->config
				->expects($this->any())
				->method('getSystemValue')
				->with('integrity.check.disabled', false)
				->willReturn(false);

		$this->environmentHelper
				->expects($this->any())
				->method('getServerRoot')
				->willReturn(\OC::$SERVERROOT . '/tests/data/integritycheck/app/');
		$signatureDataFile = '{
    "hashes": {
        "AnotherFile.txt": "1570ca9420e37629de4328f48c51da29840ddeaa03ae733da4bf1d854b8364f594aac560601270f9e1797ed4cd57c1aea87bf44cf4245295c94f2e935a2f0112",
        "subfolder\/file.txt": "410738545fb623c0a5c8a71f561e48ea69e3ada0981a455e920a5ae9bf17c6831ae654df324f9328ff8453de179276ae51931cca0fa71fe8ccde6c083ca0574b"
    },
    "signature": "dYoohBaWIFR\/To1FXEbMQB5apUhVYlEauBGSPo12nq84wxWkBx2EM3KDRgkB5Sub2tr0CgmAc2EVjPhKIEzAam26cyUb48bJziz1V6wvW7z4GZAfaJpzLkyHdSfV5117VSf5w1rDcAeZDXfGUaaNEJPWytaF4ZIxVge7f3NGshHy4odFVPADy\/u6c43BWvaOtJ4m3aJQbP6sxCO9dxwcm5yJJJR3n36jfh229sdWBxyl8BhwhH1e1DEv78\/aiL6ckKFPVNzx01R6yDFt3TgEMR97YZ\/R6lWiXG+dsJ305jNFlusLu518zBUvl7g5yjzGN778H29b2C8VLZKmi\/h1CH9jGdD72fCqCYdenD2uZKzb6dsUtXtvBmVcVT6BUGz41W1pkkEEB+YJpMrHILIxAiHRGv1+aZa9\/Oz8LWFd+BEUQjC2LJgojPnpzaG\/msw1nBkX16NNVDWWtJ25Bc\/r\/mG46rwjWB\/cmV6Lwt6KODiqlxgrC4lm9ALOCEWw+23OcYhLwNfQTYevXqHqsFfXOkhUnM8z5vDUb\/HBraB1DjFXN8iLK+1YewD4P495e+SRzrR79Oi3F8SEqRIzRLfN2rnW1BTms\/wYsz0p67cup1Slk1XlNmHwbWX25NVd2PPlLOvZRGoqcKFpIjC5few8THiZfyjiNFwt3RM0AFdZcXY=",
    "certificate": "-----BEGIN CERTIFICATE-----\r\nMIIEvjCCAqagAwIBAgIUc\/0FxYrsgSs9rDxp03EJmbjN0NwwDQYJKoZIhvcNAQEF\r\nBQAwIzEhMB8GA1UECgwYb3duQ2xvdWQgQ29kZSBTaWduaW5nIENBMB4XDTE1MTEw\r\nMzIxMDMzM1oXDTE2MTEwMzIxMDMzM1owDzENMAsGA1UEAwwEY29yZTCCAiIwDQYJ\r\nKoZIhvcNAQEBBQADggIPADCCAgoCggIBALb6EgHpkAqZbO5vRO8XSh7G7XGWHw5s\r\niOf4RwPXR6SE9bWZEm\/b72SfWk\/\/J6AbrD8WiOzBuT\/ODy6k5T1arEdHO+Pux0W1\r\nMxYJJI4kH74KKgMpC0SB0Rt+8WrMqV1r3hhJ46df6Xr\/xolP3oD+eLbShPcblhdS\r\nVtkZEkoev8Sh6L2wDCeHDyPxzvj1w2dTdGVO9Kztn0xIlyfEBakqvBWtcxyi3Ln0\r\nklnxlMx3tPDUE4kqvpia9qNiB1AN2PV93eNr5\/2riAzIssMFSCarWCx0AKYb54+d\r\nxLpcYFyqPJ0ydBCkF78DD45RCZet6PNYkdzgbqlUWEGGomkuDoJbBg4wzgzO0D77\r\nH87KFhYW8tKFFvF1V3AHl\/sFQ9tDHaxM9Y0pZ2jPp\/ccdiqnmdkBxBDqsiRvHvVB\r\nCn6qpb4vWGFC7vHOBfYspmEL1zLlKXZv3ezMZEZw7O9ZvUP3VO\/wAtd2vUW8UFiq\r\ns2v1QnNLN6jNh51obcwmrBvWhJy9vQIdtIjQbDxqWTHh1zUSrw9wrlklCBZ\/zrM0\r\ni8nfCFwTxWRxp3H9KoECzO\/zS5R5KIS7s3\/wq\/w9T2Ie4rcecgXwDizwnn0C\/aKc\r\nbDIjujpL1s9HO05pcD\/V3wKcPZ1izymBkmMyIbL52iRVN5FTVHeZdXPpFuq+CTQJ\r\nQ238lC+A\/KOVAgMBAAEwDQYJKoZIhvcNAQEFBQADggIBAGoKTnh8RfJV4sQItVC2\r\nAvfJagkrIqZ3iiQTUBQGTKBsTnAqE1H7QgUSV9vSd+8rgvHkyZsRjmtyR1e3A6Ji\r\noNCXUbExC\/0iCPUqdHZIVb+Lc\/vWuv4ByFMybGPydgtLoEUX2ZrKFWmcgZFDUSRd\r\n9Uj26vtUhCC4bU4jgu6hIrR9IuxOBLQUxGTRZyAcXvj7obqRAEZwFAKQgFpfpqTb\r\nH+kjcbZSaAlLVSF7vBc1syyI8RGYbqpwvtREqJtl5IEIwe6huEqJ3zPnlP2th\/55\r\ncf3Fovj6JJgbb9XFxrdnsOsDOu\/tpnaRWlvv5ib4+SzG5wWFT5UUEo4Wg2STQiiX\r\nuVSRQxK1LE1yg84bs3NZk9FSQh4B8vZVuRr5FaJsZZkwlFlhRO\/\/+TJtXRbyNgsf\r\noMRZGi8DLGU2SGEAHcRH\/QZHq\/XDUWVzdxrSBYcy7GSpT7UDVzGv1rEJUrn5veP1\r\n0KmauAqtiIaYRm4f6YBsn0INcZxzIPZ0p8qFtVZBPeHhvQtvOt0iXI\/XUxEWOa2F\r\nK2EqhErgMK\/N07U1JJJay5tYZRtvkGq46oP\/5kQG8hYST0MDK6VihJoPpvCmAm4E\r\npEYKQ96x6A4EH9Y9mZlYozH\/eqmxPbTK8n89\/p7Ydun4rI+B2iiLnY8REWWy6+UQ\r\nV204fGUkJqW5CrKy3P3XvY9X\r\n-----END CERTIFICATE-----"
}';
		$this->fileAccessHelper
				->expects($this->at(0))
				->method('file_get_contents')
				->with(
						\OC::$SERVERROOT . '/tests/data/integritycheck/app//core/signature.json'
				)
				->willReturn($signatureDataFile);
		$this->fileAccessHelper
				->expects($this->at(1))
				->method('file_get_contents')
				->with(
						\OC::$SERVERROOT . '/tests/data/integritycheck/app//resources/codesigning/root.crt'
				)
				->willReturn(file_get_contents(__DIR__ .'/../../data/integritycheck/root.crt'));

		$this->assertSame([], $this->checker->verifyCoreSignature());
	}

	public function testVerifyCoreSignatureWithValidModifiedHtaccessSignatureData() {
		$this->environmentHelper
			->expects($this->once())
			->method('getChannel')
			->willReturn('stable');
		$this->config
			->expects($this->any())
			->method('getSystemValue')
			->with('integrity.check.disabled', false)
			->willReturn(false);

		$this->environmentHelper
			->expects($this->any())
			->method('getServerRoot')
			->willReturn(\OC::$SERVERROOT . '/tests/data/integritycheck/htaccessWithValidModifiedContent');
		$signatureDataFile = '{
    "hashes": {
        ".htaccess": "7e6a7a4d8ee4f3fbc45dd579407c643471575a9d127d1c75f6d0a49e80766c3c587104b2139ef76d2a4bffce3f45777900605aaa49519c9532909b71e5030227",
        "subfolder\/.htaccess": "2c57b1e25050e11dc3ae975832f378c452159f7b69f818e47eeeafadd6ba568517461dcb4d843b90b906cd7c89d161bc1b89dff8e3ae0eb6f5088508c47befd1"
    },
    "signature": "YVwQvl9Dh8UebCumfgzFxfz3NiZJLmYG8oJVTfEBhulI4KXBnTG1jZTprf4XxG2XIriEYAZXsoXpu9xWsUFe9QfdncwoEpqJtGq7l6aVDTofX5Be5b03MQFJr4cflgllqW77QZ84D9O9qWF\/vNDAofXcwrzT04CxLDhyQgTCgYUnRjG9pnuP\/gtbDKbTjRvxhTyfg3T0Phv1+XAvpTPnH2q5A+1+LmiqziUJ1sMipsKo+jQP614eCi9qjmqhHIgLRgcuOBvsi4g5WUcdcAIZ6qLt5gm2Y3r6rKNVchosU9ZydMUTfjuejDbVwE2fNH5UUnV57fQBxwg9CfX7iFHqKv1bfv5Zviu12paShgWCB12uR3iH\/3lmTJn8K5Xqit3G4eymFaJ5IChdUThBp\/jhQSI2r8sPcZDYSJ\/UZKuFnezFdKhEBd5hMXe8aKAd6ijGDjLARksFuqpi1sS8llC5K1Q+DzktSL\/o64TY4Vuvykiwe\/BAk2SkL9voOtrvU7vfDBcuCPbDJnSBBC0ESpcXeClTBIn6xZ9WaxqoS7sinE\/kUwtWsRd04I7d79\/ouotyNb+mBhTuRsZT12p\/gn4JHXXNUAIpTwchYzGxbfNJ4kxnYBFZWVmvsSqOLFZu1yi5BP3ktA9yhFyWIa5659azRFEKRdXpVHtQVa4IgdhxEqA=",
    "certificate": "-----BEGIN CERTIFICATE-----\r\nMIIEvjCCAqagAwIBAgIUc\/0FxYrsgSs9rDxp03EJmbjN0NwwDQYJKoZIhvcNAQEF\r\nBQAwIzEhMB8GA1UECgwYb3duQ2xvdWQgQ29kZSBTaWduaW5nIENBMB4XDTE1MTEw\r\nMzIxMDMzM1oXDTE2MTEwMzIxMDMzM1owDzENMAsGA1UEAwwEY29yZTCCAiIwDQYJ\r\nKoZIhvcNAQEBBQADggIPADCCAgoCggIBALb6EgHpkAqZbO5vRO8XSh7G7XGWHw5s\r\niOf4RwPXR6SE9bWZEm\/b72SfWk\/\/J6AbrD8WiOzBuT\/ODy6k5T1arEdHO+Pux0W1\r\nMxYJJI4kH74KKgMpC0SB0Rt+8WrMqV1r3hhJ46df6Xr\/xolP3oD+eLbShPcblhdS\r\nVtkZEkoev8Sh6L2wDCeHDyPxzvj1w2dTdGVO9Kztn0xIlyfEBakqvBWtcxyi3Ln0\r\nklnxlMx3tPDUE4kqvpia9qNiB1AN2PV93eNr5\/2riAzIssMFSCarWCx0AKYb54+d\r\nxLpcYFyqPJ0ydBCkF78DD45RCZet6PNYkdzgbqlUWEGGomkuDoJbBg4wzgzO0D77\r\nH87KFhYW8tKFFvF1V3AHl\/sFQ9tDHaxM9Y0pZ2jPp\/ccdiqnmdkBxBDqsiRvHvVB\r\nCn6qpb4vWGFC7vHOBfYspmEL1zLlKXZv3ezMZEZw7O9ZvUP3VO\/wAtd2vUW8UFiq\r\ns2v1QnNLN6jNh51obcwmrBvWhJy9vQIdtIjQbDxqWTHh1zUSrw9wrlklCBZ\/zrM0\r\ni8nfCFwTxWRxp3H9KoECzO\/zS5R5KIS7s3\/wq\/w9T2Ie4rcecgXwDizwnn0C\/aKc\r\nbDIjujpL1s9HO05pcD\/V3wKcPZ1izymBkmMyIbL52iRVN5FTVHeZdXPpFuq+CTQJ\r\nQ238lC+A\/KOVAgMBAAEwDQYJKoZIhvcNAQEFBQADggIBAGoKTnh8RfJV4sQItVC2\r\nAvfJagkrIqZ3iiQTUBQGTKBsTnAqE1H7QgUSV9vSd+8rgvHkyZsRjmtyR1e3A6Ji\r\noNCXUbExC\/0iCPUqdHZIVb+Lc\/vWuv4ByFMybGPydgtLoEUX2ZrKFWmcgZFDUSRd\r\n9Uj26vtUhCC4bU4jgu6hIrR9IuxOBLQUxGTRZyAcXvj7obqRAEZwFAKQgFpfpqTb\r\nH+kjcbZSaAlLVSF7vBc1syyI8RGYbqpwvtREqJtl5IEIwe6huEqJ3zPnlP2th\/55\r\ncf3Fovj6JJgbb9XFxrdnsOsDOu\/tpnaRWlvv5ib4+SzG5wWFT5UUEo4Wg2STQiiX\r\nuVSRQxK1LE1yg84bs3NZk9FSQh4B8vZVuRr5FaJsZZkwlFlhRO\/\/+TJtXRbyNgsf\r\noMRZGi8DLGU2SGEAHcRH\/QZHq\/XDUWVzdxrSBYcy7GSpT7UDVzGv1rEJUrn5veP1\r\n0KmauAqtiIaYRm4f6YBsn0INcZxzIPZ0p8qFtVZBPeHhvQtvOt0iXI\/XUxEWOa2F\r\nK2EqhErgMK\/N07U1JJJay5tYZRtvkGq46oP\/5kQG8hYST0MDK6VihJoPpvCmAm4E\r\npEYKQ96x6A4EH9Y9mZlYozH\/eqmxPbTK8n89\/p7Ydun4rI+B2iiLnY8REWWy6+UQ\r\nV204fGUkJqW5CrKy3P3XvY9X\r\n-----END CERTIFICATE-----"
}';
		$this->fileAccessHelper
			->expects($this->at(0))
			->method('file_get_contents')
			->with(
				\OC::$SERVERROOT . '/tests/data/integritycheck/htaccessWithValidModifiedContent/core/signature.json'
			)
			->willReturn($signatureDataFile);
		$this->fileAccessHelper
			->expects($this->at(1))
			->method('file_get_contents')
			->with(
				\OC::$SERVERROOT . '/tests/data/integritycheck/htaccessWithValidModifiedContent/resources/codesigning/root.crt'
			)
			->willReturn(file_get_contents(__DIR__ .'/../../data/integritycheck/root.crt'));

		$this->assertSame([], $this->checker->verifyCoreSignature());
	}

	public function testVerifyCoreSignatureWithModifiedMimetypelistSignatureData() {
		$this->environmentHelper
			->expects($this->once())
			->method('getChannel')
			->willReturn('stable');
		$this->config
			->expects($this->any())
			->method('getSystemValue')
			->with('integrity.check.disabled', false)
			->willReturn(false);

		$this->mimeTypeDetector
			->expects($this->once())
			->method('getOnlyDefaultAliases')
			->willReturn(
				 [
				 	'_comment' => 'Array of mimetype aliases.',
				 	'_comment2' => 'Any changes you make here will be overwritten on an update of Nextcloud.',
				 	'_comment3' => 'Put any custom mappings in a new file mimetypealiases.json in the config/ folder of Nextcloud',
				 	'_comment4' => 'After any change to mimetypealiases.json run:',
				 	'_comment5' => './occ maintenance:mimetype:update-js',
				 	'_comment6' => 'Otherwise your update won\'t propagate through the system.',
				 	'application/coreldraw' => 'image',
				 	'application/epub+zip' => 'text',
				 	'application/font-sfnt' => 'image',
				 	'application/font-woff' => 'image',
				 	'application/gpx+xml' => 'location',
				 	'application/illustrator' => 'image',
				 	'application/javascript' => 'text/code',
				 	'application/json' => 'text/code',
				 	'application/msaccess' => 'file',
				 	'application/msexcel' => 'x-office/spreadsheet',
				 	'application/msonenote' => 'x-office/document',
				 	'application/mspowerpoint' => 'x-office/presentation',
				 	'application/msword' => 'x-office/document',
				 	'application/octet-stream' => 'file',
				 	'application/postscript' => 'image',
				 	'application/rss+xml' => 'application/xml',
				 	'application/vnd.android.package-archive' => 'package/x-generic',
				 	'application/vnd.lotus-wordpro' => 'x-office/document',
				 	'application/vnd.garmin.tcx+xml' => 'location',
				 	'application/vnd.google-earth.kml+xml' => 'location',
				 	'application/vnd.google-earth.kmz' => 'location',
				 	'application/vnd.ms-excel' => 'x-office/spreadsheet',
				 	'application/vnd.ms-excel.addin.macroEnabled.12' => 'x-office/spreadsheet',
				 	'application/vnd.ms-excel.sheet.binary.macroEnabled.12' => 'x-office/spreadsheet',
				 	'application/vnd.ms-excel.sheet.macroEnabled.12' => 'x-office/spreadsheet',
				 	'application/vnd.ms-excel.template.macroEnabled.12' => 'x-office/spreadsheet',
				 	'application/vnd.ms-fontobject' => 'image',
				 	'application/vnd.ms-powerpoint' => 'x-office/presentation',
				 	'application/vnd.ms-powerpoint.addin.macroEnabled.12' => 'x-office/presentation',
				 	'application/vnd.ms-powerpoint.presentation.macroEnabled.12' => 'x-office/presentation',
				 	'application/vnd.ms-powerpoint.slideshow.macroEnabled.12' => 'x-office/presentation',
				 	'application/vnd.ms-powerpoint.template.macroEnabled.12' => 'x-office/presentation',
				 	'application/vnd.ms-visio.drawing.macroEnabled.12' => 'application/vnd.visio',
				 	'application/vnd.ms-visio.drawing' => 'application/vnd.visio',
				 	'application/vnd.ms-visio.stencil.macroEnabled.12' => 'application/vnd.visio',
				 	'application/vnd.ms-visio.stencil' => 'application/vnd.visio',
				 	'application/vnd.ms-visio.template.macroEnabled.12' => 'application/vnd.visio',
				 	'application/vnd.ms-visio.template' => 'application/vnd.visio',
				 	'application/vnd.ms-word.document.macroEnabled.12' => 'x-office/document',
				 	'application/vnd.ms-word.template.macroEnabled.12' => 'x-office/document',
				 	'application/vnd.oasis.opendocument.presentation' => 'x-office/presentation',
				 	'application/vnd.oasis.opendocument.presentation-template' => 'x-office/presentation',
				 	'application/vnd.oasis.opendocument.spreadsheet' => 'x-office/spreadsheet',
				 	'application/vnd.oasis.opendocument.spreadsheet-template' => 'x-office/spreadsheet',
				 	'application/vnd.oasis.opendocument.text' => 'x-office/document',
				 	'application/vnd.oasis.opendocument.text-master' => 'x-office/document',
				 	'application/vnd.oasis.opendocument.text-template' => 'x-office/document',
				 	'application/vnd.oasis.opendocument.text-web' => 'x-office/document',
				 	'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'x-office/presentation',
				 	'application/vnd.openxmlformats-officedocument.presentationml.slideshow' => 'x-office/presentation',
				 	'application/vnd.openxmlformats-officedocument.presentationml.template' => 'x-office/presentation',
				 	'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'x-office/spreadsheet',
				 	'application/vnd.openxmlformats-officedocument.spreadsheetml.template' => 'x-office/spreadsheet',
				 	'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'x-office/document',
				 	'application/vnd.openxmlformats-officedocument.wordprocessingml.template' => 'x-office/document',
				 	'application/vnd.visio' => 'x-office/document',
				 	'application/vnd.wordperfect' => 'x-office/document',
				 	'application/x-7z-compressed' => 'package/x-generic',
				 	'application/x-bzip2' => 'package/x-generic',
				 	'application/x-cbr' => 'text',
				 	'application/x-compressed' => 'package/x-generic',
				 	'application/x-dcraw' => 'image',
				 	'application/x-deb' => 'package/x-generic',
				 	'application/x-fictionbook+xml' => 'text',
				 	'application/x-font' => 'image',
				 	'application/x-gimp' => 'image',
				 	'application/x-gzip' => 'package/x-generic',
				 	'application/x-iwork-keynote-sffkey' => 'x-office/presentation',
				 	'application/x-iwork-numbers-sffnumbers' => 'x-office/spreadsheet',
				 	'application/x-iwork-pages-sffpages' => 'x-office/document',
				 	'application/x-mobipocket-ebook' => 'text',
				 	'application/x-perl' => 'text/code',
				 	'application/x-photoshop' => 'image',
				 	'application/x-php' => 'text/code',
				 	'application/x-rar-compressed' => 'package/x-generic',
				 	'application/x-tar' => 'package/x-generic',
				 	'application/x-tex' => 'text',
				 	'application/xml' => 'text/html',
				 	'application/yaml' => 'text/code',
				 	'application/zip' => 'package/x-generic',
				 	'database' => 'file',
				 	'httpd/unix-directory' => 'dir',
				 	'text/css' => 'text/code',
				 	'text/csv' => 'x-office/spreadsheet',
				 	'text/html' => 'text/code',
				 	'text/x-c' => 'text/code',
				 	'text/x-c++src' => 'text/code',
				 	'text/x-h' => 'text/code',
				 	'text/x-java-source' => 'text/code',
				 	'text/x-ldif' => 'text/code',
				 	'text/x-python' => 'text/code',
				 	'text/x-shellscript' => 'text/code',
				 	'web' => 'text/code',
				 	'application/internet-shortcut' => 'link',
				 	'application/km' => 'mindmap',
				 	'application/x-freemind' => 'mindmap',
				 	'application/vnd.xmind.workbook' => 'mindmap'
				 ]);

		$this->mimeTypeDetector
			->expects($this->once())
			->method('getAllAliases')
			->willReturn(
				 [
				 	'_comment' => 'Array of mimetype aliases.',
				 	'_comment2' => 'Any changes you make here will be overwritten on an update of Nextcloud.',
				 	'_comment3' => 'Put any custom mappings in a new file mimetypealiases.json in the config/ folder of Nextcloud',
				 	'_comment4' => 'After any change to mimetypealiases.json run:',
				 	'_comment5' => './occ maintenance:mimetype:update-js',
				 	'_comment6' => 'Otherwise your update won\'t propagate through the system.',
				 	'application/coreldraw' => 'image',
				 	'application/test' => 'image',
				 	'application/epub+zip' => 'text',
				 	'application/font-sfnt' => 'image',
				 	'application/font-woff' => 'image',
				 	'application/gpx+xml' => 'location',
				 	'application/illustrator' => 'image',
				 	'application/javascript' => 'text/code',
				 	'application/json' => 'text/code',
				 	'application/msaccess' => 'file',
				 	'application/msexcel' => 'x-office/spreadsheet',
				 	'application/msonenote' => 'x-office/document',
				 	'application/mspowerpoint' => 'x-office/presentation',
				 	'application/msword' => 'x-office/document',
				 	'application/octet-stream' => 'file',
				 	'application/postscript' => 'image',
				 	'application/rss+xml' => 'application/xml',
				 	'application/vnd.android.package-archive' => 'package/x-generic',
				 	'application/vnd.lotus-wordpro' => 'x-office/document',
				 	'application/vnd.garmin.tcx+xml' => 'location',
				 	'application/vnd.google-earth.kml+xml' => 'location',
				 	'application/vnd.google-earth.kmz' => 'location',
				 	'application/vnd.ms-excel' => 'x-office/spreadsheet',
				 	'application/vnd.ms-excel.addin.macroEnabled.12' => 'x-office/spreadsheet',
				 	'application/vnd.ms-excel.sheet.binary.macroEnabled.12' => 'x-office/spreadsheet',
				 	'application/vnd.ms-excel.sheet.macroEnabled.12' => 'x-office/spreadsheet',
				 	'application/vnd.ms-excel.template.macroEnabled.12' => 'x-office/spreadsheet',
				 	'application/vnd.ms-fontobject' => 'image',
				 	'application/vnd.ms-powerpoint' => 'x-office/presentation',
				 	'application/vnd.ms-powerpoint.addin.macroEnabled.12' => 'x-office/presentation',
				 	'application/vnd.ms-powerpoint.presentation.macroEnabled.12' => 'x-office/presentation',
				 	'application/vnd.ms-powerpoint.slideshow.macroEnabled.12' => 'x-office/presentation',
				 	'application/vnd.ms-powerpoint.template.macroEnabled.12' => 'x-office/presentation',
				 	'application/vnd.ms-visio.drawing.macroEnabled.12' => 'application/vnd.visio',
				 	'application/vnd.ms-visio.drawing' => 'application/vnd.visio',
				 	'application/vnd.ms-visio.stencil.macroEnabled.12' => 'application/vnd.visio',
				 	'application/vnd.ms-visio.stencil' => 'application/vnd.visio',
				 	'application/vnd.ms-visio.template.macroEnabled.12' => 'application/vnd.visio',
				 	'application/vnd.ms-visio.template' => 'application/vnd.visio',
				 	'application/vnd.ms-word.document.macroEnabled.12' => 'x-office/document',
				 	'application/vnd.ms-word.template.macroEnabled.12' => 'x-office/document',
				 	'application/vnd.oasis.opendocument.presentation' => 'x-office/presentation',
				 	'application/vnd.oasis.opendocument.presentation-template' => 'x-office/presentation',
				 	'application/vnd.oasis.opendocument.spreadsheet' => 'x-office/spreadsheet',
				 	'application/vnd.oasis.opendocument.spreadsheet-template' => 'x-office/spreadsheet',
				 	'application/vnd.oasis.opendocument.text' => 'x-office/document',
				 	'application/vnd.oasis.opendocument.text-master' => 'x-office/document',
				 	'application/vnd.oasis.opendocument.text-template' => 'x-office/document',
				 	'application/vnd.oasis.opendocument.text-web' => 'x-office/document',
				 	'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'x-office/presentation',
				 	'application/vnd.openxmlformats-officedocument.presentationml.slideshow' => 'x-office/presentation',
				 	'application/vnd.openxmlformats-officedocument.presentationml.template' => 'x-office/presentation',
				 	'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'x-office/spreadsheet',
				 	'application/vnd.openxmlformats-officedocument.spreadsheetml.template' => 'x-office/spreadsheet',
				 	'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'x-office/document',
				 	'application/vnd.openxmlformats-officedocument.wordprocessingml.template' => 'x-office/document',
				 	'application/vnd.visio' => 'x-office/document',
				 	'application/vnd.wordperfect' => 'x-office/document',
				 	'application/x-7z-compressed' => 'package/x-generic',
				 	'application/x-bzip2' => 'package/x-generic',
				 	'application/x-cbr' => 'text',
				 	'application/x-compressed' => 'package/x-generic',
				 	'application/x-dcraw' => 'image',
				 	'application/x-deb' => 'package/x-generic',
				 	'application/x-fictionbook+xml' => 'text',
				 	'application/x-font' => 'image',
				 	'application/x-gimp' => 'image',
				 	'application/x-gzip' => 'package/x-generic',
				 	'application/x-iwork-keynote-sffkey' => 'x-office/presentation',
				 	'application/x-iwork-numbers-sffnumbers' => 'x-office/spreadsheet',
				 	'application/x-iwork-pages-sffpages' => 'x-office/document',
				 	'application/x-mobipocket-ebook' => 'text',
				 	'application/x-perl' => 'text/code',
				 	'application/x-photoshop' => 'image',
				 	'application/x-php' => 'text/code',
				 	'application/x-rar-compressed' => 'package/x-generic',
				 	'application/x-tar' => 'package/x-generic',
				 	'application/x-tex' => 'text',
				 	'application/xml' => 'text/html',
				 	'application/yaml' => 'text/code',
				 	'application/zip' => 'package/x-generic',
				 	'database' => 'file',
				 	'httpd/unix-directory' => 'dir',
				 	'text/css' => 'text/code',
				 	'text/csv' => 'x-office/spreadsheet',
				 	'text/html' => 'text/code',
				 	'text/x-c' => 'text/code',
				 	'text/x-c++src' => 'text/code',
				 	'text/x-h' => 'text/code',
				 	'text/x-java-source' => 'text/code',
				 	'text/x-ldif' => 'text/code',
				 	'text/x-python' => 'text/code',
				 	'text/x-shellscript' => 'text/code',
				 	'web' => 'text/code',
				 	'application/internet-shortcut' => 'link',
				 	'application/km' => 'mindmap',
				 	'application/x-freemind' => 'mindmap',
				 	'application/vnd.xmind.workbook' => 'mindmap'
				 ]);

		$this->environmentHelper
			->expects($this->any())
			->method('getServerRoot')
			->willReturn(\OC::$SERVERROOT . '/tests/data/integritycheck/mimetypeListModified');
		$signatureDataFile = '{
    "hashes": {
        "mimetypelist.js": "23a3fa45757b26795f5cf8f94d9e5bce76fc916288a6458679311b312c167adcf4158ccb2c5c3db791fa0b87703b6c5863afb6c9d790b8cbac5ab3ae8c3418fb"
    },
    "signature": "rqdAbS1evGMhv5vQEhFwDoDgumY1La+xAEgF8TdJjyE1uEjV5442LgnIbolqH5WmGkU4EEjcIJWXEhxbtr7P1EO8WT0v7mVJBmAT0p5Gi3nxz9z9gp7mAbjfBH8McYxNzy92XbdYgiNMqi5tltYRCBXTVw1Rexh9dFc7jmLQIfpAFImR9sQG5eqGUGm+L70o34EToDnEg6Finqj4Ii5aGaGTf+DMz6wZRM2vfeegw5pxgv7EjW4R2dVTGSwwvjQbBSDzMqJHGPXkAlpnqgPDXfSLsRYXVYoRGfD6gXJ8WkjCtceQhW3FG7zMzGykogQtxWDgvbTZsz1PlzBR82UZj144ustqmCNf+ZFSwzjKpQil0hiNZmgmAGPn7frwKbDwE6TAqvqfNbpNy1GcP+eNmTn8C+tmKgyGSkqx67w8NgEn9mmPkubZk6Q/cQ/GIVprY27iPQc5irdV+COi6ZWl+WfO6BNRewlsiAYAe1XQwf4wJ5qGFzKNUd9ovWUfgh61ZthIZEVFcTGMmH4cjymnGxpbqiow4di7RQNuCKTRowmrKAWb58KUGEXxy5Nrr9M8SSn5N232iV6HSEWf5QwoKpZ2Zv9BdTXv0Rzx3MO/NWhj2O0UBmCqL1VI/uem9HoC0751GTnMSk36bIVWflJm6fimVvF7OAvZpZgNKBbtJVs=",
    "certificate": "-----BEGIN CERTIFICATE-----\r\nMIIEvjCCAqagAwIBAgIUc\/0FxYrsgSs9rDxp03EJmbjN0NwwDQYJKoZIhvcNAQEF\r\nBQAwIzEhMB8GA1UECgwYb3duQ2xvdWQgQ29kZSBTaWduaW5nIENBMB4XDTE1MTEw\r\nMzIxMDMzM1oXDTE2MTEwMzIxMDMzM1owDzENMAsGA1UEAwwEY29yZTCCAiIwDQYJ\r\nKoZIhvcNAQEBBQADggIPADCCAgoCggIBALb6EgHpkAqZbO5vRO8XSh7G7XGWHw5s\r\niOf4RwPXR6SE9bWZEm\/b72SfWk\/\/J6AbrD8WiOzBuT\/ODy6k5T1arEdHO+Pux0W1\r\nMxYJJI4kH74KKgMpC0SB0Rt+8WrMqV1r3hhJ46df6Xr\/xolP3oD+eLbShPcblhdS\r\nVtkZEkoev8Sh6L2wDCeHDyPxzvj1w2dTdGVO9Kztn0xIlyfEBakqvBWtcxyi3Ln0\r\nklnxlMx3tPDUE4kqvpia9qNiB1AN2PV93eNr5\/2riAzIssMFSCarWCx0AKYb54+d\r\nxLpcYFyqPJ0ydBCkF78DD45RCZet6PNYkdzgbqlUWEGGomkuDoJbBg4wzgzO0D77\r\nH87KFhYW8tKFFvF1V3AHl\/sFQ9tDHaxM9Y0pZ2jPp\/ccdiqnmdkBxBDqsiRvHvVB\r\nCn6qpb4vWGFC7vHOBfYspmEL1zLlKXZv3ezMZEZw7O9ZvUP3VO\/wAtd2vUW8UFiq\r\ns2v1QnNLN6jNh51obcwmrBvWhJy9vQIdtIjQbDxqWTHh1zUSrw9wrlklCBZ\/zrM0\r\ni8nfCFwTxWRxp3H9KoECzO\/zS5R5KIS7s3\/wq\/w9T2Ie4rcecgXwDizwnn0C\/aKc\r\nbDIjujpL1s9HO05pcD\/V3wKcPZ1izymBkmMyIbL52iRVN5FTVHeZdXPpFuq+CTQJ\r\nQ238lC+A\/KOVAgMBAAEwDQYJKoZIhvcNAQEFBQADggIBAGoKTnh8RfJV4sQItVC2\r\nAvfJagkrIqZ3iiQTUBQGTKBsTnAqE1H7QgUSV9vSd+8rgvHkyZsRjmtyR1e3A6Ji\r\noNCXUbExC\/0iCPUqdHZIVb+Lc\/vWuv4ByFMybGPydgtLoEUX2ZrKFWmcgZFDUSRd\r\n9Uj26vtUhCC4bU4jgu6hIrR9IuxOBLQUxGTRZyAcXvj7obqRAEZwFAKQgFpfpqTb\r\nH+kjcbZSaAlLVSF7vBc1syyI8RGYbqpwvtREqJtl5IEIwe6huEqJ3zPnlP2th\/55\r\ncf3Fovj6JJgbb9XFxrdnsOsDOu\/tpnaRWlvv5ib4+SzG5wWFT5UUEo4Wg2STQiiX\r\nuVSRQxK1LE1yg84bs3NZk9FSQh4B8vZVuRr5FaJsZZkwlFlhRO\/\/+TJtXRbyNgsf\r\noMRZGi8DLGU2SGEAHcRH\/QZHq\/XDUWVzdxrSBYcy7GSpT7UDVzGv1rEJUrn5veP1\r\n0KmauAqtiIaYRm4f6YBsn0INcZxzIPZ0p8qFtVZBPeHhvQtvOt0iXI\/XUxEWOa2F\r\nK2EqhErgMK\/N07U1JJJay5tYZRtvkGq46oP\/5kQG8hYST0MDK6VihJoPpvCmAm4E\r\npEYKQ96x6A4EH9Y9mZlYozH\/eqmxPbTK8n89\/p7Ydun4rI+B2iiLnY8REWWy6+UQ\r\nV204fGUkJqW5CrKy3P3XvY9X\r\n-----END CERTIFICATE-----"
}';
		$this->fileAccessHelper
			->expects($this->at(0))
			->method('file_get_contents')
			->with(
				\OC::$SERVERROOT . '/tests/data/integritycheck/mimetypeListModified/core/signature.json'
			)
			->willReturn($signatureDataFile);
		$this->fileAccessHelper
			->expects($this->at(1))
			->method('file_get_contents')
			->with(
				\OC::$SERVERROOT . '/tests/data/integritycheck/mimetypeListModified/resources/codesigning/root.crt'
			)
			->willReturn(file_get_contents(__DIR__ .'/../../data/integritycheck/root.crt'));

		$this->assertSame([], $this->checker->verifyCoreSignature());
	}

	public function testVerifyCoreSignatureWithValidSignatureDataAndNotAlphabeticOrder() {
		$this->environmentHelper
				->expects($this->once())
				->method('getChannel')
				->willReturn('stable');
		$this->config
				->expects($this->any())
				->method('getSystemValue')
				->with('integrity.check.disabled', false)
				->willReturn(false);

		$this->environmentHelper
				->expects($this->any())
				->method('getServerRoot')
				->willReturn(\OC::$SERVERROOT . '/tests/data/integritycheck/app/');
		$signatureDataFile = '{
    "hashes": {
        "AnotherFile.txt": "1570ca9420e37629de4328f48c51da29840ddeaa03ae733da4bf1d854b8364f594aac560601270f9e1797ed4cd57c1aea87bf44cf4245295c94f2e935a2f0112",
        "subfolder\/file.txt": "410738545fb623c0a5c8a71f561e48ea69e3ada0981a455e920a5ae9bf17c6831ae654df324f9328ff8453de179276ae51931cca0fa71fe8ccde6c083ca0574b"
    },
    "signature": "dYoohBaWIFR\/To1FXEbMQB5apUhVYlEauBGSPo12nq84wxWkBx2EM3KDRgkB5Sub2tr0CgmAc2EVjPhKIEzAam26cyUb48bJziz1V6wvW7z4GZAfaJpzLkyHdSfV5117VSf5w1rDcAeZDXfGUaaNEJPWytaF4ZIxVge7f3NGshHy4odFVPADy\/u6c43BWvaOtJ4m3aJQbP6sxCO9dxwcm5yJJJR3n36jfh229sdWBxyl8BhwhH1e1DEv78\/aiL6ckKFPVNzx01R6yDFt3TgEMR97YZ\/R6lWiXG+dsJ305jNFlusLu518zBUvl7g5yjzGN778H29b2C8VLZKmi\/h1CH9jGdD72fCqCYdenD2uZKzb6dsUtXtvBmVcVT6BUGz41W1pkkEEB+YJpMrHILIxAiHRGv1+aZa9\/Oz8LWFd+BEUQjC2LJgojPnpzaG\/msw1nBkX16NNVDWWtJ25Bc\/r\/mG46rwjWB\/cmV6Lwt6KODiqlxgrC4lm9ALOCEWw+23OcYhLwNfQTYevXqHqsFfXOkhUnM8z5vDUb\/HBraB1DjFXN8iLK+1YewD4P495e+SRzrR79Oi3F8SEqRIzRLfN2rnW1BTms\/wYsz0p67cup1Slk1XlNmHwbWX25NVd2PPlLOvZRGoqcKFpIjC5few8THiZfyjiNFwt3RM0AFdZcXY=",
    "certificate": "-----BEGIN CERTIFICATE-----\r\nMIIEvjCCAqagAwIBAgIUc\/0FxYrsgSs9rDxp03EJmbjN0NwwDQYJKoZIhvcNAQEF\r\nBQAwIzEhMB8GA1UECgwYb3duQ2xvdWQgQ29kZSBTaWduaW5nIENBMB4XDTE1MTEw\r\nMzIxMDMzM1oXDTE2MTEwMzIxMDMzM1owDzENMAsGA1UEAwwEY29yZTCCAiIwDQYJ\r\nKoZIhvcNAQEBBQADggIPADCCAgoCggIBALb6EgHpkAqZbO5vRO8XSh7G7XGWHw5s\r\niOf4RwPXR6SE9bWZEm\/b72SfWk\/\/J6AbrD8WiOzBuT\/ODy6k5T1arEdHO+Pux0W1\r\nMxYJJI4kH74KKgMpC0SB0Rt+8WrMqV1r3hhJ46df6Xr\/xolP3oD+eLbShPcblhdS\r\nVtkZEkoev8Sh6L2wDCeHDyPxzvj1w2dTdGVO9Kztn0xIlyfEBakqvBWtcxyi3Ln0\r\nklnxlMx3tPDUE4kqvpia9qNiB1AN2PV93eNr5\/2riAzIssMFSCarWCx0AKYb54+d\r\nxLpcYFyqPJ0ydBCkF78DD45RCZet6PNYkdzgbqlUWEGGomkuDoJbBg4wzgzO0D77\r\nH87KFhYW8tKFFvF1V3AHl\/sFQ9tDHaxM9Y0pZ2jPp\/ccdiqnmdkBxBDqsiRvHvVB\r\nCn6qpb4vWGFC7vHOBfYspmEL1zLlKXZv3ezMZEZw7O9ZvUP3VO\/wAtd2vUW8UFiq\r\ns2v1QnNLN6jNh51obcwmrBvWhJy9vQIdtIjQbDxqWTHh1zUSrw9wrlklCBZ\/zrM0\r\ni8nfCFwTxWRxp3H9KoECzO\/zS5R5KIS7s3\/wq\/w9T2Ie4rcecgXwDizwnn0C\/aKc\r\nbDIjujpL1s9HO05pcD\/V3wKcPZ1izymBkmMyIbL52iRVN5FTVHeZdXPpFuq+CTQJ\r\nQ238lC+A\/KOVAgMBAAEwDQYJKoZIhvcNAQEFBQADggIBAGoKTnh8RfJV4sQItVC2\r\nAvfJagkrIqZ3iiQTUBQGTKBsTnAqE1H7QgUSV9vSd+8rgvHkyZsRjmtyR1e3A6Ji\r\noNCXUbExC\/0iCPUqdHZIVb+Lc\/vWuv4ByFMybGPydgtLoEUX2ZrKFWmcgZFDUSRd\r\n9Uj26vtUhCC4bU4jgu6hIrR9IuxOBLQUxGTRZyAcXvj7obqRAEZwFAKQgFpfpqTb\r\nH+kjcbZSaAlLVSF7vBc1syyI8RGYbqpwvtREqJtl5IEIwe6huEqJ3zPnlP2th\/55\r\ncf3Fovj6JJgbb9XFxrdnsOsDOu\/tpnaRWlvv5ib4+SzG5wWFT5UUEo4Wg2STQiiX\r\nuVSRQxK1LE1yg84bs3NZk9FSQh4B8vZVuRr5FaJsZZkwlFlhRO\/\/+TJtXRbyNgsf\r\noMRZGi8DLGU2SGEAHcRH\/QZHq\/XDUWVzdxrSBYcy7GSpT7UDVzGv1rEJUrn5veP1\r\n0KmauAqtiIaYRm4f6YBsn0INcZxzIPZ0p8qFtVZBPeHhvQtvOt0iXI\/XUxEWOa2F\r\nK2EqhErgMK\/N07U1JJJay5tYZRtvkGq46oP\/5kQG8hYST0MDK6VihJoPpvCmAm4E\r\npEYKQ96x6A4EH9Y9mZlYozH\/eqmxPbTK8n89\/p7Ydun4rI+B2iiLnY8REWWy6+UQ\r\nV204fGUkJqW5CrKy3P3XvY9X\r\n-----END CERTIFICATE-----"
}';
		$this->fileAccessHelper
				->expects($this->at(0))
				->method('file_get_contents')
				->with(
						\OC::$SERVERROOT . '/tests/data/integritycheck/app//core/signature.json'
				)
				->willReturn($signatureDataFile);
		$this->fileAccessHelper
				->expects($this->at(1))
				->method('file_get_contents')
				->with(
						\OC::$SERVERROOT . '/tests/data/integritycheck/app//resources/codesigning/root.crt'
				)
				->willReturn(file_get_contents(__DIR__ .'/../../data/integritycheck/root.crt'));

		$this->assertSame([], $this->checker->verifyCoreSignature());
	}

	public function testVerifyCoreSignatureWithTamperedSignatureData() {
		$this->environmentHelper
				->expects($this->once())
				->method('getChannel')
				->willReturn('stable');
		$this->config
				->expects($this->any())
				->method('getSystemValue')
				->with('integrity.check.disabled', false)
				->willReturn(false);

		$this->environmentHelper
				->expects($this->any())
				->method('getServerRoot')
				->willReturn(\OC::$SERVERROOT . '/tests/data/integritycheck/appWithInvalidData/');
		$signatureDataFile = '{
    "hashes": {
        "AnotherFile.txt": "tampered",
        "subfolder\/file.txt": "tampered"
    },
    "signature": "eXesvDm3pkek12xSwMG10y9suRES79Nye3jYNe5KYq1tTUPqRRNgxmMGAfcUro0zpLeAr2YgHeSMWtglblGOW7pmwGVPZ0O1Y4r1fE6jnep0kW+35PLIaqCorIOnCAtSzDNKBhwd1ow3zW2wC0DFouuEkIO8u5Fw28g8E8dp8zEk1xMblNPy+xtWkmYHrVJ\/dQgun1bYOF2ZFtAzatwndTI\/bGsy1i3Wsl+x6HyWKQdq8y8VObtOqKDH7uERBEpB9DHVyKflj1v1gQuEH6BhaRdATc7ee0MiQdGblraIySwYRdfo2d8i82OVKrenMB3SLwyCvDPyQ9iKpTOnSF52ZBqaqSXKM2N\/RAkweeBFQQCwcHhqxvB0cfbyHcbkOLeCZe\/tsh68IxwTiYgzvLfl7sOZ5arnZbzrPpZmB+hfV2omkoJ1tDwOWz9hEmLLNtfo2OxyUH1m0+XFaC+Gbn4WkVDgf7YZkwUcG+Qoa3oKDNMss8MEyZxewl2iDGZcf402dlidHRprlfmXbAYuVQ08\/a0HxIKYPGh\/nsMGmwnO15CWtFpAbhUA\/D5oRjsIxnvXaMDg0iAFpdu\/5Ffsj7g3EPdBkiQHNYK7YU1RRx609eH0bZyiIYHdUPw7ikLupvrebZmELqi3mqDFO99u4eISlxFJlUbUND3L4BtmWTWrKwI=",
    "certificate": "-----BEGIN CERTIFICATE-----\r\nMIIEvjCCAqagAwIBAgIUc\/0FxYrsgSs9rDxp03EJmbjN0NwwDQYJKoZIhvcNAQEF\r\nBQAwIzEhMB8GA1UECgwYb3duQ2xvdWQgQ29kZSBTaWduaW5nIENBMB4XDTE1MTEw\r\nMzIxMDMzM1oXDTE2MTEwMzIxMDMzM1owDzENMAsGA1UEAwwEY29yZTCCAiIwDQYJ\r\nKoZIhvcNAQEBBQADggIPADCCAgoCggIBALb6EgHpkAqZbO5vRO8XSh7G7XGWHw5s\r\niOf4RwPXR6SE9bWZEm\/b72SfWk\/\/J6AbrD8WiOzBuT\/ODy6k5T1arEdHO+Pux0W1\r\nMxYJJI4kH74KKgMpC0SB0Rt+8WrMqV1r3hhJ46df6Xr\/xolP3oD+eLbShPcblhdS\r\nVtkZEkoev8Sh6L2wDCeHDyPxzvj1w2dTdGVO9Kztn0xIlyfEBakqvBWtcxyi3Ln0\r\nklnxlMx3tPDUE4kqvpia9qNiB1AN2PV93eNr5\/2riAzIssMFSCarWCx0AKYb54+d\r\nxLpcYFyqPJ0ydBCkF78DD45RCZet6PNYkdzgbqlUWEGGomkuDoJbBg4wzgzO0D77\r\nH87KFhYW8tKFFvF1V3AHl\/sFQ9tDHaxM9Y0pZ2jPp\/ccdiqnmdkBxBDqsiRvHvVB\r\nCn6qpb4vWGFC7vHOBfYspmEL1zLlKXZv3ezMZEZw7O9ZvUP3VO\/wAtd2vUW8UFiq\r\ns2v1QnNLN6jNh51obcwmrBvWhJy9vQIdtIjQbDxqWTHh1zUSrw9wrlklCBZ\/zrM0\r\ni8nfCFwTxWRxp3H9KoECzO\/zS5R5KIS7s3\/wq\/w9T2Ie4rcecgXwDizwnn0C\/aKc\r\nbDIjujpL1s9HO05pcD\/V3wKcPZ1izymBkmMyIbL52iRVN5FTVHeZdXPpFuq+CTQJ\r\nQ238lC+A\/KOVAgMBAAEwDQYJKoZIhvcNAQEFBQADggIBAGoKTnh8RfJV4sQItVC2\r\nAvfJagkrIqZ3iiQTUBQGTKBsTnAqE1H7QgUSV9vSd+8rgvHkyZsRjmtyR1e3A6Ji\r\noNCXUbExC\/0iCPUqdHZIVb+Lc\/vWuv4ByFMybGPydgtLoEUX2ZrKFWmcgZFDUSRd\r\n9Uj26vtUhCC4bU4jgu6hIrR9IuxOBLQUxGTRZyAcXvj7obqRAEZwFAKQgFpfpqTb\r\nH+kjcbZSaAlLVSF7vBc1syyI8RGYbqpwvtREqJtl5IEIwe6huEqJ3zPnlP2th\/55\r\ncf3Fovj6JJgbb9XFxrdnsOsDOu\/tpnaRWlvv5ib4+SzG5wWFT5UUEo4Wg2STQiiX\r\nuVSRQxK1LE1yg84bs3NZk9FSQh4B8vZVuRr5FaJsZZkwlFlhRO\/\/+TJtXRbyNgsf\r\noMRZGi8DLGU2SGEAHcRH\/QZHq\/XDUWVzdxrSBYcy7GSpT7UDVzGv1rEJUrn5veP1\r\n0KmauAqtiIaYRm4f6YBsn0INcZxzIPZ0p8qFtVZBPeHhvQtvOt0iXI\/XUxEWOa2F\r\nK2EqhErgMK\/N07U1JJJay5tYZRtvkGq46oP\/5kQG8hYST0MDK6VihJoPpvCmAm4E\r\npEYKQ96x6A4EH9Y9mZlYozH\/eqmxPbTK8n89\/p7Ydun4rI+B2iiLnY8REWWy6+UQ\r\nV204fGUkJqW5CrKy3P3XvY9X\r\n-----END CERTIFICATE-----"
}';
		$this->fileAccessHelper
				->expects($this->at(0))
				->method('file_get_contents')
				->with(
						\OC::$SERVERROOT . '/tests/data/integritycheck/appWithInvalidData//core/signature.json'
				)
				->willReturn($signatureDataFile);
		$this->fileAccessHelper
				->expects($this->at(1))
				->method('file_get_contents')
				->with(
						\OC::$SERVERROOT . '/tests/data/integritycheck/appWithInvalidData//resources/codesigning/root.crt'
				)
				->willReturn(file_get_contents(__DIR__ .'/../../data/integritycheck/root.crt'));

		$expected = [
			'EXCEPTION' => [
				'class' => 'OC\\IntegrityCheck\\Exceptions\\InvalidSignatureException',
				'message' => 'Signature could not get verified.',
			]
		];
		$this->assertSame($expected, $this->checker->verifyCoreSignature());
	}

	public function testVerifyCoreSignatureWithTamperedFiles() {
		$this->environmentHelper
				->expects($this->once())
				->method('getChannel')
				->willReturn('stable');
		$this->config
				->expects($this->any())
				->method('getSystemValue')
				->with('integrity.check.disabled', false)
				->willReturn(false);

		$this->environmentHelper
				->expects($this->any())
				->method('getServerRoot')
				->willReturn(\OC::$SERVERROOT . '/tests/data/integritycheck/appWithInvalidData/');
		$signatureDataFile = '{
    "hashes": {
        "AnotherFile.txt": "1570ca9420e37629de4328f48c51da29840ddeaa03ae733da4bf1d854b8364f594aac560601270f9e1797ed4cd57c1aea87bf44cf4245295c94f2e935a2f0112",
        "subfolder\/file.txt": "410738545fb623c0a5c8a71f561e48ea69e3ada0981a455e920a5ae9bf17c6831ae654df324f9328ff8453de179276ae51931cca0fa71fe8ccde6c083ca0574b"
    },
    "signature": "dYoohBaWIFR\/To1FXEbMQB5apUhVYlEauBGSPo12nq84wxWkBx2EM3KDRgkB5Sub2tr0CgmAc2EVjPhKIEzAam26cyUb48bJziz1V6wvW7z4GZAfaJpzLkyHdSfV5117VSf5w1rDcAeZDXfGUaaNEJPWytaF4ZIxVge7f3NGshHy4odFVPADy\/u6c43BWvaOtJ4m3aJQbP6sxCO9dxwcm5yJJJR3n36jfh229sdWBxyl8BhwhH1e1DEv78\/aiL6ckKFPVNzx01R6yDFt3TgEMR97YZ\/R6lWiXG+dsJ305jNFlusLu518zBUvl7g5yjzGN778H29b2C8VLZKmi\/h1CH9jGdD72fCqCYdenD2uZKzb6dsUtXtvBmVcVT6BUGz41W1pkkEEB+YJpMrHILIxAiHRGv1+aZa9\/Oz8LWFd+BEUQjC2LJgojPnpzaG\/msw1nBkX16NNVDWWtJ25Bc\/r\/mG46rwjWB\/cmV6Lwt6KODiqlxgrC4lm9ALOCEWw+23OcYhLwNfQTYevXqHqsFfXOkhUnM8z5vDUb\/HBraB1DjFXN8iLK+1YewD4P495e+SRzrR79Oi3F8SEqRIzRLfN2rnW1BTms\/wYsz0p67cup1Slk1XlNmHwbWX25NVd2PPlLOvZRGoqcKFpIjC5few8THiZfyjiNFwt3RM0AFdZcXY=",
    "certificate": "-----BEGIN CERTIFICATE-----\r\nMIIEvjCCAqagAwIBAgIUc\/0FxYrsgSs9rDxp03EJmbjN0NwwDQYJKoZIhvcNAQEF\r\nBQAwIzEhMB8GA1UECgwYb3duQ2xvdWQgQ29kZSBTaWduaW5nIENBMB4XDTE1MTEw\r\nMzIxMDMzM1oXDTE2MTEwMzIxMDMzM1owDzENMAsGA1UEAwwEY29yZTCCAiIwDQYJ\r\nKoZIhvcNAQEBBQADggIPADCCAgoCggIBALb6EgHpkAqZbO5vRO8XSh7G7XGWHw5s\r\niOf4RwPXR6SE9bWZEm\/b72SfWk\/\/J6AbrD8WiOzBuT\/ODy6k5T1arEdHO+Pux0W1\r\nMxYJJI4kH74KKgMpC0SB0Rt+8WrMqV1r3hhJ46df6Xr\/xolP3oD+eLbShPcblhdS\r\nVtkZEkoev8Sh6L2wDCeHDyPxzvj1w2dTdGVO9Kztn0xIlyfEBakqvBWtcxyi3Ln0\r\nklnxlMx3tPDUE4kqvpia9qNiB1AN2PV93eNr5\/2riAzIssMFSCarWCx0AKYb54+d\r\nxLpcYFyqPJ0ydBCkF78DD45RCZet6PNYkdzgbqlUWEGGomkuDoJbBg4wzgzO0D77\r\nH87KFhYW8tKFFvF1V3AHl\/sFQ9tDHaxM9Y0pZ2jPp\/ccdiqnmdkBxBDqsiRvHvVB\r\nCn6qpb4vWGFC7vHOBfYspmEL1zLlKXZv3ezMZEZw7O9ZvUP3VO\/wAtd2vUW8UFiq\r\ns2v1QnNLN6jNh51obcwmrBvWhJy9vQIdtIjQbDxqWTHh1zUSrw9wrlklCBZ\/zrM0\r\ni8nfCFwTxWRxp3H9KoECzO\/zS5R5KIS7s3\/wq\/w9T2Ie4rcecgXwDizwnn0C\/aKc\r\nbDIjujpL1s9HO05pcD\/V3wKcPZ1izymBkmMyIbL52iRVN5FTVHeZdXPpFuq+CTQJ\r\nQ238lC+A\/KOVAgMBAAEwDQYJKoZIhvcNAQEFBQADggIBAGoKTnh8RfJV4sQItVC2\r\nAvfJagkrIqZ3iiQTUBQGTKBsTnAqE1H7QgUSV9vSd+8rgvHkyZsRjmtyR1e3A6Ji\r\noNCXUbExC\/0iCPUqdHZIVb+Lc\/vWuv4ByFMybGPydgtLoEUX2ZrKFWmcgZFDUSRd\r\n9Uj26vtUhCC4bU4jgu6hIrR9IuxOBLQUxGTRZyAcXvj7obqRAEZwFAKQgFpfpqTb\r\nH+kjcbZSaAlLVSF7vBc1syyI8RGYbqpwvtREqJtl5IEIwe6huEqJ3zPnlP2th\/55\r\ncf3Fovj6JJgbb9XFxrdnsOsDOu\/tpnaRWlvv5ib4+SzG5wWFT5UUEo4Wg2STQiiX\r\nuVSRQxK1LE1yg84bs3NZk9FSQh4B8vZVuRr5FaJsZZkwlFlhRO\/\/+TJtXRbyNgsf\r\noMRZGi8DLGU2SGEAHcRH\/QZHq\/XDUWVzdxrSBYcy7GSpT7UDVzGv1rEJUrn5veP1\r\n0KmauAqtiIaYRm4f6YBsn0INcZxzIPZ0p8qFtVZBPeHhvQtvOt0iXI\/XUxEWOa2F\r\nK2EqhErgMK\/N07U1JJJay5tYZRtvkGq46oP\/5kQG8hYST0MDK6VihJoPpvCmAm4E\r\npEYKQ96x6A4EH9Y9mZlYozH\/eqmxPbTK8n89\/p7Ydun4rI+B2iiLnY8REWWy6+UQ\r\nV204fGUkJqW5CrKy3P3XvY9X\r\n-----END CERTIFICATE-----"
}';
		$this->fileAccessHelper
				->expects($this->at(0))
				->method('file_get_contents')
				->with(
						\OC::$SERVERROOT . '/tests/data/integritycheck/appWithInvalidData//core/signature.json'
				)
				->willReturn($signatureDataFile);
		$this->fileAccessHelper
				->expects($this->at(1))
				->method('file_get_contents')
				->with(
						\OC::$SERVERROOT . '/tests/data/integritycheck/appWithInvalidData//resources/codesigning/root.crt'
				)
				->willReturn(file_get_contents(__DIR__ .'/../../data/integritycheck/root.crt'));

		$expected = [
			'INVALID_HASH' => [
				'AnotherFile.txt' => [
					'expected' => '1570ca9420e37629de4328f48c51da29840ddeaa03ae733da4bf1d854b8364f594aac560601270f9e1797ed4cd57c1aea87bf44cf4245295c94f2e935a2f0112',
					'current' => '7322348ba269c6d5522efe02f424fa3a0da319a7cd9c33142a5afe32a2d9af2da3a411f086fcfc96ff4301ea566f481dba0960c2abeef3594c4d930462f6584c',
				],
			],
			'FILE_MISSING' => [
				'subfolder/file.txt' => [
					'expected' => '410738545fb623c0a5c8a71f561e48ea69e3ada0981a455e920a5ae9bf17c6831ae654df324f9328ff8453de179276ae51931cca0fa71fe8ccde6c083ca0574b',
					'current' => '',
				],
			],
			'EXTRA_FILE' => [
				'UnecessaryFile' => [
					'expected' => '',
					'current' => 'cf83e1357eefb8bdf1542850d66d8007d620e4050b5715dc83f4a921d36ce9ce47d0d13c5d85f2b0ff8318d2877eec2f63b931bd47417a81a538327af927da3e',
				],
			],

		];
		$this->assertSame($expected, $this->checker->verifyCoreSignature());
	}

	public function testVerifyCoreWithInvalidCertificate() {
		$this->environmentHelper
				->expects($this->once())
				->method('getChannel')
				->willReturn('stable');
		$this->config
				->expects($this->any())
				->method('getSystemValue')
				->with('integrity.check.disabled', false)
				->willReturn(false);

		$this->environmentHelper
				->expects($this->any())
				->method('getServerRoot')
				->willReturn(\OC::$SERVERROOT . '/tests/data/integritycheck/app/');
		$signatureDataFile = '{
    "hashes": {
        "AnotherFile.txt": "1570ca9420e37629de4328f48c51da29840ddeaa03ae733da4bf1d854b8364f594aac560601270f9e1797ed4cd57c1aea87bf44cf4245295c94f2e935a2f0112",
        "subfolder\/file.txt": "410738545fb623c0a5c8a71f561e48ea69e3ada0981a455e920a5ae9bf17c6831ae654df324f9328ff8453de179276ae51931cca0fa71fe8ccde6c083ca0574b"
    },
    "signature": "eXesvDm3pkek12xSwMG10y9suRES79Nye3jYNe5KYq1tTUPqRRNgxmMGAfcUro0zpLeAr2YgHeSMWtglblGOW7pmwGVPZ0O1Y4r1fE6jnep0kW+35PLIaqCorIOnCAtSzDNKBhwd1ow3zW2wC0DFouuEkIO8u5Fw28g8E8dp8zEk1xMblNPy+xtWkmYHrVJ\/dQgun1bYOF2ZFtAzatwndTI\/bGsy1i3Wsl+x6HyWKQdq8y8VObtOqKDH7uERBEpB9DHVyKflj1v1gQuEH6BhaRdATc7ee0MiQdGblraIySwYRdfo2d8i82OVKrenMB3SLwyCvDPyQ9iKpTOnSF52ZBqaqSXKM2N\/RAkweeBFQQCwcHhqxvB0cfbyHcbkOLeCZe\/tsh68IxwTiYgzvLfl7sOZ5arnZbzrPpZmB+hfV2omkoJ1tDwOWz9hEmLLNtfo2OxyUH1m0+XFaC+Gbn4WkVDgf7YZkwUcG+Qoa3oKDNMss8MEyZxewl2iDGZcf402dlidHRprlfmXbAYuVQ08\/a0HxIKYPGh\/nsMGmwnO15CWtFpAbhUA\/D5oRjsIxnvXaMDg0iAFpdu\/5Ffsj7g3EPdBkiQHNYK7YU1RRx609eH0bZyiIYHdUPw7ikLupvrebZmELqi3mqDFO99u4eISlxFJlUbUND3L4BtmWTWrKwI=",
    "certificate": "-----BEGIN CERTIFICATE-----\r\nMIIEvjCCAqagAwIBAgIUPYoweUxCPqbDW4ntuh7QvgyqSrgwDQYJKoZIhvcNAQEF\r\nBQAwIzEhMB8GA1UECgwYb3duQ2xvdWQgQ29kZSBTaWduaW5nIENBMB4XDTE1MTEw\r\nMzIyNDIwNloXDTE2MTEwMzIyNDIwNlowDzENMAsGA1UEAwwEY29yZTCCAiIwDQYJ\r\nKoZIhvcNAQEBBQADggIPADCCAgoCggIBAJui3nDbjOIjxNnthdBZplphujsN6u8K\r\nQ\/62zAuSwzXVp0+3IMgM\/2sepklVE8YfCyVJ5+SUJqnqHoUWVRVfs8jL0wW6nrHM\r\n\/lsscAguWCee4iAdNOqI9kq4+DUau8J45e62XA9mrAo\/8\/NKzFE2y2WduDoQZcm+\r\n8+dwcUUHXw2jl8dfrmvEMYSqTNDdb4rGmQpeV+dr9BLqr+x03U1Q08qCG9j7mSOz\r\ncvJENjOvC5uzAh5LCuCgxqG4o+mPzB0FtNnwoRRu6IsF3Y3KacRqPc30fB\/iXDn5\r\nBPr14uNxTTYWoZJ1F0tZrLzRbXdjJJOC+dnQurTtXWZ8WjPB1BWQYK7fW6t82mkN\r\n2Qe2xen99gs9nX5yY\/sHM3TKSJdM7AVCEv\/emW3gNjkvWTtRlN\/Nc7X2ckNwXcvo\r\n0yi3fSPjzXpDgLbhp1FzrMlHDn1VzmRT3r8wLByWa\/hsxrJDsBzwunMJYhXhmeKb\r\n3wX0tN\/EUJTWBntpwVOIGnRPD51oBoQUOMaEAq\/kz8PgN181bWZkJbRuf+FWkijQ\r\no+HR2lVF1jWXXst5Uc+s9HN81Uly7X4O9MMg0QxT4+wymtGDs6AOkwMi9rgBTrRB\r\n3tLU3XL2UIwRXgmd8cPtTu\/I6Bm7LdyaYtZ3yJTxRewq3nZdWypqBhD8uhpIYVkf\r\no4bxmGkVAQVTAgMBAAEwDQYJKoZIhvcNAQEFBQADggIBAKKAX5EHgU1grODnJ0of\r\nspFpgB1K67YvclNUyuU6NQ6zBJx1\/w1RnM7uxLcxiiWj1BbUhwZQ0ojmEHeUyi6O\r\nGrDVajwhTccDMmja3u5adhEncx65\/H+lD85IPRRkS2qBDssMDdJHhZ0uI+40nI7M\r\nMq1kFjl+6wiuqZXqps66DuLbk45g\/ZlrFIrIo3Ix5vj0OVqwT+gO4LYirJK6KgVS\r\nUttbcEsc\/yKU9ThnM8\/n4m2jstZXfzKPgOsJrQcZrFOtpj+CWmBzVElBSPlDT3Nh\r\nHSgOeTFJ8bQBxj2iG5dLA+JZJQKxyJ1gy2ZtxIJ2GyvLtSe8NUSqvfPWOaAKEUV2\r\ngniytnEFLr+PcD+9EGux6jZNuj6HmtWVThTfD5VGFmtlVU2z71ZRYY0kn6J3mmFc\r\nS2ecEcCUwqG5YNLncEUCyZhC2klWql2SHyGctCEyWWY7ikIDjVzYt2EbcFvLNBnP\r\ntybN1TYHRRZxlug00CCoOE9EZfk46FkZpDvU6KmqJRofkNZ5sj+SffyGcwYwNrDH\r\nKqe8m+9lHf3CRTIDeMu8r2xl1I6M6ZZfjabbmVP9Jd6WN4s6f1FlXDWzhlT1N0Qw\r\nGzJj6xB+SPtS3UV05tBlvbfA4e06D5G9uD7Q8ONcINtMS0xsSJ2oo82AqlpvlF\/q\r\noj7YKHsaTVGA+FxBktZHfoxD\r\n-----END CERTIFICATE-----"
}';
		$this->fileAccessHelper
				->expects($this->at(0))
				->method('file_get_contents')
				->with(
						\OC::$SERVERROOT . '/tests/data/integritycheck/app//core/signature.json'
				)
				->willReturn($signatureDataFile);
		$this->fileAccessHelper
				->expects($this->at(1))
				->method('file_get_contents')
				->with(
						\OC::$SERVERROOT . '/tests/data/integritycheck/app//resources/codesigning/root.crt'
				)
				->willReturn(file_get_contents(__DIR__ .'/../../data/integritycheck/root.crt'));

		$expected = [
			'EXCEPTION' => [
				'class' => 'OC\\IntegrityCheck\\Exceptions\\InvalidSignatureException',
				'message' => 'Certificate is not valid.',
			]
		];
		$this->assertSame($expected, $this->checker->verifyCoreSignature());
	}

	public function testVerifyCoreWithDifferentScope() {
		$this->environmentHelper
				->expects($this->once())
				->method('getChannel')
				->willReturn('stable');
		$this->config
				->expects($this->any())
				->method('getSystemValue')
				->with('integrity.check.disabled', false)
				->willReturn(false);

		$this->environmentHelper
				->expects($this->any())
				->method('getServerRoot')
				->willReturn(\OC::$SERVERROOT . '/tests/data/integritycheck/app/');
		$signatureDataFile = '{
    "hashes": {
        "AnotherFile.txt": "1570ca9420e37629de4328f48c51da29840ddeaa03ae733da4bf1d854b8364f594aac560601270f9e1797ed4cd57c1aea87bf44cf4245295c94f2e935a2f0112",
        "subfolder\/file.txt": "410738545fb623c0a5c8a71f561e48ea69e3ada0981a455e920a5ae9bf17c6831ae654df324f9328ff8453de179276ae51931cca0fa71fe8ccde6c083ca0574b"
    },
    "signature": "EL49UaSeyMAqyMtqId+tgOhhwgOevPZsRLX4j2blnybAB6fN07z0936JqZV7+eMPiE30Idx+UCY6rCFN531Kqe9vAOCdgtHUSOjjKyKc+lvULESlMb6YQcrZrvDlEMMjzjH49ewG7Ai8sNN6HrRUd9U8ws+ewSkW2DOOBItj\/21RBnkrSt+2AtGXGigEvuTm57HrCYDj8\/lSkumC2GVkjLUHeLOKYo4PRNOr6yP5mED5v7zo66AWvXl2fKv54InZcdxsAk35lyK9DGZbk\/027ZRd0AOHT3LImRLvQ+8EAg3XLlRUy0hOFGgPC+jYonMzgYvsAXAXi2j8LnLJlsLwpFwu1k1B+kZVPMumKZvP9OvJb70EirecXmz62V+Jiyuaq7ne4y7Kp5gKZT\/T8SeZ0lFtCmPfYyzBB0y8s5ldmTTmdVYHs54t\/OCCW82HzQZxnFNPzDTRa8HglsaMKrqPtW59+R4UvRKSWhB8M\/Ah57qgzycvPV4KMz\/FbD4l\/\/9chRKSlCfc2k3b8ZSHNmi+EzCKgJjWIoKdgN1yax94puU8jfn8UW+G7H9Y1Jsf\/jox6QLyYEgtV1vOHY2xLT7fVs2vhyvkN2MNjJnmQ70gFG5Qz2lBz5wi6ZpB+tOfCcpbLxWAkoWoIrmC\/Ilqh7mfmRZ43g5upjkepHNd93ONuY8=",
    "certificate": "-----BEGIN CERTIFICATE-----\r\nMIIEwTCCAqmgAwIBAgIUWv0iujufs5lUr0svCf\/qTQvoyKAwDQYJKoZIhvcNAQEF\r\nBQAwIzEhMB8GA1UECgwYb3duQ2xvdWQgQ29kZSBTaWduaW5nIENBMB4XDTE1MTEw\r\nMzIyNDk1M1oXDTE2MTEwMzIyNDk1M1owEjEQMA4GA1UEAwwHU29tZUFwcDCCAiIw\r\nDQYJKoZIhvcNAQEBBQADggIPADCCAgoCggIBAK8q0x62agGSRBqeWsaeEwFfepMk\r\nF8cAobMMi50qHCv9IrOn\/ZH9l52xBrbIkErVmRjmly0d4JhD8Ymhidsh9ONKYl\/j\r\n+ishsZDM8eNNdp3Ew+fEYVvY1W7mR1qU24NWj0bzVsClI7hvPVIuw7AjfBDq1C5+\r\nA+ZSLSXYvOK2cEWjdxQfuNZwEZSjmA63DUllBIrm35IaTvfuyhU6BW9yHZxmb8+M\r\nw0xDv30D5UkE\/2N7Pa\/HQJLxCR+3zKibRK3nUyRDLSXxMkU9PnFNaPNX59VPgyj4\r\nGB1CFSToldJVPF4pzh7p36uGXZVxs8m3LFD4Ol8mhi7jkxDZjqFN46gzR0r23Py6\r\ndol9vfawGIoUwp9LvL0S7MvdRY0oazLXwClLP4OQ17zpSMAiCj7fgNT661JamPGj\r\nt5O7Zn2wA7I4ddDS\/HDTWCu98Zwc9fHIpsJPgCZ9awoqxi4Mnf7Pk9g5nnXhszGC\r\ncxxIASQKM+GhdzoRxKknax2RzUCwCzcPRtCj8AQT\/x\/mqN3PfRmlnFBNACUw9bpZ\r\nSOoNq2pCF9igftDWpSIXQ38pVpKLWowjjg3DVRmVKBgivHnUnVLyzYBahHPj0vaz\r\ntFtUFRaqXDnt+4qyUGyrT5h5pjZaTcHIcSB4PiarYwdVvgslgwnQzOUcGAzRWBD4\r\n6jV2brP5vFY3g6iPAgMBAAEwDQYJKoZIhvcNAQEFBQADggIBACTY3CCHC+Z28gCf\r\nFWGKQ3wAKs+k4+0yoti0qm2EKX7rSGQ0PHSas6uW79WstC4Rj+DYkDtIhGMSg8FS\r\nHVGZHGBCc0HwdX+BOAt3zi4p7Sf3oQef70\/4imPoKxbAVCpd\/cveVcFyDC19j1yB\r\nBapwu87oh+muoeaZxOlqQI4UxjBlR\/uRSMhOn2UGauIr3dWJgAF4pGt7TtIzt+1v\r\n0uA6FtN1Y4R5O8AaJPh1bIG0CVvFBE58esGzjEYLhOydgKFnEP94kVPgJD5ds9C3\r\npPhEpo1dRpiXaF7WGIV1X6DI\/ipWvfrF7CEy6I\/kP1InY\/vMDjQjeDnJ\/VrXIWXO\r\nyZvHXVaN\/m+1RlETsH7YO\/QmxRue9ZHN3gvvWtmpCeA95sfpepOk7UcHxHZYyQbF\r\n49\/au8j+5tsr4A83xzsT1JbcKRxkAaQ7WDJpOnE5O1+H0fB+BaLakTg6XX9d4Fo7\r\n7Gin7hVWX7pL+JIyxMzME3LhfI61+CRcqZQIrpyaafUziPQbWIPfEs7h8tCOWyvW\r\nUO8ZLervYCB3j44ivkrxPlcBklDCqqKKBzDP9dYOtS\/P4RB1NkHA9+NTvmBpTonS\r\nSFXdg9fFMD7VfjDE3Vnk+8DWkVH5wBYowTAD7w9Wuzr7DumiAULexnP\/Y7xwxLv7\r\n4B+pXTAcRK0zECDEaX3npS8xWzrB\r\n-----END CERTIFICATE-----"
}';
		$this->fileAccessHelper
				->expects($this->at(0))
				->method('file_get_contents')
				->with(
						\OC::$SERVERROOT . '/tests/data/integritycheck/app//core/signature.json'
				)
				->willReturn($signatureDataFile);
		$this->fileAccessHelper
				->expects($this->at(1))
				->method('file_get_contents')
				->with(
						\OC::$SERVERROOT . '/tests/data/integritycheck/app//resources/codesigning/root.crt'
				)
				->willReturn(file_get_contents(__DIR__ .'/../../data/integritycheck/root.crt'));

		$expected = [
			'EXCEPTION' => [
				'class' => 'OC\\IntegrityCheck\\Exceptions\\InvalidSignatureException',
				'message' => 'Certificate is not valid for required scope. (Requested: core, current: CN=SomeApp)',
			]
		];
		$this->assertSame($expected, $this->checker->verifyCoreSignature());
	}

	public function testRunInstanceVerification() {
		$this->checker = $this->getMockBuilder('\OC\IntegrityCheck\Checker')
			->setConstructorArgs([
				$this->environmentHelper,
				$this->fileAccessHelper,
				$this->appLocator,
				$this->config,
				$this->cacheFactory,
				$this->appManager,
				$this->mimeTypeDetector,
			])
			->setMethods([
				'verifyCoreSignature',
				'verifyAppSignature',
			])
			->getMock();

		$this->checker
			->expects($this->at(0))
			->method('verifyCoreSignature');
		$this->appLocator
			->expects($this->at(0))
			->method('getAllApps')
			->willReturn([
				'files',
				'calendar',
				'contacts',
				'dav',
			]);
		$this->appManager
			->expects($this->at(0))
			->method('isShipped')
			->with('files')
			->willReturn(true);
		$this->checker
			->expects($this->at(1))
			->method('verifyAppSignature')
			->with('files');
		$this->appManager
			->expects($this->at(1))
			->method('isShipped')
			->with('calendar')
			->willReturn(false);
		$this->appLocator
			->expects($this->at(1))
			->method('getAppPath')
			->with('calendar')
			->willReturn('/apps/calendar');
		$this->fileAccessHelper
			->expects($this->at(0))
			->method('file_exists')
			->with('/apps/calendar/appinfo/signature.json')
			->willReturn(true);
		$this->checker
			->expects($this->at(2))
			->method('verifyAppSignature')
			->with('calendar');
		$this->appManager
				->expects($this->at(2))
				->method('isShipped')
				->with('contacts')
				->willReturn(false);
		$this->appLocator
				->expects($this->at(2))
				->method('getAppPath')
				->with('contacts')
				->willReturn('/apps/contacts');
		$this->fileAccessHelper
				->expects($this->at(1))
				->method('file_exists')
				->with('/apps/contacts/appinfo/signature.json')
				->willReturn(false);
		$this->appManager
			->expects($this->at(3))
			->method('isShipped')
			->with('dav')
			->willReturn(true);
		$this->checker
			->expects($this->at(3))
			->method('verifyAppSignature')
			->with('dav');
		$this->config
			->expects($this->once())
			->method('deleteAppValue')
			->with('core', 'oc.integritycheck.checker');

		$this->checker->runInstanceVerification();
	}

	public function testVerifyAppSignatureWithoutSignatureDataAndCodeCheckerDisabled() {
		$this->environmentHelper
				->expects($this->once())
				->method('getChannel')
				->willReturn('stable');
		$this->config
				->expects($this->any())
				->method('getSystemValue')
				->with('integrity.check.disabled', false)
				->willReturn(true);

		$expected = [];
		$this->assertSame($expected, $this->checker->verifyAppSignature('SomeApp'));
	}

	/**
	 * @return array
	 */
	public function channelDataProvider() {
		return [
			['stable', true],
			['git', false],
		];
	}

	/**
	 * @param string $channel
	 * @param bool $isCodeSigningEnforced
	 * @dataProvider channelDataProvider
	 */
	public function testIsCodeCheckEnforced($channel, $isCodeSigningEnforced) {
		$this->environmentHelper
			->expects($this->once())
			->method('getChannel')
			->willReturn($channel);
		$this->config
			->expects($this->any())
			->method('getSystemValue')
			->with('integrity.check.disabled', false)
			->willReturn(false);

		$this->assertSame($isCodeSigningEnforced, $this->checker->isCodeCheckEnforced());
	}

	/**
	 * @param string $channel
	 * @dataProvider channelDataProvider
	 */
	public function testIsCodeCheckEnforcedWithDisabledConfigSwitch($channel) {
		$this->environmentHelper
				->expects($this->once())
				->method('getChannel')
				->willReturn($channel);
		$this->config
				->expects($this->any())
				->method('getSystemValue')
				->with('integrity.check.disabled', false)
				->willReturn(true);

		$this->assertFalse(self::invokePrivate($this->checker, 'isCodeCheckEnforced'));
	}
}
