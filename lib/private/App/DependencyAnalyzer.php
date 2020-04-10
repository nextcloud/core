<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @copyright Copyright (c) 2016, Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Stefan Weil <sw@weilnetz.de>
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

use OCP\IL10N;

class DependencyAnalyzer {

	/** @var Platform */
	private $platform;
	/** @var \OCP\IL10N */
	private $l;
	/** @var array */
	private $appInfo;

	/**
	 * @param Platform $platform
	 * @param \OCP\IL10N $l
	 */
	public function __construct(Platform $platform, IL10N $l) {
		$this->platform = $platform;
		$this->l = $l;
	}

	/**
	 * @param array $app
	 * @returns array of missing dependencies
	 */
	public function analyze(array $app, bool $ignoreMax = false) {
		$this->appInfo = $app;
		if (isset($app['dependencies'])) {
			$dependencies = $app['dependencies'];
		} else {
			$dependencies = [];
		}

		return array_merge(
			$this->analyzePhpVersion($dependencies),
			$this->analyzeDatabases($dependencies),
			$this->analyzeCommands($dependencies),
			$this->analyzeLibraries($dependencies),
			$this->analyzeOS($dependencies),
			$this->analyzeOC($dependencies, $app, $ignoreMax)
		);
	}

	public function isMarkedCompatible(array $app): bool {
		if (isset($app['dependencies'])) {
			$dependencies = $app['dependencies'];
		} else {
			$dependencies = [];
		}

		$maxVersion = $this->getMaxVersion($dependencies, $app);
		if ($maxVersion === null) {
			return true;
		}
		return !$this->compareBigger($this->platform->getOcVersion(), $maxVersion);
	}

	/**
	 * Truncates both versions to the lowest common version, e.g.
	 * 5.1.2.3 and 5.1 will be turned into 5.1 and 5.1,
	 * 5.2.6.5 and 5.1 will be turned into 5.2 and 5.1
	 * @param string $first
	 * @param string $second
	 * @return string[] first element is the first version, second element is the
	 * second version
	 */
	private function normalizeVersions($first, $second) {
		$first = explode('.', $first);
		$second = explode('.', $second);

		// get both arrays to the same minimum size
		$length = min(count($second), count($first));
		$first = array_slice($first, 0, $length);
		$second = array_slice($second, 0, $length);

		return [implode('.', $first), implode('.', $second)];
	}

	/**
	 * Parameters will be normalized and then passed into version_compare
	 * in the same order they are specified in the method header
	 * @param string $first
	 * @param string $second
	 * @param string $operator
	 * @return bool result similar to version_compare
	 */
	private function compare($first, $second, $operator) {
		// we can't normalize versions if one of the given parameters is not a
		// version string but null. In case one parameter is null normalization
		// will therefore be skipped
		if ($first !== null && $second !== null) {
			list($first, $second) = $this->normalizeVersions($first, $second);
		}

		return version_compare($first, $second, $operator);
	}

	/**
	 * Checks if a version is bigger than another version
	 * @param string $first
	 * @param string $second
	 * @return bool true if the first version is bigger than the second
	 */
	private function compareBigger($first, $second) {
		return $this->compare($first, $second, '>');
	}

	/**
	 * Checks if a version is smaller than another version
	 * @param string $first
	 * @param string $second
	 * @return bool true if the first version is smaller than the second
	 */
	private function compareSmaller($first, $second) {
		return $this->compare($first, $second, '<');
	}

	/**
	 * @param array $dependencies
	 * @return array
	 */
	private function analyzePhpVersion(array $dependencies) {
		$missing = [];
		if (isset($dependencies['php']['@attributes']['min-version'])) {
			$minVersion = $dependencies['php']['@attributes']['min-version'];
			if ($this->compareSmaller($this->platform->getPhpVersion(), $minVersion)) {
				$missing[] = (string)$this->l->t('PHP %s or higher is required.', [$minVersion]);
			}
		}
		if (isset($dependencies['php']['@attributes']['max-version'])) {
			$maxVersion = $dependencies['php']['@attributes']['max-version'];
			if ($this->compareBigger($this->platform->getPhpVersion(), $maxVersion)) {
				$missing[] = (string)$this->l->t('PHP with a version lower than %s is required.', [$maxVersion]);
			}
		}
		if (isset($dependencies['php']['@attributes']['min-int-size'])) {
			$intSize = $dependencies['php']['@attributes']['min-int-size'];
			if ($intSize > $this->platform->getIntSize()*8) {
				$missing[] = (string)$this->l->t('%sbit or higher PHP required.', [$intSize]);
			}
		}
		return $missing;
	}

	/**
	 * @param array $dependencies
	 * @return array
	 */
	private function analyzeDatabases(array $dependencies) {
		$missing = [];
		if (!isset($dependencies['database'])) {
			return $missing;
		}

		$supportedDatabases = $dependencies['database'];
		if (empty($supportedDatabases)) {
			return $missing;
		}
		if (!is_array($supportedDatabases)) {
			$supportedDatabases = [$supportedDatabases];
		}
		$supportedDatabases = array_map(function ($db) {
			return $this->getValue($db);
		}, $supportedDatabases);
		$currentDatabase = $this->platform->getDatabase();
		if (!in_array($currentDatabase, $supportedDatabases)) {
			$missing[] = (string)$this->l->t('Following databases are supported: %s', [implode(', ', $supportedDatabases)]);
		}
		return $missing;
	}

	/**
	 * @param array $dependencies
	 * @return array
	 */
	private function analyzeCommands(array $dependencies) {
		$missing = [];
		if (!isset($dependencies['command'])) {
			return $missing;
		}

		$commands = $dependencies['command'];
		if (!is_array($commands)) {
			$commands = [$commands];
		}
		if (isset($commands['@value'])) {
			$commands = [$commands];
		}
		$os = $this->platform->getOS();
		foreach ($commands as $command) {
			if (isset($command['@attributes']['os']) && $command['@attributes']['os'] !== $os) {
				continue;
			}
			$commandName = $this->getValue($command);
			if (!$this->platform->isCommandKnown($commandName)) {
				$missing[] = (string)$this->l->t('The command line tool %s could not be found', [$commandName]);
			}
		}
		return $missing;
	}

	/**
	 * @param array $dependencies
	 * @return array
	 */
	private function analyzeLibraries(array $dependencies) {
		$missing = [];
		if (!isset($dependencies['lib'])) {
			return $missing;
		}

		$libs = $dependencies['lib'];
		if (!is_array($libs)) {
			$libs = [$libs];
		}
		if (isset($libs['@value'])) {
			$libs = [$libs];
		}
		foreach ($libs as $lib) {
			$libName = $this->getValue($lib);
			$libVersion = $this->platform->getLibraryVersion($libName);
			if (is_null($libVersion)) {
				$missing[] = $this->l->t('The library %s is not available.', [$libName]);
				continue;
			}

			if (is_array($lib)) {
				if (isset($lib['@attributes']['min-version'])) {
					$minVersion = $lib['@attributes']['min-version'];
					if ($this->compareSmaller($libVersion, $minVersion)) {
						$missing[] = $this->l->t('Library %1$s with a version higher than %2$s is required - available version %3$s.',
							[$libName, $minVersion, $libVersion]);
					}
				}
				if (isset($lib['@attributes']['max-version'])) {
					$maxVersion = $lib['@attributes']['max-version'];
					if ($this->compareBigger($libVersion, $maxVersion)) {
						$missing[] = $this->l->t('Library %1$s with a version lower than %2$s is required - available version %3$s.',
							[$libName, $maxVersion, $libVersion]);
					}
				}
			}
		}
		return $missing;
	}

	/**
	 * @param array $dependencies
	 * @return array
	 */
	private function analyzeOS(array $dependencies) {
		$missing = [];
		if (!isset($dependencies['os'])) {
			return $missing;
		}

		$oss = $dependencies['os'];
		if (empty($oss)) {
			return $missing;
		}
		if (is_array($oss)) {
			$oss = array_map(function ($os) {
				return $this->getValue($os);
			}, $oss);
		} else {
			$oss = [$oss];
		}
		$currentOS = $this->platform->getOS();
		if (!in_array($currentOS, $oss)) {
			$missing[] = (string)$this->l->t('Following platforms are supported: %s', [implode(', ', $oss)]);
		}
		return $missing;
	}

	/**
	 * @param array $dependencies
	 * @param array $appInfo
	 * @return array
	 */
	private function analyzeOC(array $dependencies, array $appInfo, bool $ignoreMax) {
		$missing = [];
		$minVersion = null;
		if (isset($dependencies['nextcloud']['@attributes']['min-version'])) {
			$minVersion = $dependencies['nextcloud']['@attributes']['min-version'];
		} elseif (isset($dependencies['owncloud']['@attributes']['min-version'])) {
			$minVersion = $dependencies['owncloud']['@attributes']['min-version'];
		} elseif (isset($appInfo['requiremin'])) {
			$minVersion = $appInfo['requiremin'];
		} elseif (isset($appInfo['require'])) {
			$minVersion = $appInfo['require'];
		}
		$maxVersion = $this->getMaxVersion($dependencies, $appInfo);

		if (!is_null($minVersion)) {
			if ($this->compareSmaller($this->platform->getOcVersion(), $minVersion)) {
				$missing[] = (string)$this->l->t('Server version %s or higher is required.', [$this->toVisibleVersion($minVersion)]);
			}
		}
		if (!$ignoreMax && !is_null($maxVersion)) {
			if ($this->compareBigger($this->platform->getOcVersion(), $maxVersion)) {
				$missing[] = (string)$this->l->t('Server version %s or lower is required.', [$this->toVisibleVersion($maxVersion)]);
			}
		}
		return $missing;
	}

	private function getMaxVersion(array $dependencies, array $appInfo): ?string {
		if (isset($dependencies['nextcloud']['@attributes']['max-version'])) {
			return $dependencies['nextcloud']['@attributes']['max-version'];
		}
		if (isset($dependencies['owncloud']['@attributes']['max-version'])) {
			return $dependencies['owncloud']['@attributes']['max-version'];
		}
		if (isset($appInfo['requiremax'])) {
			return $appInfo['requiremax'];
		}

		return null;
	}

	/**
	 * Map the internal version number to the Nextcloud version
	 *
	 * @param string $version
	 * @return string
	 */
	protected function toVisibleVersion($version) {
		switch ($version) {
			case '9.1':
				return '10';
			default:
				if (strpos($version, '9.1.') === 0) {
					$version = '10.0.' . substr($version, 4);
				}
				return $version;
		}
	}

	/**
	 * @param $element
	 * @return mixed
	 */
	private function getValue($element) {
		if (isset($element['@value'])) {
			return $element['@value'];
		}
		return (string)$element;
	}
}
