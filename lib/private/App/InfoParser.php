<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @copyright Copyright (c) 2016, Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Andreas Fischer <bantu@owncloud.com>
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
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

use OCP\ICache;
use function libxml_disable_entity_loader;
use function simplexml_load_file;

class InfoParser {
	/** @var \OCP\ICache|null */
	private $cache;

	/**
	 * @param ICache|null $cache
	 */
	public function __construct(ICache $cache = null) {
		$this->cache = $cache;
	}

	/**
	 * @param string $file the xml file to be loaded
	 * @return null|array where null is an indicator for an error
	 */
	public function parse($file) {
		if (!file_exists($file)) {
			return null;
		}

		if ($this->cache !== null) {
			$fileCacheKey = $file . filemtime($file);
			if ($cachedValue = $this->cache->get($fileCacheKey)) {
				return json_decode($cachedValue, true);
			}
		}

		libxml_use_internal_errors(true);
		if ((PHP_VERSION_ID < 80000)) {
			$loadEntities = libxml_disable_entity_loader(false);
			$xml = simplexml_load_file($file);
			libxml_disable_entity_loader($loadEntities);
		} else {
			$xml = simplexml_load_file($file);
		}

		if ($xml === false) {
			libxml_clear_errors();
			return null;
		}
		$array = $this->xmlToArray($xml);

		if (is_null($array)) {
			return null;
		}

		if (!array_key_exists('info', $array)) {
			$array['info'] = [];
		}
		if (!array_key_exists('remote', $array)) {
			$array['remote'] = [];
		}
		if (!array_key_exists('public', $array)) {
			$array['public'] = [];
		}
		if (!array_key_exists('types', $array)) {
			$array['types'] = [];
		}
		if (!array_key_exists('repair-steps', $array)) {
			$array['repair-steps'] = [];
		}
		if (!array_key_exists('install', $array['repair-steps'])) {
			$array['repair-steps']['install'] = [];
		}
		if (!array_key_exists('pre-migration', $array['repair-steps'])) {
			$array['repair-steps']['pre-migration'] = [];
		}
		if (!array_key_exists('post-migration', $array['repair-steps'])) {
			$array['repair-steps']['post-migration'] = [];
		}
		if (!array_key_exists('live-migration', $array['repair-steps'])) {
			$array['repair-steps']['live-migration'] = [];
		}
		if (!array_key_exists('uninstall', $array['repair-steps'])) {
			$array['repair-steps']['uninstall'] = [];
		}
		if (!array_key_exists('background-jobs', $array)) {
			$array['background-jobs'] = [];
		}
		if (!array_key_exists('two-factor-providers', $array)) {
			$array['two-factor-providers'] = [];
		}
		if (!array_key_exists('commands', $array)) {
			$array['commands'] = [];
		}
		if (!array_key_exists('activity', $array)) {
			$array['activity'] = [];
		}
		if (!array_key_exists('filters', $array['activity'])) {
			$array['activity']['filters'] = [];
		}
		if (!array_key_exists('settings', $array['activity'])) {
			$array['activity']['settings'] = [];
		}
		if (!array_key_exists('providers', $array['activity'])) {
			$array['activity']['providers'] = [];
		}
		if (!array_key_exists('settings', $array)) {
			$array['settings'] = [];
		}
		if (!array_key_exists('admin', $array['settings'])) {
			$array['settings']['admin'] = [];
		}
		if (!array_key_exists('admin-section', $array['settings'])) {
			$array['settings']['admin-section'] = [];
		}
		if (!array_key_exists('personal', $array['settings'])) {
			$array['settings']['personal'] = [];
		}
		if (!array_key_exists('personal-section', $array['settings'])) {
			$array['settings']['personal-section'] = [];
		}

		if (array_key_exists('types', $array)) {
			if (is_array($array['types'])) {
				foreach ($array['types'] as $type => $v) {
					unset($array['types'][$type]);
					if (is_string($type)) {
						$array['types'][] = $type;
					}
				}
			} else {
				$array['types'] = [];
			}
		}
		if (isset($array['repair-steps']['install']['step']) && is_array($array['repair-steps']['install']['step'])) {
			$array['repair-steps']['install'] = $array['repair-steps']['install']['step'];
		}
		if (isset($array['repair-steps']['pre-migration']['step']) && is_array($array['repair-steps']['pre-migration']['step'])) {
			$array['repair-steps']['pre-migration'] = $array['repair-steps']['pre-migration']['step'];
		}
		if (isset($array['repair-steps']['post-migration']['step']) && is_array($array['repair-steps']['post-migration']['step'])) {
			$array['repair-steps']['post-migration'] = $array['repair-steps']['post-migration']['step'];
		}
		if (isset($array['repair-steps']['live-migration']['step']) && is_array($array['repair-steps']['live-migration']['step'])) {
			$array['repair-steps']['live-migration'] = $array['repair-steps']['live-migration']['step'];
		}
		if (isset($array['repair-steps']['uninstall']['step']) && is_array($array['repair-steps']['uninstall']['step'])) {
			$array['repair-steps']['uninstall'] = $array['repair-steps']['uninstall']['step'];
		}
		if (isset($array['background-jobs']['job']) && is_array($array['background-jobs']['job'])) {
			$array['background-jobs'] = $array['background-jobs']['job'];
		}
		if (isset($array['commands']['command']) && is_array($array['commands']['command'])) {
			$array['commands'] = $array['commands']['command'];
		}
		if (isset($array['two-factor-providers']['provider']) && is_array($array['two-factor-providers']['provider'])) {
			$array['two-factor-providers'] = $array['two-factor-providers']['provider'];
		}
		if (isset($array['activity']['filters']['filter']) && is_array($array['activity']['filters']['filter'])) {
			$array['activity']['filters'] = $array['activity']['filters']['filter'];
		}
		if (isset($array['activity']['settings']['setting']) && is_array($array['activity']['settings']['setting'])) {
			$array['activity']['settings'] = $array['activity']['settings']['setting'];
		}
		if (isset($array['activity']['providers']['provider']) && is_array($array['activity']['providers']['provider'])) {
			$array['activity']['providers'] = $array['activity']['providers']['provider'];
		}
		if (isset($array['collaboration']['collaborators']['searchPlugins']['searchPlugin'])
			&& is_array($array['collaboration']['collaborators']['searchPlugins']['searchPlugin'])
			&& !isset($array['collaboration']['collaborators']['searchPlugins']['searchPlugin']['class'])
		) {
			$array['collaboration']['collaborators']['searchPlugins'] = $array['collaboration']['collaborators']['searchPlugins']['searchPlugin'];
		}
		if (isset($array['settings']['admin']) && !is_array($array['settings']['admin'])) {
			$array['settings']['admin'] = [$array['settings']['admin']];
		}
		if (isset($array['settings']['admin-section']) && !is_array($array['settings']['admin-section'])) {
			$array['settings']['admin-section'] = [$array['settings']['admin-section']];
		}
		if (isset($array['settings']['personal']) && !is_array($array['settings']['personal'])) {
			$array['settings']['personal'] = [$array['settings']['personal']];
		}
		if (isset($array['settings']['personal-section']) && !is_array($array['settings']['personal-section'])) {
			$array['settings']['personal-section'] = [$array['settings']['personal-section']];
		}

		if (isset($array['navigations']['navigation']) && $this->isNavigationItem($array['navigations']['navigation'])) {
			$array['navigations']['navigation'] = [$array['navigations']['navigation']];
		}

		if ($this->cache !== null) {
			$this->cache->set($fileCacheKey, json_encode($array));
		}
		return $array;
	}

	/**
	 * @param $data
	 * @return bool
	 */
	private function isNavigationItem($data): bool {
		return isset($data['name'], $data['route']);
	}

	/**
	 * @param \SimpleXMLElement $xml
	 * @return array
	 */
	public function xmlToArray($xml) {
		if (!$xml->children()) {
			return (string)$xml;
		}

		$array = [];
		foreach ($xml->children() as $element => $node) {
			$totalElement = count($xml->{$element});

			if (!isset($array[$element])) {
				$array[$element] = $totalElement > 1 ? [] : "";
			}
			/** @var \SimpleXMLElement $node */
			// Has attributes
			if ($attributes = $node->attributes()) {
				$data = [
					'@attributes' => [],
				];
				if (!count($node->children())) {
					$value = (string)$node;
					if (!empty($value)) {
						$data['@value'] = (string)$node;
					}
				} else {
					$data = array_merge($data, $this->xmlToArray($node));
				}
				foreach ($attributes as $attr => $value) {
					$data['@attributes'][$attr] = (string)$value;
				}

				if ($totalElement > 1) {
					$array[$element][] = $data;
				} else {
					$array[$element] = $data;
				}
				// Just a value
			} else {
				if ($totalElement > 1) {
					$array[$element][] = $this->xmlToArray($node);
				} else {
					$array[$element] = $this->xmlToArray($node);
				}
			}
		}

		return $array;
	}
}
