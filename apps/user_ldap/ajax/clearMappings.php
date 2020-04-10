<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christopher Schäpers <kondou@ts.unde.re>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

use OCA\User_LDAP\Mapping\UserMapping;
use OCA\User_LDAP\Mapping\GroupMapping;

// Check user and app status
\OC_JSON::checkAdminUser();
\OC_JSON::checkAppEnabled('user_ldap');
\OC_JSON::callCheck();

$subject = (string)$_POST['ldap_clear_mapping'];
$mapping = null;
try {
	if($subject === 'user') {
		$mapping = new UserMapping(\OC::$server->getDatabaseConnection());
		$result = $mapping->clearCb(
			function ($uid) {
				\OC::$server->getUserManager()->emit('\OC\User', 'preUnassignedUserId', [$uid]);
			},
			function ($uid) {
				\OC::$server->getUserManager()->emit('\OC\User', 'postUnassignedUserId', [$uid]);
			}
		);
	} else if($subject === 'group') {
		$mapping = new GroupMapping(\OC::$server->getDatabaseConnection());
		$result = $mapping->clear();
	}

	if($mapping === null || !$result) {
		$l = \OC::$server->getL10N('user_ldap');
		throw new \Exception($l->t('Failed to clear the mappings.'));
	}
	\OC_JSON::success();
} catch (\Exception $e) {
	\OC_JSON::error(['message' => $e->getMessage()]);
}
