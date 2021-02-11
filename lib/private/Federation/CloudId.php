<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2017, Robin Appelman <robin@icewind.nl>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\Federation;

use OCP\Federation\ICloudId;

class CloudId implements ICloudId {
	/** @var string */
	private $id;
	/** @var string */
	private $user;
	/** @var string */
	private $remote;
	/** @var string|null */
	private $displayName;

	/**
	 * CloudId constructor.
	 *
	 * @param string $id
	 * @param string $user
	 * @param string $remote
	 */
	public function __construct(string $id, string $user, string $remote, ?string $displayName = null) {
		$this->id = $id;
		$this->user = $user;
		$this->remote = $remote;
		$this->displayName = $displayName;
	}

	/**
	 * The full remote cloud id
	 *
	 * @return string
	 */
	public function getId(): string {
		return $this->id;
	}

	public function getDisplayId(): string {
		if ($this->displayName) {
			$atPos = strrpos($this->getId(), '@');
			$atHost = substr($this->getId(), $atPos);
			return $this->displayName . $atHost;
		}
		return str_replace('https://', '', str_replace('http://', '', $this->getId()));
	}

	/**
	 * The username on the remote server
	 *
	 * @return string
	 */
	public function getUser(): string {
		return $this->user;
	}

	/**
	 * The base address of the remote server
	 *
	 * @return string
	 */
	public function getRemote(): string {
		return $this->remote;
	}
}
