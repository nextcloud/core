<?php
/**
 * @copyright Copyright (c) 2016 Thomas Citharel <tcit@tcit.fr>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Citharel <nextcloud@tcit.fr>
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

namespace OCA\DAV\CalDAV\Publishing\Xml;

use Sabre\Xml\Writer;
use Sabre\Xml\XmlSerializable;

class Publisher implements XmlSerializable {

	/**
	 * @var string $publishUrls
	 */
	protected $publishUrls;

	/**
	 * @param array $publishUrls
	 */
	function __construct(array $publishUrls) {
		$this->publishUrls = $publishUrls;
	}

	/**
	 * @return array
	 */
	function getValue(): array {
		return array_keys($this->publishUrls);
	}

	/**
	 * The xmlSerialize method is called during xml writing.
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
		foreach ($this->publishUrls as $publishUrl => $isPublished)
		if (!$isPublished) {
			// for pre-publish-url
			$writer->write($publishUrl);
		} else {
			// for publish-url
			$writer->writeElement('{DAV:}href', $publishUrl);
		}
	}
}
