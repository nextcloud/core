<?php

declare(strict_types=1);

/**
 * @copyright 2018, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Files_Trashbin\Sabre;

use OCA\Files_Trashbin\Trash\ITrashManager;
use OCP\IConfig;
use Sabre\DAV\INode;
use Sabre\DAVACL\AbstractPrincipalCollection;
use Sabre\DAVACL\PrincipalBackend;

class RootCollection extends AbstractPrincipalCollection {
	/** @var ITrashManager */
	private $trashManager;

	public function __construct(
		ITrashManager $trashManager,
		PrincipalBackend\BackendInterface $principalBackend,
		IConfig $config
	) {
		parent::__construct($principalBackend, 'principals/users');

		$this->trashManager = $trashManager;
		$this->disableListing = !$config->getSystemValue('debug', false);
	}

	/**
	 * This method returns a node for a principal.
	 *
	 * The passed array contains principal information, and is guaranteed to
	 * at least contain a uri item. Other properties may or may not be
	 * supplied by the authentication backend.
	 *
	 * @param array $principalInfo
	 * @return INode
	 */
	public function getChildForPrincipal(array $principalInfo): TrashHome {
		list(, $name) = \Sabre\Uri\split($principalInfo['uri']);
		$user = \OC::$server->getUserSession()->getUser();
		if (is_null($user) || $name !== $user->getUID()) {
			throw new \Sabre\DAV\Exception\Forbidden();
		}
		return new TrashHome($principalInfo, $this->trashManager, $user);
	}

	public function getName(): string {
		return 'trashbin';
	}

}
