<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OC\Http\Client;

use GuzzleHttp\Client as GuzzleClient;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\ICertificateManager;
use OCP\IConfig;

/**
 * Class ClientService
 *
 * @package OC\Http
 */
class ClientService implements IClientService {
	/** @var IConfig */
	private $config;
	/** @var ICertificateManager */
	private $certificateManager;

	/**
	 * @param IConfig $config
	 * @param ICertificateManager $certificateManager
	 */
	public function __construct(IConfig $config,
								ICertificateManager $certificateManager) {
		$this->config = $config;
		$this->certificateManager = $certificateManager;
	}

	/**
	 * @return Client
	 */
	public function newClient(): IClient {
		return new Client($this->config, $this->certificateManager, new GuzzleClient());
	}
}
