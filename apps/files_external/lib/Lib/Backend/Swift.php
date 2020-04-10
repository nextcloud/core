<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCA\Files_External\Lib\Backend;

use OCA\Files_External\Lib\Auth\AuthMechanism;
use OCA\Files_External\Lib\Auth\OpenStack\OpenStackV2;
use OCA\Files_External\Lib\Auth\OpenStack\Rackspace;
use OCA\Files_External\Lib\DefinitionParameter;
use OCA\Files_External\Lib\LegacyDependencyCheckPolyfill;
use OCP\IL10N;

class Swift extends Backend {

	use LegacyDependencyCheckPolyfill;

	public function __construct(IL10N $l, OpenStackV2 $openstackAuth, Rackspace $rackspaceAuth) {
		$this
			->setIdentifier('swift')
			->addIdentifierAlias('\OC\Files\Storage\Swift') // legacy compat
			->setStorageClass('\OCA\Files_External\Lib\Storage\Swift')
			->setText($l->t('OpenStack Object Storage'))
			->addParameters([
				(new DefinitionParameter('service_name', $l->t('Service name')))
					->setFlag(DefinitionParameter::FLAG_OPTIONAL),
				(new DefinitionParameter('region', $l->t('Region')))
					->setFlag(DefinitionParameter::FLAG_OPTIONAL),
				new DefinitionParameter('bucket', $l->t('Bucket')),
				(new DefinitionParameter('timeout', $l->t('Request timeout (seconds)')))
					->setFlag(DefinitionParameter::FLAG_OPTIONAL),
			])
			->addAuthScheme(AuthMechanism::SCHEME_OPENSTACK)
			->setLegacyAuthMechanismCallback(function (array $params) use ($openstackAuth, $rackspaceAuth) {
				if (isset($params['options']['key']) && $params['options']['key']) {
					return $rackspaceAuth;
				}
				return $openstackAuth;
			})
		;
	}

}
