<?php

declare(strict_types=1);

/**
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\ContactsInteraction\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setActorUid(string $uid)
 * @method string|null getActorUid()
 * @method void setUid(string $uid)
 * @method string|null getUid()
 * @method void setEmail(string $email)
 * @method string|null getEmail()
 * @method void setFederatedCloudId(string $federatedCloudId)
 * @method string|null getFederatedCloudId()
 * @method void setCard(string $card)
 * @method string getCard()
 * @method void setLastContact(int $lastContact)
 * @method int getLastContact()
 */
class RecentContact extends Entity {

	/** @var string */
	protected $actorUid;

	/** @var string|null */
	protected $uid;

	/** @var string|null */
	protected $email;

	/** @var string|null */
	protected $federatedCloudId;

	/** @var string */
	protected $card;

	/** @var int */
	protected $lastContact;

	public function __construct() {
		$this->addType('actorUid', 'string');
		$this->addType('uid', 'string');
		$this->addType('email', 'string');
		$this->addType('federatedCloudId', 'string');
		$this->addType('card', 'blob');
		$this->addType('lastContact', 'int');
	}
}
