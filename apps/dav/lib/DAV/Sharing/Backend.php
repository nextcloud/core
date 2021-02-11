<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Citharel <nextcloud@tcit.fr>
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

namespace OCA\DAV\DAV\Sharing;

use OCA\DAV\Connector\Sabre\Principal;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;

class Backend {

	/** @var IDBConnection */
	private $db;
	/** @var IUserManager */
	private $userManager;
	/** @var IGroupManager */
	private $groupManager;
	/** @var Principal */
	private $principalBackend;
	/** @var string */
	private $resourceType;

	public const ACCESS_OWNER = 1;
	public const ACCESS_READ_WRITE = 2;
	public const ACCESS_READ = 3;

	/**
	 * @param IDBConnection $db
	 * @param IUserManager $userManager
	 * @param IGroupManager $groupManager
	 * @param Principal $principalBackend
	 * @param string $resourceType
	 */
	public function __construct(IDBConnection $db, IUserManager $userManager, IGroupManager $groupManager, Principal $principalBackend, $resourceType) {
		$this->db = $db;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->principalBackend = $principalBackend;
		$this->resourceType = $resourceType;
	}

	/**
	 * @param IShareable $shareable
	 * @param string[] $add
	 * @param string[] $remove
	 */
	public function updateShares(IShareable $shareable, array $add, array $remove) {
		foreach ($add as $element) {
			$principal = $this->principalBackend->findByUri($element['href'], '');
			if ($principal !== '') {
				$this->shareWith($shareable, $element);
			}
		}
		foreach ($remove as $element) {
			$principal = $this->principalBackend->findByUri($element, '');
			if ($principal !== '') {
				$this->unshare($shareable, $element);
			}
		}
	}

	/**
	 * @param IShareable $shareable
	 * @param string $element
	 */
	private function shareWith($shareable, $element) {
		$user = $element['href'];
		$parts = explode(':', $user, 2);
		if ($parts[0] !== 'principal') {
			return;
		}

		// don't share with owner
		if ($shareable->getOwner() === $parts[1]) {
			return;
		}

		$principal = explode('/', $parts[1], 3);
		if (count($principal) !== 3 || $principal[0] !== 'principals' || !in_array($principal[1], ['users', 'groups', 'circles'], true)) {
			// Invalid principal
			return;
		}

		$principal[2] = urldecode($principal[2]);
		if (($principal[1] === 'users' && !$this->userManager->userExists($principal[2])) ||
			($principal[1] === 'groups' && !$this->groupManager->groupExists($principal[2]))) {
			// User or group does not exist
			return;
		}

		// remove the share if it already exists
		$this->unshare($shareable, $element['href']);
		$access = self::ACCESS_READ;
		if (isset($element['readOnly'])) {
			$access = $element['readOnly'] ? self::ACCESS_READ : self::ACCESS_READ_WRITE;
		}

		$query = $this->db->getQueryBuilder();
		$query->insert('dav_shares')
			->values([
				'principaluri' => $query->createNamedParameter($parts[1]),
				'type' => $query->createNamedParameter($this->resourceType),
				'access' => $query->createNamedParameter($access),
				'resourceid' => $query->createNamedParameter($shareable->getResourceId())
			]);
		$query->execute();
	}

	/**
	 * @param $resourceId
	 */
	public function deleteAllShares($resourceId) {
		$query = $this->db->getQueryBuilder();
		$query->delete('dav_shares')
			->where($query->expr()->eq('resourceid', $query->createNamedParameter($resourceId)))
			->andWhere($query->expr()->eq('type', $query->createNamedParameter($this->resourceType)))
			->execute();
	}

	public function deleteAllSharesByUser($principaluri) {
		$query = $this->db->getQueryBuilder();
		$query->delete('dav_shares')
			->where($query->expr()->eq('principaluri', $query->createNamedParameter($principaluri)))
			->andWhere($query->expr()->eq('type', $query->createNamedParameter($this->resourceType)))
			->execute();
	}

	/**
	 * @param IShareable $shareable
	 * @param string $element
	 */
	private function unshare($shareable, $element) {
		$parts = explode(':', $element, 2);
		if ($parts[0] !== 'principal') {
			return;
		}

		// don't share with owner
		if ($shareable->getOwner() === $parts[1]) {
			return;
		}

		$query = $this->db->getQueryBuilder();
		$query->delete('dav_shares')
			->where($query->expr()->eq('resourceid', $query->createNamedParameter($shareable->getResourceId())))
			->andWhere($query->expr()->eq('type', $query->createNamedParameter($this->resourceType)))
			->andWhere($query->expr()->eq('principaluri', $query->createNamedParameter($parts[1])))
		;
		$query->execute();
	}

	/**
	 * Returns the list of people whom this resource is shared with.
	 *
	 * Every element in this array should have the following properties:
	 *   * href - Often a mailto: address
	 *   * commonName - Optional, for example a first + last name
	 *   * status - See the Sabre\CalDAV\SharingPlugin::STATUS_ constants.
	 *   * readOnly - boolean
	 *   * summary - Optional, a description for the share
	 *
	 * @param int $resourceId
	 * @return array
	 */
	public function getShares($resourceId) {
		$query = $this->db->getQueryBuilder();
		$result = $query->select(['principaluri', 'access'])
			->from('dav_shares')
			->where($query->expr()->eq('resourceid', $query->createNamedParameter($resourceId)))
			->andWhere($query->expr()->eq('type', $query->createNamedParameter($this->resourceType)))
			->groupBy(['principaluri', 'access'])
			->execute();

		$shares = [];
		while ($row = $result->fetch()) {
			$p = $this->principalBackend->getPrincipalByPath($row['principaluri']);
			$shares[] = [
				'href' => "principal:${row['principaluri']}",
				'commonName' => isset($p['{DAV:}displayname']) ? $p['{DAV:}displayname'] : '',
				'status' => 1,
				'readOnly' => (int) $row['access'] === self::ACCESS_READ,
				'{http://owncloud.org/ns}principal' => $row['principaluri'],
				'{http://owncloud.org/ns}group-share' => is_null($p)
			];
		}

		return $shares;
	}

	/**
	 * For shared resources the sharee is set in the ACL of the resource
	 *
	 * @param int $resourceId
	 * @param array $acl
	 * @return array
	 */
	public function applyShareAcl($resourceId, $acl) {
		$shares = $this->getShares($resourceId);
		foreach ($shares as $share) {
			$acl[] = [
				'privilege' => '{DAV:}read',
				'principal' => $share['{' . \OCA\DAV\DAV\Sharing\Plugin::NS_OWNCLOUD . '}principal'],
				'protected' => true,
			];
			if (!$share['readOnly']) {
				$acl[] = [
					'privilege' => '{DAV:}write',
					'principal' => $share['{' . \OCA\DAV\DAV\Sharing\Plugin::NS_OWNCLOUD . '}principal'],
					'protected' => true,
				];
			} elseif ($this->resourceType === 'calendar') {
				// Allow changing the properties of read only calendars,
				// so users can change the visibility.
				$acl[] = [
					'privilege' => '{DAV:}write-properties',
					'principal' => $share['{' . \OCA\DAV\DAV\Sharing\Plugin::NS_OWNCLOUD . '}principal'],
					'protected' => true,
				];
			}
		}
		return $acl;
	}
}
