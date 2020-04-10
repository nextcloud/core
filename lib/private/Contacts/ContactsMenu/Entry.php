<?php
/**
 * @copyright 2017 Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OC\Contacts\ContactsMenu;

use OCP\Contacts\ContactsMenu\IAction;
use OCP\Contacts\ContactsMenu\IEntry;

class Entry implements IEntry {

	/** @var string|int|null */
	private $id = null;

	/** @var string */
	private $fullName = '';

	/** @var string[] */
	private $emailAddresses = [];

	/** @var string|null */
	private $avatar;

	/** @var IAction[] */
	private $actions = [];

	/** @var array */
	private $properties = [];

	/**
	 * @param string $id
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * @param string $displayName
	 */
	public function setFullName($displayName) {
		$this->fullName = $displayName;
	}

	/**
	 * @return string
	 */
	public function getFullName() {
		return $this->fullName;
	}

	/**
	 * @param string $address
	 */
	public function addEMailAddress($address) {
		$this->emailAddresses[] = $address;
	}

	/**
	 * @return string
	 */
	public function getEMailAddresses() {
		return $this->emailAddresses;
	}

	/**
	 * @param string $avatar
	 */
	public function setAvatar($avatar) {
		$this->avatar = $avatar;
	}

	/**
	 * @return string
	 */
	public function getAvatar() {
		return $this->avatar;
	}

	/**
	 * @param IAction $action
	 */
	public function addAction(IAction $action) {
		$this->actions[] = $action;
		$this->sortActions();
	}

	/**
	 * @return IAction[]
	 */
	public function getActions() {
		return $this->actions;
	}

	/**
	 * sort the actions by priority and name
	 */
	private function sortActions() {
		usort($this->actions, function (IAction $action1, IAction $action2) {
			$prio1 = $action1->getPriority();
			$prio2 = $action2->getPriority();

			if ($prio1 === $prio2) {
				// Ascending order for same priority
				return strcasecmp($action1->getName(), $action2->getName());
			}

			// Descending order when priority differs
			return $prio2 - $prio1;
		});
	}

	/**
	 * @param array $contact key-value array containing additional properties
	 */
	public function setProperties(array $contact) {
		$this->properties = $contact;
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function getProperty($key) {
		if (!isset($this->properties[$key])) {
			return null;
		}
		return $this->properties[$key];
	}

	/**
	 * @return array
	 */
	public function jsonSerialize() {
		$topAction = !empty($this->actions) ? $this->actions[0]->jsonSerialize() : null;
		$otherActions = array_map(function (IAction $action) {
			return $action->jsonSerialize();
		}, array_slice($this->actions, 1));

		return [
			'id' => $this->id,
			'fullName' => $this->fullName,
			'avatar' => $this->getAvatar(),
			'topAction' => $topAction,
			'actions' => $otherActions,
			'lastMessage' => '',
		];
	}

}
