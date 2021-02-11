<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCA\DAV\DAV\Sharing\Xml;

use OCA\DAV\DAV\Sharing\Plugin;
use Sabre\Xml\Reader;
use Sabre\Xml\XmlDeserializable;

class ShareRequest implements XmlDeserializable {
	public $set = [];

	public $remove = [];

	/**
	 * Constructor
	 *
	 * @param array $set
	 * @param array $remove
	 */
	public function __construct(array $set, array $remove) {
		$this->set = $set;
		$this->remove = $remove;
	}

	public static function xmlDeserialize(Reader $reader) {
		$elements = $reader->parseInnerTree([
			'{' . Plugin::NS_OWNCLOUD. '}set' => 'Sabre\\Xml\\Element\\KeyValue',
			'{' . Plugin::NS_OWNCLOUD . '}remove' => 'Sabre\\Xml\\Element\\KeyValue',
		]);

		$set = [];
		$remove = [];

		foreach ($elements as $elem) {
			switch ($elem['name']) {

				case '{' . Plugin::NS_OWNCLOUD . '}set':
					$sharee = $elem['value'];

					$sumElem = '{' . Plugin::NS_OWNCLOUD . '}summary';
					$commonName = '{' . Plugin::NS_OWNCLOUD . '}common-name';

					$set[] = [
						'href' => $sharee['{DAV:}href'],
						'commonName' => isset($sharee[$commonName]) ? $sharee[$commonName] : null,
						'summary' => isset($sharee[$sumElem]) ? $sharee[$sumElem] : null,
						'readOnly' => !array_key_exists('{' . Plugin::NS_OWNCLOUD . '}read-write', $sharee),
					];
					break;

				case '{' . Plugin::NS_OWNCLOUD . '}remove':
					$remove[] = $elem['value']['{DAV:}href'];
					break;

			}
		}

		return new self($set, $remove);
	}
}
