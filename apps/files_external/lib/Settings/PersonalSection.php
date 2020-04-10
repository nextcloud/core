<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCA\Files_External\Settings;

use OCA\Files_External\Service\BackendService;
use OCA\Files_External\Service\UserGlobalStoragesService;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserSession;

class PersonalSection extends Section {
	/** @var IUserSession */
	private $userSession;

	/** @var UserGlobalStoragesService */
	private $userGlobalStoragesService;

	/** @var BackendService */
	private $backendService;

	public function __construct(
		IURLGenerator $url,
		IL10N $l,
		IUserSession $userSession,
		UserGlobalStoragesService $userGlobalStoragesService,
		BackendService $backendService
	) {
		parent::__construct($url, $l);
		$this->userSession = $userSession;
		$this->userGlobalStoragesService = $userGlobalStoragesService;
		$this->backendService = $backendService;
	}
}
