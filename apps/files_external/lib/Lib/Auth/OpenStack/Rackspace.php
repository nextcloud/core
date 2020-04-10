<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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

namespace OCA\Files_External\Lib\Auth\OpenStack;

use OCA\Files_External\Lib\Auth\AuthMechanism;
use OCA\Files_External\Lib\DefinitionParameter;
use OCP\IL10N;

/**
 * Rackspace authentication
 */
class Rackspace extends AuthMechanism {

	public function __construct(IL10N $l) {
		$this
			->setIdentifier('openstack::rackspace')
			->setScheme(self::SCHEME_OPENSTACK)
			->setText($l->t('Rackspace'))
			->addParameters([
				new DefinitionParameter('user', $l->t('Username')),
				(new DefinitionParameter('key', $l->t('API key')))
					->setType(DefinitionParameter::VALUE_PASSWORD),
			])
		;
	}

}
