<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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

use OCP\IConfig;
use OCP\IUser;
use OCP\L10N\ILanguageIterator;

class LanguageIterator implements ILanguageIterator {
	private $i = 0;
	/** @var IConfig */
	private $config;
	/** @var IUser */
	private $user;

	public function __construct(IUser $user, IConfig $config) {
		$this->config = $config;
		$this->user = $user;
	}

	/**
	 * Rewind the Iterator to the first element
	 */
	public function rewind() {
		$this->i = 0;
	}

	/**
	 * Return the current element
	 *
	 * @since 14.0.0
	 */
	public function current(): string {
		switch($this->i) {
			/** @noinspection PhpMissingBreakStatementInspection */
			case 0:
				$forcedLang = $this->config->getSystemValue('force_language', false);
				if(is_string($forcedLang)) {
					return $forcedLang;
				}
				$this->next();
			/** @noinspection PhpMissingBreakStatementInspection */
			case 1:
				$forcedLang = $this->config->getSystemValue('force_language', false);
				if(is_string($forcedLang)
					&& ($truncated = $this->getTruncatedLanguage($forcedLang)) !== $forcedLang
				) {
					return $truncated;
				}
				$this->next();
			/** @noinspection PhpMissingBreakStatementInspection */
			case 2:
				$userLang = $this->config->getUserValue($this->user->getUID(), 'core', 'lang', null);
				if(is_string($userLang)) {
					return $userLang;
				}
				$this->next();
			/** @noinspection PhpMissingBreakStatementInspection */
			case 3:
				$userLang = $this->config->getUserValue($this->user->getUID(), 'core', 'lang', null);
				if(is_string($userLang)
					&& ($truncated = $this->getTruncatedLanguage($userLang)) !== $userLang
				) {
					return $truncated;
				}
				$this->next();
			case 4:
				return $this->config->getSystemValue('default_language', 'en');
			/** @noinspection PhpMissingBreakStatementInspection */
			case 5:
				$defaultLang = $this->config->getSystemValue('default_language', 'en');
				if(($truncated = $this->getTruncatedLanguage($defaultLang)) !== $defaultLang) {
					return $truncated;
				}
				$this->next();
			default:
				return 'en';
		}
	}

	/**
	 * Move forward to next element
	 *
	 * @since 14.0.0
	 */
	public function next() {
		++$this->i;
	}

	/**
	 * Return the key of the current element
	 *
	 * @since 14.0.0
	 */
	public function key(): int {
		return $this->i;
	}

	/**
	 * Checks if current position is valid
	 *
	 * @since 14.0.0
	 */
	public function valid(): bool {
		return $this->i <= 6;
	}

	protected function getTruncatedLanguage(string $lang):string {
		$pos = strpos($lang, '_');
		if($pos !== false) {
			$lang = substr($lang, 0, $pos);
		}
		return $lang;
	}
}
