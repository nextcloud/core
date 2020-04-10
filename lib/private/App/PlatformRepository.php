<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OC\App;

/**
 * Class PlatformRepository
 *
 * Inspired by the composer project - licensed under MIT
 * https://github.com/composer/composer/blob/master/src/Composer/Repository/PlatformRepository.php#L82
 *
 * @package OC\App
 */
class PlatformRepository {
	public function __construct() {
		$this->packages = $this->initialize();
	}

	protected function initialize() {
		$loadedExtensions = get_loaded_extensions();
		$packages = [];

		// Extensions scanning
		foreach ($loadedExtensions as $name) {
			if (in_array($name, ['standard', 'Core'])) {
				continue;
			}

			$ext = new \ReflectionExtension($name);
			try {
				$prettyVersion = $ext->getVersion();
				$prettyVersion = $this->normalizeVersion($prettyVersion);
			} catch (\UnexpectedValueException $e) {
				$prettyVersion = '0';
				$prettyVersion = $this->normalizeVersion($prettyVersion);
			}

			$packages[$this->buildPackageName($name)] = $prettyVersion;
		}

		foreach ($loadedExtensions as $name) {
			$prettyVersion = null;
			switch ($name) {
				case 'curl':
					$curlVersion = curl_version();
					$prettyVersion = $curlVersion['version'];
					break;

				case 'iconv':
					$prettyVersion = ICONV_VERSION;
					break;

				case 'intl':
					$name = 'ICU';
					if (defined('INTL_ICU_VERSION')) {
						$prettyVersion = INTL_ICU_VERSION;
					} else {
						$reflector = new \ReflectionExtension('intl');

						ob_start();
						$reflector->info();
						$output = ob_get_clean();

						preg_match('/^ICU version => (.*)$/m', $output, $matches);
						$prettyVersion = $matches[1];
					}

					break;

				case 'libxml':
					$prettyVersion = LIBXML_DOTTED_VERSION;
					break;

				case 'openssl':
					$prettyVersion = preg_replace_callback('{^(?:OpenSSL\s*)?([0-9.]+)([a-z]?).*}', function ($match) {
						return $match[1] . (empty($match[2]) ? '' : '.' . (ord($match[2]) - 96));
					}, OPENSSL_VERSION_TEXT);
					break;

				case 'pcre':
					$prettyVersion = preg_replace('{^(\S+).*}', '$1', PCRE_VERSION);
					break;

				case 'uuid':
					$prettyVersion = phpversion('uuid');
					break;

				case 'xsl':
					$prettyVersion = LIBXSLT_DOTTED_VERSION;
					break;

				default:
					// None handled extensions have no special cases, skip
					continue 2;
			}

			try {
				$prettyVersion = $this->normalizeVersion($prettyVersion);
			} catch (\UnexpectedValueException $e) {
				continue;
			}

			$packages[$this->buildPackageName($name)] = $prettyVersion;
		}

		return $packages;
	}

	private function buildPackageName($name) {
		return str_replace(' ', '-', $name);
	}

	/**
	 * @param $name
	 * @return string
	 */
	public function findLibrary($name) {
		$extName = $this->buildPackageName($name);
		if (isset($this->packages[$extName])) {
			return $this->packages[$extName];
		}
		return null;
	}

	private static $modifierRegex = '[._-]?(?:(stable|beta|b|RC|alpha|a|patch|pl|p)(?:[.-]?(\d+))?)?([.-]?dev)?';

	/**
	 * Normalizes a version string to be able to perform comparisons on it
	 *
	 * https://github.com/composer/composer/blob/master/src/Composer/Package/Version/VersionParser.php#L94
	 *
	 * @param string $version
	 * @param string $fullVersion optional complete version string to give more context
	 * @throws \UnexpectedValueException
	 * @return string
	 */
	public function normalizeVersion($version, $fullVersion = null) {
		$version = trim($version);
		if (null === $fullVersion) {
			$fullVersion = $version;
		}
		// ignore aliases and just assume the alias is required instead of the source
		if (preg_match('{^([^,\s]+) +as +([^,\s]+)$}', $version, $match)) {
			$version = $match[1];
		}
		// match master-like branches
		if (preg_match('{^(?:dev-)?(?:master|trunk|default)$}i', $version)) {
			return '9999999-dev';
		}
		if ('dev-' === strtolower(substr($version, 0, 4))) {
			return 'dev-' . substr($version, 4);
		}
		// match classical versioning
		if (preg_match('{^v?(\d{1,3})(\.\d+)?(\.\d+)?(\.\d+)?' . self::$modifierRegex . '$}i', $version, $matches)) {
			$version = $matches[1]
				. (!empty($matches[2]) ? $matches[2] : '.0')
				. (!empty($matches[3]) ? $matches[3] : '.0')
				. (!empty($matches[4]) ? $matches[4] : '.0');
			$index = 5;
		} elseif (preg_match('{^v?(\d{4}(?:[.:-]?\d{2}){1,6}(?:[.:-]?\d{1,3})?)' . self::$modifierRegex . '$}i', $version, $matches)) { // match date-based versioning
			$version = preg_replace('{\D}', '-', $matches[1]);
			$index = 2;
		} elseif (preg_match('{^v?(\d{4,})(\.\d+)?(\.\d+)?(\.\d+)?' . self::$modifierRegex . '$}i', $version, $matches)) {
			$version = $matches[1]
				. (!empty($matches[2]) ? $matches[2] : '.0')
				. (!empty($matches[3]) ? $matches[3] : '.0')
				. (!empty($matches[4]) ? $matches[4] : '.0');
			$index = 5;
		}
		// add version modifiers if a version was matched
		if (isset($index)) {
			if (!empty($matches[$index])) {
				if ('stable' === $matches[$index]) {
					return $version;
				}
				$version .= '-' . $this->expandStability($matches[$index]) . (!empty($matches[$index + 1]) ? $matches[$index + 1] : '');
			}
			if (!empty($matches[$index + 2])) {
				$version .= '-dev';
			}
			return $version;
		}
		$extraMessage = '';
		if (preg_match('{ +as +' . preg_quote($version) . '$}', $fullVersion)) {
			$extraMessage = ' in "' . $fullVersion . '", the alias must be an exact version';
		} elseif (preg_match('{^' . preg_quote($version) . ' +as +}', $fullVersion)) {
			$extraMessage = ' in "' . $fullVersion . '", the alias source must be an exact version, if it is a branch name you should prefix it with dev-';
		}
		throw new \UnexpectedValueException('Invalid version string "' . $version . '"' . $extraMessage);
	}

	/**
	 * @param string $stability
	 */
	private function expandStability($stability) {
		$stability = strtolower($stability);
		switch ($stability) {
			case 'a':
				return 'alpha';
			case 'b':
				return 'beta';
			case 'p':
			case 'pl':
				return 'patch';
			case 'rc':
				return 'RC';
			default:
				return $stability;
		}
	}
}
