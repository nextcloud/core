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

namespace OCA\Files_External\Lib\Auth\OAuth2;

use OCA\Files_External\Lib\Auth\AuthMechanism;
use OCA\Files_External\Lib\DefinitionParameter;
use OCP\IL10N;

/**
 * OAuth2 authentication
 */
class OAuth2 extends AuthMechanism {

	public function __construct(IL10N $l) {
		$this
			->setIdentifier('oauth2::oauth2')
			->setScheme(self::SCHEME_OAUTH2)
			->setText($l->t('OAuth2'))
			->addParameters([
				(new DefinitionParameter('configured', 'configured'))
					->setType(DefinitionParameter::VALUE_HIDDEN),
				new DefinitionParameter('client_id', $l->t('Client ID')),
				(new DefinitionParameter('client_secret', $l->t('Client secret')))
					->setType(DefinitionParameter::VALUE_PASSWORD),
				(new DefinitionParameter('token', 'token'))
					->setType(DefinitionParameter::VALUE_HIDDEN),
			])
			->addCustomJs('oauth2')
		;
	}

}
