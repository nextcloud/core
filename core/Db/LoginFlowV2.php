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

namespace OC\Core\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getTimestamp()
 * @method void setTimestamp(int $timestamp)
 * @method int getStarted()
 * @method void setStarted(int $started)
 * @method string getPollToken()
 * @method void setPollToken(string $token)
 * @method string getLoginToken()
 * @method void setLoginToken(string $token)
 * @method string getPublicKey()
 * @method void setPublicKey(string $key)
 * @method string getPrivateKey()
 * @method void setPrivateKey(string $key)
 * @method string getClientName()
 * @method void setClientName(string $clientName)
 * @method string getLoginName()
 * @method void setLoginName(string $loginName)
 * @method string getServer()
 * @method void setServer(string $server)
 * @method string getAppPassword()
 * @method void setAppPassword(string $appPassword)
 */
class LoginFlowV2 extends Entity {
	/** @var int */
	protected $timestamp;
	/** @var int */
	protected $started;
	/** @var string */
	protected $pollToken;
	/** @var string */
	protected $loginToken;
	/** @var string */
	protected $publicKey;
	/** @var string */
	protected $privateKey;
	/** @var string */
	protected $clientName;
	/** @var string */
	protected $loginName;
	/** @var string */
	protected $server;
	/** @var string */
	protected $appPassword;

	public function __construct() {
		$this->addType('timestamp', 'int');
		$this->addType('started', 'int');
		$this->addType('pollToken', 'string');
		$this->addType('loginToken', 'string');
		$this->addType('publicKey', 'string');
		$this->addType('privateKey', 'string');
		$this->addType('clientName', 'string');
		$this->addType('loginName', 'string');
		$this->addType('server', 'string');
		$this->addType('appPassword', 'string');
	}
}
