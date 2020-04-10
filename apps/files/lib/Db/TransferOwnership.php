<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
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

namespace OCA\Files\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setSourceUser(string $uid)
 * @method string getSourceUser()
 * @method void setTargetUser(string $uid)
 * @method string getTargetUser()
 * @method void setFileId(int $fileId)
 * @method int getFileId()
 * @method void setNodeName(string $name)
 * @method string getNodeName()
 */
class TransferOwnership extends Entity {
	/** @var string */
	protected $sourceUser;

	/** @var string */
	protected $targetUser;

	/** @var integer */
	protected $fileId;

	/** @var string */
	protected $nodeName;

	public function __construct() {
		$this->addType('sourceUser', 'string');
		$this->addType('targetUser', 'string');
		$this->addType('fileId', 'integer');
		$this->addType('nodeName', 'string');
	}


}
