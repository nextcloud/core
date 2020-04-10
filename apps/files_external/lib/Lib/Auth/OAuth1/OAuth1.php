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

namespace OCA\Files_External\Lib\Auth\OAuth1;

use OCA\Files_External\Lib\Auth\AuthMechanism;
use OCA\Files_External\Lib\DefinitionParameter;
use OCP\IL10N;

/**
 * OAuth1 authentication
 */
class OAuth1 extends AuthMechanism {

	public function __construct(IL10N $l) {
		$this
			->setIdentifier('oauth1::oauth1')
			->setScheme(self::SCHEME_OAUTH1)
			->setText($l->t('OAuth1'))
			->addParameters([
				(new DefinitionParameter('configured', 'configured'))
					->setType(DefinitionParameter::VALUE_HIDDEN),
				new DefinitionParameter('app_key', $l->t('App key')),
				(new DefinitionParameter('app_secret', $l->t('App secret')))
					->setType(DefinitionParameter::VALUE_PASSWORD),
				(new DefinitionParameter('token', 'token'))
					->setType(DefinitionParameter::VALUE_HIDDEN),
				(new DefinitionParameter('token_secret', 'token_secret'))
					->setType(DefinitionParameter::VALUE_HIDDEN),
			])
			->addCustomJs('oauth1')
		;
	}

}
