<?php
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\SystemTags\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;

class LastUsedController extends Controller {

	/** @var IConfig */
	protected $config;

	/** @var IUserSession */
	protected $userSession;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param IConfig $config
	 * @param IUserSession $userSession
	 */
	public function __construct($appName, IRequest $request, IConfig $config, IUserSession $userSession) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->userSession = $userSession;
	}

	/**
	 * @NoAdminRequired
	 */
	public function getLastUsedTagIds() {
		$lastUsed = $this->config->getUserValue($this->userSession->getUser()->getUID(), 'systemtags', 'last_used', '[]');
		$tagIds = json_decode($lastUsed, true);
		return new DataResponse(array_map(function ($id) { return (string) $id; }, $tagIds));
	}
}
