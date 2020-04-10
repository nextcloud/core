<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Juan Pablo Villafáñez <jvillafanez@solidgear.es>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

// Check user and app status
\OC_JSON::checkAdminUser();
\OC_JSON::checkAppEnabled('user_ldap');
\OC_JSON::callCheck();

$prefix = (string)$_POST['ldap_serverconfig_chooser'];
$ldapWrapper = new OCA\User_LDAP\LDAP();
$connection = new \OCA\User_LDAP\Connection($ldapWrapper, $prefix);
$configuration = $connection->getConfiguration();
if (isset($configuration['ldap_agent_password']) && $configuration['ldap_agent_password'] !== '') {
	// hide password
	$configuration['ldap_agent_password'] = '**PASSWORD SET**';
}
\OC_JSON::success(['configuration' => $configuration]);
