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

namespace OCA\Files_External\Lib\Auth\AmazonS3;

use OCA\Files_External\Lib\Auth\AuthMechanism;
use OCA\Files_External\Lib\DefinitionParameter;
use OCP\IL10N;

/**
 * Amazon S3 access key authentication
 */
class AccessKey extends AuthMechanism {

	const SCHEME_AMAZONS3_ACCESSKEY = 'amazons3_accesskey';

	public function __construct(IL10N $l) {
		$this
			->setIdentifier('amazons3::accesskey')
			->setScheme(self::SCHEME_AMAZONS3_ACCESSKEY)
			->setText($l->t('Access key'))
			->addParameters([
				new DefinitionParameter('key', $l->t('Access key')),
				(new DefinitionParameter('secret', $l->t('Secret key')))
					->setType(DefinitionParameter::VALUE_PASSWORD),
			]);
	}

}
