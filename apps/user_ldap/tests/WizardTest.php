<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
 * @author Viktor Szépe <viktor@szepe.net>
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

namespace OCA\User_LDAP\Tests;

use OCA\User_LDAP\Access;
use OCA\User_LDAP\Configuration;
use OCA\User_LDAP\ILDAPWrapper;
use OCA\User_LDAP\Wizard;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

/**
 * Class Test_Wizard
 *
 * @group DB
 *
 * @package OCA\User_LDAP\Tests
 */
class WizardTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		//we need to make sure the consts are defined, otherwise tests will fail
		//on systems without php5_ldap
		$ldapConsts = ['LDAP_OPT_PROTOCOL_VERSION',
			'LDAP_OPT_REFERRALS', 'LDAP_OPT_NETWORK_TIMEOUT'];
		foreach ($ldapConsts as $const) {
			if (!defined($const)) {
				define($const, 42);
			}
		}
	}

	private function getWizardAndMocks() {
		static $confMethods;
		static $connMethods;
		static $accMethods;

		if (is_null($confMethods)) {
			$confMethods = get_class_methods('\OCA\User_LDAP\Configuration');
			$connMethods = get_class_methods('\OCA\User_LDAP\Connection');
			$accMethods = get_class_methods('\OCA\User_LDAP\Access');
		}
		/** @var ILDAPWrapper|\PHPUnit\Framework\MockObject\MockObject $lw */
		$lw = $this->createMock(ILDAPWrapper::class);

		/** @var Configuration|\PHPUnit\Framework\MockObject\MockObject $conf */
		$conf = $this->getMockBuilder(Configuration::class)
			->setMethods($confMethods)
			->setConstructorArgs([$lw, null, null])
			->getMock();

		/** @var Access|\PHPUnit\Framework\MockObject\MockObject $access */
		$access = $this->createMock(Access::class);

		return [new Wizard($conf, $lw, $access), $conf, $lw, $access];
	}

	private function prepareLdapWrapperForConnections(MockObject &$ldap) {
		$ldap->expects($this->once())
			->method('connect')
			//dummy value, usually invalid
			->willReturn(true);

		$ldap->expects($this->exactly(3))
			->method('setOption')
			->willReturn(true);

		$ldap->expects($this->once())
			->method('bind')
			->willReturn(true);
	}

	public function testCumulativeSearchOnAttributeLimited() {
		list($wizard, $configuration, $ldap) = $this->getWizardAndMocks();

		$configuration->expects($this->any())
			->method('__get')
			->willReturnCallback(function ($name) {
				if ($name === 'ldapBase') {
					return ['base'];
				}
				return null;
			});

		$this->prepareLdapWrapperForConnections($ldap);

		$ldap->expects($this->any())
			->method('isResource')
			->willReturn(true);

		$ldap->expects($this->exactly(2))
			->method('search')
			//dummy value, usually invalid
			->willReturn(true);

		$ldap->expects($this->exactly(2))
			->method('countEntries')
			//an is_resource check will follow, so we need to return a dummy resource
			->willReturn(23);

		//5 DNs per filter means 2x firstEntry and 8x nextEntry
		$ldap->expects($this->exactly(2))
			->method('firstEntry')
			//dummy value, usually invalid
			->willReturn(true);

		$ldap->expects($this->exactly(8))
			->method('nextEntry')
			//dummy value, usually invalid
			->willReturn(true);

		$ldap->expects($this->exactly(10))
			->method('getAttributes')
			//dummy value, usually invalid
			->willReturn(['cn' => ['foo'], 'count' => 1]);

		global $uidnumber;
		$uidnumber = 1;
		$ldap->expects($this->exactly(10))
			->method('getDN')
			//dummy value, usually invalid
			->willReturnCallback(function ($a, $b) {
				global $uidnumber;
				return $uidnumber++;
			});

		// The following expectations are the real test
		$filters = ['f1', 'f2', '*'];
		$wizard->cumulativeSearchOnAttribute($filters, 'cn', 5);
		unset($uidnumber);
	}

	public function testCumulativeSearchOnAttributeUnlimited() {
		list($wizard, $configuration, $ldap) = $this->getWizardAndMocks();

		$configuration->expects($this->any())
			->method('__get')
			->willReturnCallback(function ($name) {
				if ($name === 'ldapBase') {
					return ['base'];
				}
				return null;
			});

		$this->prepareLdapWrapperForConnections($ldap);

		$ldap->expects($this->any())
			->method('isResource')
			->willReturnCallback(function ($r) {
				if ($r === true) {
					return true;
				}
				if ($r % 24 === 0) {
					global $uidnumber;
					$uidnumber++;
					return false;
				}
				return true;
			});

		$ldap->expects($this->exactly(2))
			->method('search')
			//dummy value, usually invalid
			->willReturn(true);

		$ldap->expects($this->exactly(2))
			->method('countEntries')
			//an is_resource check will follow, so we need to return a dummy resource
			->willReturn(23);

		//5 DNs per filter means 2x firstEntry and 8x nextEntry
		$ldap->expects($this->exactly(2))
			->method('firstEntry')
			//dummy value, usually invalid
			->willReturnCallback(function ($r) {
				global $uidnumber;
				return $uidnumber;
			});

		$ldap->expects($this->exactly(46))
			->method('nextEntry')
			//dummy value, usually invalid
			->willReturnCallback(function ($r) {
				global $uidnumber;
				return $uidnumber;
			});

		$ldap->expects($this->exactly(46))
			->method('getAttributes')
			//dummy value, usually invalid
			->willReturn(['cn' => ['foo'], 'count' => 1]);

		global $uidnumber;
		$uidnumber = 1;
		$ldap->expects($this->exactly(46))
			->method('getDN')
			//dummy value, usually invalid
			->willReturnCallback(function ($a, $b) {
				global $uidnumber;
				return $uidnumber++;
			});

		// The following expectations are the real test
		$filters = ['f1', 'f2', '*'];
		$wizard->cumulativeSearchOnAttribute($filters, 'cn', 0);
		unset($uidnumber);
	}

	public function testDetectEmailAttributeAlreadySet() {
		list($wizard, $configuration, $ldap, $access)
			= $this->getWizardAndMocks();

		$configuration->expects($this->any())
			->method('__get')
			->willReturnCallback(function ($name) {
				if ($name === 'ldapEmailAttribute') {
					return 'myEmailAttribute';
				} else {
					//for requirement checks
					return 'let me pass';
				}
			});

		$access->expects($this->once())
			->method('countUsers')
			->willReturn(42);

		$wizard->detectEmailAttribute();
	}

	public function testDetectEmailAttributeOverrideSet() {
		list($wizard, $configuration, $ldap, $access)
			= $this->getWizardAndMocks();

		$configuration->expects($this->any())
			->method('__get')
			->willReturnCallback(function ($name) {
				if ($name === 'ldapEmailAttribute') {
					return 'myEmailAttribute';
				} else {
					//for requirement checks
					return 'let me pass';
				}
			});

		$access->expects($this->exactly(3))
			->method('combineFilterWithAnd')
			->willReturnCallback(function ($filterParts) {
				return str_replace('=*', '', array_pop($filterParts));
			});

		$access->expects($this->exactly(3))
			->method('countUsers')
			->willReturnCallback(function ($filter) {
				if ($filter === 'myEmailAttribute') {
					return 0;
				} elseif ($filter === 'mail') {
					return 3;
				} elseif ($filter === 'mailPrimaryAddress') {
					return 17;
				}
				throw new \Exception('Untested filter: ' . $filter);
			});

		$result = $wizard->detectEmailAttribute()->getResultArray();
		$this->assertSame('mailPrimaryAddress',
			$result['changes']['ldap_email_attr']);
	}

	public function testDetectEmailAttributeFind() {
		list($wizard, $configuration, $ldap, $access)
			= $this->getWizardAndMocks();

		$configuration->expects($this->any())
			->method('__get')
			->willReturnCallback(function ($name) {
				if ($name === 'ldapEmailAttribute') {
					return '';
				} else {
					//for requirement checks
					return 'let me pass';
				}
			});

		$access->expects($this->exactly(2))
			->method('combineFilterWithAnd')
			->willReturnCallback(function ($filterParts) {
				return str_replace('=*', '', array_pop($filterParts));
			});

		$access->expects($this->exactly(2))
			->method('countUsers')
			->willReturnCallback(function ($filter) {
				if ($filter === 'myEmailAttribute') {
					return 0;
				} elseif ($filter === 'mail') {
					return 3;
				} elseif ($filter === 'mailPrimaryAddress') {
					return 17;
				}
				throw new \Exception('Untested filter: ' . $filter);
			});

		$result = $wizard->detectEmailAttribute()->getResultArray();
		$this->assertSame('mailPrimaryAddress',
			$result['changes']['ldap_email_attr']);
	}

	public function testDetectEmailAttributeFindNothing() {
		list($wizard, $configuration, $ldap, $access)
			= $this->getWizardAndMocks();

		$configuration->expects($this->any())
			->method('__get')
			->willReturnCallback(function ($name) {
				if ($name === 'ldapEmailAttribute') {
					return 'myEmailAttribute';
				} else {
					//for requirement checks
					return 'let me pass';
				}
			});

		$access->expects($this->exactly(3))
			->method('combineFilterWithAnd')
			->willReturnCallback(function ($filterParts) {
				return str_replace('=*', '', array_pop($filterParts));
			});

		$access->expects($this->exactly(3))
			->method('countUsers')
			->willReturnCallback(function ($filter) {
				if ($filter === 'myEmailAttribute') {
					return 0;
				} elseif ($filter === 'mail') {
					return 0;
				} elseif ($filter === 'mailPrimaryAddress') {
					return 0;
				}
				throw new \Exception('Untested filter: ' . $filter);
			});

		$result = $wizard->detectEmailAttribute();
		$this->assertSame(false, $result->hasChanges());
	}

	public function testCumulativeSearchOnAttributeSkipReadDN() {
		// tests that there is no infinite loop, when skipping already processed
		// DNs (they can be returned multiple times for multiple filters )
		list($wizard, $configuration, $ldap) = $this->getWizardAndMocks();

		$configuration->expects($this->any())
			->method('__get')
			->willReturnCallback(function ($name) {
				if ($name === 'ldapBase') {
					return ['base'];
				}
				return null;
			});

		$this->prepareLdapWrapperForConnections($ldap);

		$ldap->expects($this->any())
			->method('isResource')
			->willReturnCallback(function ($res) {
				return (bool)$res;
			});

		$ldap->expects($this->any())
			->method('search')
			//dummy value, usually invalid
			->willReturn(true);

		$ldap->expects($this->any())
			->method('countEntries')
			//an is_resource check will follow, so we need to return a dummy resource
			->willReturn(7);

		//5 DNs per filter means 2x firstEntry and 8x nextEntry
		$ldap->expects($this->any())
			->method('firstEntry')
			//dummy value, usually invalid
			->willReturn(1);

		global $mark;
		$mark = false;
		// entries return order: 1, 2, 3, 4, 4, 5, 6
		$ldap->expects($this->any())
			->method('nextEntry')
			//dummy value, usually invalid
			->willReturnCallback(function ($a, $prev) {
				$current = $prev + 1;
				if ($current === 7) {
					return false;
				}
				global $mark;
				if ($prev === 4 && !$mark) {
					$mark = true;
					return 4;
				}
				return $current;
			});

		$ldap->expects($this->any())
			->method('getAttributes')
			//dummy value, usually invalid
			->willReturnCallback(function ($a, $entry) {
				return ['cn' => [$entry], 'count' => 1];
			});

		$ldap->expects($this->any())
			->method('getDN')
			//dummy value, usually invalid
			->willReturnCallback(function ($a, $b) {
				return $b;
			});

		// The following expectations are the real test
		$filters = ['f1', 'f2', '*'];
		$resultArray = $wizard->cumulativeSearchOnAttribute($filters, 'cn', 0);
		$this->assertSame(6, count($resultArray));
		unset($mark);
	}
}
