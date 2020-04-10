<?php
/**
 * @copyright Copyright (c) 2017 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OCA\OAuth2\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getClientIdentifier()
 * @method void setClientIdentifier(string $identifier)
 * @method string getSecret()
 * @method void setSecret(string $secret)
 * @method string getRedirectUri()
 * @method void setRedirectUri(string $redirectUri)
 * @method string getName()
 * @method void setName(string $name)
 */
class Client extends Entity {
	/** @var string */
	protected $name;
	/** @var string */
	protected $redirectUri;
	/** @var string */
	protected $clientIdentifier;
	/** @var string */
	protected $secret;

	public function __construct() {
		$this->addType('id', 'int');
		$this->addType('name', 'string');
		$this->addType('redirect_uri', 'string');
		$this->addType('client_identifier', 'string');
		$this->addType('secret', 'string');
	}
}
