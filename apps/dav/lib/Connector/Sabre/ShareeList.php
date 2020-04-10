<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Tobias Kaminsky <tobias@kaminsky.me>
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

namespace OCA\DAV\Connector\Sabre;

use OCP\Share\IShare;
use Sabre\Xml\Writer;
use Sabre\Xml\XmlSerializable;

/**
 * This property contains multiple "sharee" elements, each containing a share sharee
 */
class ShareeList implements XmlSerializable {
	const NS_NEXTCLOUD = 'http://nextcloud.org/ns';

	/** @var IShare[] */
	private $shares;

	public function __construct(array $shares) {
		$this->shares = $shares;
	}

	/**
	 * The xmlSerialize metod is called during xml writing.
	 *
	 * @param Writer $writer
	 * @return void
	 */
	function xmlSerialize(Writer $writer) {
		foreach ($this->shares as $share) {
			$writer->startElement('{' . self::NS_NEXTCLOUD . '}sharee');
			$writer->writeElement('{' . self::NS_NEXTCLOUD . '}id', $share->getSharedWith());
			$writer->writeElement('{' . self::NS_NEXTCLOUD . '}display-name', $share->getSharedWithDisplayName());
			$writer->writeElement('{' . self::NS_NEXTCLOUD . '}type', $share->getShareType());
			$writer->endElement();
		}
	}
}
