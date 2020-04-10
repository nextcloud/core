<?php
/**
 * @copyright Copyright (c) 2017 Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\DAV\CalDAV\Search\Xml\Filter;

use OCA\DAV\CalDAV\Search\SearchPlugin;
use Sabre\DAV\Exception\BadRequest;
use Sabre\Xml\Reader;
use Sabre\Xml\XmlDeserializable;

class OffsetFilter implements XmlDeserializable {

	/**
	 * @param Reader $reader
	 * @throws BadRequest
	 * @return int
	 */
	static function xmlDeserialize(Reader $reader) {
		$value = $reader->parseInnerTree();
		if (!is_int($value) && !is_string($value)) {
			throw new BadRequest('The {' . SearchPlugin::NS_Nextcloud . '}offset has illegal value');
		}

		return (int)$value;
	}
}
