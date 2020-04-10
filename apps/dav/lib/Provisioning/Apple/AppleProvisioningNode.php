<?php
/**
 * @copyright 2018, Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
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

namespace OCA\DAV\Provisioning\Apple;

use OCP\AppFramework\Utility\ITimeFactory;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\INode;
use Sabre\DAV\IProperties;
use Sabre\DAV\PropPatch;

class AppleProvisioningNode implements INode, IProperties {

	const FILENAME = 'apple-provisioning.mobileconfig';

	protected $timeFactory;

	/**
	 * @param ITimeFactory $timeFactory
	 */
	public function __construct(ITimeFactory $timeFactory) {
		$this->timeFactory = $timeFactory;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return self::FILENAME;
	}


	public function setName($name) {
		throw new Forbidden('Renaming ' . self::FILENAME . ' is forbidden');
	}

	/**
	 * @return null
	 */
	public function getLastModified() {
		return null;
	}

	/**
	 * @throws Forbidden
	 */
	public function delete() {
		throw new Forbidden(self::FILENAME . ' may not be deleted.');
	}

	/**
	 * @param array $properties
	 * @return array
	 */
	public function getProperties($properties) {
		$datetime = $this->timeFactory->getDateTime();

		return [
			'{DAV:}getcontentlength' => 42,
			'{DAV:}getlastmodified' => $datetime->format(\DateTime::RFC2822),
		];
	}

	/**
	 * @param PropPatch $propPatch
	 * @throws Forbidden
	 */
	public function propPatch(PropPatch $propPatch) {
		throw new Forbidden(self::FILENAME . '\'s properties may not be altered.');
	}
}
