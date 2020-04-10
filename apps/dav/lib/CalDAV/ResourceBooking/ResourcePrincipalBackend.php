<?php
/**
 * @copyright 2018, Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
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

namespace OCA\DAV\CalDAV\ResourceBooking;

use OCA\DAV\CalDAV\Proxy\ProxyMapper;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IUserSession;

/**
 * Class ResourcePrincipalBackend
 *
 * @package OCA\DAV\CalDAV\ResourceBooking
 */
class ResourcePrincipalBackend extends AbstractPrincipalBackend {

	/**
	 * ResourcePrincipalBackend constructor.
	 *
	 * @param IDBConnection $dbConnection
	 * @param IUserSession $userSession
	 * @param IGroupManager $groupManager
	 * @param ILogger $logger
	 * @param ProxyMapper $proxyMapper
	 */
	public function __construct(IDBConnection $dbConnection,
								IUserSession $userSession,
								IGroupManager $groupManager,
								ILogger $logger,
								ProxyMapper $proxyMapper) {
		parent::__construct($dbConnection, $userSession, $groupManager, $logger,
			$proxyMapper, 'principals/calendar-resources', 'resource', 'RESOURCE');
	}
}
