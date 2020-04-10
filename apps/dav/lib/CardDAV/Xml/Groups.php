<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
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

namespace OCA\DAV\CardDAV\Xml;

use Sabre\Xml\Writer;
use Sabre\Xml\XmlSerializable;

class Groups implements XmlSerializable {
	const NS_OWNCLOUD = 'http://owncloud.org/ns';

	/** @var string[] of TYPE:CHECKSUM */
	private $groups;

	/**
	 * @param string $groups
	 */
	public function __construct($groups) {
		$this->groups = $groups;
	}

	function xmlSerialize(Writer $writer) {
		foreach ($this->groups as $group) {
			$writer->writeElement('{' . self::NS_OWNCLOUD . '}group', $group);
		}
	}
}
