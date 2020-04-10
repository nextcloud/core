<?php
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OC\RichObjectStrings;

use OCP\RichObjectStrings\Definitions;
use OCP\RichObjectStrings\InvalidObjectExeption;
use OCP\RichObjectStrings\IValidator;

/**
 * Class Validator
 *
 * @package OCP\RichObjectStrings
 * @since 11.0.0
 */
class Validator implements IValidator {

	/** @var Definitions */
	protected $definitions;

	/** @var array[] */
	protected $requiredParameters = [];

	/**
	 * Constructor
	 *
	 * @param Definitions $definitions
	 */
	public function __construct(Definitions $definitions) {
		$this->definitions = $definitions;
	}

	/**
	 * @param string $subject
	 * @param array[] $parameters
	 * @throws InvalidObjectExeption
	 * @since 11.0.0
	 */
	public function validate($subject, array $parameters) {
		$matches = [];
		$result = preg_match_all('/\{([a-z0-9]+)\}/i', $subject, $matches);

		if ($result === false) {
			throw new InvalidObjectExeption();
		}

		if (!empty($matches[1])) {
			foreach ($matches[1] as $parameter) {
				if (!isset($parameters[$parameter])) {
					throw new InvalidObjectExeption('Parameter is undefined');
				}
			}
		}

		foreach ($parameters as $parameter) {
			if (!\is_array($parameter)) {
				throw new InvalidObjectExeption('Parameter is malformed');
			}

			$this->validateParameter($parameter);
		}
	}

	/**
	 * @param array $parameter
	 * @throws InvalidObjectExeption
	 */
	protected function validateParameter(array $parameter) {
		if (!isset($parameter['type'])) {
			throw new InvalidObjectExeption('Object type is undefined');
		}

		$definition = $this->definitions->getDefinition($parameter['type']);
		$requiredParameters = $this->getRequiredParameters($parameter['type'], $definition);

		$missingKeys = array_diff($requiredParameters, array_keys($parameter));
		if (!empty($missingKeys)) {
			throw new InvalidObjectExeption('Object is invalid');
		}
	}

	/**
	 * @param string $type
	 * @param array $definition
	 * @return string[]
	 */
	protected function getRequiredParameters($type, array $definition) {
		if (isset($this->requiredParameters[$type])) {
			return $this->requiredParameters[$type];
		}

		$this->requiredParameters[$type] = [];
		foreach ($definition['parameters'] as $parameter => $data) {
			if ($data['required']) {
				$this->requiredParameters[$type][] = $parameter;
			}
		}

		return $this->requiredParameters[$type];
	}
}
