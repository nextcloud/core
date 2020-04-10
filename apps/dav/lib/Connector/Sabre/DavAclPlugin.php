<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCA\DAV\Connector\Sabre;

use OCA\DAV\CardDAV\AddressBook;
use Sabre\CalDAV\Principal\User;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\INode;
use Sabre\DAV\PropFind;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

/**
 * Class DavAclPlugin is a wrapper around \Sabre\DAVACL\Plugin that returns 404
 * responses in case the resource to a response has been forbidden instead of
 * a 403. This is used to prevent enumeration of valid resources.
 *
 * @see https://github.com/owncloud/core/issues/22578
 * @package OCA\DAV\Connector\Sabre
 */
class DavAclPlugin extends \Sabre\DAVACL\Plugin {
	public function __construct() {
		$this->hideNodesFromListings = true;
		$this->allowUnauthenticatedAccess = false;
	}

	function checkPrivileges($uri, $privileges, $recursion = self::R_PARENT, $throwExceptions = true) {
		$access = parent::checkPrivileges($uri, $privileges, $recursion, false);
		if($access === false && $throwExceptions) {
			/** @var INode $node */
			$node = $this->server->tree->getNodeForPath($uri);

			switch(get_class($node)) {
				case AddressBook::class:
					$type = 'Addressbook';
					break;
				default:
					$type = 'Node';
					break;
			}
			throw new NotFound(
				sprintf(
					"%s with name '%s' could not be found",
					$type,
					$node->getName()
				)
			);
		}

		return $access;
	}

	public function propFind(PropFind $propFind, INode $node) {
		// If the node is neither readable nor writable then fail unless its of
		// the standard user-principal
		if(!($node instanceof User)) {
			$path = $propFind->getPath();
			$readPermissions = $this->checkPrivileges($path, '{DAV:}read', self::R_PARENT, false);
			$writePermissions = $this->checkPrivileges($path, '{DAV:}write', self::R_PARENT, false);
			if ($readPermissions === false && $writePermissions === false) {
				$this->checkPrivileges($path, '{DAV:}read', self::R_PARENT, true);
				$this->checkPrivileges($path, '{DAV:}write', self::R_PARENT, true);
			}
		}

		return parent::propFind($propFind, $node);
	}

	function beforeMethod(RequestInterface $request, ResponseInterface $response) {
		$path = $request->getPath();

		// prevent the plugin from causing an unneeded overhead for file requests
		if (strpos($path, 'files/') !== 0) {
			parent::beforeMethod($request, $response);
		}
	}
}
