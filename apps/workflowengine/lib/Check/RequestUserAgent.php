<?php
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
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
 *
 */

namespace OCA\WorkflowEngine\Check;

use OCP\IL10N;
use OCP\IRequest;

class RequestUserAgent extends AbstractStringCheck {

	/** @var IRequest */
	protected $request;

	/**
	 * @param IL10N $l
	 * @param IRequest $request
	 */
	public function __construct(IL10N $l, IRequest $request) {
		parent::__construct($l);
		$this->request = $request;
	}

	/**
	 * @param string $operator
	 * @param string $value
	 * @return bool
	 */
	public function executeCheck($operator, $value) {
		$actualValue = $this->getActualValue();
		if (in_array($operator, ['is', '!is'], true)) {
			switch ($value) {
				case 'android':
					$operator = $operator === 'is' ? 'matches' : '!matches';
					$value = IRequest::USER_AGENT_CLIENT_ANDROID;
					break;
				case 'ios':
					$operator = $operator === 'is' ? 'matches' : '!matches';
					$value = IRequest::USER_AGENT_CLIENT_IOS;
					break;
				case 'desktop':
					$operator = $operator === 'is' ? 'matches' : '!matches';
					$value = IRequest::USER_AGENT_CLIENT_DESKTOP;
					break;
				case 'mail':
					if ($operator === 'is') {
						return $this->executeStringCheck('matches', IRequest::USER_AGENT_OUTLOOK_ADDON, $actualValue)
							|| $this->executeStringCheck('matches', IRequest::USER_AGENT_THUNDERBIRD_ADDON, $actualValue);
					}

					return $this->executeStringCheck('!matches', IRequest::USER_AGENT_OUTLOOK_ADDON, $actualValue)
						&& $this->executeStringCheck('!matches', IRequest::USER_AGENT_THUNDERBIRD_ADDON, $actualValue);
			}
		}
		return $this->executeStringCheck($operator, $value, $actualValue);
	}

	/**
	 * @return string
	 */
	protected function getActualValue() {
		return $this->request->getHeader('User-Agent');
	}

	public function isAvailableForScope(int $scope): bool {
		return true;
	}
}
