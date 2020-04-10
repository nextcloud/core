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

namespace OC\Core\Command\Config\System;

use OC\SystemConfig;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;

abstract class Base extends \OC\Core\Command\Base {

	/** @var SystemConfig */
	protected $systemConfig;

	/**
	 * @param string $argumentName
	 * @param CompletionContext $context
	 * @return string[]
	 */
	public function completeArgumentValues($argumentName, CompletionContext $context) {
		if ($argumentName === 'name') {
			$words = $this->getPreviousNames($context, $context->getWordIndex());
			if (empty($words)) {
				$completions = $this->systemConfig->getKeys();
			} else {
				$key = array_shift($words);
				$value = $this->systemConfig->getValue($key);
				$completions = array_keys($value);

				while (!empty($words) && is_array($value)) {
					$key = array_shift($words);
					if (!isset($value[$key]) || !is_array($value[$key])) {
						break;
					}

					$value = $value[$key];
					$completions = array_keys($value);
				}
			}

			return $completions;
		}
		return parent::completeArgumentValues($argumentName, $context);
	}

	/**
	 * @param CompletionContext $context
	 * @param int $currentIndex
	 * @return string[]
	 */
	protected function getPreviousNames(CompletionContext $context, $currentIndex) {
		$word = $context->getWordAtIndex($currentIndex - 1);
		if ($word === $this->getName() || $currentIndex <= 0) {
			return [];
		}

		$words = $this->getPreviousNames($context, $currentIndex - 1);
		$words[] = $word;
		return $words;
	}
}
