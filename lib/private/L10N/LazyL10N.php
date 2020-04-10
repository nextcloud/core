<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
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

namespace OC\L10N;

use OCP\IL10N;

class LazyL10N implements IL10N {

	/** @var IL10N */
	private $l;

	/** @var \Closure */
	private $factory;


	public function __construct(\Closure $factory) {
		$this->factory = $factory;
	}

	private function getL(): IL10N {
		if ($this->l === null) {
			$this->l = ($this->factory)();
		}

		return $this->l;
	}

	public function t(string $text, $parameters = []): string {
		return $this->getL()->t($text, $parameters);
	}

	public function n(string $text_singular, string $text_plural, int $count, array $parameters = []): string {
		return $this->getL()->n($text_singular, $text_plural, $count, $parameters);
	}

	public function l(string $type, $data, array $options = []) {
		return $this->getL()->l($type, $data, $options);
	}

	public function getLanguageCode(): string {
		return $this->getL()->getLanguageCode();
	}

	public function getLocaleCode(): string {
		return $this->getL()->getLocaleCode();
	}

}
