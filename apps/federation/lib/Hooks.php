<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
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

namespace OCA\Federation;

class Hooks {

	/** @var TrustedServers */
	private $trustedServers;

	public function __construct(TrustedServers $trustedServers) {
		$this->trustedServers = $trustedServers;
	}

	/**
	 * add servers to the list of trusted servers once a federated share was established
	 *
	 * @param array $params
	 */
	public function addServerHook($params) {
		if (
			$this->trustedServers->getAutoAddServers() === true &&
			$this->trustedServers->isTrustedServer($params['server']) === false
		) {
			$this->trustedServers->addServer($params['server']);
		}
	}

}
