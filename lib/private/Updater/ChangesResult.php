<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OC\Updater;

use OCP\AppFramework\Db\Entity;

/**
 * Class ChangesResult
 *
 * @package OC\Updater
 * @method string getVersion()=1
 * @method void setVersion(string $version)
 * @method string getEtag()
 * @method void setEtag(string $etag)
 * @method int getLastCheck()
 * @method void setLastCheck(int $lastCheck)
 * @method string getData()
 * @method void setData(string $data)
 */
class ChangesResult extends Entity {
	/** @var string */
	protected $version = '';

	/** @var string */
	protected $etag = '';

	/** @var int */
	protected $lastCheck = 0;

	/** @var string */
	protected $data = '';

	public function __construct() {
		$this->addType('version', 'string');
		$this->addType('etag', 'string');
		$this->addType('lastCheck', 'int');
		$this->addType('data', 'string');
	}
}
