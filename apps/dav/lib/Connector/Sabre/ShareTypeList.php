<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Vincent Petry <vincent@nextcloud.com>
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

use Sabre\Xml\Element;
use Sabre\Xml\Reader;
use Sabre\Xml\Writer;

/**
 * ShareTypeList property
 *
 * This property contains multiple "share-type" elements, each containing a share type.
 */
class ShareTypeList implements Element {
	public const NS_OWNCLOUD = 'http://owncloud.org/ns';

	/**
	 * Share types
	 *
	 * @var int[]
	 */
	private $shareTypes;

	/**
	 * @param int[] $shareTypes
	 */
	public function __construct($shareTypes) {
		$this->shareTypes = $shareTypes;
	}

	/**
	 * Returns the share types
	 *
	 * @return int[]
	 */
	public function getShareTypes() {
		return $this->shareTypes;
	}

	/**
	 * The deserialize method is called during xml parsing.
	 *
	 * @param Reader $reader
	 * @return mixed
	 */
	public static function xmlDeserialize(Reader $reader) {
		$shareTypes = [];

		$tree = $reader->parseInnerTree();
		if ($tree === null) {
			return null;
		}
		foreach ($tree as $elem) {
			if ($elem['name'] === '{' . self::NS_OWNCLOUD . '}share-type') {
				$shareTypes[] = (int)$elem['value'];
			}
		}
		return new self($shareTypes);
	}

	/**
	 * The xmlSerialize metod is called during xml writing.
	 *
	 * @param Writer $writer
	 * @return void
	 */
	public function xmlSerialize(Writer $writer) {
		foreach ($this->shareTypes as $shareType) {
			$writer->writeElement('{' . self::NS_OWNCLOUD . '}share-type', $shareType);
		}
	}
}
