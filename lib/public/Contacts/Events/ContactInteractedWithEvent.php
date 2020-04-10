<?php

declare(strict_types=1);

/**
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCP\Contacts\Events;

use OCP\EventDispatcher\Event;
use OCP\IUser;

/**
 * An event that allows apps to notify other components about an interaction
 * between two users. This can be used to build better recommendations and
 * suggestions in user interfaces.
 *
 * Emitters should add at least one identifier (uid, email, federated cloud ID)
 * of the recipient of the interaction.
 *
 * @since 19.0.0
 */
class ContactInteractedWithEvent extends Event {

	/** @var IUser */
	private $actor;

	/** @var string|null */
	private $uid;

	/** @var string|null */
	private $email;

	/** @var string|null */
	private $federatedCloudId;

	/**
	 * @param IUser $actor the user who started the interaction
	 *
	 * @since 19.0.0
	 */
	public function __construct(IUser $actor) {
		parent::__construct();
		$this->actor = $actor;
	}

	/**
	 * @return IUser
	 * @since 19.0.0
	 */
	public function getActor(): IUser {
		return $this->actor;
	}

	/**
	 * @return string|null
	 * @since 19.0.0
	 */
	public function getUid(): ?string {
		return $this->uid;
	}

	/**
	 * Set the uid of the person interacted with, if known
	 *
	 * @param string $uid
	 *
	 * @return self
	 * @since 19.0.0
	 */
	public function setUid(string $uid): self {
		$this->uid = $uid;
		return $this;
	}

	/**
	 * @return string|null
	 * @since 19.0.0
	 */
	public function getEmail(): ?string {
		return $this->email;
	}

	/**
	 * Set the email of the person interacted with, if known
	 *
	 * @param string $email
	 *
	 * @return self
	 * @since 19.0.0
	 */
	public function setEmail(string $email): self {
		$this->email = $email;
		return $this;
	}

	/**
	 * @return string|null
	 * @since 19.0.0
	 */
	public function getFederatedCloudId(): ?string {
		return $this->federatedCloudId;
	}

	/**
	 * Set the federated cloud of the person interacted with, if known
	 *
	 * @param string $federatedCloudId
	 *
	 * @return self
	 * @since 19.0.0
	 */
	public function setFederatedCloudId(string $federatedCloudId): self {
		$this->federatedCloudId = $federatedCloudId;
		return $this;
	}

}
