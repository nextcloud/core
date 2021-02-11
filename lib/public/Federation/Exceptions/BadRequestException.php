<?php
/**
 * @copyright Copyright (c) 2018 Bjoern Schiessle <bjoern@schiessle.org>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
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

namespace OCP\Federation\Exceptions;

use OC\HintException;

/**
 * Class BadRequestException
 *
 *
 * @since 14.0.0
 */
class BadRequestException extends HintException {
	private $parameterList;

	/**
	 * BadRequestException constructor.
	 *
	 * @since 14.0.0
	 *
	 * @param array $missingParameters
	 */
	public function __construct(array $missingParameters) {
		$l = \OC::$server->getL10N('federation');
		$this->parameterList = $missingParameters;
		$parameterList = implode(',', $missingParameters);
		$message = 'Parameters missing in order to complete the request. Missing Parameters: ' . $parameterList;
		$hint = $l->t('Parameters missing in order to complete the request. Missing Parameters: "%s"', [$parameterList]);
		parent::__construct($message, $hint);
	}

	/**
	 * get array with the return message as defined in the OCM API
	 *
	 * @since 14.0.0
	 *
	 * @return array
	 */
	public function getReturnMessage() {
		$result = [
			'message' => 'RESOURCE_NOT_FOUND',
			'validationErrors' => [
			]
		];

		foreach ($this->parameterList as $missingParameter) {
			$result['validationErrors'] = [
				'name' => $missingParameter,
				'message' => 'NOT_FOUND'
			];
		}

		return $result;
	}
}
