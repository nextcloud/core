<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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
if(php_sapi_name() !== 'cli') {
	print('Only via CLI, please.');
	exit(1);
}

include __DIR__ . '/config.php';

$cr = ldap_connect($host, $port);
ldap_set_option($cr, LDAP_OPT_PROTOCOL_VERSION, 3);
$ok = ldap_bind($cr, $adn, $apwd);

if (!$ok) {
	die(ldap_error($cr));
}

$ouName = 'Users';
$ouDN = 'ou=' . $ouName . ',' . $bdn;

$users = ['robot'];

foreach ($users as $uid) {
	$newDN = 'uid=' . $uid . ',' . $ouDN;
	$fn = ucfirst($uid);
	$sn = ucfirst(str_shuffle($uid));   // not so explicit but it's OK.

	$entry = [];
	$entry['cn'] = ucfirst($uid);
	$entry['objectclass'][] = 'inetOrgPerson';
	$entry['objectclass'][] = 'person';
	$entry['sn'] = $sn;
	$entry['userPassword'] = $uid;

	$ok = ldap_add($cr, $newDN, $entry);
	if ($ok) {
		echo('created user ' . ': ' . $entry['cn'] . PHP_EOL);
	} else {
		die(ldap_error($cr));
	}
}
