<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Morris Jobke <hey@morrisjobke.de>
 *
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OC\Files\ObjectStore;

class AppdataPreviewObjectStoreStorage extends ObjectStoreStorage {

	/** @var string */
	private $internalId;

	public function __construct($params) {
		if (!isset($params['internal-id'])) {
			throw new \Exception('missing id in parameters');
		}
		$this->internalId = (string)$params['internal-id'];
		parent::__construct($params);
	}

	public function getId() {
		return 'object::appdata::preview:' . $this->internalId;
	}
}
