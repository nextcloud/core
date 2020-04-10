<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCA\DAV\Upload;

use Sabre\DAVACL\AbstractPrincipalCollection;
use Sabre\DAVACL\PrincipalBackend;

class RootCollection extends AbstractPrincipalCollection {

	/** @var CleanupService */
	private $cleanupService;

	public function __construct(PrincipalBackend\BackendInterface $principalBackend,
								string $principalPrefix,
								CleanupService $cleanupService) {
		parent::__construct($principalBackend, $principalPrefix);
		$this->cleanupService = $cleanupService;
	}

	/**
	 * @inheritdoc
	 */
	public function getChildForPrincipal(array $principalInfo): UploadHome {
		return new UploadHome($principalInfo, $this->cleanupService);
	}

	/**
	 * @inheritdoc
	 */
	public function getName(): string {
		return 'uploads';
	}

}
