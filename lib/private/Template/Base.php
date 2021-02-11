<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
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

use OCP\Defaults;
use Throwable;

class Base {
	private $template; // The template
	private $vars; // Vars

	/** @var \OCP\IL10N */
	private $l10n;

	/** @var Defaults */
	private $theme;

	/**
	 * @param string $template
	 * @param string $requestToken
	 * @param \OCP\IL10N $l10n
	 * @param Defaults $theme
	 */
	public function __construct($template, $requestToken, $l10n, $theme) {
		$this->vars = [];
		$this->vars['requesttoken'] = $requestToken;
		$this->l10n = $l10n;
		$this->template = $template;
		$this->theme = $theme;
	}

	/**
	 * @param string $serverRoot
	 * @param string|false $app_dir
	 * @param string $theme
	 * @param string $app
	 * @return string[]
	 */
	protected function getAppTemplateDirs($theme, $app, $serverRoot, $app_dir) {
		// Check if the app is in the app folder or in the root
		if (file_exists($app_dir.'/templates/')) {
			return [
				$serverRoot.'/themes/'.$theme.'/apps/'.$app.'/templates/',
				$app_dir.'/templates/',
			];
		}
		return [
			$serverRoot.'/themes/'.$theme.'/'.$app.'/templates/',
			$serverRoot.'/'.$app.'/templates/',
		];
	}

	/**
	 * @param string $serverRoot
	 * @param string $theme
	 * @return string[]
	 */
	protected function getCoreTemplateDirs($theme, $serverRoot) {
		return [
			$serverRoot.'/themes/'.$theme.'/core/templates/',
			$serverRoot.'/core/templates/',
		];
	}

	/**
	 * Assign variables
	 * @param string $key key
	 * @param array|bool|integer|string|Throwable $value value
	 * @return bool
	 *
	 * This function assigns a variable. It can be accessed via $_[$key] in
	 * the template.
	 *
	 * If the key existed before, it will be overwritten
	 */
	public function assign($key, $value) {
		$this->vars[$key] = $value;
		return true;
	}

	/**
	 * Appends a variable
	 * @param string $key key
	 * @param mixed $value value
	 *
	 * This function assigns a variable in an array context. If the key already
	 * exists, the value will be appended. It can be accessed via
	 * $_[$key][$position] in the template.
	 */
	public function append($key, $value) {
		if (array_key_exists($key, $this->vars)) {
			$this->vars[$key][] = $value;
		} else {
			$this->vars[$key] = [ $value ];
		}
	}

	/**
	 * Prints the proceeded template
	 * @return bool
	 *
	 * This function proceeds the template and prints its output.
	 */
	public function printPage() {
		$data = $this->fetchPage();
		if ($data === false) {
			return false;
		} else {
			print $data;
			return true;
		}
	}

	/**
	 * Process the template
	 *
	 * @param array|null $additionalParams
	 * @return string This function processes the template.
	 *
	 * This function processes the template.
	 */
	public function fetchPage($additionalParams = null) {
		return $this->load($this->template, $additionalParams);
	}

	/**
	 * doing the actual work
	 *
	 * @param string $file
	 * @param array|null $additionalParams
	 * @return string content
	 *
	 * Includes the template file, fetches its output
	 */
	protected function load($file, $additionalParams = null) {
		// Register the variables
		$_ = $this->vars;
		$l = $this->l10n;
		$theme = $this->theme;

		if (!is_null($additionalParams)) {
			$_ = array_merge($additionalParams, $this->vars);
			foreach ($_ as $var => $value) {
				${$var} = $value;
			}
		}

		// Include
		ob_start();
		try {
			include $file;
			$data = ob_get_contents();
		} catch (\Exception $e) {
			@ob_end_clean();
			throw $e;
		}
		@ob_end_clean();

		// Return data
		return $data;
	}
}
