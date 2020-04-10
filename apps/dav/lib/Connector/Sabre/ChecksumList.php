<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
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

namespace OCA\DAV\Connector\Sabre;

use Sabre\Xml\Writer;
use Sabre\Xml\XmlSerializable;

/**
 * Checksumlist property
 *
 * This property contains multiple "checksum" elements, each containing a
 * checksum name.
 */
class ChecksumList implements XmlSerializable {
	const NS_OWNCLOUD = 'http://owncloud.org/ns';

	/** @var string[] of TYPE:CHECKSUM */
	private $checksums;

	/**
	 * @param string $checksum
	 */
	public function __construct($checksum) {
		$this->checksums = explode(',', $checksum);
	}

	/**
	 * The xmlSerialize metod is called during xml writing.
	 *
	 * Use the $writer argument to write its own xml serialization.
	 *
	 * An important note: do _not_ create a parent element. Any element
	 * implementing XmlSerializble should only ever write what's considered
	 * its 'inner xml'.
	 *
	 * The parent of the current element is responsible for writing a
	 * containing element.
	 *
	 * This allows serializers to be re-used for different element names.
	 *
	 * If you are opening new elements, you must also close them again.
	 *
	 * @param Writer $writer
	 * @return void
	 */
	function xmlSerialize(Writer $writer) {

		foreach ($this->checksums as $checksum) {
			$writer->writeElement('{' . self::NS_OWNCLOUD . '}checksum', $checksum);
		}
	}
}
