<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Kyle Fazzari <kyrofa@ubuntu.com>
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

namespace OC\Template;

class JSResourceLocator extends ResourceLocator {

	/** @var JSCombiner */
	protected $jsCombiner;

	public function __construct(\OCP\ILogger $logger, $theme, array $core_map, array $party_map, JSCombiner $JSCombiner) {
		parent::__construct($logger, $theme, $core_map, $party_map);

		$this->jsCombiner = $JSCombiner;
	}

	/**
	 * @param string $script
	 */
	public function doFind($script) {
		$theme_dir = 'themes/'.$this->theme.'/';
		if (strpos($script, '3rdparty') === 0
			&& $this->appendIfExist($this->thirdpartyroot, $script.'.js')) {
			return;
		}

		if (strpos($script, '/l10n/') !== false) {
			// For language files we try to load them all, so themes can overwrite
			// single l10n strings without having to translate all of them.
			$found = 0;
			$found += $this->appendIfExist($this->serverroot, 'core/'.$script.'.js');
			$found += $this->appendIfExist($this->serverroot, $theme_dir.'core/'.$script.'.js');
			$found += $this->appendIfExist($this->serverroot, $script.'.js');
			$found += $this->appendIfExist($this->serverroot, $theme_dir.$script.'.js');
			$found += $this->appendIfExist($this->serverroot, 'apps/'.$script.'.js');
			$found += $this->appendIfExist($this->serverroot, $theme_dir.'apps/'.$script.'.js');

			if ($found) {
				return;
			}
		} elseif ($this->appendIfExist($this->serverroot, $theme_dir.'apps/'.$script.'.js')
			|| $this->appendIfExist($this->serverroot, $theme_dir.$script.'.js')
			|| $this->appendIfExist($this->serverroot, $script.'.js')
			|| $this->cacheAndAppendCombineJsonIfExist($this->serverroot, $script.'.json')
			|| $this->appendIfExist($this->serverroot, $theme_dir.'core/'.$script.'.js')
			|| $this->appendIfExist($this->serverroot, 'core/'.$script.'.js')
			|| $this->cacheAndAppendCombineJsonIfExist($this->serverroot, 'core/'.$script.'.json')
		) {
			return;
		}

		$app = substr($script, 0, strpos($script, '/'));
		$script = substr($script, strpos($script, '/') + 1);
		$app_path = \OC_App::getAppPath($app);
		$app_url = \OC_App::getAppWebPath($app);

		if ($app_path !== false) {
			// Account for the possibility of having symlinks in app path. Only
			// do this if $app_path is set, because an empty argument to realpath
			// gets turned into cwd.
			$app_path = realpath($app_path);
		}

		// missing translations files fill be ignored
		if (strpos($script, 'l10n/') === 0) {
			$this->appendIfExist($app_path, $script . '.js', $app_url);
			return;
		}

		if ($app_path === false && $app_url === false) {
			$this->logger->error('Could not find resource {resource} to load', [
				'resource' => $app . '/' . $script . '.js',
				'app' => 'jsresourceloader',
			]);
			return;
		}

		if (!$this->cacheAndAppendCombineJsonIfExist($app_path, $script.'.json', $app)) {
			$this->append($app_path, $script . '.js', $app_url);
		}
	}

	/**
	 * @param string $script
	 */
	public function doFindTheme($script) {
	}

	protected function cacheAndAppendCombineJsonIfExist($root, $file, $app = 'core') {
		if (is_file($root.'/'.$file)) {
			if ($this->jsCombiner->process($root, $file, $app)) {
				$this->append($this->serverroot, $this->jsCombiner->getCachedJS($app, $file), false, false);
			} else {
				// Add all the files from the json
				$files = $this->jsCombiner->getContent($root, $file);
				$app_url = \OC_App::getAppWebPath($app);

				foreach ($files as $jsFile) {
					$this->append($root, $jsFile, $app_url);
				}
			}
			return true;
		}

		return false;
	}
}
